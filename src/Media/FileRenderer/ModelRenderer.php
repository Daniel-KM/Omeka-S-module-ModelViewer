<?php declare(strict_types=1);

namespace ModelViewer\Media\FileRenderer;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;

class ModelRenderer implements RendererInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/model-viewer';

    /**
     * @var array
     */
    protected $defaultOptions = [
        'attributes' => 'class="model" allowfullscreen="allowfullscreen" style="height: 600px; height: 70vh;"',
        'template' => self::PARTIAL_NAME,
    ];

    /**
     * Render a media via the library Three js.
     *
     * @todo Factorize with the view helper.
     *
     * @param PhpRenderer $view,
     * @param MediaRepresentation $media
     * @param array $options These options are managed for sites:
     *   - template: the partial to use
     *   - attributes: set the attributes to add
     *   - config: config of the viewer, overriding resource and admin settings.
     * @return string
     */
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        // Avoid to load library multiple times.
        static $hasModelViewer = false;
        static $assets = [];
        static $config = [];
        static $property = null;
        static $assetUrl;
        static $headScript;

        // By construction, the media has a supported media-type.
        $mediaType = $media->mediaType();

        if (!$hasModelViewer) {
            $setting = $view->plugin('setting');
            $config = $setting('modelviewer_config_default', []) ?: [];
            if ($config) {
                $config = json_decode($config, true) ?: [];
            }
            $property = $setting('modelviewer_config_property');

            $assetUrl = $view->plugin('assetUrl');
            $view->headLink()
                ->appendStylesheet($assetUrl('css/model-viewer.css', 'ModelViewer'));

            // Js modules are not used because they are not enough browser-supported.
            $headScript = $view->headScript()
                ->appendFile($assetUrl('vendor/threejs/three.min.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
        }

        // Load only assets not yet loaded by a previous model in the page.
        if (!in_array($mediaType, $assets)) {
            switch ($mediaType) {
                // Format glTF v2.0.
                case 'application/octet-stream':
                    if ($media->extension() !== 'glb') {
                        return '';
                    }
                    // no break.
                case 'model/gltf-binary':
                case 'model/gltf+json':
                    $headScript
                        ->appendFile($assetUrl('vendor/threejs/js/loaders/GLTFLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
                    break;
                // Format ThreeJs/Vrml.
                case 'application/vnd.threejs+json':
                    $headScript
                        ->appendFile($assetUrl('vendor/threejs/js/libs/chevrotain.min.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                        // Vrml loader may require Gltf loader.
                        ->appendFile($assetUrl('vendor/threejs/js/loaders/GLTFLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                        ->appendFile($assetUrl('vendor/threejs/js/loaders/VRMLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
                    break;
                default:
                    return '';
            }
            $assets[] = $mediaType;
        }

        if (empty($options['config'])) {
            $options['config'] = $config;
            if ($property) {
                $configValues = $media->value($property);
                if ($configValues) {
                    $configValues = json_decode($configValues->value(), true);
                    if (is_array($configValues)) {
                        $options['config'] = $configValues;
                    } else {
                        $view->logger()->warn(sprintf('[Model viewer]: Specific config for media %d is not a valid json.', $media->id())); // @translate
                    }
                }
            }
        }

        switch ($options['config']['controls'] ?? null) {
            default:
            case 'OrbitControl':
                $headScript
                    ->appendFile($assetUrl('vendor/threejs/js/controls/OrbitControls.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
                break;
            case 'FirstPersonControls':
                $headScript
                    ->appendFile($assetUrl('vendor/threejs/js/controls/FirstPersonControls.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
                break;
        }

        if (!$hasModelViewer) {
            $hasModelViewer = true;
            $headScript
                ->appendFile($assetUrl('js/model-viewer.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
        }

        $status = $view->status();
        if ($status->isSiteRequest()) {
            $template = $options['template'] ?? $this->defaultOptions['template'];
            $options['attributes'] = $options['attributes'] ?? $this->defaultOptions['attributes'];
        } else {
            $template = $this->defaultOptions['template'];
            $options['attributes'] = $this->defaultOptions['attributes'];
        }

        unset($options['template']);
        return $view->partial($template, [
            'resource' => $media,
            'options' => $options,
        ]);
    }
}

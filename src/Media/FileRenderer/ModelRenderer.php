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
     * @return string
     */
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        // Avoid to load library multiple times.
        static $hasModelViewer;

        if (!$hasModelViewer) {
            $hasModelViewer = true;
            $assetUrl = $view->plugin('assetUrl');
            $view->headLink()
                ->appendStylesheet($assetUrl('css/model-viewer.css', 'ModelViewer'));
            // Js modules are not used because not enough supported currently.
            $view->headScript()
                ->appendFile($assetUrl('vendor/threejs/three.min.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('vendor/threejs/js/controls/OrbitControls.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('vendor/threejs/js/controls/FirstPersonControls.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                // Format glTF v2.0.
                ->appendFile($assetUrl('vendor/threejs/js/loaders/GLTFLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                // Format ModelViewer.
                ->appendFile($assetUrl('vendor/threejs/js/libs/chevrotain.min.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('vendor/threejs/js/loaders/VRMLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                // Manager.
                ->appendFile($assetUrl('js/model-viewer.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
            ;
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

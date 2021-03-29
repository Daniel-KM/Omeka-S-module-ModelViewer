<?php declare(strict_types=1);

namespace ThreeJs\Media\FileRenderer;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;

class ThreeJs implements RendererInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/threejs';

    /**
     * @var array
     */
    protected $defaultOptions = [
        'attributes' => 'class="threejs" allowfullscreen="allowfullscreen" style="height: 600px; height: 70vh;"',
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
        static $hasThreeJs;

        if (!$hasThreeJs) {
            $hasThreeJs = true;
            $assetUrl = $view->plugin('assetUrl');
            // Js modules are not used because not enough supported currently.
            $view->headScript()
                ->appendFile($assetUrl('vendor/threejs/three.min.js', 'ThreeJs'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('vendor/threejs/js/controls/OrbitControls.js', 'ThreeJs'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('vendor/threejs/js/controls/FirstPersonControls.js', 'ThreeJs'), 'text/javascript', ['defer' => 'defer'])
                // Format glTF v2.0.
                ->appendFile($assetUrl('vendor/threejs/js/loaders/GLTFLoader.js', 'ThreeJs'), 'text/javascript', ['defer' => 'defer'])
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

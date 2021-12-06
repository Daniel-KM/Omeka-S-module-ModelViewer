<?php declare(strict_types=1);

namespace ModelViewer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

class PrepareModelViewer extends AbstractHelper
{
    /**
     * Get all options for the model viewer (threejs) and init css/js.
     *
     * The resource may be empty: in that cases, options['source'] must be set
     * and options['mediaType'] is recommended.
     *
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
     * @param array $options
     * @return array|null Returns null in case of error.
     */
    public function __invoke(?AbstractResourceEntityRepresentation$resource, array $options = []): ?array
    {
        if (empty($options['config'])) {
            $options['config'] = [
                // Use page background color (or "white" or "lightgray" or #181818 or anything else).
                'background' => 'white',
                'controls' => 'OrbitControls',
                'camera' => [
                    'position' => ['x' => 0, 'y' => 10, 'z' => 20],
                    'lookAt' => ['x' => 0, 'y' => 0, 'z' => 0],
                ],
            ];
        }

        $supported = [
            'application/octet-stream',
            'application/vnd.threejs+json',
            'model/gltf-binary',
            'model/gltf+json',
            'model/mtl',
            'model/obj',
            'model/vnd.collada+xml',
            // Unofficial mime-type that is used only here instead of "application/octet-stream".
            // This is a proprietary file format that is not recommended (extension .fbx).
            'model/vnd.filmbox',
        ];
        $extensionMediaTypes = [
            'glb' => 'model/gltf-binary',
            'fbx' => 'model/vnd.filmbox',
        ];

        $item = null;
        $media = null;
        if ($resource instanceof \Omeka\Api\Representation\ItemRepresentation) {
            $item = $resource;
            // Get the main media.
            foreach ($resource->media() as $itemMedia) {
                $mediaType = $itemMedia->mediaType();
                if (in_array($mediaType, $supported)) {
                    $extension = $itemMedia->extension();
                    if ($mediaType === 'application/octet-stream' && isset($extensionMediaTypes[$extension])) {
                        $media = $itemMedia;
                        $mediaType = $extensionMediaTypes[$extension];
                        break;
                    }
                }
            }
            if (!$media) {
                return null;
            }
        } elseif ($resource instanceof \Omeka\Api\Representation\MediaRepresentation) {
            $mediaType = $resource->mediaType();
            if (!in_array($mediaType, $supported)) {
                return null;
            }
            $extension = $resource->extension();
            if ($mediaType === 'application/octet-stream') {
                if (!isset($extensionMediaTypes[$extension])) {
                    return null;
                }
                $mediaType = $extensionMediaTypes[$extension];
            }
            $media = $resource;
            $item = $media->item();
        } elseif (empty($options['source'])) {
            return null;
        } elseif (empty($options['mediaType'])) {
            $mediaType = 'model/gltf+json';
        } elseif (in_array($options['mediaType'], $supported)) {
            $mediaType = $options['mediaType'];
        } else {
            return null;
        }

        $options['id'] = $options['id'] ?? ($media ? 'model-' . $media->id() : 'model-0');
        $options['source'] = empty($options['source']) ? $media->originalUrl() : $options['source'];
        $options['dirpath'] = dirname($options['source']) . '/';
        $options['filename'] = basename($options['source']);
        $options['mediaType'] = $mediaType;
        $options['extension'] = $media ? $media->extension() : null;
        $options['mtl'] = $options['mtl'] ?? [];

        // Check associated files if they are not listed in the main file for
        // some formats.
        if ($item) {
            foreach ($item->media() as $itemMedia) {
                $extension = $itemMedia->extension();
                if ($extension === 'mtl') {
                    $options['mtl'][] = $media->originalUrl();
                }
            }
        }

        $this->initAssets($media, $options);

        return $options;
    }

    /**
     * Avoid to load assets multiple times.
     */
    protected function initAssets(?MediaRepresentation $media, array $options): void
    {
        static $hasModelViewer = false;
        static $assets = [];
        static $config = [];
        static $property = null;
        static $assetUrl;
        static $headScript;

        $view = $this->getView();

        $mediaType = $options['mediaType'];

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
            // Js modules are not used because they are not enough browser-supported
            // yet and the jsm requires a path that is relative to the main
            // threejs libray in examples.
            $headScript = $view->headScript()
                ->appendFile($assetUrl('vendor/threejs/three.min.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
        }

        // Load only assets not yet loaded by a previous model in the page.
        if (!in_array($mediaType, $assets)) {
            switch ($mediaType) {
                case 'model/gltf-binary':
                case 'model/gltf+json':
                case 'model/gltf':
                    $mediaType = 'model/gltf';
                    $headScript
                        ->appendFile($assetUrl('vendor/threejs/js/loaders/GLTFLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
                    break;
                case 'model/obj':
                    $headScript
                        ->appendFile($assetUrl('vendor/threejs/js/loaders/OBJLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
                    break;
                case 'model/vnd.collada+xml':
                    $headScript
                        ->appendFile($assetUrl('vendor/threejs/js/loaders/ColladaLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
                    break;
                case 'model/vnd.filmbox':
                    $headScript
                        ->appendFile($assetUrl('vendor/threejs/js/libs/fflate.min.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                        ->appendFile($assetUrl('vendor/threejs/js/loaders/FBXLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
                    break;
                case 'application/vnd.threejs+json':
                    $headScript
                        ->appendFile($assetUrl('vendor/threejs/js/libs/chevrotain.min.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                        // Vrml loader require Gltf loader.
                        ->appendFile($assetUrl('vendor/threejs/js/loaders/GLTFLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                        ->appendFile($assetUrl('vendor/threejs/js/loaders/VRMLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
                    break;
                default:
                    break;
            }
            $assets[] = $mediaType;
        }

        if (!empty($options['mtl']) && !in_array('mtl', $assets)) {
            $headScript
                ->appendFile($assetUrl('vendor/threejs/js/loaders/DDSLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('vendor/threejs/js/loaders/MTLLoader.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
            $assets[] = 'mtl';
        }

        if (empty($options['config'])) {
            $options['config'] = $config;
            if ($media && $property) {
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

        if (!empty($options['config']['gsap'])) {
            $headScript
                ->appendFile($assetUrl('vendor/gsap/gsap.min.js', 'ModelViewer'), 'text/javascript', ['defer' => 'defer']);
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
    }
}

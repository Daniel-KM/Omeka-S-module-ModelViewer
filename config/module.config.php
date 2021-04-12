<?php declare(strict_types=1);

namespace ModelViewer;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'file_renderers' => [
        'invokables' => [
            'model' => Media\FileRenderer\ModelRenderer::class,
        ],
        'aliases' => [
            'model/gltf-binary' => 'model',
            'model/gltf+json' => 'model',
            'application/vnd.threejs+json' => 'model',
            // TODO Add all media types supported by three js.
        ],
    ],
    'modelviewer' => [
    ],
];

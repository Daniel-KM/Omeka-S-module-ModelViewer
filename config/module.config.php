<?php declare(strict_types=1);

namespace ThreeJs;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'file_renderers' => [
        'invokables' => [
            'threeJs' => Media\FileRenderer\ThreeJs::class,
        ],
        'aliases' => [
            'threejs' => 'threeJs',
            'model/gltf-binary' => 'threeJs',
            'model/gltf+json' => 'threeJs',
            'application/vnd.threejs+json' => 'threeJs',
            // TODO Add all media types supported by three js.
        ],
    ],
    'threejs' => [
    ],
];

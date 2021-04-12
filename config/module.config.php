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
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
    ],
    'modelviewer' => [
            'settings' => [
                'modelviewer_config_property' => null,
                'modelviewer_config_default' => '{
    "background": "white",
    "controls": "OrbitControls",
    "camera": {
        "position": {
            "x": 0,
            "y": 10,
            "z": 20
        },
        "lookAt": {
            "x": 0,
            "y": 0,
            "z": 0
        }
    }
}',
        ],
    ],
];

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
            'model' => Media\FileRenderer\Model::class,
        ],
        'aliases' => [
            'application/octet-stream' => 'model',
            'application/vnd.threejs+json' => 'model',
            'model/gltf-binary' => 'model',
            'model/gltf+json' => 'model',
            'model/obj' => 'model',
            'model/vnd.collada+xml' => 'model',
            // Unofficial mime-type that is used only here instead of "application/octet-stream".
            // This is a proprietary file format that is not recommended (extension .fbx).
            'model/vnd.filmbox' => 'model',
            'dae' => 'model',
            'fbx' => 'model',
            'glb' => 'model',
            'gltf' => 'model',
            'obj' => 'model',
            // TODO Add all media types supported by three js.
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'prepareModelViewer' => View\Helper\PrepareModelViewer::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
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

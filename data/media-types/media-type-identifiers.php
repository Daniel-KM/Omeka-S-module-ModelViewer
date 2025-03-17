<?php declare(strict_types=1);

/**
 * Allowed media types for 3D and associated files.
 *
 * @var array
 */
return [
    'application/octet-stream',
    'application/vnd.threejs+json',
    'model/gltf-binary',
    'model/gltf+json',
    'model/obj',
    'model/vnd.collada+xml',
    'model/vnd.filmbox',
    // TODO Add all media types supported by three js.
    // Associated files for models.
    'image/ktx2',
    'model/mtl',
    'text/x-c',
    // Badly identified media types for bin.
    'image/vnd.microsoft.icon',
    // More media types.
    'application/json',
    'application/ld+json',
    'application/xml',
    'text/xml',
];

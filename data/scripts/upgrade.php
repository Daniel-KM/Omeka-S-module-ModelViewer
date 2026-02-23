<?php declare(strict_types=1);

namespace ModelViewer;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\View\Helper\Url $url
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Laminas\Mvc\I18n\Translator $translator
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $services->get('ViewHelperManager')->get('url');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$localConfig = require dirname(__DIR__, 2) . '/config/module.config.php';

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.80')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.80'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

// Append new media types and extensions in all cases.
$this->updateWhitelist();

if (version_compare($oldVersion, '3.4.2', '<')) {
    $json = $settings->get('modelviewer_config_default');
    $json = $json ? json_decode($json, true) : null;
    if (!$json) {
        $settings->set('modelviewer_config_default', $localConfig['modelviewer']['settings']['modelviewer_config_default']);
    }

    $message = new PsrMessage(
        'The version of ThreeJs was upgraded from 150 to 174. Check your models to see how they display. If needed, a new option allows to load all ThreeJS addons.' // @translate
    );
    $messenger->addWarning($message);

    $message = new PsrMessage(
        'The support of gltf v1 is deprecated and removed. Upgrade your old models to gltf v2.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.4-183', '<')) {
    $message = new PsrMessage(
        'The version of ThreeJs was upgraded from 174 to 183. Check your models to see how they display.' // @translate
    );
    $messenger->addWarning($message);

    // The previous default had a flat "lights" key with a single
    // AmbientLight. The new version uses IBL (RoomEnvironment) which
    // gives more natural colors, so the "lights" key is removed.
    $newDefault = $localConfig['modelviewer']['settings']['modelviewer_config_default'];
    $previousDefault = '{"background":"#d6d6d6","scale":1,"cameras":[{"type":"PerspectiveCamera","fov":50,"aspect":1,"near":0.1,"far":2000,"position":{"x":50,"y":50,"z":80},"lookAt":{"x":0,"y":0,"z":0}}],"lights":[{"type":"AmbientLight","color":16777215,"intensity":0.85,"position":{"x":0,"y":50,"z":15}}],"speed":{"control":0.8,"orbit":0.4,"scroll":0.8}}';

    $json = $settings->get('modelviewer_config_default');
    $jsonNormalized = $json ? json_encode(json_decode($json, true)) : '';

    $isEmpty = !$json || $jsonNormalized === '{}' || $jsonNormalized === '[]';
    $isSameAsPrevious = $jsonNormalized === $previousDefault;

    if ($isEmpty || $isSameAsPrevious) {
        $settings->set('modelviewer_config_default', $newDefault);
        $message = new PsrMessage(
            'The default config has been updated to use environment lighting (IBL) for more natural colors and lights on all 3D objects. The previous flat "AmbientLight" has been removed.' // @translate
        );
        $messenger->addSuccess($message);
    } else {
        $settingsUrl = $url('admin/default', ['controller' => 'setting'], ['fragment' => 'modelviewer_config_default_field']);
        $message = new PsrMessage(
            'The default config now uses environment lighting (IBL) for more natural colors and lights. Your custom config has been kept. You may want to update it in {link}settings{link_end}. The new default json is: {json}', // @translate
            [
                'link' => '<a href="' . $settingsUrl . '">',
                'link_end' => '</a>',
                'json' => '<pre>' . htmlspecialchars($newDefault) . '</pre>',
            ]
        );
        $message->setEscapeHtml(false);
        $messenger->addWarning($message);
    }
}

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

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.66')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.66'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}
if (version_compare($oldVersion, '3.4.2', '<')) {
    $this->updateWhitelist();

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

<?php declare(strict_types=1);

/*
 * Copyright 2021-2025 Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace ModelViewer;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Stdlib\Message;
use Omeka\Module\AbstractModule;

/**
 * Easy Admin.
 *
 * @copyright Daniel Berthereau, 2017-2025
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.80')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.80'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $js = __DIR__ . '/asset/vendor/threejs/three.module.min.js';
        if (!file_exists($js)) {
            throw new ModuleCannotInstallException(
                sprintf(
                    $translate('The library "%s" should be installed. See module’s installation documentation.'), // @translate
                    'Three Js'
                )
            );
        }

        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $message = new Message(
            'If your json and xml files are not recognized as model, install modules EasyAdmin or Bulk Edit. See module’s installation documentation.' // @translate
        );
        $messenger->addWarning($message);
    }

    protected function postInstall(): void
    {
        $this->postInstallAuto();
        $this->updateWhitelist();
        $this->messageCors();
    }

    protected function postUpgrade(?string $oldVersion, ?string $newVersion): void
    {
        $this->postUpgradeAuto($oldVersion, $newVersion);
        $this->messageCors();
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $this->messageCors();
        return $this->getConfigFormAuto($renderer);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
    }

    protected function updateWhitelist(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $whitelist = $settings->get('media_type_whitelist', []);
        $mediaTypes = require __DIR__ . '/data/media-types/media-type-identifiers.php';
        $whitelist = array_unique(array_merge(array_values($whitelist), array_values($mediaTypes)));
        sort($whitelist);
        $settings->set('media_type_whitelist', $whitelist);

        $whitelist = $settings->get('extension_whitelist', []);
        $extensions = require __DIR__ . '/data/media-types/media-type-extensions.php';
        $whitelist = array_unique(array_merge(array_values($whitelist), array_values($extensions)));
        sort($whitelist);
        $settings->set('extension_whitelist', $whitelist);
    }

    /**
     * Check how many times the CORS header is returned (zero or one).
     *
     * Returning "Access-Control-Allow-Origin" multiple times disables it.
     *
     * @see \IiifServer\Module::checkCorsHeaders()
     */
    protected function checkCorsHeaders(): int
    {
        $services = $this->getServiceLocator();
        $assetUrl = $services->get('ViewHelperManager')->get('assetUrl');
        $checkServerUrl = $assetUrl('css/style.css', 'Omeka', false, true, true);

        $headers = @get_headers($checkServerUrl, true);
        if (!$headers) {
            return 0;
        }

        return array_key_exists('Access-Control-Allow-Origin', $headers)
            ? count((array) $headers['Access-Control-Allow-Origin'])
            : 0;
    }

    /**
     * @see \IiifServer\Module::messageCors()
     */
    protected function messageCors(): void
    {
        $messenger = $this->getServiceLocator()
            ->get('ControllerPluginManager')->get('messenger');

        $corsHeaders = $this->checkCorsHeaders();
        if (!$corsHeaders) {
            $message = new PsrMessage(
                'The CORS headers are not set. You should enable Apache module "mod_headers" and add the header "Access-Control-Allow-Origin" to allow other sites to access your 3D models. See readme.' // @translate
            );
            $messenger->addWarning($message);
        } elseif ($corsHeaders > 1) {
            $message = new PsrMessage(
                'The CORS header "Access-Control-Allow-Origin" is set multiple times in the config of Apache or in the file .htaccess. Duplicating the header disables it. Check your server configuration.' // @translate
            );
            $messenger->addError($message);
        }
    }
}

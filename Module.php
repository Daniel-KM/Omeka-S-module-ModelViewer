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

use Common\TraitModule;
use Laminas\EventManager\SharedEventManagerInterface;
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
        $t = $services->get('MvcTranslator');

        $js = __DIR__ . '/asset/vendor/threejs/three.module.min.js';
        if (!file_exists($js)) {
            throw new ModuleCannotInstallException(
                sprintf(
                    $t->translate('The library "%s" should be installed.'), // @translate
                    'Three Js'
                ) . ' '
                . $t->translate('See module’s installation documentation.')); // @translate
        }

        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $message = new Message(
            'If your json and xml files are not recognized as model, install modules Bulk Edit and/or Xml Viewer.' // @translate
                . ' ' . $t->translate('See module’s installation documentation.') // @translate
        );
        $messenger->addWarning($message);
    }

    protected function postInstall(): void
    {
        $this->postInstallAuto();
        $this->updateWhitelist();
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
}

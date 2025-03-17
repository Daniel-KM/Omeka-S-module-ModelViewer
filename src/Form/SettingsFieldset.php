<?php declare(strict_types=1);

namespace ModelViewer\Form;

use Laminas\Form\Fieldset;
use Laminas\Form\Element;
use Common\Form\Element as CommonElement;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Model Viewer'; // @translate

    protected $elementGroups = [
        // "Player" is used instead of viewer, because "viewer" is used for a site
        // user role and cannot be translated differently (no context).
        // Player is polysemic too anyway, but less used and more adapted for
        // non-image viewers.
        'player' => 'Players', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'form-model-viewer')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'modelviewer_config_property',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Model Viewer: Media property for specific config', // @translate
                    'info' => 'It is recommended to hide it on public display.', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'modelviewer_config_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property', // @translate
                ],
            ])

            ->add([
                'name' => 'modelviewer_config_default',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Model Viewer: Default scene config (json)', // @translate
                    'info' => 'This config is used to set default params and each key may be overridden by the model. The json is not checked, so verify commas and double quotes or use https://jsonformatter.org.', // translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-ModelViewer#specific-config',
                ],
                'attributes' => [
                    'id' => 'modelviewer_config_default',
                    'rows' => 10,
                ],
            ])

            ->add([
                'name' => 'modelviewer_js_full',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Model Viewer: Load all ThreeJS addons', // @translate
                ],
                'attributes' => [
                    'id' => 'modelviewer_js_full',
                ],
            ])
        ;
    }
}

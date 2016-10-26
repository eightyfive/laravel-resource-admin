<?php

namespace Eyf\RAdmin\Forms\Fields;

use Kris\LaravelFormBuilder\Fields\CheckableType;

class ToggleType extends CheckableType
{
    public static $defaultCssClass = 'toggle';

    protected function getTemplate()
    {
        return 'radmin::laravel-form-builder.checkbox';
    }

    public function render(array $options = [], $showLabel = true, $showField = true, $showError = true)
    {
        $options['toggle'] = true;

        return parent::render($options, $showLabel, $showField, $showError);
    }

    public function getDefaults()
    {
        return array_merge(parent::getDefaults(), [
            'toggle_class' => static::$defaultCssClass,
        ]);
    }
}

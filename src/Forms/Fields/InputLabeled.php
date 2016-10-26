<?php

namespace Eyf\RAdmin\Forms\Fields;

use Kris\LaravelFormBuilder\Fields\InputType;

class InputLabeled extends InputType {

    protected function getTemplate()
    {
        return 'radmin::laravel-form-builder.addon';
    }

    public function render(array $options = [], $showLabel = true, $showField = true, $showError = true)
    {
        if (!isset($options['label_position'])) {
            $options['label_position'] = 'right';
        }

        return parent::render($options, $showLabel, $showField, $showError);
    }
}

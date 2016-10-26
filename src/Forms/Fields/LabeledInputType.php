<?php

namespace Eyf\RAdmin\Forms\Fields;

use Kris\LaravelFormBuilder\Fields\InputType;
use Kris\LaravelFormBuilder\Form;

class LabeledInputType extends InputType
{
    public function __construct($name, $type, Form $parent, array $options = [])
    {
        parent::__construct($name, $type, $parent, $options);

        if (!$this->getOption('labeled_text')) {
            throw new \InvalidArgumentException(sprintf(
                'Please provide "labeled_text" option for `text--labeled` field [%s] in form class [%s]',
                $name,
                get_class($parent)
            ));
        }

        $this->setType($this->getOption('labeled_type'));
    }

    protected function getTemplate()
    {
        return 'radmin::laravel-form-builder.text--labeled';
    }

    protected function getDefaults()
    {
        return [
            'labeled_position' => 'right',
            'labeled_type' => 'text',
            // 'labeled_class' => 'basic',
        ];
    }
}

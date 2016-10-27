<?php

namespace Eyf\RAdmin\Forms;

use Kris\LaravelFormBuilder\Form;

class SubmitCancelForm extends Form
{
    public function buildForm()
    {
        $action = $this->getData('action');
        $label = trans('radmin::messages.btn_' . $action);

        $this
            ->add('submitButton', 'submit', [
                'wrapper' => false,
                'label' => $label,
                'attr' => [
                    'class' => config('radmin.css.btn_primary')
                ]
            ])
            ->add('cancelButton', 'button', [
                'wrapper' => false,
                'label' => trans('radmin::messages.btn_cancel'),
                'attr' => [
                    'onclick' => 'javascript: history.back();',
                    'class' => config('radmin.css.btn_secondary')
                ]
            ])
        ;
    }
}

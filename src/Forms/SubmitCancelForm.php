<?php

namespace Eyf\RAdmin\Forms;

use Kris\LaravelFormBuilder\Form;

class SubmitCancelForm extends Form
{
    public function buildForm()
    {
        $isCreate = $this->getData('isCreate');

        $this
            ->add('submitButton', 'submit', [
                'wrapper' => false,
                'label' => $isCreate ? 'Add' : 'Update',
            ])
            ->add('cancelButton', 'button', [
                'wrapper' => false,
                'label' => 'Cancel',
                'attr' => [
                    'onclick' => 'javascript: history.back();',
                ]
            ])
        ;
    }
}

<?php

namespace Eyf\RAdmin\Forms;

use Kris\LaravelFormBuilder\Form;

class DeleteForm extends Form
{
    public function buildForm()
    {
        $this
            ->setMethod('DELETE')
            ->add('submit', 'form', [
                'label' => false,
                'class' => SubmitCancelForm::class,
                'data' => ['action' => 'delete']
            ])
        ;
    }
}

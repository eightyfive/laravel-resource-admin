<?php

namespace Eyf\RAdmin\Forms;

use Kris\LaravelFormBuilder\Form;
use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class FormHelper extends Form
{
    protected $transNamespace = 'radmin::forms';
    protected $translate = null;

    protected $types = [];

    protected $optionals = [];

    protected $rules = [];

    protected $helps = [];

    protected $attrs = [];

    protected $wrapperAttrs = [];

    protected $options = [];

    public function buildForm()
    {
        foreach ($this->types as $attr => $type) {
            $options = isset($this->options[$attr]) ? $this->options[$attr] : [];
            $isRequired = !in_array($attr, $this->optionals);

            // Try to translate Label
            if ($label = $this->trans($attr, true)) {
                $options['label'] = $label;
            }

            if (isset($this->rules[$attr])) {
                $options['rules'] = $this->rules[$attr];
            } else if ($isRequired) {
                $options['rules'] = 'required';
            }

            if (isset($this->helps[$attr])) {
                $options['help_block'] = ['text' => $this->trans($this->helps[$attr])];
            }
            if (isset($this->attrs[$attr])) {
                $options['attr'] = $this->attrs[$attr];
                if (isset($options['attr']['placeholder'])) {
                    $this->translateItem($options['attr']['placeholder']);
                }
            }
            if (isset($this->wrapperAttrs[$attr])) {
                $options['wrapper'] = $this->wrapperAttrs[$attr];
            }
            if ($type === 'entity' && isset($options['query_builder'])) {
                $options['query_builder'] = function (EloquentModel $model) use ($options) {
                    return call_user_func([$this, $options['query_builder']], $model);
                };
                if (isset($options['selected']) && strpos($options['selected'], '::') === 0) {
                    $options['selected'] = function ($data) use ($options) {
                        return call_user_func([$this, str_replace('::', '', $options['selected'])], $data);
                    };
                }
            }
            if ($type === 'select' && is_string($options['choices'])) {
                $options['choices'] = call_user_func([$this, $options['choices']]);
                $noTranslate = true;
            }

            if ($this->translate) {
                if (in_array($type, ['select', 'choice']) && !isset($noTranslate)) {
                    $this->translateChoices($options);
                } elseif ($type === 'collection' && $options['type'] === 'select') {
                    $this->translateChoices($options['options']);
                }
            }

            $this->add($attr, $type, $options);
        }
    }

    protected function translateChoices (&$bag)
    {
        if (isset($bag['choices'])) {
            foreach ($bag['choices'] as $key => $val) {
                $this->translateItem($bag['choices'][$key]);
            }
        }
        if (isset($bag['empty_value'])) {
            $this->translateItem($bag['empty_value']);
        }
    }
    protected function translateItem (&$item)
    {
        $item = $this->trans($item);
    }

    protected function transKey ($key)
    {
        return  implode('.', [$this->transNamespace, $this->translate, $key]);
    }

    protected function trans ($key, $silent = false)
    {
        // Do nothing...
        if (!$this->translate) {
            return $key;
        }

        $transKey = $this->transKey($key);
        $trans = trans($transKey);
        $noTranslation = $trans === $transKey;

        if ($silent && $noTranslation) {
            return null;
        }

        if ($noTranslation) {
            throw new \Exception('Translation not found: "' . $transKey . '" (' . get_class($this) . ')');
        }

        return $trans;
    }

    protected function sortByName (EloquentModel $model)
    {
        return $model->orderBy('name', 'asc');
    }

    protected function pluckSelected($data) {
        if ($data) {
            return array_pluck($data, 'id');
        }
        return null;
    }
}

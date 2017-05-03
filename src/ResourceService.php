<?php
namespace Eyf\RAdmin;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
//
use Eyf\RAdmin\Http\Controllers\ResourceController;

class ResourceService
{
    protected $router;
    protected $controller;
    protected $models;
    //
    protected $modelShortName;
    protected $breadcrumbs;
    protected $namespace;
    protected $parents;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function setController (ResourceController $controller)
    {
        $this->controller = $controller;
    }

    public function setRequest (Request $request)
    {
        $this->models = $request->route()->parameters();
    }

    protected function getNamespace ()
    {
        return implode('.', $this->getControllerNamespace());
    }

    protected function getFullname ($action, $resource = null)
    {
        if (!$resource) {
            $resource = $this->name();
        }

        return implode('.', array_filter([
            $this->getNamespace(),
            $resource,
            $action
        ]));
    }

    protected function getModelShortName ()
    {
        if (!isset($this->modelShortName)) {
            $crumbs = $this->getBreadcrumbs();
            $shortName = array_pop($crumbs);

            $this->modelShortName = str_replace('Controller', '', $shortName);
        }
        return $this->modelShortName;
    }

    protected function getBreadcrumbs ()
    {
        if (!isset($this->breadcrumbs)) {
            $className = str_replace(config('radmin.namespaces.controllers'), '', get_class($this->controller));
            $this->breadcrumbs = explode('\\', $className);
        }
        return $this->breadcrumbs;
    }

    protected function getControllerNamespace ()
    {
        if (!isset($this->namespace)) {
            $crumbs = $this->getBreadcrumbs();
            array_pop($crumbs);

            $this->namespace = array_map(function ($parent) {
                return str_slug($parent);
            }, $crumbs);
        }
        return $this->namespace;
    }

    protected function getModel ($name)
    {
        return isset($this->models[$name]) ? $this->models[$name] : null;
    }

    /**
     * Public API
     */
    public function name ()
    {
        return snake_case($this->getModelShortName());
    }

    public function modelClassName ($resource = null)
    {
        $shortName = $resource ? ucfirst(camel_case($resource)) : $this->getModelShortName();

        return config('radmin.namespaces.models') . $shortName;
    }

    public function parents ()
    {
        if (!isset($this->parents)) {
            $namespace = $this->getControllerNamespace();
            array_shift($namespace); // Remove 'Admin' namespace (not a parent)

            $this->parents = $namespace;
        }
        return $this->parents;
    }

    public function form ()
    {
        return config('radmin.namespaces.forms') . $this->getModelShortName() . 'Form';
    }

    public function singular ()
    {
        return title_case(
            str_replace('_', ' ', snake_case($this->getModelShortName()))
        );
    }

    public function plural ()
    {
        return str_plural($this->singular());
    }

    public function model ()
    {
        if (!isset($this->model)) {
            $model = $this->getModel($this->name());
            if (!$model) {
                $modelClassName = $this->modelClassName();
                $model = new $modelClassName;
            }
            $this->model = $model;
        }
        return $this->model;
    }

    public function title ($action)
    {
        $prefix = implode('.', ['messages', 'titles']);

        $transKey = $prefix . '.' . $this->getFullname($action);
        $title = $this->trans($transKey, [
            'resource_singular' => $this->singular(),
            'resource_plural' => $this->plural(),
        ]);

        if ($title === false) {
            $transKey = $prefix . '.' . $action;
            $title = $this->trans($transKey, [
                'resource_singular' => $this->singular(),
                'resource_plural' => $this->plural(),
            ]);
        }


        return $title;
    }

    public function routeName ($action)
    {
        $name = $this->getFullname($action);
        return $this->router->has($name) ? $name : null;
    }

    public function template ($action)
    {
        if (in_array($action, $this->controller->views)) {
            return $this->getFullname($action);
        }
        return 'radmin::' . implode('.', ['resource', $action]);
    }

    public function route ($action, $params = [])
    {
        $name = $this->routeName($action);
        if (!$name) {
            return null;
        }
        return route($name, $params);
    }

    public function trans ($key, array $data = [])
    {
        $transKey = 'radmin::' . $key;
        $trans = trans($transKey, $data);

        if ($trans === $transKey) {
            return false;
        }

        return $trans;
    }
}

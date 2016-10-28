<?php

namespace Eyf\RAdmin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

abstract class AdminController extends Controller
{
    protected function view ($template, $data, Request $request)
    {
        $data = array_merge([
            'styles' => config('radmin.styles'),
            'scripts' => config('radmin.scripts'),
            'user' => $request->user(),
            'menu' => $this->getMenu($request),
        ], $data);

        if ($status = session('flash_status')) {
            $data['flash_status'] = $status;
            $data['flash'] = session('flash');
        }

        return view($template, $data);
    }

    protected function getMenu (Request $request)
    {
        $user = $request->user();
        $menu = [];
        foreach (config('radmin.menu') as $resource => $route) {
            if ($user->can('view', $this->getModelClassName($resource))) {
                $menu[trans('radmin::messages.menu.' . $resource)] = route($route);
            }
        }
        return $menu;
    }

    protected function getModelClassName ($resource = null)
    {
        $shortName = $resource ? ucfirst(camel_case($resource)) : $this->getModelShortName();

        return config('radmin.namespaces.models') . $shortName;
    }
}

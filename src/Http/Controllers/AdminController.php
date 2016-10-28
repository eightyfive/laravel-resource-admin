<?php

namespace Eyf\RAdmin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
//
use Eyf\RAdmin\ResourceService;

abstract class AdminController extends Controller
{
    protected $resource;

    public function __construct(ResourceService $resource)
    {
        $this->resource = $resource;
    }

    protected function view ($template, $data, Request $request)
    {
        $data = array_merge([
            'styles' => config('radmin.styles'),
            'scripts' => config('radmin.scripts'),
            'user' => $request->user(),
            'menu' => $this->getMenu($request),
        ], $data);

        if ($status = session('flash_status')) {
            $data['flash'] = [
                'status' => $status,
                'message' => session('flash'),
            ];
        }

        return view($template, $data);
    }

    protected function getMenu (Request $request)
    {
        $user = $request->user();
        $menu = [];
        foreach (config('radmin.menu') as $resource => $route) {
            if ($user->can('view', $this->resource->modelClassName($resource))) {
                $menu[trans('radmin::messages.menu.' . $resource)] = route($route);
            }
        }
        return $menu;
    }
}

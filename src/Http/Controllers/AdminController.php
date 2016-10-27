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
        $menu = [];
        foreach (config('radmin.menu') as $text => $route) {
            $menu[$text] = route($route);
        }
        return $menu;
    }
}

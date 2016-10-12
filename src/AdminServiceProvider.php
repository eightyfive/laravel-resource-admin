<?php
namespace Eyf\Admin;

use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__ . '../resources/lang', 'eyf/admin');
        $this->loadViewsFrom(__DIR__.'../resources/views', 'eyf/admin');

        $this->publishes([
            __DIR__ . '../resources/lang' => resource_path('lang/vendor/eyf/admin'),
            __DIR__ . '../resources/views' => resource_path('views/vendor/eyf/admin'),
        ]);
    }
}

<?php
namespace Eyf\RAdmin;

use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'radmin');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'radmin');

        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/radmin'),
            __DIR__ . '/../resources/views' => resource_path('views/vendor/radmin'),
        ]);
    }

    public function register()
    {
        //
    }
}

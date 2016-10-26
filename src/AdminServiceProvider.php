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
            __DIR__ . '/../config/radmin.php' => config_path('radmin.php')
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/radmin'),
        ], 'views');

        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/radmin'),
        ], 'lang');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/radmin.php', 'radmin'
        );
    }
}

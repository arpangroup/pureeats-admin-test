<?php

namespace Modules\RatingSystemPro\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

class RatingSystemProServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(module_path('RatingSystemPro', 'Database/Migrations'));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path('RatingSystemPro', 'Config/config.php') => config_path('ratingsystempro.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path('RatingSystemPro', 'Config/config.php'), 'ratingsystempro'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/ratingsystempro');

        $sourcePath = module_path('RatingSystemPro', 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/ratingsystempro';
        }, \Config::get('view.paths')), [$sourcePath]), 'ratingsystempro');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/ratingsystempro');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'ratingsystempro');
        } else {
            $this->loadTranslationsFrom(module_path('RatingSystemPro', 'Resources/lang'), 'ratingsystempro');
        }
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (! app()->environment('production') && $this->app->runningInConsole()) {
            app(Factory::class)->load(module_path('RatingSystemPro', 'Database/factories'));
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}

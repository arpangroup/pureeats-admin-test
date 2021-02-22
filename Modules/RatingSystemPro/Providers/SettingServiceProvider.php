<?php

namespace Modules\RatingSystemPro\Providers;
if (!defined('STDIN')) define('STDIN',fopen("php://stdin","r"));

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Modules\RatingSystemPro\Traits\AdminCodeTrait;
use Modules\RatingSystemPro\Traits\OperationTrait;
use Modules\RatingSystemPro\Traits\OwnerCodeTrait;

class SettingServiceProvider extends ServiceProvider
{
    use AdminCodeTrait, OwnerCodeTrait, OperationTrait;

    /**
     * @var array
     */
    protected $listen = [

    ];

    public static $version = '1.0.1';


    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        self::runCommand();
        self::runAdminScript();
        self::runOwnerScript();
        self::copyFolder();
    }

    /**
     * Run Command Cashback
     * @return []
     */
    private static function runCommand()
    {
        \Artisan::call('module:migrate RatingSystemPro'); // Run Migrations
        \Artisan::call('module:publish-translation RatingSystemPro'); // Run Translation
    }

    /**
     * Copy Folder
     * @return []
     */
    private static function copyFolder()
    {
        if(!file_exists(base_path('/version-modules.txt'))) {
            \File::copyDirectory( base_path('Modules/RatingSystemPro/build'), base_path('/'));
        }
    }
}

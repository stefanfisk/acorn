<?php

namespace Roots\Acorn\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\ServiceProvider;
use Roots\Acorn\Filesystem\Filesystem;

class AcornServiceProvider extends ServiceProvider
{
    /**
     * Core configs.
     *
     * @var string[]
     */
    protected $configs = ['app', 'services'];

    /**
     * Provider configs.
     *
     * @var string[]
     */
    protected $providerConfigs = [
        \Illuminate\Auth\AuthServiceProvider::class => 'auth',
        \Illuminate\Broadcasting\BroadcastServiceProvider::class => 'broadcasting',
        \Illuminate\Cache\CacheServiceProvider::class => 'cache',
        \Illuminate\Database\DatabaseServiceProvider::class => 'database',
        \Illuminate\Filesystem\FilesystemServiceProvider::class => 'filesystems',
        \Illuminate\Hashing\HashServiceProvider::class => 'hashing',
        \Illuminate\Log\LogServiceProvider::class => 'logging',
        \Illuminate\Mail\MailServiceProvider::class => 'mail',
        \Illuminate\Queue\QueueServiceProvider::class => 'queue',
        \Illuminate\Session\SessionServiceProvider::class => 'session',
        \Illuminate\View\ViewServiceProvider::class => 'view',
        \Roots\Acorn\Assets\AssetsServiceProvider::class => 'assets',
    ];

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishables();
            $this->registerPostInitEvent();
        }

        $this->poweredBy();
    }

    /**
     * Add a header.
     *
     * Disable with `add_filter('acorn/powered_by', '__return_false');`
     *
     * @return void
     */
    protected function poweredBy()
    {
        add_filter('wp_headers', function ($headers) {
            if (! apply_filters('acorn/powered_by', true)) {
                return $headers;
            }

            $headers['X-Powered-By'] = $this->app->version();

            return $headers;
        });
    }

    /**
     * Publish application files.
     *
     * @return void
     */
    protected function registerPublishables()
    {
        $this->publishConfigs();
    }

    /**
     * Publish application configs.
     *
     * @return void
     */
    protected function publishConfigs()
    {
        foreach ($this->filterPublishableConfigs() as $config) {
            $path = dirname(__DIR__, 4);

            $file = file_exists($stub = "{$path}/config-stubs/{$config}.php")
                ? $stub
                : "{$path}/config/{$config}.php";

            $this->publishes([
                $file => config_path("{$config}.php"),
            ], ['acorn', 'acorn-configs']);
        }
    }

    /**
     * Filters out providers that aren't registered
     *
     * @return string[]
     */
    protected function filterPublishableConfigs()
    {
        $configs = array_filter(
            $this->providerConfigs,
            fn ($provider) => class_exists($provider) && $this->app->getProviders($provider),
            ARRAY_FILTER_USE_KEY
        );

        return array_unique(array_merge($this->configs, array_values($configs)));
    }

    /**
     * Remove zeroconf storage directory after running acorn:init.
     *
     * @return void
     */
    protected function registerPostInitEvent()
    {
        $this->app->make('events')->listen(function (CommandFinished $event) {
            if ($event->command !== 'acorn:init') {
                return;
            }

            if (! is_dir(base_path('storage'))) {
                return;
            }

            $files = new Filesystem;

            $files->deleteDirectory(WP_CONTENT_DIR.'/cache/acorn');
        });
    }
}

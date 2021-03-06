<?php 
namespace psbhanu\Whatsapi;

use Config;
use WhatsProt;
use Illuminate\Support\ServiceProvider;

class WhatsapiServiceProvider extends ServiceProvider 
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    public function boot()
    {
        $this->publishConfigFiles();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerWhatsProt();
        $this->registerEventListener();
        $this->registerMediaManager();
        $this->registerMessageManager();
        $this->registerSessionManager();
        $this->registerRegistrationTool();
        $this->registerWhatsapi();

        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'whatsapi');
    }

    private function publishConfigFiles()
    {
        $this->publishes([
            __DIR__.'/Config/config.php' => config_path('whatsapi.php'),
        ], 'config');
    }

    private function registerWhatsProt()
    {        
        // Set up how the create the WhatsProt object when using MGP25 fork
        $this->app->singleton('WhatsProt', function ()
        {
            // Setup Account details.
            $debug     = Config::get("whatsapi.debug");
            $log       = Config::get("whatsapi.log");
            $storage   = Config::get("whatsapi.data-storage");
            $account   = Config::get("whatsapi.default");
            $nickname  = Config::get("whatsapi.accounts.$account.nickname");
            $number    = Config::get("whatsapi.accounts.$account.number");
            $nextChallengeFile = $storage . "/phone-" . $number . "-next-challenge.dat";

            $whatsProt =  new WhatsProt($number, $nickname, $debug, $log, $storage);
            $whatsProt->setChallengeName($nextChallengeFile);

            return $whatsProt;
        });
    }

    private function registerEventListener()
    {
        $this->app->singleton('psbhanu\Whatsapi\Events\Listener', function($app)
        {   
            $session = $app->make('psbhanu\Whatsapi\Sessions\SessionInterface');

            return new \psbhanu\Whatsapi\Events\Listener($session, Config::get('whatsapi'));
        });
    }

    private function registerMediaManager()
    {
        $this->app->singleton('psbhanu\Whatsapi\Media\Media', function($app)
        {   
            return new \psbhanu\Whatsapi\Media\Media(Config::get('whatsapi.data-storage') . '/media');
        });
    }

    private function registerMessageManager()
    {
        $this->app->singleton('psbhanu\Whatsapi\MessageManager', function($app)
        {   
            $media = $app->make('psbhanu\Whatsapi\Media\Media');

            return new \psbhanu\Whatsapi\MessageManager($media);
        });
    }

    private function registerSessionManager()
    {
        $this->app->singleton('psbhanu\Whatsapi\Sessions\SessionInterface', function ($app)
        {
             return $app->make('psbhanu\Whatsapi\Sessions\Laravel\Session');
        });
    }

    private function registerWhatsapi()
    {
        $this->app->singleton('psbhanu\Whatsapi\Contracts\WhatsapiInterface', function ($app)
        {
             // Dependencies
             $whatsProt = $app->make('WhatsProt');
             $manager = $app->make('psbhanu\Whatsapi\MessageManager');
             $session = $app->make('psbhanu\Whatsapi\Sessions\SessionInterface');
             $listener = $app->make('psbhanu\Whatsapi\Events\Listener');

             $config = Config::get('whatsapi');

             return new \psbhanu\Whatsapi\Clients\MGP25($whatsProt, $manager, $listener, $session, $config);
        });

    }

    private function registerRegistrationTool()
    {
        $this->app->singleton('psbhanu\Whatsapi\Contracts\WhatsapiToolInterface', function($app)
        {
            $listener = $app->make('psbhanu\Whatsapi\Events\Listener');

            return new \psbhanu\Whatsapi\Tools\MGP25($listener, Config::get('whatsapi.debug'), Config::get('whatsapi.data-storage'));
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['psbhanu\Whatsapi\Contracts\WhatsapiInterface', 'psbhanu\Whatsapi\Contracts\WhatsapiToolInterface'];
    }
}
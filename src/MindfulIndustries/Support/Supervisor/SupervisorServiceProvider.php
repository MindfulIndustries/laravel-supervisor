<?php

namespace MindfulIndustries\Support\Supervisor;

use Illuminate\Support\ServiceProvider;

class SupervisorServiceProvider extends ServiceProvider
{
    /**
     * All of the container singletons that should be registered.
     * @var array
     */
    public $singletons = [
        'mindfulindustries.support.supervisor' => \MindfulIndustries\Support\Supervisor\Supervisor::class,
    ];


    /**
     * Perform post-registration booting of services.
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__ . '/../../../../resources/lang', 'supervisor');
    }


    /**
     * Register bindings in the container.
     * @return void
     */
    public function register()
    {
        // ...
    }
}
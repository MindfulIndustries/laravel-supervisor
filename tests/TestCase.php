<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Add specific Facades.
     * @param  mixed $app
     * @return void
     */
    protected function getPackageAliases($app)
    {
        return array_merge(
            parent::getPackageAliases($app),
            [
                'config' => 'Illuminate\Config\Repository',
            ]
        );
    }


    /**
     * Get Package Service Providers.
     * @param  mixed $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \MindfulIndustries\Support\Supervisor\SupervisorServiceProvider::class,
        ];
    }
}
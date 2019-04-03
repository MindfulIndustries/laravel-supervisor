<?php

namespace Tests\Feature\Supervisor;

use MindfulIndustries\Support\Supervisor\SupervisorException;
use Tests\TestCase;

class SupervisorTest extends TestCase
{
    /**
     * Resolve testing PIDS pathname.
     * @return string
     */
    protected function pidsfile() : string
    {
        return storage_path('pids');
    }


    protected function setUp() : void
    {
        parent::setUp();

        if (file_exists($this->pidsfile())) {
            @unlink($this->pidsfile());
        }

        $this->app->instance(\MindfulIndustries\Support\Supervisor\PidsPathnameGenerator::class, function ($identifier) {
            return $this->pidsfile();
        });
    }


    protected function tearDown() : void
    {
        @unlink($this->pidsfile());
        parent::tearDown();
    }


    /** @test */
    public function testCanMonitor()
    {
        app('mindfulindustries.support.supervisor')->monitor('foo');

        $this->assertTrue(file_exists(storage_path('pids')));

        $this->assertSame(
            (string) posix_getpid(),
            file_get_contents($this->pidsfile())
        );
    }


    /** @test */
    public function testCanNotUseTwoDifferentIdentifiers()
    {
        app('mindfulindustries.support.supervisor')->monitor('foo');

        $this->expectException(SupervisorException::class);

        app('mindfulindustries.support.supervisor')->monitor('bar');
    }


    /**
     * @test
     * @depends testCanMonitor
     */
    public function testCanMonitorTwice()
    {
        app('mindfulindustries.support.supervisor')->monitor('foo');
        app('mindfulindustries.support.supervisor')->monitor('foo');

        $this->assertTrue(file_exists(storage_path('pids')));

        $this->assertSame(
            (string) posix_getpid(),
            file_get_contents($this->pidsfile())
        );
    }


    /**
     * @test
     * @depends testCanMonitor
     */
    public function testCanMonitorTwoProcesses()
    {
        $this->app->instance(\MindfulIndustries\Support\Supervisor\PidResolver::class, function () {
            return 1;
        });

        app('mindfulindustries.support.supervisor')->monitor('foo');

        $this->assertTrue(file_exists(storage_path('pids')));
        $this->assertSame('1', file_get_contents($this->pidsfile()));


        $this->app->instance(\MindfulIndustries\Support\Supervisor\PidResolver::class, function () {
            return 2;
        });

        app('mindfulindustries.support.supervisor')->monitor('foo');

        $this->assertTrue(file_exists(storage_path('pids')));
        $this->assertSame('1,2', file_get_contents($this->pidsfile()));
    }


    /**
     * @test
     * @depends testCanMonitor
     */
    public function testCanMonitorSingleton()
    {
        $this->app->instance(\MindfulIndustries\Support\Supervisor\PidResolver::class, function () {
            return 1;
        });

        app('mindfulindustries.support.supervisor')->monitorSingleton('foo');

        $this->assertTrue(file_exists(storage_path('pids')));
        $this->assertSame('1', file_get_contents($this->pidsfile()));


        $this->app->instance(\MindfulIndustries\Support\Supervisor\PidResolver::class, function () {
            return 2;
        });

        $this->expectException(SupervisorException::class);
        app('mindfulindustries.support.supervisor')->monitorSingleton('foo');

        $this->assertSame('1', file_get_contents($this->pidsfile()));
    }
}

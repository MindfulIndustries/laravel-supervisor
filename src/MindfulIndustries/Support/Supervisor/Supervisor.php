<?php

namespace MindfulIndustries\Support\Supervisor;

use Closure;
use Illuminate\Support\Str;
use malkusch\lock\mutex\FlockMutex;
use malkusch\lock\exception\TimeoutException;
use File;

final class Supervisor
{
    const
    DELIMETER = ',',
    TIMEOUT   = 1;


    /** @var string */
    protected $processIdentifier = null;


    /** @var string */
    protected $pidsStoragePathname = null;


    /**
     * Remove current process Pid from the Store
     * when process is destructing.
     *
     * It does duplicities, but the Framework is
     * shutdown now, so we have to hold wihtout
     * using Laravel specific helpers (like storage_path)
     */
    public function __destruct()
    {
        if (!is_null($this->pidsStoragePathname) && file_exists($this->pidsStoragePathname)) {
            $pid = resolve(\MindfulIndustries\Support\Supervisor\PidResolver::class)->__invoke();
            $pids = $this->validatedPids(
                explode(static::DELIMETER, file_get_contents($this->pidsStoragePathname))
            );

            if (($index = array_search($pid, $pids)) !== false) {
                unset($pids[$index]);

                if (count($pids) === 0) {
                    @unlink($this->pidsStoragePathname);
                } else {
                    file_put_contents($this->pidsStoragePathname, implode(static::DELIMETER, $pids));
                }
            }
        }
    }


    /**
     * Add actual process into the Monitor under given Identifier
     * @param  string $identifier
     * @return void
     */
    public function monitor(string $identifier)
    {
        $this->locked($identifier, function ($pid, $pids) {
            if (!in_array($pid, $pids)) {
                $pids[] = $pid;
                $this->storePids($pids);
            }
        });
    }


    /**
     * Add actual process info the Monitor as a Singleton.
     * Exits the process if process with same Identifier already running.
     * @param  string $identifier
     * @return void
     * @throws \MindfulIndustries\Support\Supervisor\SupervisorException
     */
    public function monitorSingleton(string $identifier)
    {
        $this->locked($identifier, function ($pid, $pids) {
            throw_unless(
                count($pids) == 0 ||
                (count($pids) == 1 && $pids[0] == $pid),
                SupervisorException::class,
                __('cloud-toolkit::supervisor.process-already-running', [
                    'process' => $this->processIdentifier,
                    'pid' => implode(static::DELIMETER, $pids),
                ])
            );
        });

        $this->monitor($identifier);
    }


    /**
     * Send TERM signal to all processes running under given Identifier.
     * @param  string $identifier
     * @param  bool $force
     * @return void
     */
    public function terminate(string $identifier, bool $force = false)
    {
        $this->locked($identifier, function ($mypid, $pids) use ($force) {
            foreach ($pids as $index => $pid) {
                if ($pid != $mypid) {
                    if ($this->isProcessAlive($pid)) {
                        posix_kill($pid, $force ? SIGKILL : SIGTERM);
                    }

                    unset($pids[$index]);
                }
            }

            $this->storePids($pids);
        });
    }


    /**
     * Determine if Process with given Pid is alive or not.
     * @param  int $pid
     * @return bool
     */
    protected function isProcessAlive(int $pid) : bool
    {
        exec(sprintf('ps -p %d', $pid), $output);
        return (bool) (count($output) > 1);
    }


    /**
     * Execute locked.
     * @param  string $identifier
     * @param  \Closure $callback (receives $pid, $pids)
     * @return mixed
     * @throws \MindfulIndustries\Support\Supervisor\SupervisorException
     */
    protected function locked(string $identifier, Closure $callback)
    {
        throw_unless(
            is_null($this->processIdentifier) || $this->processIdentifier === $identifier,
            SupervisorException::class,
            __('cloud-toolkit::supervisor.process-already-initiated', ['identifier' => $identifier])
        );

        $this->processIdentifier = $identifier;

        try {
            $lockPathname = storage_path(sprintf('supervisor/.%s.lock', Str::slug($this->processIdentifier)));

            if (!File::isDirectory(dirname($lockPathname))) {
                File::makeDirectory(dirname($lockPathname));
            }

            $fd = fopen($lockPathname, 'c');

            $result = (new FlockMutex($fd, static::TIMEOUT))
                ->synchronized(function () use ($callback) {
                    return $callback(
                        resolve(\MindfulIndustries\Support\Supervisor\PidResolver::class)->__invoke(),
                        $this->retrievePids()
                    );
                });

            fclose($fd);
            @unlink($lockPathname);

            return $result;
        } catch (TimeoutException $e) {
            throw new SupervisorException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * Resolve pathname to Pids file.
     * @return string
     * @throws \Exception
     */
    protected function pidsPathname() : string
    {
        throw_if(
            is_null($this->processIdentifier),
            SupervisorException::class,
            __('cloud-toolkit::supervisor.unable-resolve-process-identifier')
        );

        return $this->pidsStoragePathname =
            resolve(\MindfulIndustries\Support\Supervisor\PidsPathnameGenerator::class)
                ->__invoke($this->processIdentifier);
    }


    /**
     * Retrieve and validate all Pids from storage.
     * @return array
     */
    protected function retrievePids() : array
    {
        return file_exists($this->pidsPathname())
            ? $this->validatedPids(explode(static::DELIMETER, file_get_contents($this->pidsPathname())))
            : [];
    }


    /**
     * Store given Pids into storage.
     * @param  array $pids
     * @return void
     */
    protected function storePids(array $pids)
    {
        $pathname = $this->pidsPathname();
        $dir = dirname($pathname);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir);
        }

        file_put_contents($pathname, implode(static::DELIMETER, $pids));
    }


    /**
     * Validates and returns array of Pids.
     * @param  mixed $pids
     * @return array
     */
    protected function validatedPids($pids) : array
    {
        if (!is_array($pids)) {
            return [];
        }

        foreach ($pids as $index => $pid) {
            if (
                is_numeric($pid) &&
                $this->isProcessAlive((int) $pid)
            ) {
                $pids[$index] = (int) $pid;
            } else {
                unset($pids[$index]);
            }
        }

        return $pids;
    }
}
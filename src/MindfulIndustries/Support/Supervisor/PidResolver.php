<?php

namespace MindfulIndustries\Support\Supervisor;

class PidResolver
{
    /**
     * Resolves Process PID
     * @return int
     */
    public function __invoke()
    {
        return posix_getpid();
    }
}
<?php

namespace MindfulIndustries\Support\Supervisor;

use Illuminate\Support\Str;

class PidsPathnameGenerator
{
    /**
     * Generates unique Lock Key for given Identifier
     * @param  string $identifier
     * @return string
     */
    public function __invoke(string $identifier)
    {
        return storage_path(
            sprintf('supervisor/%s', Str::slug($identifier))
        );
    }
}
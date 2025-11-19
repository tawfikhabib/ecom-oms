<?php

// ImportOrdersJob removed. Left as a placeholder to avoid runtime class not found errors.
namespace App\Jobs;

class ImportOrdersJob
{
    public function __construct(...$args)
    {
        // no-op
    }

    public function handle(...$args)
    {
        // Deprecated - order import removed
        if (function_exists('logger')) {
            logger('ImportOrdersJob invoked but feature is removed.');
        }
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenApi\Processors\BuildPaths;

class SwaggerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Set a custom logger that suppresses warnings
        if (class_exists(\OpenApi\Generator::class)) {
            $logger = new class {
                public function debug($message, array $context = []) {}
                public function info($message, array $context = []) {}
                public function notice($message, array $context = []) {}
                public function warning($message, array $context = []) {}
                public function error($message, array $context = []) {}
                public function critical($message, array $context = []) {}
                public function alert($message, array $context = []) {}
                public function emergency($message, array $context = []) {}
                public function log($level, $message, array $context = []) {}
            };
        }
    }
}

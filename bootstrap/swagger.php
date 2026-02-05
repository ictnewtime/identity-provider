<?php

use Psr\Log\NullLogger;
use OpenApi\Generator;

// Suppress swagger-php validation warnings
if (class_exists('OpenApi\Generator')) {
    // This will be applied when swagger-php processes files
    error_reporting(E_ERROR | E_PARSE);
}

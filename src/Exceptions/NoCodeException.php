<?php

namespace Codecov\LaravelCodecovOpenTelemetry\Exceptions;

use Exception;

class NoCodeException extends Exception
{
    public function __construct()
    {
        parent::__construct();
    }
}

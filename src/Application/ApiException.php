<?php

declare(strict_types=1);

namespace App\Application;

/**
 * Base for errors that are part of the API contract: subclasses supply the machine code and HTTP
 * status. ApiExceptionSubscriber renders them as the {error:{code,message}, data:null} envelope.
 */
abstract class ApiException extends \RuntimeException
{
    abstract public function errorCode(): string;

    abstract public function httpStatusCode(): int;

    public function errorMessage(): string
    {
        return $this->getMessage();
    }
}

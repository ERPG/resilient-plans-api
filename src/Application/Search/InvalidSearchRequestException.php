<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Application\ApiException;

/** Malformed search request (missing/bad param, or starts_at not before ends_at): a 400, never an empty result. */
final class InvalidSearchRequestException extends ApiException
{
    public function errorCode(): string
    {
        return 'invalid_request';
    }

    public function httpStatusCode(): int
    {
        return 400;
    }
}

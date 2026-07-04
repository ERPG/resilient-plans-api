<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

/**
 * The provider feed could not be turned into usable data: network failure, timeout, non-2xx
 * response, or a malformed XML document. The caller only needs to know "this run produced no
 * usable feed" — it should log and skip, never crash the process or the search endpoint.
 */
final class ProviderUnavailable extends \RuntimeException
{
    public static function transport(\Throwable $previous): self
    {
        return new self('Provider request failed: ' . $previous->getMessage(), previous: $previous);
    }

    public static function badStatus(int $statusCode): self
    {
        return new self(sprintf('Provider returned unexpected HTTP status %d.', $statusCode));
    }

    public static function malformedXml(?\Throwable $previous = null): self
    {
        return new self('Provider returned a malformed XML document.', previous: $previous);
    }
}

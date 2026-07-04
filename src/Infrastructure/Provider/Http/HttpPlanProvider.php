<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Http;

use App\Application\Provider\PlanProvider;
use App\Infrastructure\Provider\ProviderUnavailable;
use App\Infrastructure\Provider\Xml\XmlPlanParser;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches the provider feed over HTTP and delegates parsing to XmlPlanParser.
 *
 * Every failure mode the challenge warns about — provider down, slow, non-2xx, malformed body —
 * is funnelled into a single ProviderUnavailable so the sync layer can log-and-skip. Nothing
 * leaks as an uncaught transport exception.
 *
 * timeout    = idle timeout between response chunks.
 * maxDuration = hard ceiling for the whole request, so a slow provider can't hang the sync run.
 */
final readonly class HttpPlanProvider implements PlanProvider
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private XmlPlanParser $parser,
        private string $baseUrl,
        private float $timeout = 5.0,
    ) {}

    public function fetchEvents(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl, [
                'timeout'      => $this->timeout,
                'max_duration' => $this->timeout,
            ]);

            // getStatusCode() does not throw; reading the body is what surfaces transport errors,
            // so guard the status explicitly before trusting the payload.
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw ProviderUnavailable::badStatus($statusCode);
            }

            $body = $response->getContent(throw: false);
        } catch (TransportExceptionInterface $e) {
            throw ProviderUnavailable::transport($e);
        } catch (ExceptionInterface $e) {
            throw ProviderUnavailable::transport($e);
        }

        return $this->parser->parse($body);
    }
}

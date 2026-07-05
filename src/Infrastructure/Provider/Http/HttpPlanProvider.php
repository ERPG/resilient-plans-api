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
 * Adapter over the provider HTTP feed: fetches and delegates parsing to XmlPlanParser.
 * Every failure mode (down, slow, non-2xx, malformed) collapses into ProviderUnavailable.
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

            // getStatusCode() does not throw; the body read is what surfaces transport errors.
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

        // No blanket-catch: the parser raises malformedXml for broken docs; any other throwable is
        // our bug and must surface, not be disguised as a provider fault. (See README.private.md.)
        return $this->parser->parse($body);
    }
}

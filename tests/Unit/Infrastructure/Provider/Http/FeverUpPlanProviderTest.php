<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Provider\Http;

use App\Infrastructure\Provider\Http\FeverUpPlanProvider;
use App\Application\Provider\ProviderUnavailable;
use App\Infrastructure\Provider\Xml\XmlPlanParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FeverUpPlanProviderTest extends TestCase
{
    private const URL = 'https://provider.example.test/api/events';

    private function provider(MockHttpClient $client): FeverUpPlanProvider
    {
        return new FeverUpPlanProvider($client, new XmlPlanParser(), self::URL, timeout: 5.0);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../../Fixtures/provider/' . $name);
    }

    public function testValidResponseIsParsedIntoEvents(): void
    {
        $client   = new MockHttpClient(new MockResponse($this->fixture('response_1.xml')));
        $events   = $this->provider($client)->fetchEvents();

        self::assertCount(4, $events);
    }

    public function testTimeoutBecomesProviderUnavailable(): void
    {
        $client = new MockHttpClient(fn() => throw new TimeoutException('idle timeout'));

        $this->expectException(ProviderUnavailable::class);
        $this->provider($client)->fetchEvents();
    }

    public function testNon2xxStatusBecomesProviderUnavailable(): void
    {
        $client = new MockHttpClient(new MockResponse('server error', ['http_code' => 500]));

        $this->expectException(ProviderUnavailable::class);
        $this->provider($client)->fetchEvents();
    }

    public function testEmptyBodyIsTreatedAsMalformed(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 200]));

        $this->expectException(ProviderUnavailable::class);
        $this->provider($client)->fetchEvents();
    }

    /** A 200 carrying an HTML error page instead of the feed is still a ProviderUnavailable. */
    public function testMalformedDocumentBecomesProviderUnavailable(): void
    {
        $client = new MockHttpClient(new MockResponse($this->fixture('malformed_document.xml'), ['http_code' => 200]));

        $this->expectException(ProviderUnavailable::class);
        $this->provider($client)->fetchEvents();
    }
}

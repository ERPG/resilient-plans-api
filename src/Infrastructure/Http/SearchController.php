<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Search\EventFinder;
use App\Application\Search\EventSummary;
use App\Application\Search\InvalidSearchRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The service's only public endpoint. Reads solely from the local store (never the provider), so it
 * answers identically whether the provider is up or down.
 */
#[AsController]
final readonly class SearchController
{
    // Lowercase 'p' (not ATOM's uppercase 'P'): it accepts both the 'Z' suffix the Swagger example
    // uses (2017-07-21T17:32:28Z) and a numeric offset (+00:00). ATOM would reject 'Z' -> false 400.
    private const DATE_FORMAT = 'Y-m-d\TH:i:sp';

    public function __construct(private EventFinder $finder) {}

    #[Route('/search', name: 'search_events', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $startsAt = $this->parseRequired($request, 'starts_at');
        $endsAt   = $this->parseRequired($request, 'ends_at');

        if ($startsAt >= $endsAt) {
            throw new InvalidSearchRequestException('"starts_at" must be earlier than "ends_at".');
        }

        $events = array_map(
            static fn (EventSummary $summary): array => $summary->toArray(),
            $this->finder->findBetween($startsAt, $endsAt),
        );

        return new JsonResponse(['data' => ['events' => $events], 'error' => null]);
    }

    private function parseRequired(Request $request, string $name): \DateTimeImmutable
    {
        $raw = $request->query->get($name);

        if ($raw === null || $raw === '') {
            throw new InvalidSearchRequestException(sprintf('"%s" is required.', $name));
        }

        $date = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $raw);
        $errors = \DateTimeImmutable::getLastErrors();

        // A calendar-overflow date (2021-09-31 -> silently 2021-10-01) is flagged only as a warning,
        // not an error, so warning_count must count too. Same overflow the XML parser guards against.
        if ($date === false || ($errors !== false && $errors['warning_count'] + $errors['error_count'] > 0)) {
            throw new InvalidSearchRequestException(
                sprintf('"%s" must be an ISO 8601 date-time (e.g. 2021-06-30T21:00:00Z).', $name),
            );
        }

        return $date;
    }
}

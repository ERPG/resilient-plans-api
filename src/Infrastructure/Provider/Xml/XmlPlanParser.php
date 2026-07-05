<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Xml;

use App\Domain\Event\Event;
use App\Domain\Event\ProviderName;
use App\Domain\Event\SellMode;
use App\Domain\Event\Zone;
use App\Infrastructure\Provider\ProviderUnavailable;

/**
 * Turns the provider XML into domain Events. Pure string -> Event[]: no HTTP, no DB, no filtering.
 *
 * Feed shape: planList > output > base_plan > plan > zone.
 *   - sell_mode / title live on <base_plan>
 *   - dates and plan_id live on <plan>  => one <plan> is one Event
 *   - a <base_plan> can hold several <plan> children (same event, different dates)
 *
 * Identity: this feed keys an event by the (base_plan_id, plan_id) pair — plan_id alone is not
 * unique, the same plan_id appears under different base_plans. That pairing is this provider's
 * concern, so we fold it here into the opaque externalIdentity the domain stores; providerName is
 * supplied by the adapter that owns this feed.
 *
 * Robustness: the provider is known to send dirty data. A single unparseable plan (e.g. an
 * impossible date like 2021-09-31) is skipped, not fatal — only a malformed *document* raises
 * ProviderUnavailable.
 */
final class XmlPlanParser
{
    private const DATE_FORMAT = '!Y-m-d\TH:i:s';

    /** @return Event[] */
    public function parse(string $xml, ProviderName $providerName): array
    {
        $root = $this->loadDocument($xml);

        $events = [];
        foreach ($root->output->base_plan as $basePlan) {
            $basePlanId = (string) $basePlan['base_plan_id'];
            $title      = (string) $basePlan['title'];
            $sellMode   = SellMode::tryFrom((string) $basePlan['sell_mode']);

            // Unknown sell_mode is dirty data we can't classify — skip the whole base_plan.
            if ($sellMode === null) {
                continue;
            }

            foreach ($basePlan->plan as $plan) {
                $event = $this->mapPlan($providerName, $basePlanId, $title, $sellMode, $plan);
                if ($event !== null) {
                    $events[] = $event;
                }
            }
        }

        return $events;
    }

    private function loadDocument(string $xml): \SimpleXMLElement
    {
        // Without libxml_use_internal_errors, malformed XML emits a PHP warning; with it,
        // simplexml_load_string quietly returns false and we can raise a controlled exception.
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $root = simplexml_load_string($xml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($root === false) {
            throw ProviderUnavailable::malformedXml();
        }

        return $root;
    }

    private function mapPlan(
        ProviderName $providerName,
        string $basePlanId,
        string $title,
        SellMode $sellMode,
        \SimpleXMLElement $plan,
    ): ?Event {
        $startDate = $this->parseDate((string) $plan['plan_start_date']);
        if ($startDate === null) {
            return null; // impossible/garbage start date -> drop this plan, keep the feed
        }

        $endDate = null;
        if ((string) $plan['plan_end_date'] !== '') {
            $endDate = $this->parseDate((string) $plan['plan_end_date']);
            if ($endDate === null) {
                return null;
            }
        }

        $zones = [];
        foreach ($plan->zone as $zone) {
            $zones[] = new Zone(
                zoneId:   (string) $zone['zone_id'],
                capacity: (int) $zone['capacity'],
                price:    (float) $zone['price'],
                name:     (string) $zone['name'],
                numbered: filter_var((string) $zone['numbered'], FILTER_VALIDATE_BOOLEAN),
            );
        }

        return new Event(
            providerName:     $providerName,
            externalIdentity: $basePlanId . ':' . (string) $plan['plan_id'],
            title:            $title,
            sellMode:         $sellMode,
            startDate:        $startDate,
            endDate:          $endDate,
            zones:            $zones,
        );
    }

    /**
     * Strict parse: createFromFormat is lenient about impossible dates (2021-09-31 rolls over to
     * Oct 1) but records it in getLastErrors(). Treat any warning/error as "not a real date".
     */
    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}

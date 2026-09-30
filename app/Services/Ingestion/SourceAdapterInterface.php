<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion;

/**
 * Contract every discovery connector implements (web pages, feeds, sitemaps,
 * approved social APIs, licensed search providers). The pipeline is:
 *
 *   discover() -> fetch() -> extract() -> normalize() -> validate()
 *     -> raw_event_records (CANDIDATES — never shown to users directly)
 *     -> IngestionService::processPendingRecords() (quality, location,
 *        duplicates, trust) -> canonical events
 */
interface SourceAdapterInterface
{
    /**
     * Return URLs or opaque identifiers of items to process from this source.
     * Must return [] (not throw) when the connector is not configured.
     * @return array<string>
     */
    public function discover(array $sourceConfig): array;

    /**
     * Fetch raw content for a single discovered item.
     * @return string Raw HTML, JSON, XML or other body
     */
    public function fetch(string $url, array $sourceConfig): string;

    /**
     * Extract structured fields from raw content.
     * @return array<string, mixed> Extracted fields (may be partial/messy)
     */
    public function extract(string $rawContent, string $url, array $sourceConfig): array;

    /**
     * Normalize extracted fields to candidate records. Recognised keys:
     * title, description, start_datetime, end_datetime, venue_name,
     * venue_address, city_raw, organizer_name, registration_url, is_free,
     * price_from, image_url, format_raw, status_raw, source_url, external_id,
     * account_handle, confidence (0-100), review_reasons (string[]),
     * reject_reason (string — candidate is stored but never published).
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $extracted, int $sourceId): array;

    /**
     * Validate a normalized record before it is stored as a candidate.
     * @return array<string> Error strings, empty if valid
     */
    public function validate(array $normalized): array;

    /** Health-check the source — true if reachable/available. */
    public function healthCheck(array $sourceConfig): bool;

    /** True when every credential/setting the connector needs is present. */
    public function isConfigured(array $sourceConfig): bool;

    /**
     * Describes the connector for the admin UI / README: platform,
     * acquisition method, required credentials, limitations.
     * @return array{platform:string, acquisition_method:string, credentials:array, configured:bool, limitations:string}
     */
    public function getSourceMetadata(array $sourceConfig): array;
}

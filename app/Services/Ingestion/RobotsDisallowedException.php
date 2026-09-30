<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion;

/** Thrown when a site's robots.txt forbids the URL — the page is skipped, never fetched. */
class RobotsDisallowedException extends \RuntimeException {}

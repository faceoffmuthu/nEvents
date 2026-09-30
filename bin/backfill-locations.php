#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Gives in-person events that have no district yet (discovered outside Tamil Nadu
 * before all of India's districts were added, and kept only as a "City, State"
 * label) their district and city, using the same lookup as discovery.
 *
 *   php bin/backfill-locations.php            apply
 *   php bin/backfill-locations.php --dry-run  only report
 *
 * Safe to run again: it only touches events whose city is still empty, and an
 * event whose place is unknown or ambiguous is left as it is.
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;
use NEvents\Services\Location\DistrictService;

$app = Application::getInstance();
$app->bootstrap(BASE_PATH);
/** @var Connection $db */
$db        = $app->get(Connection::class);
/** @var DistrictService $districts */
$districts = $app->get(DistrictService::class);
$dryRun    = in_array('--dry-run', $argv, true);

$rows = $db->select(
    "SELECT e.id, e.title, v.id AS venue_id, v.name, v.address, v.locality
       FROM events e JOIN venues v ON v.id = e.venue_id
      WHERE e.city_id IS NULL AND e.format <> 'online'
      ORDER BY e.id"
);

$done = $left = 0;
foreach ($rows as $r) {
    $context    = implode(', ', array_filter([$r['locality'], $r['address'], $r['name']]));
    $districtId = null;
    foreach (['locality', 'address', 'name'] as $f) {
        if (trim((string) $r[$f]) !== '' && ($districtId = $districts->resolveDistrictId((string) $r[$f], $context)) !== null) {
            break;
        }
    }
    if ($districtId === null) {
        $left++;
        echo "  left   #{$r['id']} {$r['title']} — " . ($r['locality'] ?: $r['address'] ?: 'no place') . "\n";
        continue;
    }
    $district = $districts->findById($districtId);
    $cityId   = $districts->cityIdFor($districtId, $context);
    $done++;
    echo "  mapped #{$r['id']} → {$district['name']}, {$district['state_name']}\n";
    if ($dryRun) {
        continue;
    }
    $db->update("UPDATE events SET city_id = :c, updated_at = NOW() WHERE id = :id", [':c' => $cityId, ':id' => $r['id']]);
    // The place label was only a stand-in for the missing district
    $db->update(
        "UPDATE venues SET city_id = :c, district_id = :d, state_id = :s, locality = NULL WHERE id = :v",
        [':c' => $cityId, ':d' => $districtId, ':s' => $district['state_id'], ':v' => $r['venue_id']]
    );
}

echo ($dryRun ? '[dry run] ' : '') . "{$done} event(s) given a district, {$left} left for review.\n";

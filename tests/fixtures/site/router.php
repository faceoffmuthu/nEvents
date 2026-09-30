<?php
/**
 * Fixture "public event website" for bin/test-platform.php (keyless discovery).
 *   php -S 127.0.0.1:8098 tests/fixtures/site/router.php
 * Content comes from state.json (written by the test) so a test can change an
 * event between crawls and check that the database record is updated.
 * Every request is appended to access.log so robots.txt compliance can be asserted.
 */
$dir   = __DIR__;
$state = json_decode((string) @file_get_contents("$dir/state.json"), true) ?: [];
$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
file_put_contents("$dir/access.log", $path . ($query !== '' ? "?$query" : '') . "\n", FILE_APPEND);

$base = 'http://' . $_SERVER['HTTP_HOST'];
$ev   = $state['events'] ?? [];
$e    = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$ld   = function (array $x) use ($base) {
    $o = [
        '@context' => 'https://schema.org', '@type' => $x['type'] ?? 'Event', 'name' => $x['name'],
        'startDate' => $x['start'], 'endDate' => $x['end'] ?? null, 'url' => $base . $x['path'],
        'description' => $x['description'] ?? 'A community event.',
        'location' => !empty($x['online'])
            ? ['@type' => 'VirtualLocation', 'url' => $base . $x['path']]
            : ['@type' => 'Place', 'name' => $x['venue'], 'address' => ['@type' => 'PostalAddress', 'streetAddress' => $x['street'] ?? '', 'addressLocality' => $x['city'], 'addressCountry' => $x['country'] ?? null]],
        'eventAttendanceMode' => !empty($x['online']) ? 'https://schema.org/OnlineEventAttendanceMode' : null,
        'offers' => ['@type' => 'Offer', 'price' => (string) ($x['price'] ?? 0), 'priceCurrency' => $x['currency'] ?? 'INR', 'url' => $x['register'] ?? ($base . $x['path'] . '/register')],
        'organizer' => ['@type' => 'Organization', 'name' => $x['organizer'] ?? 'Fixture Org'],
    ];
    return '<script type="application/ld+json">' . json_encode(array_filter($o, fn($v) => $v !== null), JSON_UNESCAPED_SLASHES) . '</script>';
};
$page = fn(string $title, string $body) => "<!doctype html><html><head><title>{$title}</title></head><body>{$body}</body></html>";

switch (true) {
    case $path === '/robots.txt':
        header('Content-Type: text/plain');
        echo "User-agent: *\nDisallow: /private/\n";
        break;

    case $path === '/events' && $query === 'page=2':
        echo $page('Events page 2', '<a href="/events/startup-mixer">Startup Mixer</a> <a href="/events">Back</a>');
        break;

    case $path === '/events':
        // listing: summary JSON-LD for one event + links (incl. a robots-disallowed one and an external one)
        echo $page('Events', $ld($ev['ai-summit']) .
            '<a href="/events/ai-summit">AI Summit</a> <a href="events/cycle-rally">Cycle Rally</a>
             <a href="/private/events/secret">Members only</a> <a href="https://elsewhere.example/events/x">Other site</a>
             <a href="/events?page=2">Next page</a> <a href="/about">About</a>');
        break;

    case preg_match('#^/events/([a-z-]+)$#', $path, $m) === 1 && isset($ev[$m[1]]):
        $x = $ev[$m[1]];
        if (($x['markup'] ?? 'jsonld') === 'microdata') {
            echo $page($x['name'], '<article itemscope itemtype="https://schema.org/SportsEvent">
                <h1 itemprop="name">' . $e($x['name']) . '</h1>
                <time itemprop="startDate" datetime="' . $e($x['start']) . '">start</time>
                <div itemprop="location" itemscope itemtype="https://schema.org/Place"><span itemprop="name">' . $e($x['venue']) . '</span>
                  <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress"><span itemprop="addressLocality">' . $e($x['city']) . '</span></div></div>
                <div itemprop="offers" itemscope itemtype="https://schema.org/Offer"><meta itemprop="price" content="' . $e($x['price'] ?? 0) . '"><a itemprop="url" href="' . $e($x['register'] ?? '/r') . '">Register</a></div>
                <p itemprop="description">' . $e($x['description'] ?? 'Ride.') . '</p></article>');
        } else {
            echo $page($x['name'], $ld($x) . '<h1>' . $e($x['name']) . '</h1>');
        }
        break;

    case $path === '/national':
        // national-platform style listing: events are named ONLY in JSON-LD (no <a> links)
        echo $page('Across India', implode('', array_map($ld, array_values(array_intersect_key($ev, array_flip($state['national'] ?? []))))));
        break;

    case $path === '/private/events/secret':
        echo $page('Secret', $ld(['name' => 'SECRET MEMBERS EVENT', 'start' => '2030-01-01T10:00:00+05:30', 'path' => $path, 'venue' => 'X', 'city' => 'Chennai']));
        break;

    case $path === '/news/recap':
        echo $page('Recap', '<h1>What a week!</h1><p>No structured data here.</p>');
        break;

    case $path === '/feed.xml':
        header('Content-Type: application/rss+xml');
        $items = '';
        foreach ($state['feed'] ?? [] as $it) {
            $items .= '<item><title>' . $e($it['title']) . '</title><link>' . $base . $e($it['path']) . '</link><guid>' . $e($it['path']) . '</guid><pubDate>' . date(DATE_RSS) . '</pubDate><description>' . $e($it['title']) . '</description></item>';
        }
        echo '<?xml version="1.0"?><rss version="2.0"><channel><title>Fixture feed</title>' . $items . '</channel></rss>';
        break;

    case $path === '/cal.ics':
        header('Content-Type: text/calendar');
        $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//fixture//EN\r\nX-WR-TIMEZONE:Asia/Kolkata\r\n";
        foreach ($state['ics'] ?? [] as $v) {
            $out .= "BEGIN:VEVENT\r\nUID:{$v['uid']}\r\nSUMMARY:" . str_replace(',', '\\,', $v['name']) . "\r\nDTSTART;TZID=Asia/Kolkata:{$v['start']}\r\nDTEND;TZID=Asia/Kolkata:{$v['end']}\r\nLOCATION:" . str_replace(',', '\\,', $v['location']) . "\r\nURL:{$v['url']}\r\nEND:VEVENT\r\n";
        }
        echo $out . "END:VCALENDAR\r\n";
        break;

    default:
        http_response_code(404);
        echo $page('Not found', 'Not found');
}

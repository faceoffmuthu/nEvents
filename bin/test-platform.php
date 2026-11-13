#!/usr/bin/env php
<?php

/**
 * End-to-end acceptance tests for: WhatsApp-number signup, user event
 * posting / visibility / ownership / duplicates, reporting, email
 * notifications, Google Calendar links, and multi-source discovery
 * (social connectors run against local API-shaped fixtures).
 *
 *   php bin/test-platform.php                    run everything (no real email sent)
 *   php bin/test-platform.php --send-email=ADDR  also send ONE real notification email via SMTP
 *
 * Uses the configured MySQL database. Test data is tagged (emails
 * @nevents-test.local, titles containing "[T-") and removed at the START
 * of each run, so the last run's data stays browsable afterwards.
 * HTTP tests start PHP's built-in server on 127.0.0.1:8099.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;
use NEvents\Repositories\UserRepository;
use NEvents\Services\Auth\AuthService;
use NEvents\Services\Mail\MailService;
use NEvents\Services\Notifications\NotificationService;
use NEvents\Services\Ingestion\IngestionService;
use NEvents\Services\Ingestion\JsonLdEventAdapter;
use NEvents\Services\Events\EventLifecycleService;

// Keyless-discovery tests crawl a local fixture site: allow that host, no politeness delay
foreach (['DISCOVERY_TEST_HOSTS' => '127.0.0.1:8098', 'DISCOVERY_MIN_DELAY_MS' => '0'] as $k => $v) {
    $_ENV[$k] = $v;
    putenv("{$k}={$v}");
}

$app = Application::getInstance();
$app->bootstrap(BASE_PATH);
$db  = $app->get(Connection::class);

$sendTo = null;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--send-email=')) $sendTo = substr($a, 13);
}

// ---------------------------------------------------------------------
// Tiny test harness
// ---------------------------------------------------------------------
$results = ['pass' => 0, 'fail' => 0];
function check(string $label, bool $ok, string $detail = ''): void
{
    global $results;
    $results[$ok ? 'pass' : 'fail']++;
    echo ($ok ? "  \033[32mPASS\033[0m " : "  \033[31mFAIL\033[0m ") . $label . ($detail !== '' ? "  — {$detail}" : '') . "\n";
}
function section(string $s): void { echo "\n\033[1m{$s}\033[0m\n"; }

/** Mailer that records instead of sending (keeps tests off SMTP). */
class CaptureMailer extends MailService
{
    public array $sent = [];
    public function sendVerificationEmail(array $user, string $rawToken): bool { $this->sent[] = ['verify', $user['email'], $rawToken]; return true; }
    public function sendPasswordResetEmail(array $user, string $rawToken): bool { $this->sent[] = ['reset', $user['email']]; return true; }
    public function sendNotification(string $toEmail, string $toName, string $subject, string $html, string $text): bool
    {
        $this->sent[] = ['notification', $toEmail, $subject, $html];
        return true;
    }
}

/** Minimal HTTP client with a cookie jar per user. */
class Http
{
    private string $jar;
    public function __construct(private string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'nejar'); }
    public function get(string $path): array { return $this->req('GET', $path); }
    public function post(string $path, array $fields, bool $withCsrf = true): array
    {
        if ($withCsrf) $fields['_csrf'] = $this->csrf();
        return $this->req('POST', $path, $fields);
    }
    public function csrf(): string
    {
        $r = $this->get('/about');   // any page rendering the layout (/login redirects once signed in)
        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $r['body'], $m);
        return $m[1] ?? '';
    }
    private function req(string $method, string $path, array $fields = []): array
    {
        $ch = curl_init($this->base . $path);
        $hasFile = (bool) array_filter($fields, fn($v) => $v instanceof CURLFile);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 30,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $this->flatten($fields) : http_build_query($fields));
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hsize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headers = substr($raw, 0, $hsize);
        preg_match('/^Location:\s*(\S+)/mi', $headers, $loc);
        return ['status' => $status, 'body' => substr($raw, $hsize), 'location' => $loc[1] ?? ''];
    }
    /** multipart needs flat keys: additional_category_ids[0] … */
    private function flatten(array $fields): array
    {
        $out = [];
        foreach ($fields as $k => $v) {
            if (is_array($v)) { foreach (array_values($v) as $i => $x) $out["{$k}[{$i}]"] = $x; }
            else $out[$k] = $v;
        }
        return $out;
    }
}

function png(int $w, int $h): string
{
    $raw = '';
    for ($y = 0; $y < $h; $y++) $raw .= "\x00" . str_repeat("\x6c\x63\xff", $w);
    $chunk = fn(string $t, string $d) => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
    return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)) . $chunk('IDAT', gzcompress($raw)) . $chunk('IEND', '');
}

function login(Http $http, string $email, string $password): array
{
    return $http->post('/login', ['email' => $email, 'password' => $password]);
}

function eventForm(array $over = []): array
{
    $d = new DateTimeImmutable('+20 days', new DateTimeZone('Asia/Kolkata'));
    return array_merge([
        'title' => 'Placeholder', 'short_summary' => 'A hands-on workshop on building AI agents with open models and Python.',
        'description' => "Bring your laptop. We will build a retrieval-augmented agent from scratch.\n\nAgenda:\n- Intro to agents\n- Tools & memory\n- Hands-on build",
        'primary_category_id' => '20', 'additional_category_ids' => ['12'], 'tags' => 'genai, python, agents',
        'primary_language' => 'en', 'format' => 'offline',
        'start_date' => $d->format('Y-m-d'), 'start_time' => '18:00', 'end_date' => $d->format('Y-m-d'), 'end_time' => '21:00',
        'timezone' => 'Asia/Kolkata', 'district_id' => '1', 'city_area' => 'Guindy', 'venue_name' => 'IITM Research Park',
        'address' => 'Kanagam Road, Taramani', 'postal_code' => '600113', 'latitude' => '', 'longitude' => '', 'map_url' => '',
        'online_platform' => '', 'online_url' => '', 'registration_required' => '1',
        'registration_url' => 'https://www.townscript.com/e/chennai-ai-agents-workshop', 'registration_deadline' => '',
        'pricing_type' => 'paid', 'currency' => 'INR', 'min_price' => '499', 'max_price' => '999',
        'organizer_name' => 'Chennai AI Guild', 'organizer_email' => 'hello@example.org', 'organizer_phone' => '',
        'organizer_website' => 'https://example.org', 'organizer_social_url' => '',
    ], $over);
}

$tag  = '[T-' . date('His') . ']';
$pass = 'TestPass123';

// ---------------------------------------------------------------------
section('Cleanup of previous test data');
// ---------------------------------------------------------------------
$db->statement("DELETE FROM events WHERE title LIKE '%[T-%' OR created_by_user_id IN (SELECT id FROM users WHERE email LIKE '%@nevents-test.local')");
$db->statement("DELETE e FROM events e JOIN event_sources es ON es.event_id = e.id JOIN sources s ON s.id = es.source_id WHERE s.slug LIKE 'test-%'");
$db->statement("DELETE FROM sources WHERE slug LIKE 'test-%'");
$db->statement("DELETE FROM users WHERE email LIKE '%@nevents-test.local'");
$db->statement("DELETE FROM notification_jobs WHERE user_id NOT IN (SELECT id FROM users)");
echo "  done\n";

// ---------------------------------------------------------------------
section('TEST A — Signup with WhatsApp number (no WhatsApp messaging)');
// ---------------------------------------------------------------------
$capture = new CaptureMailer();
$auth    = new AuthService($app->get(UserRepository::class), $capture);
$emailA  = 'user.a.' . time() . '@nevents-test.local';
$emailB  = 'user.b.' . time() . '@nevents-test.local';

$bad = $auth->register(['name' => 'No Phone', 'email' => 'x' . time() . '@nevents-test.local', 'whatsapp_number' => '', 'password' => $pass, 'password_confirm' => $pass, 'accept_terms' => '1']);
check('WhatsApp number is mandatory', !$bad['success'] && isset($bad['errors']['whatsapp_number']));
$bad = $auth->register(['name' => 'Bad Phone', 'email' => 'y' . time() . '@nevents-test.local', 'whatsapp_number' => '12345', 'password' => $pass, 'password_confirm' => $pass, 'accept_terms' => '1']);
check('Invalid number rejected', !$bad['success'] && isset($bad['errors']['whatsapp_number']));
$bad = $auth->register(['name' => 'No Terms', 'email' => 'z' . time() . '@nevents-test.local', 'whatsapp_number' => '9876543210', 'password' => $pass, 'password_confirm' => $pass]);
check('Privacy/terms agreement required server-side', !$bad['success'] && isset($bad['errors']['accept_terms']));

$ra = $auth->register(['name' => 'Asha Test', 'email' => $emailA, 'whatsapp_number' => '098765 43210', 'password' => $pass, 'password_confirm' => $pass, 'accept_terms' => '1']);
$rb = $auth->register(['name' => 'Bala Test', 'email' => $emailB, 'whatsapp_number' => '+44 20 7946 0958', 'password' => $pass, 'password_confirm' => $pass, 'accept_terms' => '1']);
check('User A registered', $ra['success'] ?? false);
check('User B registered', $rb['success'] ?? false);
$ua = $db->selectOne("SELECT * FROM users WHERE email = :e", [':e' => $emailA]);
$ub = $db->selectOne("SELECT * FROM users WHERE email = :e", [':e' => $emailB]);
check('Indian number normalized to +91 E.164', $ua['whatsapp_number'] === '+919876543210', (string) $ua['whatsapp_number']);
check('International number stored in full E.164', $ub['whatsapp_number'] === '+442079460958', (string) $ub['whatsapp_number']);
check('Number stored once (no duplicate phone copy)', $ua['phone'] === null);
check('Verification email generated (captured, not WhatsApp)', count(array_filter($capture->sent, fn($s) => $s[0] === 'verify')) === 2);
$wa = $db->selectOne("SELECT COUNT(*) n FROM notification_jobs WHERE channel = 'whatsapp' AND user_id IN (:a, :b)", [':a' => $ua['id'], ':b' => $ub['id']]);
check('No WhatsApp notification queued', (int) $wa['n'] === 0);
check('Login blocked before verification', ($auth->login($emailA, $pass, '127.0.0.9')['unverified'] ?? false) === true);
check('Email verification works (A)', $auth->verifyEmail($ra['token'])['success']);
check('Email verification works (B)', $auth->verifyEmail($rb['token'])['success']);

// ---------------------------------------------------------------------
section('HTTP server');
// ---------------------------------------------------------------------
$port = 8099;
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', BASE_PATH . '/public', BASE_PATH . '/router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/ne-test-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/ne-test-server.log', 'a']], $pipes);
register_shutdown_function(fn() => proc_terminate($proc));
$base = "http://127.0.0.1:{$port}";
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) usleep(100000);
echo "  server on {$base}\n";

$A = new Http($base);
$B = new Http($base);
$guest = new Http($base);   // used to test the login-return flow (ends up logged in as A)
$anon  = new Http($base);   // stays logged out

/** Field errors shown on a re-rendered form — printed when a check fails. */
function formErrors(string $body): string
{
    preg_match_all('#class="ne-form-error">.*?</i>(.*?)</div>|ne-alert ne-alert-error[^>]*>.*?</i>\s*(.*?)</div>#s', $body, $m);
    return trim(implode(' | ', array_filter(array_map('trim', array_merge($m[1], $m[2])))));
}

// ---------------------------------------------------------------------
section('Access control');
// ---------------------------------------------------------------------
$r = $guest->get('/events/create');
check('Visitor is redirected to login', $r['status'] === 302 && str_contains($r['location'], '/login'), $r['location']);
$r = login($guest, $emailA, $pass);
check('After login, visitor returns to /events/create', str_contains($r['location'], '/events/create'), $r['location']);
$r = login($A, $emailA, $pass);
check('User A logs in', $r['status'] === 302);
$r = login($B, $emailB, $pass);
check('User B logs in', $r['status'] === 302);
$r = $A->get('/events/create');
check('Create Event form loads for logged-in user', $r['status'] === 200 && str_contains($r['body'], 'Post an'));
check('Form has the all-India location picker for the district', str_contains($r['body'], 'data-loc-picker') && str_contains($r['body'], 'name="district_id"'));
$r = $A->post('/events/create', eventForm(['title' => 'No CSRF ' . $tag]), false);
check('POST without CSRF token rejected', $r['status'] === 419);

// ---------------------------------------------------------------------
section('TEST B — User A creates "AI workshop in Chennai"');
// ---------------------------------------------------------------------
$pngPath = sys_get_temp_dir() . '/ne-test-poster.png';
file_put_contents($pngPath, png(640, 360));
$titleB = "Chennai AI Agents Workshop {$tag}";
$r = $A->post('/events/create', eventForm(['title' => $titleB]) + ['image' => new CURLFile($pngPath, 'image/png', 'poster.png')]);
check('Submission redirects to success page', $r['status'] === 302 && preg_match('#/my-events/(\d+)/submitted#', $r['location'], $m), $r['location'] . ' ' . formErrors($r['body']));
$evB = $db->selectOne("SELECT e.*, (SELECT start_at_utc FROM event_occurrences WHERE event_id = e.id LIMIT 1) AS start_utc FROM events e WHERE title = :t", [':t' => $titleB]);
check('Event saved to MySQL', (bool) $evB);
check('created_by_user_id = user A', (int) ($evB['created_by_user_id'] ?? 0) === (int) $ua['id']);
check('data_origin = user_submitted', ($evB['data_origin'] ?? '') === 'user_submitted');
check('status = published (passed automatic checks)', ($evB['status'] ?? '') === 'published' && $evB['moderation_status'] === 'clean', ($evB['status'] ?? '') . '/' . ($evB['moderation_reason'] ?? ''));
check('18:00 IST stored as 12:30 UTC', str_ends_with((string) $evB['start_utc'], '12:30:00'), (string) $evB['start_utc']);
check('Poster stored with random name under public/uploads', (bool) preg_match('#^uploads/events/\d{4}/\d{2}/[a-f0-9]{32}\.png$#', (string) $evB['featured_image_url']) && is_file(BASE_PATH . '/public/' . $evB['featured_image_url']), (string) $evB['featured_image_url']);
$r = $A->get('/my-events/' . $evB['id'] . '/submitted');
check('Success page: "Your event has been published."', str_contains($r['body'], 'Your event has been published.') && str_contains($r['body'], 'Post Another Event'));
$r = $A->get('/my-events');
check('My Events lists the event with Edit + Cancel', str_contains($r['body'], htmlspecialchars($titleB)) && str_contains($r['body'], '/my-events/' . $evB['id'] . '/edit'));
$owner = $db->selectOne("SELECT COUNT(*) n FROM notification_jobs WHERE user_id = :u AND kind = 'owner_published'", [':u' => $ua['id']]);
check('"Event published" email queued for owner', (int) $owner['n'] === 1);

// ---------------------------------------------------------------------
section('TEST C — User B sees and uses A\'s event');
// ---------------------------------------------------------------------
// Filter the list to the event's own day: live discovered events can fill page 1 otherwise
$bDay = (new DateTimeImmutable($evB['start_utc'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d');
$r = $B->get("/discover?district=1&from={$bDay}&to={$bDay}");
check('Visible in Chennai list for user B (same query as discovered events)', str_contains($r['body'], htmlspecialchars($titleB)));
$r = $anon->get("/discover?district=1&from={$bDay}&to={$bDay}");
check('Visible to logged-out visitors too (existing visibility model)', str_contains($r['body'], htmlspecialchars($titleB)));
$r = $B->get('/event/' . $evB['slug']);
check('Detail page loads', $r['status'] === 200);
check('Detail shows venue, district, organizer, price', str_contains($r['body'], 'IITM Research Park') && str_contains($r['body'], 'Chennai') && str_contains($r['body'], 'Chennai AI Guild') && str_contains($r['body'], '499'));
check('Detail shows "Posted by" community credit', str_contains($r['body'], 'Posted by Asha Test'));
check('Report Event available', str_contains($r['body'], 'reportModal'));
preg_match('#https://calendar\.google\.com/calendar/render\?[^"]+#', $r['body'], $gm);
$gcal = html_entity_decode($gm[0] ?? '');
parse_str((string) parse_url($gcal, PHP_URL_QUERY), $gq);
$d20 = (new DateTimeImmutable('+20 days', new DateTimeZone('Asia/Kolkata')))->format('Ymd');
check('Google Calendar link: title', ($gq['text'] ?? '') === $titleB);
check('Google Calendar link: UTC start/end', ($gq['dates'] ?? '') === "{$d20}T123000Z/{$d20}T153000Z", $gq['dates'] ?? '');
check('Google Calendar link: location + registration link in details', str_contains($gq['location'] ?? '', 'IITM Research Park') && str_contains($gq['details'] ?? '', 'townscript.com'));
$r = $B->post('/event/' . $evB['slug'] . '/save', []);
check('Save event', str_contains($r['body'], '"success":true'));
$r = $B->get('/event/' . $evB['slug'] . '/register');
check('Register Now -> redirect page to organizer URL', $r['status'] === 200 && str_contains($r['body'], 'townscript.com/e/chennai-ai-agents-workshop'));
$r = $B->get('/event/' . $evB['slug'] . '/calendar.ics');
check('.ics has correct UTC DTSTART', str_contains($r['body'], "DTSTART:{$d20}T123000Z"));

// ---------------------------------------------------------------------
section('TEST D — District filtering');
// ---------------------------------------------------------------------
$titleD = "Coimbatore Startup Networking Night {$tag}";
$r = $A->post('/events/create', eventForm([
    'title' => $titleD, 'primary_category_id' => '3', 'additional_category_ids' => [], 'district_id' => '2', 'city_area' => 'RS Puram',
    'venue_name' => 'PSG Step Hall', 'address' => 'Peelamedu, Coimbatore', 'postal_code' => '641004', 'pricing_type' => 'free', 'min_price' => '', 'max_price' => '',
    'registration_url' => 'https://www.meetup.com/cbe-startups/events/1', 'start_time' => '19:00', 'end_time' => '21:00',
]));
check('Coimbatore event created', $r['status'] === 302, $r['location'] . ' ' . formErrors($r['body']));
$r = $B->get('/discover?district=2');
check('Shows in Coimbatore results', str_contains($r['body'], htmlspecialchars($titleD)));
$r = $B->get('/discover?district=1&format=offline');
check('Does NOT show in Chennai offline results', !str_contains($r['body'], htmlspecialchars($titleD)));
$r = $B->get('/events/coimbatore');
check('Shows on /events/coimbatore landing page', str_contains($r['body'], htmlspecialchars($titleD)));

// ---------------------------------------------------------------------
section('TEST D2 — Locations across India (states, union territories, districts)');
// ---------------------------------------------------------------------
$n = fn(string $sql) => (int) array_values($db->selectOne($sql))[0];
check('36 states and union territories', $n("SELECT COUNT(*) FROM states WHERE is_active = 1") === 36);
check('780 districts, every one with at least one city', $n("SELECT COUNT(*) FROM districts") === 780
    && $n("SELECT COUNT(*) FROM districts d WHERE NOT EXISTS (SELECT 1 FROM cities c WHERE c.district_id = d.id)") === 0);
$pudu = $db->selectOne("SELECT id FROM states WHERE slug = 'puducherry'");
$r = $anon->get('/api/districts?state=' . (int) ($pudu['id'] ?? 0));
$names = array_column(json_decode($r['body'], true)['data'] ?? [], 'name');
check('Puducherry lists its 4 districts', $names === ['Karaikal', 'Mahe', 'Puducherry', 'Yanam'], implode(', ', $names));
$loc = fn(string $q) => json_decode($anon->get('/api/locations?q=' . rawurlencode($q))['body'], true)['data'] ?? [];
$hyd = $db->selectOne("SELECT id FROM districts WHERE slug = 'hyderabad'");
check('Picker search: a city finds its district (Secunderabad -> Hyderabad)', ($loc('secunderabad')[0]['district_id'] ?? 0) === (int) $hyd['id']);
check('Picker search: an old name finds the place (Pondicherry -> Puducherry)', ($loc('pondicherry')[0]['detail'] ?? '') === 'Puducherry, Puducherry');
check('Picker search: typing a state lists its districts (Kerala -> 14)', count($loc('kerala')) === 14);
$ds = $app->get(\NEvents\Services\Location\DistrictService::class);
$dname = fn(?int $id) => $id ? ($db->selectOne("SELECT CONCAT(d.name, ', ', s.name) n FROM districts d JOIN states s ON s.id = d.state_id WHERE d.id = :i", [':i' => $id])['n']) : 'none';
check('Resolver: a name in two states is not guessed (Bilaspur)', $ds->resolveDistrictId('Bilaspur') === null);
check('Resolver: the state settles it (Bilaspur, Chhattisgarh)', $dname($ds->resolveDistrictId('Bilaspur', 'Bilaspur, Chhattisgarh')) === 'Bilaspur, Chhattisgarh');
check('Resolver: Puducherry neighbourhood (White Town)', $dname($ds->resolveDistrictId('White Town, Puducherry 605001')) === 'Puducherry, Puducherry');
check('Resolver: Tamil Nadu unchanged (Velachery, Chennai)', $dname($ds->resolveDistrictId('Velachery, Chennai')) === 'Chennai, Tamil Nadu');
$kkl = (int) $db->selectOne("SELECT id FROM districts WHERE slug = 'karaikal'")['id'];
$titleK = "Karaikal Carnival Tech Meetup {$tag}";
$r = $A->post('/events/create', eventForm([
    'title' => $titleK, 'district_id' => (string) $kkl, 'city_area' => 'Karaikal Beach', 'venue_name' => 'Beach Road Hall',
    'address' => 'Beach Road, Karaikal', 'postal_code' => '609602', 'latitude' => '10.9254', 'longitude' => '79.8380',
    'primary_category_id' => '3', 'additional_category_ids' => [], 'start_time' => '10:00', 'end_time' => '13:00',
    'short_summary' => 'Founders and makers from Karaikal share what they are building, over filter coffee by the beach.',
    'registration_url' => 'https://www.meetup.com/karaikal-tech/events/1', 'pricing_type' => 'free', 'min_price' => '', 'max_price' => '',
]));
check('Event posted in Karaikal (Puducherry) with its PIN and coordinates', $r['status'] === 302, $r['location'] . ' ' . formErrors($r['body']));
check('Shows under Karaikal and under Puducherry', str_contains($B->get("/discover?district={$kkl}")['body'], htmlspecialchars($titleK))
    && str_contains($B->get('/discover?q=Carnival&state=' . (int) $pudu['id'])['body'], htmlspecialchars($titleK)));
check('Not under Chennai', !str_contains($B->get('/discover?district=1')['body'], htmlspecialchars($titleK)));
$r = $A->post('/events/create', eventForm(['title' => "Bad place {$tag}", 'postal_code' => '012345', 'latitude' => '51.5', 'longitude' => '-0.12']));
check('PIN starting with 0 and coordinates outside India are rejected', $r['status'] === 422
    && str_contains($r['body'], 'Indian PIN codes') && str_contains($r['body'], 'inside India'), formErrors($r['body']));
$r = (new Http($base))->get('/');
check('Visitors start at All India', str_contains($r['body'], 'across India') && str_contains($r['body'], 'value="All India"'));

// ---------------------------------------------------------------------
section('TEST E — Edit + ownership');
// ---------------------------------------------------------------------
$edit = eventForm(['title' => $titleB, 'venue_name' => 'IIT Madras Research Park — Hall 2', 'start_time' => '17:30', 'end_time' => '20:30']);
$r = $B->post('/my-events/' . $evB['id'] . '/edit', $edit);
check('User B cannot edit A\'s event (404)', $r['status'] === 404);
$r = $B->get('/my-events/' . $evB['id'] . '/edit');
check('User B cannot open A\'s edit form (404)', $r['status'] === 404);
$r = $B->post('/my-events/' . $evB['id'] . '/cancel', []);
check('User B cannot cancel A\'s event (404)', $r['status'] === 404);
$r = $A->post('/my-events/' . $evB['id'] . '/edit', $edit + ['created_by_user_id' => $ub['id']]);
check('Owner edit saved', $r['status'] === 302 && str_contains($r['location'], '/my-events'), $r['location'] . ' ' . formErrors($r['body']));
$after = $db->selectOne("SELECT e.created_by_user_id, e.updated_at, v.name AS venue FROM events e JOIN venues v ON v.id = e.venue_id WHERE e.id = :id", [':id' => $evB['id']]);
check('Mass assignment ignored (owner unchanged)', (int) $after['created_by_user_id'] === (int) $ua['id']);
$r = $B->get('/event/' . $evB['slug']);
check('Other users see the new venue', str_contains($r['body'], 'Hall 2'));
$upd = $db->selectOne("SELECT COUNT(*) n FROM notification_jobs WHERE user_id = :u AND event_id = :e AND kind = 'event_updated'", [':u' => $ub['id'], ':e' => $evB['id']]);
check('User B (saved it) gets an "event updated" email queued', (int) $upd['n'] === 1);

// ---------------------------------------------------------------------
section('TEST F — Duplicate detection');
// ---------------------------------------------------------------------
$dupForm = eventForm(['title' => $titleB, 'venue_name' => 'IIT Madras Research Park — Hall 2', 'start_time' => '17:30', 'end_time' => '20:30', 'organizer_name' => 'Someone Else']);
$r = $B->post('/events/create', $dupForm);
check('Near-identical event blocked with duplicate warning', $r['status'] === 422 && str_contains($r['body'], 'already listed'));
$n = $db->selectOne("SELECT COUNT(*) n FROM events WHERE title = :t", [':t' => $titleB]);
check('No second card created', (int) $n['n'] === 1);
$r = $B->post('/events/create', $dupForm + ['confirm_not_duplicate' => '1']);
$dupEv = $db->selectOne("SELECT * FROM events WHERE title = :t AND created_by_user_id = :u", [':t' => $titleB, ':u' => $ub['id']]);
check('"Different event" confirmation -> held for review, not published', $dupEv && $dupEv['status'] === 'pending' && $dupEv['moderation_status'] === 'needs_review', $dupEv['moderation_reason'] ?? '');
$pair = $db->selectOne("SELECT COUNT(*) n FROM duplicate_candidates WHERE status = 'pending' AND (event_a_id = :a OR event_b_id = :a2)", [':a' => $evB['id'], ':a2' => $evB['id']]);
check('Pair queued for admin duplicate review', (int) $pair['n'] >= 1);
$r = $B->get("/discover?district=1&from={$bDay}&to={$bDay}");
check('Still one public card', $dupEv && !str_contains($r['body'], '/event/' . $dupEv['slug'] . '"') && str_contains($r['body'], '/event/' . $evB['slug'] . '"'));

// ---------------------------------------------------------------------
section('Security & abuse');
// ---------------------------------------------------------------------
$r = $A->post('/events/create', eventForm(['title' => "Hack <script>alert(1)</script> {$tag}"]));
check('HTML/script in title rejected', $r['status'] === 422 && str_contains($r['body'], 'HTML and scripts are not allowed'));
$xssTitle = "Quote test x');alert(1);// {$tag}";
$r = $A->post('/events/create', eventForm(['title' => $xssTitle, 'start_time' => '10:00', 'end_time' => '12:00', 'district_id' => '3', 'city_area' => 'Madurai', 'registration_url' => 'https://example.org/q']));
$xss = $db->selectOne("SELECT slug FROM events WHERE title = :t", [':t' => $xssTitle]);
$page = $xss ? $B->get('/event/' . $xss['slug'])['body'] : '';
$pageNoLd = preg_replace('#<script type="application/ld\+json">.*?</script>#s', '', $page);   // JSON string data, not executable
check('Apostrophe title rendered escaped everywhere (no JS breakout)', $xss && !str_contains($pageNoLd, "x');alert(1)") && str_contains($page, 'x' . chr(92) . 'u0027);alert(1)'),
    $xss ? '' : formErrors($r['body']));
$fake = sys_get_temp_dir() . '/ne-evil.png';
file_put_contents($fake, "<?php echo 'pwned'; ?>");
$r = $A->post('/events/create', eventForm(['title' => "Evil upload {$tag}", 'district_id' => '4', 'city_area' => 'Trichy']) + ['image' => new CURLFile($fake, 'image/png', 'evil.php.png')]);
check('PHP file disguised as .png rejected', $r['status'] === 422 && str_contains($r['body'], 'Only JPEG, PNG or WebP'));
$r = $A->post('/events/create', eventForm(['title' => "Bad link {$tag}", 'registration_url' => 'javascript:alert(1)']));
check('javascript: registration URL rejected', $r['status'] === 422);
$r = $A->post('/events/create', eventForm(['title' => "Internal link {$tag}", 'registration_url' => 'http://localhost/admin']));
check('localhost registration URL rejected', $r['status'] === 422);
$past = (new DateTimeImmutable('-3 days'))->format('Y-m-d');
$r = $A->post('/events/create', eventForm(['title' => "Old event {$tag}", 'start_date' => $past, 'end_date' => $past]));
check('Expired event rejected', $r['status'] === 422 && str_contains($r['body'], 'already ended'));
$r = $A->post('/events/create', eventForm(['title' => "Backwards {$tag}", 'end_time' => '09:00']));
check('End before start rejected', $r['status'] === 422 && str_contains($r['body'], 'end after it starts'));
$r = $A->post('/events/create', eventForm(['title' => "Casino betting jackpot night {$tag}", 'district_id' => '5', 'city_area' => 'Salem', 'start_time' => '08:00', 'end_time' => '09:00']));
$spam = $db->selectOne("SELECT status, moderation_status FROM events WHERE title = :t", [':t' => "Casino betting jackpot night {$tag}"]);
check('Suspicious content held for review (not published)', $spam && $spam['status'] === 'pending' && $spam['moderation_status'] === 'needs_review');

// ---------------------------------------------------------------------
section('Report + cancel');
// ---------------------------------------------------------------------
$r = $B->post('/event/' . $evB['slug'] . '/report', ['reason' => 'wrong_venue', 'details' => 'Hall number changed']);
check('Logged-in user can report', str_contains($r['body'], '"success":true'));
$rep = $db->selectOne("SELECT reason FROM reports WHERE event_id = :e AND user_id = :u", [':e' => $evB['id'], ':u' => $ub['id']]);
check('Report stored for admin review', ($rep['reason'] ?? '') === 'wrong_venue');
$r = $anon->post('/event/' . $evB['slug'] . '/report', ['reason' => 'spam']);
check('Logged-out visitor cannot report (401)', $r['status'] === 401);
$cbe = $db->selectOne("SELECT id, slug FROM events WHERE title = :t", [':t' => $titleD]);
$r = $A->post('/my-events/' . $cbe['id'] . '/cancel', []);
$st = $db->selectOne("SELECT status FROM events WHERE id = :id", [':id' => $cbe['id']]);
check('Owner cancels event -> status cancelled', $st['status'] === 'cancelled');
check('Cancelled event removed from upcoming lists', !str_contains($B->get('/discover?district=2')['body'], htmlspecialchars($titleD)));
$r = $A->post('/my-events/' . $evB['id'] . '/delete', []);
check('Published event cannot be hard-deleted', $db->selectOne("SELECT COUNT(*) n FROM events WHERE id = :id", [':id' => $evB['id']])['n'] == 1);

// ---------------------------------------------------------------------
section('Email notifications (queue + background delivery)');
// ---------------------------------------------------------------------
$mailer = new CaptureMailer();
$notify = new NotificationService($db, $mailer);
// make B's saved event start ~24h from now to exercise "happening tomorrow"
$db->update("UPDATE event_occurrences SET start_at_utc = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR), end_at_utc = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 27 HOUR) WHERE event_id = :e", [':e' => $evB['id']]);
$q1 = $notify->queueSavedEventReminders();
$q2 = $notify->queueSavedEventReminders();
$rem = $db->selectOne("SELECT COUNT(*) n FROM notification_jobs WHERE user_id = :u AND event_id = :e AND kind = 'reminder_tomorrow'", [':u' => $ub['id'], ':e' => $evB['id']]);
check('"Happening tomorrow" reminder queued once (idempotent)', (int) $rem['n'] === 1, "run1={$q1} run2={$q2}");
$db->update("UPDATE users SET email_verified_at = email_verified_at WHERE id = :u", [':u' => $ub['id']]);
$stats = $notify->deliverDue(50);
$sentTo = array_column(array_filter($mailer->sent, fn($s) => $s[0] === 'notification'), 1);
check('Queued emails delivered by the background sender', $stats['sent'] >= 3 && in_array($emailB, $sentTo, true), json_encode($stats));
$html = implode('', array_column(array_filter($mailer->sent, fn($s) => $s[0] === 'notification'), 3));
check('Email body escapes content + links to event', str_contains($html, '/event/' . $evB['slug']) && !str_contains($html, '<script'));
$left = $db->selectOne("SELECT COUNT(*) n FROM notification_jobs WHERE user_id IN (:a, :b) AND status = 'queued'", [':a' => $ua['id'], ':b' => $ub['id']]);
check('Nothing left queued for test users', (int) $left['n'] === 0);
$db->update("UPDATE notification_preferences SET digest_time = '00:00:00' WHERE user_id = :u", [':u' => $ub['id']]);
$db->insert("INSERT INTO user_locations (user_id, district_id, is_primary) VALUES (:u, 1, 1)", [':u' => $ub['id']]);
$dq = $notify->queueDigests('daily');
check('Daily digest queued for user with digest enabled', $dq >= 1 && $db->selectOne("SELECT COUNT(*) n FROM notification_jobs WHERE user_id = :u AND kind = 'digest_daily'", [':u' => $ub['id']])['n'] == 1);
$db->update("UPDATE notification_preferences SET email_enabled = 0 WHERE user_id = :u", [':u' => $ua['id']]);
$db->statement("DELETE FROM notification_jobs WHERE user_id = :u AND kind = 'digest_daily'", [':u' => $ua['id']]);
$notify->queueDigests('daily');
check('No digest for user with email notifications off', $db->selectOne("SELECT COUNT(*) n FROM notification_jobs WHERE user_id = :u AND kind = 'digest_daily'", [':u' => $ua['id']])['n'] == 0);

if ($sendTo) {
    $real = $app->get(MailService::class);
    $ok = $real->sendNotification($sendTo, 'N Events test', 'N Events — notification test', $html ?: '<p>Test</p>', 'N Events notification test');
    check("Real SMTP send to {$sendTo}", $ok, $ok ? 'accepted by SMTP server' : 'see storage/logs');
}

// ---------------------------------------------------------------------
section('TEST G/H/I — Multi-source discovery (social fixtures + JSON-LD)');
// ---------------------------------------------------------------------
$tz  = new DateTimeZone('Asia/Kolkata');
$now = new DateTimeImmutable('now', $tz);
$dt  = fn(string $mod, string $fmt) => $now->modify($mod)->format($fmt);
$iso = fn(string $mod) => $now->modify($mod)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sO');

$ig = ['data' => [
    ['id' => '1790001', 'username' => 'chennai_ai_builders', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.example/1.jpg',
     'permalink' => 'https://www.instagram.com/p/TESTAIB01/', 'timestamp' => $iso('-2 days'),
     'caption' => "Chennai AI Builders Meetup {$tag}\n\n📅 " . $dt('+18 days', 'j M Y') . " | 6:00 PM – 9:00 PM\n📍 Venue: IIT Madras Research Park, Taramani, Chennai\n\nLightning talks on GenAI agents + open networking. Free entry, limited seats!\nRegister: https://forms.gle/AIBtest123 #ai #chennai"],
    ['id' => '1790002', 'username' => 'chennai_ai_builders', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.example/2.jpg',
     'permalink' => 'https://www.instagram.com/p/TESTAIB02/', 'timestamp' => $iso('-1 days'),
     'caption' => "What an amazing evening at our last meetup! Thanks to everyone who attended and made it special. Highlights from the talks coming soon. #meetup #workshop"],
    ['id' => '1790003', 'username' => 'chennai_ai_builders', 'media_type' => 'IMAGE',
     'permalink' => 'https://www.instagram.com/p/TESTAIB03/', 'timestamp' => $iso('-40 days'),
     'caption' => "GenAI Hack Night {$tag}\nDate: " . $dt('-20 days', 'j F Y') . ", 5 PM\nVenue: Workafella, Teynampet, Chennai\nRegister now at https://forms.gle/hacknight — limited seats"],
    ['id' => '1790004', 'username' => 'chennai_ai_builders', 'media_type' => 'IMAGE',
     'permalink' => 'https://www.instagram.com/p/TESTAIB04/', 'timestamp' => $iso('-1 days'),
     'caption' => "We're hiring! Join our team as a Community Manager for events, meetups and workshops. Send your CV — register interest at https://forms.gle/jobs"],
    ['id' => '1790005', 'username' => 'kovai_founders', 'media_type' => 'IMAGE',
     'permalink' => 'https://www.instagram.com/p/TESTKF05/', 'timestamp' => $iso('-1 days'),
     'caption' => "Founders Coffee Circle {$tag}\nJoin us this Saturday at 7 AM for an informal networking session.\nVenue: Brew Room, RS Puram, Coimbatore\nRSVP: https://lu.ma/founders-coffee"],
    ['id' => '1790006', 'username' => 'chennai_ai_builders', 'media_type' => 'IMAGE',
     'permalink' => 'https://www.instagram.com/p/TESTAIB06/', 'timestamp' => $iso('-1 days'),
     'caption' => "Flat 40% off on all workshop recordings this week only! Buy now, limited stock. Use code AI40 — event bundle on sale."],
]];
$fb = ['data' => [
    ['id' => '1122_3344', 'message' => "Kovai Sunrise Cyclothon {$tag}\n\nDate: " . $dt('+25 days', 'j F Y') . "\nTime: 5:30 AM flag-off\nVenue: Codissia Trade Fair Complex, Coimbatore\n25 km and 50 km rides. Registration: https://www.townscript.com/e/kovai-cyclothon (Rs 350)",
     'created_time' => $iso('-3 days'), 'permalink_url' => 'https://www.facebook.com/kovaicyclists/posts/3344', 'from' => ['name' => 'Kovai Cyclists']],
]];
$x = ['data' => [
    ['id' => '18000000001', 'author_id' => '42', 'created_at' => $iso('-1 days'),
     'text' => "Chennai AI Builders Meetup {$tag} is on " . $dt('+18 days', 'M j, Y') . " 6 PM at IIT Madras Research Park, Chennai. Talks + networking. Register: https://forms.gle/AIBtest123",
     'entities' => ['urls' => [['expanded_url' => 'https://forms.gle/AIBtest123']]]],
], 'includes' => ['users' => [['id' => '42', 'username' => 'chennaiaibuilders', 'name' => 'Chennai AI Builders']]]];

@mkdir(BASE_PATH . '/tests/fixtures', 0755, true);
file_put_contents(BASE_PATH . '/tests/fixtures/instagram_media.json', json_encode($ig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents(BASE_PATH . '/tests/fixtures/facebook_page_posts.json', json_encode($fb, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents(BASE_PATH . '/tests/fixtures/x_recent_search.json', json_encode($x, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$mkSource = function (string $slug, string $platform, string $class, int $trust, array $config) use ($db): int {
    return $db->insert(
        "INSERT INTO sources (name, slug, platform, source_type, trust_level, acquisition_method, adapter_class, enabled, config_json, terms_status, health_status)
         VALUES (:n, :s, :p, 'social', :t, 'api', :c, 1, :cfg, 'compliant', 'unknown')",
        [':n' => "Test {$platform}", ':s' => $slug, ':p' => $platform, ':t' => $trust, ':c' => $class, ':cfg' => json_encode($config)]
    );
};
// Organizer-authorized Instagram account -> trusted (75); X / Facebook keep default social trust
$accounts = [['handle' => 'chennai_ai_builders', 'district' => 'Chennai', 'id' => '0'], ['handle' => 'kovai_founders', 'district' => 'Coimbatore', 'id' => '0']];
$sIg = $mkSource('test-instagram', 'instagram', 'NEvents\\Services\\Ingestion\\Social\\InstagramSourceAdapter', 75, ['fixture' => 'instagram_media.json', 'accounts' => $accounts]);
$sX  = $mkSource('test-x', 'x', 'NEvents\\Services\\Ingestion\\Social\\XSourceAdapter', 45, ['fixture' => 'x_recent_search.json']);
$sFb = $mkSource('test-facebook', 'facebook', 'NEvents\\Services\\Ingestion\\Social\\FacebookSourceAdapter', 55, ['fixture' => 'facebook_page_posts.json']);

$ingestion = $app->get(IngestionService::class);

$liveIg = $app->get(\NEvents\Services\Ingestion\Social\InstagramSourceAdapter::class);
check('Instagram connector disabled without credentials (no fixture)', !$liveIg->isConfigured([]) && $liveIg->discover([]) === []);
check('Facebook connector disabled without credentials', !$app->get(\NEvents\Services\Ingestion\Social\FacebookSourceAdapter::class)->isConfigured([]));
check('X connector disabled without credentials', !$app->get(\NEvents\Services\Ingestion\Social\XSourceAdapter::class)->isConfigured([]));
check('Search discovery disabled (no licensed provider)', !$app->get(\NEvents\Services\Ingestion\Search\SearchDiscoveryAdapter::class)->isConfigured([]));

foreach (['instagram' => $sIg, 'x' => $sX, 'facebook' => $sFb] as $name => $sid) {
    $f = $ingestion->runSource($sid);
    $p = $ingestion->processPendingRecords($sid, 50);
    echo "  {$name}: fetch " . json_encode($f) . "  process " . json_encode($p) . "\n";
}

$aib = $db->selectOne("SELECT e.*, c.district_id FROM events e LEFT JOIN cities c ON c.id = e.city_id WHERE e.title = :t", [':t' => "Chennai AI Builders Meetup {$tag}"]);
check('G: IG candidate discovered -> published (trusted account, explicit date+time)', $aib && $aib['status'] === 'published', $aib['moderation_reason'] ?? 'missing');
check('G: District resolved to Chennai from "Venue:" line', (int) ($aib['district_id'] ?? 0) === 1);
$srcs = $aib ? $db->select("SELECT es.*, s.platform FROM event_sources es JOIN sources s ON s.id = es.source_id WHERE es.event_id = :e ORDER BY es.id", [':e' => $aib['id']]) : [];
check('G: Original post URL + post ID + account preserved', ($srcs[0]['source_url'] ?? '') === 'https://www.instagram.com/p/TESTAIB01/' && ($srcs[0]['external_id'] ?? '') === 'instagram:1790001' && ($srcs[0]['account_handle'] ?? '') === 'chennai_ai_builders');
check('G: Registration URL extracted', ($aib['registration_url'] ?? '') === 'https://forms.gle/AIBtest123');
check('G: Social image not reused without permission (fallback art)', empty($aib['featured_image_url']));
check('Duplicate across sources: X post attached to SAME canonical event', count($srcs) === 2 && ($srcs[1]['platform'] ?? '') === 'x', count($srcs) . ' source refs');
check('Duplicate across sources: only one card', (int) $db->selectOne("SELECT COUNT(*) n FROM events WHERE title LIKE :t", [':t' => "Chennai AI Builders Meetup {$tag}%"])['n'] === 1);
$rej = fn(string $ext) => $db->selectOne("SELECT processing_status, error_message FROM raw_event_records WHERE external_id = :x", [':x' => $ext]);
check('I: "Thanks for attending" recap rejected', ($rej('instagram:1790002')['error_message'] ?? '') === 'social_filter: past_event_recap');
check('H: Past event post rejected (never displayed)', str_contains((string) ($rej('instagram:1790003')['error_message'] ?? ''), 'event_already_past'), (string) ($rej('instagram:1790003')['error_message'] ?? ''));
check('H: Past event not in any list', !$db->selectOne("SELECT id FROM events WHERE title LIKE :t", [':t' => 'GenAI Hack Night%']));
check('I: Job post with event keywords rejected', ($rej('instagram:1790004')['error_message'] ?? '') === 'social_filter: job_post');
check('I: Product ad rejected', ($rej('instagram:1790006')['error_message'] ?? '') === 'social_filter: product_ad');
$rel = $db->selectOne("SELECT status, moderation_status, moderation_reason FROM events WHERE title = :t", [':t' => "Founders Coffee Circle {$tag}"]);
check('Low-confidence date ("this Saturday") -> review, not published', $rel && $rel['status'] === 'pending' && str_contains($rel['moderation_reason'], 'relative_date_wording'), $rel['moderation_reason'] ?? 'missing');
$cyc = $db->selectOne("SELECT e.status, e.moderation_reason, c.district_id FROM events e LEFT JOIN cities c ON c.id = e.city_id WHERE e.title = :t", [':t' => "Kovai Sunrise Cyclothon {$tag}"]);
check('Facebook candidate: Coimbatore resolved, held for review (source trust 55 < 70)', $cyc && (int) $cyc['district_id'] === 2 && $cyc['status'] === 'pending' && str_contains($cyc['moderation_reason'], 'source_trust_55'), json_encode($cyc));

// Re-run: nothing new, freshness updated
$again = $ingestion->runSource($sIg);
check('Re-run: no duplicate candidates, existing marked seen again', $again['inserted'] === 0 && $again['seen_again'] === 6, json_encode($again));

// JSON-LD via a test adapter that reads a local page instead of HTTP
$html = '<html><head><script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => [
    ['@type' => 'BusinessEvent', 'name' => "Madurai MSME Growth Summit {$tag}", 'startDate' => $now->modify('+30 days')->format('Y-m-d') . 'T04:30:00Z',
     'endDate' => $now->modify('+30 days')->format('Y-m-d') . 'T11:30:00Z', 'description' => 'Annual summit for MSME founders across south Tamil Nadu.',
     'location' => ['@type' => 'Place', 'name' => 'Hotel Fortune Pandiyan', 'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Race Course Road', 'addressLocality' => 'Madurai']],
     'offers' => ['@type' => 'Offer', 'price' => '0', 'url' => 'https://example.org/msme-summit'], 'organizer' => ['@type' => 'Organization', 'name' => 'Madurai MSME Forum']],
]]) . '</script></head></html>';
$jsonLdAdapter = new class($app->get(\NEvents\Services\Events\RegistrationRedirectService::class), $html) extends JsonLdEventAdapter {
    public function __construct($ssrf, private string $page) { parent::__construct($ssrf, Application::getInstance()->get(\NEvents\Services\Ingestion\Web\StructuredDataExtractor::class)); }
    public function fetch(string $url, array $c): string { return $this->page; }
};
$ingestion->registerAdapter('TestJsonLd', $jsonLdAdapter);
$sWeb = $db->insert("INSERT INTO sources (name, slug, platform, source_type, trust_level, acquisition_method, adapter_class, enabled, config_json)
                     VALUES ('Test organizer site', 'test-web', 'web', 'organizer_website', 80, 'json_ld', 'TestJsonLd', 1, :c)", [':c' => json_encode(['url' => 'https://example.org/events'])]);
$ingestion->runSource($sWeb);
$ingestion->processPendingRecords($sWeb);
$msme = $db->selectOne("SELECT e.*, c.district_id, (SELECT start_at_utc FROM event_occurrences WHERE event_id = e.id LIMIT 1) AS s FROM events e LEFT JOIN cities c ON c.id = e.city_id WHERE e.title = :t", [':t' => "Madurai MSME Growth Summit {$tag}"]);
check('JSON-LD (Event subtype) from organizer site published', $msme && $msme['status'] === 'published');
check('JSON-LD "Z" time kept correct (04:30 UTC)', str_ends_with((string) ($msme['s'] ?? ''), '04:30:00'), (string) ($msme['s'] ?? ''));
check('JSON-LD district Madurai + venue stored', (int) ($msme['district_id'] ?? 0) === 3 && !empty($msme['venue_id']));

// Source later marks it cancelled -> canonical event cancelled, not duplicated
$cancelled = str_replace('"startDate"', '"eventStatus":"https://schema.org/EventCancelled","startDate"', $html);
$ingestion->registerAdapter('TestJsonLd', new class($app->get(\NEvents\Services\Events\RegistrationRedirectService::class), $cancelled) extends JsonLdEventAdapter {
    public function __construct($ssrf, private string $page) { parent::__construct($ssrf, Application::getInstance()->get(\NEvents\Services\Ingestion\Web\StructuredDataExtractor::class)); }
    public function fetch(string $url, array $c): string { return $this->page; }
});
$ingestion->runSource($sWeb);
$ingestion->processPendingRecords($sWeb);
check('Source says cancelled -> event cancelled (no new card)', $db->selectOne("SELECT status FROM events WHERE id = :id", [':id' => $msme['id']])['status'] === 'cancelled');

// Expiry
$db->update("UPDATE event_occurrences SET start_at_utc = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 HOUR), end_at_utc = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR), status = 'scheduled' WHERE event_id = :e", [':e' => $aib['id']]);
$app->get(EventLifecycleService::class)->expirePastEvents();
check('Expired events marked completed by background job', $db->selectOne("SELECT status FROM events WHERE id = :id", [':id' => $aib['id']])['status'] === 'completed');

// ---------------------------------------------------------------------
section('Admin moderation');
// ---------------------------------------------------------------------
$mod = $app->get(\NEvents\Services\Moderation\ModerationService::class);
$mod->apply('unpublish', (int) $evB['id'], (int) $ua['id'] + 1000000, 'Test unpublish');
$e = $db->selectOne("SELECT status, moderation_status FROM events WHERE id = :id", [':id' => $evB['id']]);
check('Admin unpublish -> hidden + needs_review', $e['status'] === 'pending' && $e['moderation_status'] === 'needs_review');
$r = $A->post('/my-events/' . $evB['id'] . '/edit', eventForm(['title' => $titleB, 'venue_name' => 'IIT Madras Research Park — Hall 2', 'start_time' => '17:30', 'end_time' => '20:30', 'confirm_not_duplicate' => '1']));
check('Owner edit is saved', $r['status'] === 302, formErrors($r['body']));
check('Owner edit does not bypass moderator unpublish', $db->selectOne("SELECT status FROM events WHERE id = :id", [':id' => $evB['id']])['status'] === 'pending');
$mod->apply('publish', (int) $evB['id'], (int) $ua['id'] + 1000000);
check('Admin re-publish', $db->selectOne("SELECT status FROM events WHERE id = :id", [':id' => $evB['id']])['status'] === 'published');
$mod->setSubmitterPosting((int) $ub['id'], false, (int) $ua['id'] + 1000000, 'test');
$r = $B->post('/events/create', eventForm(['title' => "Suspended user post {$tag}", 'district_id' => '6', 'city_area' => 'Erode']));
check('Suspended submitter cannot post', $r['status'] === 422 && str_contains($r['body'], 'not currently allowed'));
$audit = $db->selectOne("SELECT COUNT(*) n FROM audit_logs WHERE action LIKE 'moderation.%' AND entity_id = :e", [':e' => $evB['id']]);
check('Moderation actions audit-logged', (int) $audit['n'] >= 2);

// ---------------------------------------------------------------------
section('Keyless discovery — crawler + RSS + ICS against a local fixture site');
// ---------------------------------------------------------------------
// tests/fixtures/site/router.php serves a small "public event website" built
// from state.json: listing + pagination, JSON-LD and microdata event pages,
// a robots.txt-disallowed page, an RSS feed and an .ics calendar.
$site = BASE_PATH . '/tests/fixtures/site';
$d40  = $now->modify('+40 days');
$d45  = $now->modify('+45 days');
$d50  = $now->modify('+50 days');
$summit = "Chennai AI Summit {$tag}";
$evBOcc = $db->selectOne("SELECT start_at_utc FROM event_occurrences WHERE event_id = :e ORDER BY start_at_utc LIMIT 1", [':e' => $evB['id']]);
$evBIst = (new DateTimeImmutable($evBOcc['start_at_utc'], new DateTimeZone('UTC')))->setTimezone($tz);
$state = [
    'events' => [
        'ai-summit'     => ['name' => $summit, 'start' => $d40->format('Y-m-d') . 'T10:00:00+05:30', 'end' => $d40->format('Y-m-d') . 'T17:00:00+05:30',
                            'path' => '/events/ai-summit', 'venue' => 'IIT Madras Research Park', 'street' => 'Kanagam Road, Taramani', 'city' => 'Chennai',
                            'price' => 0, 'organizer' => 'Chennai AI Guild', 'type' => 'BusinessEvent',
                            'description' => 'A full day of talks on applied AI for Tamil Nadu startups and enterprises.'],
        'cycle-rally'   => ['name' => "Kovai Cycle Rally {$tag}", 'start' => $d45->format('Y-m-d') . 'T06:00:00+05:30', 'path' => '/events/cycle-rally',
                            'venue' => 'Race Course', 'city' => 'Coimbatore', 'price' => 200, 'register' => 'https://example.org/kovai-rally', 'markup' => 'microdata'],
        'startup-mixer' => ['name' => "Madurai Startup Mixer {$tag}", 'start' => $d50->format('Y-m-d') . 'T18:30:00+05:30', 'end' => $d50->format('Y-m-d') . 'T21:00:00+05:30',
                            'path' => '/events/startup-mixer', 'venue' => 'Hotel Fortune Pandiyan', 'city' => 'Madurai', 'price' => 0],
        // national platform: one event elsewhere in India, one abroad
        'mumbai-gig'    => ['name' => "Mumbai Indie Music Night {$tag}", 'start' => $d45->format('Y-m-d') . 'T20:00:00+05:30', 'path' => '/events/mumbai-gig',
                            'venue' => 'Antisocial Lower Parel', 'city' => 'Mumbai', 'price' => 499, 'type' => 'MusicEvent'],
        'online-in'     => ['name' => "Online GenAI Study Circle {$tag}", 'start' => $d45->format('Y-m-d') . 'T19:00:00+05:30', 'path' => '/events/online-in', 'online' => true,
                            'description' => 'Join us online for a weekly study circle on building with open models.', 'price' => 0],
        'online-us'     => ['name' => "Startup Growth Webinar {$tag}", 'start' => $d45->format('Y-m-d') . 'T13:00:00-05:00', 'path' => '/events/online-us', 'online' => true,
                            'description' => 'Join founders for a webinar on growth and hiring.', 'price' => 0, 'currency' => 'USD'],
        'online-fr'     => ['name' => "Atelier en ligne pour entrepreneurs {$tag}", 'start' => $d45->format('Y-m-d') . 'T18:00:00+05:30', 'path' => '/events/online-fr', 'online' => true,
                            'description' => 'Un atelier en ligne pour les entrepreneurs avec des experts.', 'price' => 0],
        'online-ca'     => ['name' => "Product Leaders Roundtable {$tag}", 'start' => $d45->format('Y-m-d') . 'T20:00:00-05:00', 'path' => '/events/online-ca', 'online' => true,
                            'description' => 'Join product leaders for an online roundtable on AI products.', 'price' => 15, 'currency' => 'CAD'],
        'dubai-fest'    => ['name' => "Dubai Desert Fest {$tag}", 'start' => $d45->format('Y-m-d') . 'T19:00:00+04:00', 'path' => '/events/dubai-fest',
                            'venue' => 'Meydan Racecourse', 'city' => 'Dubai', 'country' => 'AE', 'price' => 999, 'type' => 'MusicEvent'],
    ],
    'national' => ['mumbai-gig', 'dubai-fest', 'online-in', 'online-us', 'online-fr', 'online-ca'],
    'feed' => [['title' => $summit, 'path' => '/events/ai-summit']],
    'ics'  => [
        // same summit, from the organizer's calendar: venue moved + ends an hour later
        ['uid' => "summit-{$tag}@fixture", 'name' => $summit, 'start' => $d40->format('Ymd') . 'T100000', 'end' => $d40->format('Ymd') . 'T180000',
         'location' => 'Chennai Trade Centre, Nandambakkam, Chennai', 'url' => 'http://127.0.0.1:8098/events/ai-summit'],
        // an external copy of user A's own event with a different venue
        ['uid' => "workshop-{$tag}@fixture", 'name' => $titleB, 'start' => $evBIst->format('Ymd\THis'), 'end' => $evBIst->modify('+3 hours')->format('Ymd\THis'),
         'location' => 'Anna Centenary Library, Kotturpuram, Chennai', 'url' => 'http://127.0.0.1:8098/events/workshop'],
    ],
];
file_put_contents("{$site}/state.json", json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
file_put_contents("{$site}/access.log", '');
$fixtureProc = proc_open([PHP_BINARY, '-S', '127.0.0.1:8098', "{$site}/router.php"],
    [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/ne-fixture-site.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/ne-fixture-site.log', 'a']], $fixturePipes);
register_shutdown_function(fn() => proc_terminate($fixtureProc));
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', 8098); $i++) usleep(100000);

$mkWeb = fn(string $slug, string $method, string $class, int $trust, array $cfg) => $db->insert(
    "INSERT INTO sources (name, slug, platform, source_type, trust_level, acquisition_method, adapter_class, enabled, config_json, terms_status, health_status)
     VALUES (:n, :s, 'web', 'organizer_website', :t, :m, :c, 1, :cfg, 'compliant', 'unknown')",
    [':n' => "Test {$slug}", ':s' => $slug, ':t' => $trust, ':m' => $method, ':c' => $class, ':cfg' => json_encode($cfg)]
);
$sCrawl = $mkWeb('test-crawler', 'crawler', 'NEvents\\Services\\Ingestion\\WebCrawlerAdapter', 75,
    ['start_urls' => ['http://127.0.0.1:8098/events'], 'event_pattern' => '#/events/[a-z-]+$#', 'follow_pattern' => '#[?&]page=\d+#']);
$sIcs   = $mkWeb('test-ics', 'ics', 'NEvents\\Services\\Ingestion\\IcsFeedAdapter', 85, ['url' => 'http://127.0.0.1:8098/cal.ics']);
$sRss   = $mkWeb('test-rss', 'rss', 'NEvents\\Services\\Ingestion\\RssEventAdapter', 60, ['url' => 'http://127.0.0.1:8098/feed.xml']);

$run = function (int $sid, string $label) use ($ingestion): array {
    $f = $ingestion->runSource($sid);
    $p = $ingestion->processPendingRecords($sid, 50);
    echo "  {$label}: fetch " . json_encode($f) . "  process " . json_encode($p) . "\n";
    return [$f, $p];
};
$ev = fn(string $title) => $db->selectOne(
    "SELECT e.*, c.district_id, v.name AS venue_name, o.start_at_utc, o.end_at_utc
       FROM events e LEFT JOIN cities c ON c.id = e.city_id LEFT JOIN venues v ON v.id = e.venue_id
       LEFT JOIN event_occurrences o ON o.event_id = e.id WHERE e.title = :t ORDER BY o.start_at_utc LIMIT 1", [':t' => $title]);
$istToUtc = fn(string $local) => (new DateTimeImmutable($local, $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$cards = fn(string $title) => (int) $db->selectOne("SELECT COUNT(*) n FROM events WHERE title = :t", [':t' => $title])['n'];

// 1. Crawl
[$f] = $run($sCrawl, 'crawler');
$log = (string) file_get_contents("{$site}/access.log");
$s1 = $ev($summit);
check('Crawler: event from JSON-LD page published (Chennai)', $s1 && $s1['status'] === 'published' && (int) $s1['district_id'] === 1, json_encode([$s1['status'] ?? null, $s1['moderation_reason'] ?? null]));
$rally = $ev("Kovai Cycle Rally {$tag}");
check('Crawler: microdata event page extracted (Coimbatore, relative link followed)', $rally && (int) $rally['district_id'] === 2 && $rally['start_at_utc'] === $istToUtc($d45->format('Y-m-d') . ' 06:00:00'), json_encode($rally ? [$rally['district_id'], $rally['start_at_utc']] : null));
check('Crawler: pagination followed (event found on page 2)', (bool) $ev("Madurai Startup Mixer {$tag}") && str_contains($log, '/events?page=2'));
check('robots.txt read, disallowed page never requested', str_contains($log, '/robots.txt') && !str_contains($log, '/private/') && $f['robots_skipped'] > 0, 'robots_skipped=' . $f['robots_skipped']);
check('Only the configured site is crawled (external link and /about ignored)', !str_contains($log, '/about') && !$db->selectOne("SELECT id FROM events WHERE title = 'SECRET MEMBERS EVENT'"));
check('Listing + detail page of the same event -> one card', $cards($summit) === 1);
check('Stored score = avg(trust 75, confidence 90)', (int) $s1['content_score'] === 83 && (int) $s1['content_source_id'] === $sCrawl, (string) $s1['content_score']);

// 2. Organizer calendar (higher trust) with a changed venue / end time -> updates the record
$run($sIcs, 'ics');
$s2 = $ev($summit);
check('ICS copy merged into the same event (no second card)', $cards($summit) === 1);
check('Higher-trust source updates venue', ($s2['venue_name'] ?? '') === 'Chennai Trade Centre', (string) ($s2['venue_name'] ?? ''));
check('Higher-trust source updates end time', $s2['end_at_utc'] === $istToUtc($d40->format('Y-m-d') . ' 18:00:00'), (string) $s2['end_at_utc']);
check('Provenance moves to the ICS source', (int) $s2['content_source_id'] === $sIcs && (int) $s2['content_score'] > 83, $s2['content_score'] . '/' . $s2['content_source_id']);

// 3. RSS (lower trust) re-states the old venue -> conflict logged, value kept
$run($sRss, 'rss');
$s3 = $ev($summit);
$nSrc = (int) $db->selectOne("SELECT COUNT(DISTINCT source_id) n FROM event_sources WHERE event_id = :e", [':e' => $s3['id']])['n'];
check('Three sources attached to ONE canonical event', $nSrc === 3 && $cards($summit) === 1, "{$nSrc} sources");
check('Lower-trust source does NOT overwrite the venue', ($s3['venue_name'] ?? '') === 'Chennai Trade Centre');
$conf = $db->selectOne("SELECT * FROM event_conflicts WHERE event_id = :e AND field = 'venue' AND status = 'open'", [':e' => $s3['id']]);
check('Conflicting value logged for admin review', $conf && $conf['value_b'] === 'IIT Madras Research Park' && (int) $conf['source_b_id'] === $sRss, json_encode($conf));

// 4. Site changes an event's time -> next crawl updates the database record
$state['events']['cycle-rally']['start'] = $d45->format('Y-m-d') . 'T07:00:00+05:30';
file_put_contents("{$site}/state.json", json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
[, $p] = $run($sCrawl, 'crawler (re-run)');
$rally2 = $ev("Kovai Cycle Rally {$tag}");
check('Re-crawl: changed start time updated on the same event', $rally2 && (int) $rally2['id'] === (int) $rally['id'] && $rally2['start_at_utc'] === $istToUtc($d45->format('Y-m-d') . ' 07:00:00'), (string) ($rally2['start_at_utc'] ?? ''));
check('Re-crawl: no duplicate card created', $cards("Kovai Cycle Rally {$tag}") === 1 && $p['updated'] >= 1, json_encode($p));

// 5. User-posted event: external differences are flagged, never applied
$b = $ev($titleB);
check('User event NOT overwritten by external source', ($b['venue_name'] ?? '') === 'IIT Madras Research Park — Hall 2', (string) ($b['venue_name'] ?? ''));
check('User event flagged for moderators with the difference', $b['moderation_status'] === 'flagged' && str_contains((string) $b['moderation_reason'], 'venue'), $b['moderation_status'] . ' / ' . $b['moderation_reason']);
check('User event stays published', $b['status'] === 'published');

// 6. National platform (region_scope = india): events named only in the listing's JSON-LD
$sNat = $mkWeb('test-national', 'crawler', 'NEvents\\Services\\Ingestion\\WebCrawlerAdapter', 75,
    ['start_urls' => ['http://127.0.0.1:8098/national'], 'event_pattern' => '#/events/[a-z-]+$#', 'strip_query' => true, 'max_depth' => 0, 'region_scope' => 'india']);
$run($sNat, 'national');
$log = (string) file_get_contents("{$site}/access.log");
check('Crawler follows event URLs found only in structured data', str_contains($log, '/events/mumbai-gig'));
$mum = $ev("Mumbai Indie Music Night {$tag}");
$mumDist = $mum && $mum['city_id'] ? $db->selectOne("SELECT d.name, s.name AS state FROM cities c JOIN districts d ON d.id = c.district_id JOIN states s ON s.id = d.state_id WHERE c.id = :c", [':c' => $mum['city_id']]) : null;
check('India scope: event outside Tamil Nadu kept, filed under its district (Mumbai City, Maharashtra)',
    $mum && $mum['status'] === 'published' && ($mumDist['name'] ?? '') === 'Mumbai City' && ($mumDist['state'] ?? '') === 'Maharashtra', json_encode([$mum['status'] ?? null, $mumDist]));
$dub = $db->selectOne("SELECT processing_status, error_message FROM raw_event_records WHERE source_id = :s AND raw_payload LIKE '%Dubai Desert Fest%' LIMIT 1", [':s' => $sNat]);
check('India scope: event abroad rejected (outside_india), no card', ($dub['error_message'] ?? '') === 'outside_india' && $cards("Dubai Desert Fest {$tag}") === 0, json_encode($dub));
$r = $anon->get('/discover?from=' . $d45->format('Y-m-d') . '&to=' . $d45->format('Y-m-d'));
check('Out-of-TN event card shows its city', (bool) preg_match('/Mumbai Indie Music Night.{0,2000}?, Mumbai</s', $r['body']));

// 7. Categories for discovered events (topic before format / schema.org type)
$cat = fn(string $title) => $db->selectOne("SELECT c.slug FROM events e JOIN event_categories ec ON ec.event_id = e.id AND ec.is_primary = 1 JOIN categories c ON c.id = ec.category_id WHERE e.title = :t", [':t' => $title])['slug'] ?? null;
check('Category: "AI Summit" -> artificial-intelligence', $cat($summit) === 'artificial-intelligence', (string) $cat($summit));
check('Category: "Cycle Rally" -> cycling', $cat("Kovai Cycle Rally {$tag}") === 'cycling', (string) $cat("Kovai Cycle Rally {$tag}"));
check('Category: MusicEvent -> culture', $cat("Mumbai Indie Music Night {$tag}") === 'culture', (string) $cat("Mumbai Indie Music Night {$tag}"));
// 8. Online events: only from India/Canada AND in English/Tamil/Hindi/Malayalam/Telugu (unknown country = rejected)
$onl = fn(string $t) => $db->selectOne("SELECT processing_status, error_message FROM raw_event_records WHERE source_id = :s AND raw_payload LIKE :t LIMIT 1", [':s' => $sNat, ':t' => '%' . $t . '%']);
check('Online, English, India (+05:30) -> kept', $cards("Online GenAI Study Circle {$tag}") === 1);
check('Online, English, Canada (CAD) -> kept', $cards("Product Leaders Roundtable {$tag}") === 1);
check('Online, English, USA-only clues -> rejected (country unknown)', $cards("Startup Growth Webinar {$tag}") === 0
    && str_contains((string) ($onl('Startup Growth Webinar')['error_message'] ?? ''), 'country_unknown'), json_encode($onl('Startup Growth Webinar')));
check('Online, French (even with an India time zone) -> rejected (language)', $cards("Atelier en ligne pour entrepreneurs {$tag}") === 0
    && str_contains((string) ($onl('Atelier en ligne')['error_message'] ?? ''), 'language_other'), json_encode($onl('Atelier en ligne')));

check('User-chosen category never replaced by discovery', $db->selectOne("SELECT COUNT(*) n FROM event_categories WHERE event_id = :e AND is_primary = 1", [':e' => $b['id']])['n'] == 1
    && (int) $db->selectOne("SELECT category_id FROM event_categories WHERE event_id = :e AND is_primary = 1", [':e' => $b['id']])['category_id'] === 20);

proc_terminate($fixtureProc);

// Never leave mail queued for fake test addresses (the scheduler would try to send it)
$db->update("UPDATE notification_jobs SET status = 'cancelled' WHERE status = 'queued' AND user_id IN (SELECT id FROM users WHERE email LIKE '%@nevents-test.local')");
// ...and never let the scheduler poll the test sources
$db->update("UPDATE sources SET enabled = 0 WHERE slug LIKE 'test-%'");

// ---------------------------------------------------------------------
echo "\n\033[1mResult: {$results['pass']} passed, {$results['fail']} failed\033[0m\n";
exit($results['fail'] ? 1 : 0);

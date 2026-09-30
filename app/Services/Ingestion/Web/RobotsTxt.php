<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Web;

/**
 * robots.txt rules for one host (RFC 9309).
 *
 *  - The group whose User-agent token matches our bot name wins; otherwise "*".
 *  - Allow/Disallow: the LONGEST matching pattern wins; on a tie Allow wins.
 *    Patterns support "*" (any sequence) and a trailing "$" (end of URL).
 *  - Crawl-delay (seconds) is honored by the fetcher (non-standard but common).
 *  - Status handling is the caller's job: 4xx robots.txt => allow all,
 *    5xx/unreachable => disallow all (RobotsTxt::disallowAll()).
 */
class RobotsTxt
{
    /** @var array<int, array{allow:bool, pattern:string}> */
    private array $rules = [];
    private ?float $crawlDelay = null;
    private bool $blockAll = false;

    public function __construct(string $content, string $agentToken = 'nevents-bot')
    {
        $groups = [];          // agent => ['rules' => [], 'delay' => ?float]
        $current = [];         // agents of the group being read
        $lastWasAgent = false;

        foreach (preg_split('/\R/', $content) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (!$lastWasAgent) {
                    $current = [];
                }
                $agent = strtolower($value);
                $current[] = $agent;
                $groups[$agent] ??= ['rules' => [], 'delay' => null];
                $lastWasAgent = true;
                continue;
            }
            $lastWasAgent = false;
            if (!$current) {
                continue;
            }
            foreach ($current as $agent) {
                if ($field === 'allow' || $field === 'disallow') {
                    if ($value !== '') {
                        $groups[$agent]['rules'][] = ['allow' => $field === 'allow', 'pattern' => $value];
                    }
                } elseif ($field === 'crawl-delay' && is_numeric($value)) {
                    $groups[$agent]['delay'] = (float) $value;
                }
            }
        }

        $agentToken = strtolower($agentToken);
        $chosen = null;
        foreach ($groups as $agent => $g) {
            if ($agent !== '*' && str_contains($agentToken, $agent)) {
                $chosen = $g;
                break;
            }
        }
        $chosen ??= $groups['*'] ?? ['rules' => [], 'delay' => null];

        $this->rules = $chosen['rules'];
        $this->crawlDelay = $chosen['delay'];
    }

    public static function allowAll(): self
    {
        return new self('');
    }

    public static function disallowAll(): self
    {
        $r = new self('');
        $r->blockAll = true;
        return $r;
    }

    /** @param string $pathAndQuery e.g. "/events/123?x=1" */
    public function isAllowed(string $pathAndQuery): bool
    {
        if ($this->blockAll) {
            return false;
        }
        if ($pathAndQuery === '' || $pathAndQuery[0] !== '/') {
            $pathAndQuery = '/' . $pathAndQuery;
        }
        if ($pathAndQuery === '/robots.txt') {
            return true;
        }

        $best = null;   // [length, allow]
        foreach ($this->rules as $rule) {
            if ($this->matches($rule['pattern'], $pathAndQuery)) {
                $len = strlen($rule['pattern']);
                if ($best === null || $len > $best[0] || ($len === $best[0] && $rule['allow'])) {
                    $best = [$len, $rule['allow']];
                }
            }
        }
        return $best === null ? true : $best[1];
    }

    public function crawlDelay(): ?float
    {
        return $this->crawlDelay;
    }

    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        if ($anchored) {
            $pattern = substr($pattern, 0, -1);
        }
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . ($anchored ? '$' : '') . '#';
        return (bool) preg_match($regex, $path);
    }
}

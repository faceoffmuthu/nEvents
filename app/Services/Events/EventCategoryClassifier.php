<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

use NEvents\Core\Database\Connection;

/**
 * Picks a primary category for a discovered event (discovered events have
 * no category otherwise). Title keywords first, topic before format
 * ("AI workshop" -> Artificial Intelligence, not Workshop), then the
 * description, then the schema.org Event subtype, then Community.
 */
class EventCategoryClassifier
{
    /** regex => category slug, most specific first */
    private const KEYWORDS = [
        // social / relationship events first: "AI-matched speed dating" is not an AI event
        '/\b(speed[- ]?dating|dating|singles|matrimony|matrimonial|marriage)\b/i'                => 'community',
        '/\b(ai|a\.i\.|artificial intelligence|gen ?ai|llms?|chatgpt|claude|ai agents?|agentic)\b/i' => 'artificial-intelligence',
        '/\b(machine learning|deep learning|ml ops|mlops)\b/i'                                    => 'machine-learning',
        '/\b(data science|data engineering|analytics|big data|power bi|tableau)\b/i'              => 'data-science',
        '/\b(cyber ?security|infosec|ethical hacking|owasp|pentest(ing)?)\b/i'                    => 'cybersecurity',
        '/\b(blockchain|web3|crypto(currency)?|bitcoin|ethereum)\b/i'                             => 'blockchain',
        '/\b(aws|azure|gcp|cloud|kubernetes|docker)\b/i'                                          => 'cloud-computing',
        '/\b(devops|sre)\b/i'                                                                     => 'devops',
        '/\b(android|ios|flutter|mobile app)\b/i'                                                 => 'mobile-development',
        '/\b(web dev(elopment)?|react|angular|javascript|frontend|wordpress)\b/i'                 => 'web-development',
        '/\b(python|java|golang|rust|coding|developers?|programming|software|hackathon|qa|testing)\b/i' => 'software-development',
        '/\b(start ?ups?|founders?|entrepreneurs?|pitch(ing)?|investors?|venture)\b/i'           => 'startup',
        '/\b(seo|digital marketing|social media marketing|branding|marketing)\b/i'               => 'marketing',
        '/\b(msme|smes?)\b/i'                                                                     => 'msme',
        '/\b(women in tech|tech talks?|technology)\b/i'                                          => 'technology',
        '/\b(careers?|job fair|placement|recruitment|resume|interview)\b/i'                      => 'career',
        '/\b(leadership|management)\b/i'                                                          => 'leadership',
        '/\b(marathon|half marathon)\b/i'                                                         => 'marathon',
        '/\b(running|fun run|5k|10k)\b/i'                                                        => 'running',
        '/\b(cycl(e|ing|ists?|othon)|bike ride)\b/i'                                              => 'cycling',
        '/\b(yoga|meditation|pranayama)\b/i'                                                      => 'yoga',
        '/\b(conference|summit|conclave|symposium)\b/i'                                           => 'conference',
        '/\b(expo|exhibition|trade fair|trade show)\b/i'                                          => 'expo',
        '/\b(workshop|masterclass|bootcamp|training|hands-on)\b/i'                                => 'workshop',
        '/\b(course|seminar|webinar|lecture|master\'s fair|education fair|study abroad|admissions?)\b/i'                                           => 'education',
        '/\b(fitness|sports?|badminton|football|fifa|cricket|tennis|pickleball|karting|swim(ming)?|trek(king)?|zumba)\b/i' => 'fitness',
        '/\b(concert|live music|music|tour|movie|storytelling|gig|dj|comedy|stand-? ?up|theatre|theater|drama|dance|festival|fest|film|screening|art|arts|kirtan|carnival|party|nightlife)\b/i' => 'culture',
        '/\b(networking|network|mixer|meet-? ?up|meetups?|social)\b/i'                                    => 'networking',
        '/\b(business|b2b|sales|finance)\b/i'                                                     => 'business',
    ];

    private const TYPES = [
        'MusicEvent' => 'culture', 'TheaterEvent' => 'culture', 'DanceEvent' => 'culture', 'ComedyEvent' => 'culture',
        'Festival' => 'culture', 'ScreeningEvent' => 'culture', 'VisualArtsEvent' => 'culture', 'LiteraryEvent' => 'culture',
        'SportsEvent' => 'fitness', 'EducationEvent' => 'education', 'CourseInstance' => 'education', 'BusinessEvent' => 'business',
        'ExhibitionEvent' => 'expo', 'SocialEvent' => 'community', 'Hackathon' => 'software-development',
    ];

    private ?array $ids = null;

    public function __construct(private Connection $db) {}

    public function classify(array $record): ?int
    {
        $title = (string) ($record['title'] ?? '');
        $desc  = mb_substr((string) ($record['description'] ?? ''), 0, 600);
        foreach ([$title, $desc] as $text) {
            foreach (self::KEYWORDS as $rx => $slug) {
                if ($text !== '' && preg_match($rx, $text) && ($id = $this->id($slug))) {
                    return $id;
                }
            }
        }
        $type = (string) ($record['event_type_raw'] ?? '');
        return $this->id(self::TYPES[$type] ?? 'community');
    }

    /** Sets the primary category if the event has none yet (never replaces one). */
    public function assignIfMissing(int $eventId, array $record): void
    {
        if ($this->db->selectOne("SELECT 1 FROM event_categories WHERE event_id = :e LIMIT 1", [':e' => $eventId])) {
            return;
        }
        if ($id = $this->classify($record)) {
            $this->db->statement("INSERT IGNORE INTO event_categories (event_id, category_id, is_primary) VALUES (:e, :c, 1)", [':e' => $eventId, ':c' => $id]);
        }
    }

    private function id(string $slug): ?int
    {
        $this->ids ??= array_column($this->db->select("SELECT id, slug FROM categories"), 'id', 'slug');
        return isset($this->ids[$slug]) ? (int) $this->ids[$slug] : null;
    }
}

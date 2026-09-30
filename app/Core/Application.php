<?php

declare(strict_types=1);

namespace NEvents\Core;

use NEvents\Core\Database\Connection;

class Application
{
    private static self $instance;
    private Container $container;
    private array $config = [];

    private function __construct()
    {
        $this->container = new Container();
    }

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bootstrap(string $basePath): void
    {
        $this->config['base_path'] = $basePath;

        $this->loadEnvironment($basePath);
        $this->useLocalAddress();
        $this->loadConfig($basePath);
        $this->configureTimezone();
        $this->configureSession();
        $this->registerCoreBindings();
    }

    private function loadEnvironment(string $basePath): void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();
    }

    /**
     * Development only: build links (pages, CSS, JS) from the address this request was
     * opened with, when that is this machine or the local network — so the site works at
     * http://localhost:8080 on the PC and at http://<Wi-Fi address>/... from a phone or the
     * app's Wi-Fi test build at the same time, without editing APP_URL. Production, the
     * command line (emails, scheduler) and any public host name keep APP_URL from .env.
     */
    private function useLocalAddress(): void
    {
        if (PHP_SAPI === 'cli' || ($_ENV['APP_ENV'] ?? 'production') === 'production') {
            return;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $name = strtolower((string) preg_replace('/:\d+$/', '', $host));
        $local = '/^(localhost|\[::1\]|127(\.\d{1,3}){3}|10(\.\d{1,3}){3}|192\.168(\.\d{1,3}){2}|172\.(1[6-9]|2\d|3[01])(\.\d{1,3}){2})$/';
        if ($host === '' || !preg_match($local, $name) || !preg_match('/^[a-z0-9.\[\]:]+$/i', $host)) {
            return;
        }
        $base   = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $scheme . '://' . $host . $base;
    }

    private function loadConfig(string $basePath): void
    {
        $configPath = $basePath . '/config';
        foreach (glob($configPath . '/*.php') as $file) {
            $key = basename($file, '.php');
            $this->config[$key] = require $file;
        }
    }

    private function configureTimezone(): void
    {
        date_default_timezone_set($this->config['app']['timezone'] ?? 'Asia/Kolkata');
    }

    private function configureSession(): void
    {
        $cfg = $this->config['app']['session'] ?? [];
        session_name($cfg['name'] ?? 'nevents_session');
        session_set_cookie_params([
            'lifetime' => $cfg['lifetime'] ?? 7200,
            'path'     => '/',
            'secure'   => $cfg['secure']   ?? false,
            'httponly' => $cfg['httponly']  ?? true,
            'samesite' => $cfg['samesite']  ?? 'Lax',
        ]);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function registerCoreBindings(): void
    {
        $this->container->singleton(Connection::class, function () {
            $cfg = $this->config['database']['connections']['mysql'];
            return new Connection($cfg);
        });

        // Licensed web-search provider for discovery. None is bundled: 'none'
        // (default) disables search-based discovery entirely. Bind a real
        // SearchDiscoveryProviderInterface implementation here when one is licensed.
        $this->container->singleton(\NEvents\Services\Ingestion\Search\SearchDiscoveryProviderInterface::class, function () {
            return new \NEvents\Services\Ingestion\Search\NullSearchProvider();
        });

        // IngestionService needs its source adapters pre-registered (the DI
        // container can auto-resolve everything except a plain array param).
        // Registering an adapter does NOT activate it: a source row must be
        // enabled by an admin AND the adapter must report isConfigured().
        $this->container->singleton(\NEvents\Services\Ingestion\IngestionService::class, function ($c) {
            $service = new \NEvents\Services\Ingestion\IngestionService(
                $c->resolve(Connection::class),
                $c->resolve(\NEvents\Services\Events\EventQualityService::class),
                $c->resolve(\NEvents\Services\Location\DistrictService::class),
                $c->resolve(\NEvents\Services\Events\DuplicateDetectionService::class),
                $c->resolve(\NEvents\Services\Notifications\NotificationService::class),
                $c->resolve(\NEvents\Services\Events\EventMergeService::class),
                $c->resolve(\NEvents\Services\Events\EventCategoryClassifier::class),
                $c->resolve(\NEvents\Services\Ingestion\Web\IndiaLocation::class),
                $c->resolve(\NEvents\Services\Ingestion\Web\OnlineAudience::class),
            );
            foreach ([
                // keyless web sources
                \NEvents\Services\Ingestion\WebCrawlerAdapter::class,
                \NEvents\Services\Ingestion\IcsFeedAdapter::class,
                \NEvents\Services\Ingestion\JsonLdEventAdapter::class,
                \NEvents\Services\Ingestion\RssEventAdapter::class,
                \NEvents\Services\Ingestion\SitemapJsonLdAdapter::class,
                // need credentials / a licensed provider (disabled until configured)
                \NEvents\Services\Ingestion\Social\InstagramSourceAdapter::class,
                \NEvents\Services\Ingestion\Social\FacebookSourceAdapter::class,
                \NEvents\Services\Ingestion\Social\XSourceAdapter::class,
                \NEvents\Services\Ingestion\Search\SearchDiscoveryAdapter::class,
            ] as $adapterClass) {
                $service->registerAdapter($adapterClass, $c->resolve($adapterClass));
            }
            return $service;
        });
    }

    public function get(string $abstract): mixed
    {
        return $this->container->resolve($abstract);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}

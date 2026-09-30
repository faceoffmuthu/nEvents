<?php

declare(strict_types=1);

namespace NEvents\Controllers\Public;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;

class PageController
{
    public function about(Request $request): Response
    {
        return View::make('pages/about', ['title' => 'About N Events']);
    }

    public function howItWorks(Request $request): Response
    {
        return View::make('pages/how-it-works', ['title' => 'How It Works']);
    }

    public function faq(Request $request): Response
    {
        return View::make('pages/faq', ['title' => 'FAQ']);
    }

    public function contact(Request $request): Response
    {
        return View::make('pages/contact', ['title' => 'Contact Us']);
    }

    public function privacy(Request $request): Response
    {
        return View::make('pages/privacy', ['title' => 'Privacy Policy']);
    }

    public function terms(Request $request): Response
    {
        return View::make('pages/terms', ['title' => 'Terms of Service']);
    }

    /** Old public URL — event posting now lives at /events/create (login required, returns there after sign-in). */
    public function submitEvent(Request $request): Response
    {
        return Response::redirect(View::url('events/create'));
    }

    public function submitEventPost(Request $request): Response
    {
        return Response::redirect(View::url('events/create'));
    }

    public function contactPost(Request $request): Response
    {
        $_SESSION['flash_success'] = 'Thank you for your message! We will get back to you soon.';
        return Response::redirect('/contact');
    }

    public function sitemap(Request $request): Response
    {
        return (new Response())
            ->setHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->setBody($this->buildSitemap());
    }

    public function robots(Request $request): Response
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        $body   = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nSitemap: {$appUrl}/sitemap.xml\n";
        return (new Response())
            ->setHeader('Content-Type', 'text/plain')
            ->setBody($body);
    }

    private function buildSitemap(): string
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        $urls   = ['/', '/discover', '/organizers', '/about', '/how-it-works', '/faq', '/contact'];
        $xml    = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml   .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $path) {
            $xml .= "  <url><loc>{$appUrl}{$path}</loc></url>\n";
        }
        $xml .= '</urlset>';
        return $xml;
    }
}

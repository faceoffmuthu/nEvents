<?php

declare(strict_types=1);

namespace NEvents\Core;

class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    public function setStatus(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public static function html(string $body, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Content-Type'] = 'text/html; charset=UTF-8';
        $r->body = $body;
        return $r;
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Content-Type'] = 'application/json';
        $r->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $r;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Location'] = $url;
        return $r;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }

    public function getStatus(): int    { return $this->status; }
    public function getBody(): string   { return $this->body;   }
    public function getHeaders(): array { return $this->headers; }
}

<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

use NEvents\Core\Application;

/**
 * Validates and stores an uploaded event poster.
 *
 *  - PHP upload error + is_uploaded_file() checked
 *  - size limit (user_events.image_max_mb)
 *  - MIME sniffed from the file bytes with finfo — the client-supplied type
 *    and original filename/extension are ignored entirely
 *  - getimagesize() must decode it as the SAME image type (rejects polyglots
 *    whose magic bytes say JPEG but whose structure isn't one)
 *  - min/max pixel dimensions
 *  - random filename + extension derived from the sniffed type, written under
 *    public/uploads/events/YYYY/MM/ — no user-controlled path segment exists,
 *    so path traversal isn't possible
 *
 * No thumbnails: the GD extension isn't enabled in this environment, and the
 * card/detail templates already constrain display size with CSS object-fit.
 */
class EventImageUploadService
{
    private const TYPES = [
        'image/jpeg' => ['ext' => 'jpg',  'imagetype' => IMAGETYPE_JPEG],
        'image/png'  => ['ext' => 'png',  'imagetype' => IMAGETYPE_PNG],
        'image/webp' => ['ext' => 'webp', 'imagetype' => IMAGETYPE_WEBP],
    ];

    /**
     * @param array|null $file One entry from $_FILES
     * @return array{ok:bool, path?:string, width?:int, height?:int, error?:string, skipped?:bool}
     */
    public function store(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'skipped' => true];
        }

        $cfg   = Application::getInstance()->config('app.user_events', []);
        $maxMb = (int) ($cfg['image_max_mb'] ?? 5);

        if (($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => "Image is too large (max {$maxMb} MB)."];
        }
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'error' => 'The image upload failed. Please try again.'];
        }

        $tmp = $file['tmp_name'];
        if (filesize($tmp) > $maxMb * 1024 * 1024) {
            return ['ok' => false, 'error' => "Image is too large (max {$maxMb} MB)."];
        }

        return $this->validateAndMove($tmp, $cfg, fn(string $from, string $to) => move_uploaded_file($from, $to));
    }

    /**
     * Validation + storage, split out so CLI tests can exercise the exact same
     * checks against a local file (copy instead of move_uploaded_file).
     */
    public function validateAndMove(string $tmp, array $cfg, callable $mover): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!isset(self::TYPES[$mime])) {
            return ['ok' => false, 'error' => 'Only JPEG, PNG or WebP images are allowed.'];
        }

        $info = @getimagesize($tmp);
        if ($info === false || (int) $info[2] !== self::TYPES[$mime]['imagetype']) {
            return ['ok' => false, 'error' => 'That file is not a valid image.'];
        }

        [$w, $h] = [(int) $info[0], (int) $info[1]];
        $min = (int) ($cfg['image_min_px'] ?? 300);
        $max = (int) ($cfg['image_max_px'] ?? 6000);
        if ($w < $min || $h < $min) {
            return ['ok' => false, 'error' => "Image is too small — at least {$min}×{$min} pixels."];
        }
        if ($w > $max || $h > $max) {
            return ['ok' => false, 'error' => "Image is too large — at most {$max}×{$max} pixels."];
        }

        $sub  = date('Y/m');
        $dir  = rtrim((string) $cfg['upload_dir'], '/\\') . '/' . $sub;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not store the image right now.'];
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime]['ext'];
        if (!$mover($tmp, $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Could not store the image right now.'];
        }

        return [
            'ok'     => true,
            'path'   => trim((string) $cfg['upload_url'], '/') . '/' . $sub . '/' . $name,
            'width'  => $w,
            'height' => $h,
        ];
    }

    /** Deletes a previously stored upload (only paths inside the upload dir). */
    public function delete(?string $relativePath): void
    {
        $cfg = Application::getInstance()->config('app.user_events', []);
        $prefix = trim((string) ($cfg['upload_url'] ?? 'uploads/events'), '/') . '/';
        if (!$relativePath || !str_starts_with($relativePath, $prefix) || str_contains($relativePath, '..')) {
            return;
        }
        $full = rtrim((string) $cfg['upload_dir'], '/\\') . '/' . substr($relativePath, strlen($prefix));
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

<?php

namespace App\Services\File;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * SECURITY (Tahap 19) — Secure file upload helper.
 *
 * Validates:
 *  - MIME type (server-detected via finfo, bukan trust client MIME)
 *  - Extension whitelist
 *  - File size limit (per category)
 *  - Randomized filename (prevent overwrite + path traversal)
 *  - Private storage disk (NOT publicly accessible)
 *
 * Stores under "private" disk:
 *   storage/app/private/{category}/{store_id}/{random}.{ext}
 *
 * Returns the relative path for DB storage. Retrieve via
 * Storage::disk('private')->download($path) — NEVER public URL.
 */
class SecureFileUpload
{
    public const ALLOWED_MIME = [
        // Documents (store onboarding, dispute evidence)
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public const MAX_SIZE_BYTES = [
        'store_document' => 5 * 1024 * 1024,   // 5 MB
        'dispute_evidence' => 5 * 1024 * 1024, // 5 MB
        'avatar' => 2 * 1024 * 1024,           // 2 MB
    ];

    /**
     * Store an uploaded file securely.
     *
     * @param  UploadedFile $file
     * @param  string       $category  store_document|dispute_evidence|avatar
     * @param  int|null     $ownerId   store_id or user_id for path isolation
     * @return array{path: string, original_filename: string, mime_type: string, file_size: int}
     *
     * @throws InvalidArgumentException on validation failure
     */
    public function store(UploadedFile $file, string $category, ?int $ownerId = null): array
    {
        $maxSize = self::MAX_SIZE_BYTES[$category] ?? (5 * 1024 * 1024);
        $originalFilename = $file->getClientOriginalName();

        // 1. Size check (cheap, run first)
        if ($file->getSize() > $maxSize) {
            throw new InvalidArgumentException(sprintf(
                'File too large (%d bytes > %d max).', $file->getSize(), $maxSize
            ));
        }

        // 2. Extension whitelist + lowercase normalization (cheap, run before
        //    expensive finfo call to short-circuit obvious attacks).
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid extension: .%s. Allowed: %s',
                $ext, implode(', ', self::ALLOWED_EXT)
            ));
        }

        // 3. Server-detected MIME via finfo (don't trust client MIME).
        $serverMime = $this->detectMime($file);
        if (!in_array($serverMime, self::ALLOWED_MIME, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid MIME type: %s. Allowed: %s',
                $serverMime, implode(', ', self::ALLOWED_MIME)
            ));
        }

        // 4. Cross-check: ext vs server MIME consistency (defense in depth).
        $this->assertExtMatchesMime($ext, $serverMime);

        // 5. Randomized filename + owner-scoped path (prevents overwrite & traversal).
        $random = Str::random(32);
        $filename = "{$random}.{$ext}";
        $path = $ownerId !== null
            ? "{$category}/{$ownerId}/{$filename}"
            : "{$category}/{$filename}";

        // 6. Store on PRIVATE disk (not public/). Files must be served via
        //    authenticated controller, never via direct URL.
        $stored = Storage::disk('private')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if ($stored === false) {
            throw new InvalidArgumentException('Failed to store uploaded file.');
        }

        return [
            'path' => $stored,
            'original_filename' => $this->sanitizeFilename($originalFilename),
            'mime_type' => $serverMime,
            'file_size' => $file->getSize(),
        ];
    }

    /**
     * Detect MIME via finfo, fall back to mime_content_type.
     */
    protected function detectMime(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            // File from in-memory upload — try the temp path.
            $realPath = $file->getPathname();
        }
        $mime = @finfo_file(finfo_open(FILEINFO_MIME_TYPE), $realPath);
        return $mime ?: $file->getMimeType() ?: 'application/octet-stream';
    }

    /**
     * Cross-check: extension must match server-detected MIME.
     * Prevents polyglot files (e.g., .jpg that is actually PHP).
     */
    protected function assertExtMatchesMime(string $ext, string $mime): void
    {
        $allowed = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];

        $ok = in_array($mime, $allowed[$ext] ?? [], true);
        if (!$ok) {
            throw new InvalidArgumentException(sprintf(
                'Extension .%s does not match detected MIME %s.', $ext, $mime
            ));
        }
    }

    /**
     * Sanitize original filename (strip path separators, control chars,
     * null bytes — prevent header injection / log poisoning).
     */
    protected function sanitizeFilename(string $name): string
    {
        // Strip directory components (both / and \).
        $name = basename(str_replace('\\', '/', $name));
        // Remove null bytes + control characters.
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        // Limit length.
        return Str::limit($name, 200, '');
    }

    /**
     * Serve a private file (for authorized download).
     * Caller MUST verify the user is authorized to read this file before
     * passing the path in.
     */
    public function getContent(string $path): ?string
    {
        if (!Storage::disk('private')->exists($path)) {
            return null;
        }
        return Storage::disk('private')->get($path);
    }

    /**
     * Delete a private file.
     */
    public function delete(string $path): bool
    {
        return Storage::disk('private')->delete($path);
    }
}
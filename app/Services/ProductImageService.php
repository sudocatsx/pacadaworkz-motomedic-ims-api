<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function storeUpload(UploadedFile $file): array
    {
        $mimeType = $file->getMimeType();
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;

        if (! $extension || ! $this->isDecodableImage($file->get())) {
            throw ValidationException::withMessages(['image' => ['The uploaded file must be a valid JPEG, PNG, or WebP image.']]);
        }

        $path = 'products/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $file->get());

        return [
            'image_url' => $path,
            'image_original_name' => $file->getClientOriginalName(),
            'image_mime_type' => $mimeType,
            'image_size_bytes' => $file->getSize(),
            'image_source' => 'upload',
        ];
    }

    public function storeRemote(string $url): array
    {
        $currentUrl = $url;
        $response = null;

        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $this->validateRemoteHost($currentUrl);
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['allow_redirects' => false])
                ->withHeaders(['Accept' => implode(', ', array_keys(self::ALLOWED_MIME_TYPES))])
                ->get($currentUrl);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                break;
            }

            $location = $response->header('Location');
            if (! $location || $redirects === 3) {
                throw ValidationException::withMessages(['image_source_url' => ['The image URL redirected too many times.']]);
            }
            $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);
        }

        if (! $response?->successful()) {
            throw ValidationException::withMessages(['image_source_url' => ['The image URL could not be downloaded.']]);
        }

        if ((int) $response->header('Content-Length') > self::MAX_BYTES) {
            throw ValidationException::withMessages(['image_source_url' => ['The remote image may not be larger than 5 MB.']]);
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['image_source_url' => ['The remote image may not be larger than 5 MB.']]);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
        if (! $extension || ! $this->isDecodableImage($body)) {
            throw ValidationException::withMessages(['image_source_url' => ['The URL must return a valid JPEG, PNG, or WebP image.']]);
        }

        $path = 'products/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $body);

        return [
            'image_url' => $path,
            'image_original_name' => basename(parse_url($url, PHP_URL_PATH)) ?: null,
            'image_mime_type' => $mimeType,
            'image_size_bytes' => strlen($body),
            'image_source' => 'url',
        ];
    }

    public function deleteManaged(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
            Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $path), '/'));
        }
    }

    private function validateRemoteHost(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw ValidationException::withMessages(['image_source_url' => ['The image URL must use HTTPS.']]);
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if (! $ips) {
            throw ValidationException::withMessages(['image_source_url' => ['The image URL host could not be resolved.']]);
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw ValidationException::withMessages(['image_source_url' => ['Private or reserved image hosts are not allowed.']]);
            }
        }
    }

    private function isDecodableImage(string $contents): bool
    {
        return @getimagesizefromstring($contents) !== false;
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        if (str_starts_with($location, 'https://') || str_starts_with($location, 'http://')) {
            return $location;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT);
        $origin = $scheme.'://'.$host.($port ? ':'.$port : '');

        return $origin.'/'.ltrim($location, '/');
    }
}

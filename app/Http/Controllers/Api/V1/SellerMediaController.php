<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\AppServices;
use App\Http\Responses\ApiEnvelope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SellerMediaController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function upload(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $file = $request->files->get('file');
        if (! $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return ApiEnvelope::error('validation_failed', 'File is required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $purpose = strtolower(trim((string) $request->request->get('purpose', 'kyc')));
        if ($purpose === '') {
            $purpose = 'kyc';
        }
        $allowedPurposes = ['kyc', 'store_media', 'product_image', 'profile', 'support'];
        if (! in_array($purpose, $allowedPurposes, true)) {
            $purpose = 'kyc';
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $safeName = sprintf(
            '%s.%s',
            (string) Str::uuid(),
            preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin'
        );
        $relativeDir = sprintf('seller-uploads/%d/%s', (int) $actor->id, $purpose);
        $relativePath = $relativeDir.'/'.$safeName;
        Storage::disk('local')->putFileAs($relativeDir, $file, $safeName);

        $absolute = Storage::disk('local')->path($relativePath);

        return ApiEnvelope::created([
            'storage_path' => $relativePath,
            'path' => $relativePath,
            'url' => '/api/v1/media/'.str_replace('%2F', '/', rawurlencode($relativePath)),
            'original_name' => $file->getClientOriginalName(),
            'purpose' => $purpose,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'checksum_sha256' => is_readable($absolute) ? hash_file('sha256', $absolute) : null,
        ]);
    }

    public function show(Request $request): Response
    {
        $path = trim((string) $request->attributes->get('path', ''), '/');
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'seller-uploads/')) {
            return ApiEnvelope::error('not_found', 'Media file not found.', Response::HTTP_NOT_FOUND);
        }

        $parts = explode('/', $path);
        $purpose = $parts[2] ?? '';
        if (! in_array($purpose, ['store_media', 'product_image', 'profile'], true)) {
            return ApiEnvelope::error('not_found', 'Media file not found.', Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return ApiEnvelope::error('not_found', 'Media file not found.', Response::HTTP_NOT_FOUND);
        }

        $absolute = $disk->path($path);
        $response = new BinaryFileResponse($absolute);
        $mime = mime_content_type($absolute) ?: 'application/octet-stream';
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

        return $response;
    }
}

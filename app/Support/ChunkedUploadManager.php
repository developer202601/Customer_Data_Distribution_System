<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ChunkedUploadManager
{
    private const BASE_DIRECTORY = 'chunked-uploads';

    public function start(string $scope, string $originalName, int $fileSize, ?string $mimeType = null, array $context = []): array
    {
        $token = (string) Str::uuid();
        $directory = $this->directory($scope, $token);

        Storage::disk('local')->makeDirectory($directory . '/chunks');

        $metadata = [
            'token' => $token,
            'scope' => $scope,
            'original_name' => $originalName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'context' => $context,
            'created_at' => now()->toIso8601String(),
        ];

        Storage::disk('local')->put($directory . '/meta.json', json_encode($metadata, JSON_PRETTY_PRINT));

        return $metadata;
    }

    public function append(string $scope, string $token, int $chunkIndex, UploadedFile $chunk): void
    {
        $directory = $this->directory($scope, $token);
        $metadataPath = $directory . '/meta.json';

        if (! Storage::disk('local')->exists($metadataPath)) {
            throw new RuntimeException('Upload session could not be found. Please start again.');
        }

        $chunkPath = $directory . '/chunks/' . sprintf('%06d.part', $chunkIndex);
        Storage::disk('local')->putFileAs($directory . '/chunks', $chunk, basename($chunkPath));
    }

    public function assemble(string $scope, string $token, int $totalChunks): array
    {
        $metadata = $this->metadata($scope, $token);
        $disk = Storage::disk('local');
        $directory = $this->directory($scope, $token);
        $extension = pathinfo($metadata['original_name'] ?? 'upload.bin', PATHINFO_EXTENSION) ?: 'bin';
        $assembledRelativePath = $directory . '/assembled.' . strtolower($extension);
        $assembledAbsolutePath = $disk->path($assembledRelativePath);

        $handle = fopen($assembledAbsolutePath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to prepare the uploaded file for processing.');
        }

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $chunkRelativePath = $directory . '/chunks/' . sprintf('%06d.part', $index);
                if (! $disk->exists($chunkRelativePath)) {
                    throw new RuntimeException('Upload is incomplete. Missing chunk #' . ($index + 1) . '.');
                }

                $chunkAbsolutePath = $disk->path($chunkRelativePath);
                $chunkHandle = fopen($chunkAbsolutePath, 'rb');
                if ($chunkHandle === false) {
                    throw new RuntimeException('Unable to read uploaded chunk #' . ($index + 1) . '.');
                }

                stream_copy_to_stream($chunkHandle, $handle);
                fclose($chunkHandle);
            }
        } finally {
            fclose($handle);
        }

        return [
            'metadata' => $metadata,
            'relative_path' => $assembledRelativePath,
            'absolute_path' => $assembledAbsolutePath,
        ];
    }

    public function metadata(string $scope, string $token): array
    {
        $metadataPath = $this->directory($scope, $token) . '/meta.json';
        if (! Storage::disk('local')->exists($metadataPath)) {
            throw new RuntimeException('Upload session could not be found. Please start again.');
        }

        $decoded = json_decode((string) Storage::disk('local')->get($metadataPath), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Upload metadata is invalid. Please start again.');
        }

        return $decoded;
    }

    public function delete(string $scope, string $token): void
    {
        Storage::disk('local')->deleteDirectory($this->directory($scope, $token));
    }

    private function directory(string $scope, string $token): string
    {
        return self::BASE_DIRECTORY . '/' . trim($scope, '/') . '/' . trim($token, '/');
    }
}
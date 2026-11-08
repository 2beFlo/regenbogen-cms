<?php

namespace App\Http\Controllers;

use App\Support\ResponsiveImages;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageVariantController
{
    public function __invoke(int $width, string $path): Response
    {
        abort_unless(
            $width > 0
                && $width <= 2400
                && ! str_contains($path, '..')
                && ! str_starts_with($path, '/')
                && ResponsiveImages::isOptimizable($path),
            404,
        );
        $disk = Storage::disk('uploads');
        $sourcePath = $disk->path($path);
        $dimensions = @getimagesize($sourcePath);
        if (! is_array($dimensions) || ! isset($dimensions[0], $dimensions[1])) {
            abort(404);
        }

        // Image optimization is best effort. A missing/limited GD build must
        // never turn an otherwise valid original upload into a broken image.
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return $this->original($disk, $path);
        }

        $width = min($width, (int) $dimensions[0]);
        $cachePath = 'optimized/'.hash('sha256', $path.'|'.$width).'.webp';

        try {
            if (! $disk->exists($cachePath)) {
                $source = @imagecreatefromstring((string) file_get_contents($sourcePath));
                if ($source === false) {
                    return $this->original($disk, $path);
                }

                $height = max(1, (int) round(((int) $dimensions[1]) * ($width / (int) $dimensions[0])));
                $target = imagecreatetruecolor($width, $height);
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, (int) $dimensions[0], (int) $dimensions[1]);

                ob_start();
                imagewebp($target, null, 82);
                $webp = ob_get_clean();
                imagedestroy($source);
                imagedestroy($target);

                if (! is_string($webp) || $webp === '' || ! $disk->put($cachePath, $webp)) {
                    return $this->original($disk, $path);
                }
            }

            $optimized = $disk->get($cachePath);
            if (! is_string($optimized) || $optimized === '') {
                return $this->original($disk, $path);
            }

            return response($optimized, 200, [
                'Content-Type' => 'image/webp',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Responsive image optimization failed; serving original.', [
                'path' => $path,
                'width' => $width,
                'error' => $exception->getMessage(),
            ]);

            return $this->original($disk, $path);
        }
    }

    private function original($disk, string $path): Response
    {
        $mime = (string) ($disk->mimeType($path) ?: 'application/octet-stream');

        return response($disk->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

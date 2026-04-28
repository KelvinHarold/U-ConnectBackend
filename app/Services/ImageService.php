<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageService
{
    /**
     * Compress and save an uploaded image.
     *
     * @param UploadedFile $file The uploaded image file
     * @param string $directory The directory to save the image in (within public disk)
     * @param int|null $maxWidth Maximum width of the image
     * @param int|null $maxHeight Maximum height of the image
     * @param int $quality Compression quality (1-100)
     * @return string The relative path to the saved image
     */
    public function compressAndSave(UploadedFile $file, string $directory, ?int $maxWidth = 1200, ?int $maxHeight = 1200, int $quality = 80): string
    {
        // Generate a unique filename
        $filename = time() . '_' . Str::random(10) . '.webp'; // Save as WebP for better compression
        $path = $directory . '/' . $filename;

        // Create the image instance
        $image = Image::read($file);

        // Resize image if it exceeds maximum dimensions while maintaining aspect ratio
        if ($maxWidth || $maxHeight) {
            $image->scaleDown($maxWidth, $maxHeight);
        }

        // Encode as WebP with the specified quality
        $encoded = $image->toWebp($quality);

        // Save to public storage
        Storage::disk('public')->put($path, (string) $encoded);

        return '/storage/' . $path;
    }

    /**
     * Delete an image from storage.
     *
     * @param string|null $path The path to the image (including /storage/)
     * @return bool
     */
    public function delete(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        // Remove the /storage/ prefix to get the relative path for Storage facade
        $relativePaths = [
            str_replace('/storage/', '', $path),
            ltrim($path, '/'), // Just in case
            str_replace('storage/', '', $path)
        ];

        foreach ($relativePaths as $relPath) {
            if (Storage::disk('public')->exists($relPath)) {
                return Storage::disk('public')->delete($relPath);
            }
        }

        return false;
    }
}

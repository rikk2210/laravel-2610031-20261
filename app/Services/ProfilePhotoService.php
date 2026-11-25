<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProfilePhotoService
{
    public function storeAndOptimize(UploadedFile $file, User $user): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi PHP GD belum aktif di server.');
        }

        $rawBinary = file_get_contents($file->getRealPath());
        if ($rawBinary === false) {
            throw new RuntimeException('Gagal membaca file gambar yang diunggah.');
        }

        $sourceImage = @imagecreatefromstring($rawBinary);
        if ($sourceImage === false) {
            throw new RuntimeException('Isi file bukan gambar yang valid atau file rusak.');
        }

        $sourceImage = $this->fixExifOrientation($sourceImage, $file);

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        $cropSize = min($origWidth, $origHeight);
        $srcX = (int) floor(($origWidth - $cropSize) / 2);
        $srcY = (int) floor(($origHeight - $cropSize) / 2);
        $targetSize = min($cropSize, 512);

        $canvas = imagecreatetruecolor($targetSize, $targetSize);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetSize, $targetSize, $transparent);

        imagecopyresampled(
            $canvas,
            $sourceImage,
            0,
            0,
            $srcX,
            $srcY,
            $targetSize,
            $targetSize,
            $cropSize,
            $cropSize
        );

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($canvas, null, 80);
            $extension = 'webp';
        } else {
            imagejpeg($canvas, null, 82);
            $extension = 'jpg';
        }
        $optimizedBinary = ob_get_clean();

        unset($sourceImage, $canvas);

        if ($optimizedBinary === false || $optimizedBinary === '') {
            throw new RuntimeException('Gagal mengonversi foto profil di server.');
        }

        $relativePath = sprintf('profiles/user_%d_%d.%s', $user->id, time(), $extension);
        if (! Storage::disk('public')->put($relativePath, $optimizedBinary)) {
            throw new RuntimeException('Gagal menyimpan foto profil di server.');
        }

        if (! empty($user->foto) && Storage::disk('public')->exists($user->foto)) {
            Storage::disk('public')->delete($user->foto);
        }

        return $relativePath;
    }

    private function fixExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }
}

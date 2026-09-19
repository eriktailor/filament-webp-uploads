<?php

declare(strict_types=1);

namespace Eriktailor\FilamentWebpUploads\Components;

use Eriktailor\FilamentWebpUploads\Support\Intervention;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * A Filament FileUpload that re-encodes uploaded images on the way to storage.
 *
 * Works with Intervention Image v3 and v4; see Support\Intervention for the
 * two call sites where the majors differ.
 */
class WebpFileUpload extends FileUpload
{
    protected ?int $webpQuality = null;

    protected ?int $resizeWidth = null;

    protected bool $hasConversionHandler = false;

    /**
     * Convert the upload to WebP.
     *
     * @param  int|null  $quality  Quality level (1-100). Defaults to the config value, or 80.
     */
    public function webp(?int $quality = null): static
    {
        $this->webpQuality = $quality ?? (int) config('filament-webp-uploads.quality', 80);

        return $this->registerConversionHandler();
    }

    /**
     * Scale the upload down to a maximum width, preserving aspect ratio.
     *
     * Smaller images are left alone — this never upscales. May be used on its
     * own, without webp(), in which case the original format is preserved.
     *
     * @param  int|null  $width  Maximum width in pixels. Defaults to the config value.
     */
    public function resize(?int $width = null): static
    {
        $width = $width ?? config('filament-webp-uploads.resize_width');

        $this->resizeWidth = $width === null ? null : (int) $width;

        return $this->registerConversionHandler();
    }

    /**
     * Take over storing the file, so we can rewrite it first.
     *
     * Both webp() and resize() call this, and either may be called first, so
     * it must be idempotent. The closure reads the settings at save time
     * rather than capturing them, so call order does not matter.
     */
    protected function registerConversionHandler(): static
    {
        if ($this->hasConversionHandler) {
            return $this;
        }

        $this->hasConversionHandler = true;

        return $this->saveUploadedFileUsing(
            fn (TemporaryUploadedFile $file): ?string => $this->convertUploadedFile($file),
        );
    }

    /**
     * Re-encode the upload and write it to the configured disk.
     *
     * Anything that is not a processable image — and any failure along the way
     * — falls back to storing the file untouched, because losing a user's
     * upload is worse than storing it in the wrong format.
     */
    protected function convertUploadedFile(TemporaryUploadedFile $file): ?string
    {
        try {
            if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
                return $this->saveOriginalFile($file);
            }

            $manager = new ImageManager(new Driver);
            $image = Intervention::decodePath($manager, $file->getRealPath());

            // Bake the EXIF orientation into the pixels. Encoders strip EXIF,
            // so without this, photos straight off a phone come out rotated.
            $image = $this->orient($image);

            if ($this->resizeWidth !== null && $image->width() > $this->resizeWidth) {
                $image->scaleDown(width: $this->resizeWidth);
            }

            [$encoded, $extension] = $this->webpQuality !== null
                ? [$image->encode(new WebpEncoder(quality: $this->webpQuality)), 'webp']
                : [Intervention::encodeByExtension($image, $this->sourceExtension($file)), $this->sourceExtension($file)];

            $path = $this->joinPath(
                $this->getUploadDirectory(),
                $this->getStorageFilename($file, $extension),
            );

            Storage::disk($this->getDiskName())->put(
                $path,
                (string) $encoded,
                $this->getVisibility(),
            );

            return $path;
        } catch (Throwable $e) {
            Log::error('Image conversion failed, storing the original upload instead', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return $this->saveOriginalFile($file);
        }
    }

    /**
     * Apply EXIF orientation where the driver and the source format support it.
     */
    protected function orient(ImageInterface $image): ImageInterface
    {
        try {
            return $image->orient();
        } catch (Throwable) {
            // No EXIF data, or a format that cannot carry it. Not worth failing over.
            return $image;
        }
    }

    /**
     * The storage filename Filament picked, with the extension we actually wrote.
     */
    protected function getStorageFilename(TemporaryUploadedFile $file, string $extension): string
    {
        $name = pathinfo($this->getUploadedFileNameForStorage($file), PATHINFO_FILENAME);

        return $name.'.'.$extension;
    }

    /**
     * Extension of the uploaded file, used when re-encoding without webp().
     */
    protected function sourceExtension(TemporaryUploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return $extension !== '' ? $extension : 'jpg';
    }

    /**
     * Store the upload verbatim. Used for non-images and as the failure path.
     */
    protected function saveOriginalFile(TemporaryUploadedFile $file): ?string
    {
        $directory = $this->getUploadDirectory();
        $filename = $this->getUploadedFileNameForStorage($file);

        $path = Storage::disk($this->getDiskName())->putFileAs(
            $directory,
            $file,
            $filename,
            $this->getVisibility(),
        );

        return $path === false ? null : $path;
    }

    /**
     * Upload directory, normalised to have no leading or trailing slash.
     */
    protected function getUploadDirectory(): string
    {
        return trim((string) $this->getDirectory(), '/');
    }

    /**
     * Join a directory and a filename without producing a leading slash when
     * the directory is empty — a path like "/foo.webp" is a different key on
     * S3 than "foo.webp", and breaks Storage::url().
     */
    protected function joinPath(string $directory, string $filename): string
    {
        return $directory === '' ? $filename : $directory.'/'.$filename;
    }
}

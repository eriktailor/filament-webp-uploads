<?php

namespace Eriktailor\FilamentWebpUploads\Components;

use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class WebpFileUpload extends FileUpload
{
    protected ?int $webpQuality = null;
    protected ?int $resizeWidth = null;

    /**
     * Enable WebP conversion with optional quality setting.
     *
     * @param int|null $quality Quality level (1-100). Defaults to config value or 80.
     * @return static
     */
    public function webp(?int $quality = null): static
    {
        $this->webpQuality = $quality ?? config('filament-webp-uploads.quality', 80);

        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file) {
            return $this->convertToWebp($file);
        });

        return $this;
    }

    /**
     * Enable image resizing with maximum width (maintains aspect ratio).
     *
     * @param int|null $width Maximum width in pixels. Defaults to config value.
     * @return static
     */
    public function resize(?int $width = null): static
    {
        $this->resizeWidth = $width ?? config('filament-webp-uploads.resize_width');

        return $this;
    }

    /**
     * Convert uploaded file to WebP format.
     *
     * @param TemporaryUploadedFile $file
     * @return string The stored file path
     */
    protected function convertToWebp(TemporaryUploadedFile $file): string
    {
        try {
            // Check if file is an image
            $mimeType = $file->getMimeType();
            if (!str_starts_with($mimeType, 'image/')) {
                // Not an image, save as-is
                return $this->saveOriginalFile($file);
            }

            // Initialize Intervention Image with GD driver
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());

            // Apply resize if configured (prevents upscaling)
            if ($this->resizeWidth !== null) {
                $currentWidth = $image->width();
                
                if ($currentWidth > $this->resizeWidth) {
                    $image->scaleDown(width: $this->resizeWidth);
                }
            }

            // Encode to WebP
            $encoded = $image->toWebp(quality: $this->webpQuality);

            // Generate filename with .webp extension
            $filename = $this->getWebpFilename($file);
            $path = $this->getUploadDirectory() . '/' . $filename;

            // Save to storage
            $disk = $this->getDiskName();
            Storage::disk($disk)->put(
                $path,
                (string) $encoded,
                $this->getVisibility()
            );

            return $path;

        } catch (\Exception $e) {
            // Log error and fallback to original file
            Log::error('WebP conversion failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return $this->saveOriginalFile($file);
        }
    }

    /**
     * Generate WebP filename from original file.
     *
     * @param TemporaryUploadedFile $file
     * @return string
     */
    protected function getWebpFilename(TemporaryUploadedFile $file): string
    {
        $originalName = $this->getUploadedFileNameForStorage($file);
        $pathInfo = pathinfo($originalName);
        
        return $pathInfo['filename'] . '.webp';
    }

    /**
     * Save original file without conversion (fallback).
     *
     * @param TemporaryUploadedFile $file
     * @return string
     */
    protected function saveOriginalFile(TemporaryUploadedFile $file): string
    {
        $filename = $this->getUploadedFileNameForStorage($file);
        $path = $this->getUploadDirectory() . '/' . $filename;
        
        $disk = $this->getDiskName();
        Storage::disk($disk)->putFileAs(
            $this->getUploadDirectory(),
            $file,
            $filename,
            $this->getVisibility()
        );

        return $path;
    }

    /**
     * Get upload directory path.
     *
     * @return string
     */
    protected function getUploadDirectory(): string
    {
        $directory = $this->getDirectory();
        
        return $directory ? trim($directory, '/') : '';
    }
}

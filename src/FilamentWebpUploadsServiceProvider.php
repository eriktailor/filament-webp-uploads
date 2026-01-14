<?php

namespace Eriktailor\FilamentWebpUploads;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentWebpUploadsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-webp-uploads';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile();
    }
}

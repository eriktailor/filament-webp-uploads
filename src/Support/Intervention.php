<?php

declare(strict_types=1);

namespace Eriktailor\FilamentWebpUploads\Support;

use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;

/**
 * Thin compatibility layer over the two Intervention Image majors we support.
 *
 * v4 renamed the entry points this package relies on: ImageManager::read()
 * became decodePath(), and Image::encodeByExtension() became
 * encodeUsingFileExtension(). Everything else we touch (scaleDown, orient,
 * encode(new WebpEncoder(...))) is identical across v3 and v4, so those are
 * called directly and only these two need a shim.
 *
 * @internal
 */
final class Intervention
{
    /**
     * Decode an image from a path on the local filesystem.
     */
    public static function decodePath(ImageManagerInterface $manager, string $path): ImageInterface
    {
        // v4+
        if (method_exists($manager, 'decodePath')) {
            return $manager->decodePath($path);
        }

        // v3
        return $manager->read($path);
    }

    /**
     * Encode an image back into the format implied by a file extension.
     */
    public static function encodeByExtension(
        ImageInterface $image,
        string $extension,
        mixed ...$options,
    ): EncodedImageInterface {
        // v4+
        if (method_exists($image, 'encodeUsingFileExtension')) {
            return $image->encodeUsingFileExtension($extension, ...$options);
        }

        // v3
        return $image->encodeByExtension($extension, ...$options);
    }
}

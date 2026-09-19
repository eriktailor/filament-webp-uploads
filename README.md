# Filament WebP Uploads

Automatic WebP conversion for Filament FileUpload fields with optional resizing and quality control.

## Features

- 🎨 **Automatic WebP Conversion**: Converts uploaded images to WebP format automatically
- 📏 **Smart Resizing**: Optional image resizing with aspect ratio preservation (no upscaling)
- ⚙️ **Configurable Quality**: Control WebP compression quality per-field or globally
- 🔄 **Multiple Upload Support**: Works with both single and multiple file uploads
- 🧭 **EXIF Orientation**: Rotation from the camera is baked into the pixels, so photos never come out sideways
- 🛡️ **Safe Fallback**: Non-image files and conversion failures handled gracefully
- 📝 **Error Logging**: Failed conversions logged for debugging

## Requirements

- PHP 8.2+
- Laravel 12+
- Filament v5.0+
- Intervention Image v3 or v4 (either works; the package adapts at runtime)
- GD extension with WebP support

## Installation

Install the package via Composer:

```bash
composer require eriktailor/filament-webp-uploads
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=filament-webp-uploads-config
```

## Usage

### Basic Usage

Replace Filament's `FileUpload` with `WebpFileUpload` and add the `->webp()` method:

```php
use Eriktailor\FilamentWebpUploads\Components\WebpFileUpload;

WebpFileUpload::make('image')
    ->webp()
    ->disk('public')
    ->directory('images');
```

### Custom Quality

Specify WebP quality (1-100, default is 80):

```php
WebpFileUpload::make('image')
    ->webp(90) // Higher quality, larger file size
    ->disk('public')
    ->directory('images');
```

### With Resizing

Add the `->resize()` method to limit maximum width (maintains aspect ratio):

```php
WebpFileUpload::make('image')
    ->webp(85)
    ->resize(1920) // Max width 1920px
    ->disk('public')
    ->directory('images');
```

**Note**: Images smaller than the specified width will NOT be upscaled - they keep their original size.

### Resizing Without Converting

`->resize()` may be used on its own. The image is scaled down and re-encoded in
its original format, leaving the extension unchanged:

```php
WebpFileUpload::make('image')
    ->resize(1920) // no ->webp(), so a JPEG stays a JPEG
    ->disk('public')
    ->directory('images');
```

The two methods may be called in either order.

### Multiple Uploads

Works seamlessly with multiple file uploads:

```php
WebpFileUpload::make('gallery')
    ->multiple()
    ->webp(80)
    ->resize(1600)
    ->disk('public')
    ->directory('gallery');
```

### Complete Example

```php
use Eriktailor\FilamentWebpUploads\Components\WebpFileUpload;
use Filament\Forms\Components\Section;

Section::make('Media')
    ->schema([
        WebpFileUpload::make('featured_image')
            ->label('Featured Image')
            ->webp(90)
            ->resize(1920)
            ->image()
            ->maxSize(5120) // 5MB
            ->disk('public')
            ->directory('posts')
            ->required(),
            
        WebpFileUpload::make('gallery')
            ->label('Gallery')
            ->multiple()
            ->webp(85)
            ->resize(1600)
            ->image()
            ->maxFiles(10)
            ->disk('public')
            ->directory('posts/gallery'),
    ]);
```

## Configuration

The `config/filament-webp-uploads.php` file contains global defaults:

```php
return [
    // Default WebP quality (1-100)
    'quality' => 80,
    
    // Default resize width in pixels (null = no resize)
    'resize_width' => null,
];
```

These defaults are used when `->webp()` or `->resize()` are called without arguments.

## How It Works

1. **File Upload**: User uploads an image through Filament FileUpload field
2. **MIME Check**: Plugin checks if file is an image (starts with `image/`)
3. **Orient**: EXIF rotation is applied to the pixels, since encoding strips the EXIF data that described it
4. **Resize** (optional): If configured and image is larger than target width, scales down maintaining aspect ratio
5. **Convert**: Image is re-encoded using Intervention Image with the GD driver — to WebP when `->webp()` was called, otherwise back to its original format
6. **Save**: The result is saved to the configured storage disk/directory
7. **Fallback**: Non-images or conversion errors result in original file being saved

**Important**: Only the converted version is saved - original files are not retained.

### Intervention Image v3 and v4

Intervention Image v4 renamed two entry points this package uses
(`ImageManager::read()` → `decodePath()`, `Image::encodeByExtension()` →
`encodeUsingFileExtension()`). `Support\Intervention` dispatches on whichever
major is installed, so the package works unchanged on both.

## Error Handling

- **Non-image files**: Silently saved as-is without conversion
- **Conversion failures**: Logged to Laravel log and original file saved as fallback
- **Missing GD/WebP**: Check your PHP installation has GD extension compiled with WebP support

Check logs at `storage/logs/laravel.log` for conversion errors.

## Verifying WebP Support

Ensure your PHP installation supports WebP:

```bash
php -r "var_dump(gd_info());"
```

Look for `WebP Support => enabled` in the output.

## Use Cases

- **Performance Optimization**: Reduce image file sizes by 25-80% compared to JPEG/PNG
- **Responsive Images**: Generate optimally sized images for different screen resolutions
- **Admin Panels**: Automatically optimize user-uploaded content in Filament admin interfaces
- **Content Management**: Streamline media management without manual image processing

## Validation

The plugin does not automatically add image validation. Add your own validation as needed:

```php
WebpFileUpload::make('image')
    ->webp(90)
    ->resize(1920)
    ->image() // Validates image MIME types
    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
    ->maxSize(5120) // 5MB max
    ->required();
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Erik Tailor](https://github.com/eriktailor)
- Built with [Filament](https://filamentphp.com)
- Powered by [Intervention Image](https://image.intervention.io)

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default WebP Quality
    |--------------------------------------------------------------------------
    |
    | The default quality level for WebP conversion (1-100).
    | Higher values mean better quality but larger file sizes.
    | This can be overridden per-field using ->webp(quality).
    |
    */
    'quality' => 80,

    /*
    |--------------------------------------------------------------------------
    | Default Resize Width
    |--------------------------------------------------------------------------
    |
    | The default maximum width for resizing images (in pixels).
    | Set to null to disable resizing by default.
    | Height is automatically calculated to maintain aspect ratio.
    | This can be overridden per-field using ->resize(width).
    |
    */
    'resize_width' => null,
];

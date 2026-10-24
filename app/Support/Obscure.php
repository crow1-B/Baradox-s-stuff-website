<?php

namespace App\Support;

use Intervention\Image\Interfaces\ImageInterface;

// The cover a gated photo or video is allowed to show. Shrunk to a few blocks across, blurred
// at that size so neighbouring blocks bleed into each other, then blown back up: blocky and
// soft at once, with nothing left to recognise. This is the protection; any CSS blur on top of
// it is decoration.
class Obscure
{
    private const CELLS = 8;

    private const BLUR = 1;

    public static function image(ImageInterface $image): ImageInterface
    {
        $width = $image->width();
        $height = $image->height();

        $image->scale(width: self::CELLS);
        $image->blur(self::BLUR);
        $image->resize($width, $height);

        return $image;
    }
}

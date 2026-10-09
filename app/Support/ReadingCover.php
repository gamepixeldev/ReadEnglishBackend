<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ReadingCover
{
    public static function url(?string $asset): ?string
    {
        if (! $asset || ! preg_match('~\A(?:article-covers|article-detail-images|series-covers)/[a-zA-Z0-9._-]+\z~', $asset)) {
            return null;
        }

        return Storage::disk('public')->url($asset);
    }
}

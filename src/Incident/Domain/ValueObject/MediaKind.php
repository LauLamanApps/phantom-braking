<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** Photos and videos are stored apart (separate buckets), so each can be monitored and managed on its own. */
enum MediaKind: string implements TranslatableEnum
{
    case Photo = 'photo';
    case Video = 'video';

    public static function fromContentType(string $contentType): self
    {
        return str_starts_with($contentType, 'video/') ? self::Video : self::Photo;
    }

    /** Our own file names tell the kind: "footage.<ext>" is the video, "photo-<id>…" a photo. */
    public static function fromKey(string $key): self
    {
        return str_starts_with(basename($key), 'footage.') ? self::Video : self::Photo;
    }

    public function translationKey(): string
    {
        return 'enum.media_kind.'.$this->value;
    }
}

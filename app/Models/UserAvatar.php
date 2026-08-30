<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'mime_type', 'image_data'])]
#[Hidden(['image_data'])]
class UserAvatar extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function binaryContents(): string
    {
        $data = $this->attributes['image_data'] ?? '';

        if (is_resource($data)) {
            $contents = stream_get_contents($data);

            if (is_resource($data)) {
                rewind($data);
            }

            return $contents === false ? '' : $contents;
        }

        if (! is_string($data) || $data === '') {
            return '';
        }

        if (str_starts_with($data, '\\x')) {
            $decoded = hex2bin(substr($data, 2));

            return $decoded === false ? $data : $decoded;
        }

        return $data;
    }
}

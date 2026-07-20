<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamChatAttachment extends Model
{
    protected $fillable = [
        'team_chat_message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $appends = ['url', 'human_size'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(TeamChatMessage::class, 'team_chat_message_id');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => route('attachments.team-chat.show', $this));
    }

    protected function humanSize(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->size < 1024) {
                return $this->size.' B';
            }

            if ($this->size < 1024 * 1024) {
                return round($this->size / 1024, 1).' KB';
            }

            return round($this->size / 1024 / 1024, 1).' MB';
        });
    }
}

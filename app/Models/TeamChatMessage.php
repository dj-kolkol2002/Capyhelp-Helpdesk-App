<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient_id',
        'body',
    ];

    protected $appends = ['formatted_created_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TeamChatAttachment::class);
    }

    protected function formattedCreatedAt(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->created_at?->toJSON());
    }
}

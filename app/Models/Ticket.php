<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'requester_name',
        'requester_email',
        'subject',
        'assignee',
        'status',
        'priority',
        'channel',
        'customer_access_token',
        'sla_warning_sent_at',
        'ai_summary',
    ];

    protected $appends = ['initials'];

    protected function casts(): array
    {
        return [
            'sla_warning_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            $ticket->customer_access_token ??= Str::random(64);
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee');
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            return collect(explode(' ', $this->requester_name))
                ->map(fn (string $part): string => substr($part, 0, 1))
                ->join('');
        });
    }

    public function customerUrl(): string
    {
        return route('support.tickets.show', [
            'ticket' => $this,
            'token' => $this->customer_access_token,
        ]);
    }
}

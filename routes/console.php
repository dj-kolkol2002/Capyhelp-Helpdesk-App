<?php

use App\Mail\SlaWarningNotification;
use App\Mail\WeeklyReportNotification;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('helpdesk:sla-warnings', function () {
    $now = now();
    $warningWindowEndsAt = $now->copy()->addMinutes(30);
    $sent = 0;

    Ticket::query()
        ->with('assigneeUser')
        ->whereIn('status', ['open', 'in_progress'])
        ->whereNull('sla_warning_sent_at')
        ->whereDoesntHave('messages', fn ($query) => $query->where('author_type', 'agent'))
        ->oldest()
        ->get()
        ->each(function (Ticket $ticket) use ($warningWindowEndsAt, &$sent): void {
            $slaMinutes = match ($ticket->priority) {
                'urgent' => 60,
                'high' => 240,
                'medium' => 480,
                default => 1440,
            };

            $dueAt = $ticket->created_at->copy()->addMinutes($slaMinutes);

            if ($dueAt->greaterThan($warningWindowEndsAt)) {
                return;
            }

            $recipients = $ticket->assigneeUser
                ? new EloquentCollection([$ticket->assigneeUser])
                : User::query()->where('role', 'admin')->get();

            $recipients = $recipients
                ->filter(fn (User $user): bool => $user->wantsNotification('slaWarning'))
                ->unique('id')
                ->values();

            if ($recipients->isEmpty()) {
                return;
            }

            $dueAtLabel = $dueAt->format('Y-m-d H:i');

            $recipients->each(function (User $user) use ($ticket, $dueAtLabel, &$sent): void {
                UserNotification::create([
                    'user_id' => $user->id,
                    'ticket_id' => $ticket->id,
                    'type' => 'sla_warning',
                    'title' => 'SLA: wymagana reakcja w '.$ticket->number,
                    'body' => 'Zgloszenie wymaga pierwszej odpowiedzi do '.$dueAtLabel.'.',
                ]);

                Mail::to($user->email)->queue(new SlaWarningNotification($ticket, $dueAtLabel));

                $sent++;
            });

            $ticket->forceFill([
                'sla_warning_sent_at' => now(),
            ])->save();
        });

    $this->info("Wyslano {$sent} ostrzezen SLA.");
})->purpose('Send SLA warning notifications before first response deadlines');

Artisan::command('helpdesk:weekly-report', function () {
    $start = now()->subWeek()->startOfWeek();
    $end = now()->subWeek()->endOfWeek();

    $activeStatuses = ['open', 'in_progress'];
    $priorityLabels = [
        'urgent' => 'Pilny',
        'high' => 'Wysoki',
        'medium' => 'Sredni',
        'low' => 'Niski',
    ];

    $report = [
        'range_label' => $start->format('Y-m-d').' - '.$end->format('Y-m-d'),
        'kpis' => [
            [
                'label' => 'Nowe zgloszenia',
                'value' => Ticket::query()->whereBetween('created_at', [$start, $end])->count(),
            ],
            [
                'label' => 'Zamkniete lub rozwiazane',
                'value' => Ticket::query()
                    ->whereIn('status', ['resolved', 'closed'])
                    ->whereBetween('updated_at', [$start, $end])
                    ->count(),
            ],
            [
                'label' => 'Aktywne zgloszenia',
                'value' => Ticket::query()->whereIn('status', $activeStatuses)->count(),
            ],
            [
                'label' => 'Pilne aktywne',
                'value' => Ticket::query()->whereIn('status', $activeStatuses)->where('priority', 'urgent')->count(),
            ],
        ],
        'priorities' => collect($priorityLabels)
            ->map(fn (string $label, string $priority): array => [
                'label' => $label,
                'value' => Ticket::query()
                    ->whereIn('status', $activeStatuses)
                    ->where('priority', $priority)
                    ->count(),
            ])
            ->values()
            ->all(),
    ];

    $sent = 0;

    User::query()
        ->whereIn('role', ['admin', 'agent'])
        ->get()
        ->filter(fn (User $user): bool => $user->wantsNotification('weeklyReport'))
        ->each(function (User $user) use ($report, &$sent): void {
            UserNotification::create([
                'user_id' => $user->id,
                'ticket_id' => null,
                'type' => 'weekly_report',
                'title' => 'Tygodniowy raport CAPYHELP',
                'body' => 'Podsumowanie pracy zespolu: '.$report['range_label'].'.',
            ]);

            Mail::to($user->email)->queue(new WeeklyReportNotification($report));

            $sent++;
        });

    $this->info("Wyslano {$sent} raportow tygodniowych.");
})->purpose('Send weekly helpdesk summary reports');

Schedule::command('helpdesk:sla-warnings')->everyFifteenMinutes();
Schedule::command('helpdesk:weekly-report')->weeklyOn(1, '08:00');

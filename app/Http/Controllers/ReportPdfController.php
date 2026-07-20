<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ReportPdfController extends Controller
{
    public function __invoke(): Response
    {
        $tickets = Ticket::query()
            ->with('assigneeUser:id,name,email')
            ->latest('updated_at')
            ->get();

        $total = $tickets->count();
        $active = $tickets->whereIn('status', ['open', 'in_progress'])->count();
        $completed = $tickets->whereIn('status', ['resolved', 'closed'])->count();
        $urgent = $tickets->where('priority', 'urgent')->count();
        $unassigned = $tickets->filter(fn (Ticket $ticket): bool => blank($ticket->assignee) || $ticket->assignee === 'unassigned')->count();

        $statusLabels = $this->labels('statuses', ['open', 'in_progress', 'resolved', 'closed']);
        $priorityLabels = $this->labels('priorities', ['urgent', 'high', 'medium', 'low']);
        $channelLabels = $this->labels('channels', ['email', 'phone', 'chat', 'in-person']);

        $pdf = Pdf::loadView('reports.tickets-pdf', [
            'locale' => app()->getLocale(),
            'documentTitle' => __('helpdesk.reports.document_title'),
            'title' => __('helpdesk.reports.title'),
            'generatedLabel' => __('helpdesk.reports.generated'),
            'generatedAt' => now(),
            'kpis' => [
                ['label' => __('helpdesk.reports.kpis.all_tickets'), 'value' => $total, 'detail' => __('helpdesk.reports.details.all_queue')],
                ['label' => __('helpdesk.reports.kpis.active'), 'value' => $active, 'detail' => $this->percentageText('queue_percent', $active, $total)],
                ['label' => __('helpdesk.reports.kpis.closed_resolved'), 'value' => $completed, 'detail' => $this->percentageText('queue_percent', $completed, $total)],
                ['label' => __('helpdesk.reports.kpis.urgent'), 'value' => $urgent, 'detail' => $this->percentageText('queue_percent', $urgent, $total)],
                ['label' => __('helpdesk.reports.kpis.unassigned'), 'value' => $unassigned, 'detail' => $this->percentageText('unassigned_percent', $unassigned, $total)],
            ],
            'statuses' => $this->distribution($tickets, 'status', $statusLabels, $total),
            'priorities' => $this->distribution($tickets, 'priority', $priorityLabels, $total),
            'channels' => $this->distribution($tickets, 'channel', $channelLabels, $total),
            'agents' => $this->agentWorkload($tickets),
            'tickets' => $this->recentTickets($tickets, $statusLabels, $priorityLabels),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(Str::slug(__('helpdesk.reports.file_prefix')).'-'.now()->format('Y-m-d').'.pdf');
    }

    private function percentage(int $value, int $total): int
    {
        return $total > 0 ? (int) round(($value / $total) * 100) : 0;
    }

    private function percentageText(string $key, int $value, int $total): string
    {
        return __('helpdesk.reports.details.'.$key, [
            'percent' => $this->percentage($value, $total),
        ]);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, string>
     */
    private function labels(string $group, array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => __('helpdesk.'.$group.'.'.$key)])
            ->all();
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function distribution($tickets, string $field, array $labels, int $total): array
    {
        return collect($labels)
            ->map(fn (string $label, string $key): array => [
                'label' => $label,
                'value' => $tickets->where($field, $key)->count(),
                'percentage' => $this->percentage($tickets->where($field, $key)->count(), $total),
            ])
            ->values()
            ->all();
    }

    private function agentWorkload($tickets): array
    {
        return $tickets
            ->groupBy(fn (Ticket $ticket): string => $ticket->assigneeUser?->name ?: __('helpdesk.common.unassigned'))
            ->map(fn ($items, string $name): array => [
                'name' => $name,
                'total' => $items->count(),
                'active' => $items->whereIn('status', ['open', 'in_progress'])->count(),
                'completed' => $items->whereIn('status', ['resolved', 'closed'])->count(),
                'urgent' => $items->where('priority', 'urgent')->count(),
            ])
            ->sortByDesc('active')
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $statusLabels
     * @param  array<string, string>  $priorityLabels
     */
    private function recentTickets($tickets, array $statusLabels, array $priorityLabels): array
    {
        return $tickets
            ->take(20)
            ->map(fn (Ticket $ticket): array => [
                'number' => $ticket->number,
                'requester_name' => $ticket->requester_name,
                'requester_email' => $ticket->requester_email,
                'subject' => $ticket->subject,
                'status' => $statusLabels[$ticket->status] ?? $ticket->status,
                'priority' => $priorityLabels[$ticket->priority] ?? $ticket->priority,
            ])
            ->all();
    }
}

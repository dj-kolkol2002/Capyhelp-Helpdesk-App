<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnglishTicketDemoSeeder extends Seeder
{
    public function run(): void
    {
        $agents = User::query()
            ->whereIn('email', [
                'admin@example.com',
                'test@example.com',
                'peter@helpdesk.test',
                'patricia@helpdesk.test',
                'aleksander@helpdesk.test',
                'agata@helpdesk.test',
            ])
            ->get()
            ->keyBy('email');

        $subjects = [
            'Invoice 25032019/B/567 requires correction',
            'Forwarding configuration for sales mailbox',
            'LiveChat pre-chat survey is missing',
            'Automatic responder sends outdated signature',
            'Case #678234 missing attachment',
            'Next license renewal and billing details',
            'Autoresponder reply contains the wrong text',
            'Account profile change request',
            'Unable to sign in to the admin panel',
            'Email notifications are not being delivered',
            'Imported ticket has the wrong priority',
            'Agent permissions need review',
            'Billing contact change request',
            'Invoice requires correction',
            'Attachment upload fails on submit',
            'Imported ticket priority is incorrect',
            'Account data update request',
            'Customer cannot open previous replies',
            'License renewal quote needed',
            'Agent role access is incomplete',
            'Priority changed after import',
            'Customer cannot see chat history',
            'Request for ticket status update',
            'Chat integration is not responding',
            'Missing email alerts for new replies',
            'Notification preferences are not saved',
            'Email notifications delayed for one domain',
            'Outbound notification email bounced',
            'Attachment upload fails from mobile',
            'Autoresponder content is outdated',
            'Password reset link does not work',
            'Agent cannot access assigned queue',
            'Imported case has incorrect priority',
            'Customer asks for current ticket status',
            'Account details need updating',
            'Status update request from customer',
            'Autoresponder sends old content',
            'Panel login fails after password change',
            'Agent permissions block ticket update',
            'Invoice details need correction',
            'Autoresponder template uses outdated copy',
            'Customer asks for ETA on request',
            'No email alert after ticket update',
            'Imported ticket priority looks wrong',
            'Status update requested by customer',
            'Customer email notifications missing',
            'Mail service was restarted after outage',
        ];

        $requesterNames = [
            'Viola Holmes',
            'Earl McDonald',
            'Marian Logan',
            'Douglas Olson',
            'Estelle Nguyen',
            'Bobby Huff',
            'Marek Novak',
            'Natalie Lawson',
            'Thomas Wilson',
            'Anna Cooper',
            'Julia Green',
            'Mark Spencer',
            'Natalie Ross',
            'Julia Collins',
            'Thomas Ward',
            'Mark Brooks',
            'Michael Dawson',
            'Eve Carter',
            'Mark Stone',
            'Kevin Miller',
            'Eva Parker',
            'Thomas Wright',
            'Julia Adams',
            'Anna Walsh',
            'Thomas Foster',
            'Thomas Bell',
            'Michael Brown',
            'Eve Simmons',
            'Anna Hughes',
            'Kevin Turner',
            'Natalie Bennett',
            'Julia Foster',
            'Peter Grant',
            'Natalie Price',
            'Kevin Mason',
            'Kevin Reed',
            'Natalie Coleman',
            'Mark Ellis',
            'Thomas Morgan',
            'Caroline Powell',
            'Julia Peterson',
            'Mark Abbott',
            'Thomas Bailey',
            'Natalie Fisher',
            'Eve Kennedy',
            'Julia Stone',
            'Damian Dunn',
        ];

        $statuses = [
            'resolved', 'open', 'in_progress', 'open', 'closed', 'in_progress',
            'closed', 'closed', 'in_progress', 'in_progress', 'resolved', 'closed',
            'closed', 'closed', 'open', 'in_progress', 'open', 'closed',
            'closed', 'closed', 'in_progress', 'open', 'closed', 'resolved',
            'open', 'open', 'closed', 'resolved', 'open', 'in_progress',
            'in_progress', 'closed', 'open', 'open', 'open', 'closed',
            'closed', 'resolved', 'in_progress', 'closed', 'closed', 'open',
            'in_progress', 'open', 'resolved', 'open', 'open',
        ];

        $priorities = [
            'medium', 'high', 'medium', 'low', 'medium', 'high',
            'low', 'low', 'urgent', 'high', 'low', 'low',
            'medium', 'medium', 'urgent', 'medium', 'urgent', 'urgent',
            'high', 'high', 'urgent', 'urgent', 'low', 'medium',
            'high', 'low', 'urgent', 'urgent', 'urgent', 'high',
            'urgent', 'low', 'medium', 'low', 'urgent', 'low',
            'low', 'urgent', 'urgent', 'low', 'medium', 'low',
            'medium', 'medium', 'low', 'low', 'high',
        ];

        $channels = [
            'email', 'chat', 'chat', 'email', 'email', 'email',
            'phone', 'in-person', 'phone', 'in-person', 'in-person', 'phone',
            'email', 'email', 'chat', 'email', 'chat', 'email',
            'email', 'chat', 'email', 'chat', 'chat', 'chat',
            'phone', 'in-person', 'in-person', 'email', 'in-person', 'chat',
            'email', 'phone', 'email', 'in-person', 'email', 'chat',
            'phone', 'email', 'email', 'email', 'email', 'in-person',
            'in-person', 'chat', 'chat', 'email', 'chat',
        ];

        $assignees = [
            'peter@helpdesk.test', 'patricia@helpdesk.test', 'aleksander@helpdesk.test', null, null, 'agata@helpdesk.test',
            'test@example.com', 'test@example.com', 'peter@helpdesk.test', 'agata@helpdesk.test', 'aleksander@helpdesk.test', 'test@example.com',
            'patricia@helpdesk.test', 'agata@helpdesk.test', null, 'peter@helpdesk.test', 'aleksander@helpdesk.test', 'patricia@helpdesk.test',
            'test@example.com', 'agata@helpdesk.test', 'peter@helpdesk.test', null, 'test@example.com', 'aleksander@helpdesk.test',
            'agata@helpdesk.test', 'patricia@helpdesk.test', 'test@example.com', 'peter@helpdesk.test', 'aleksander@helpdesk.test', 'patricia@helpdesk.test',
            'peter@helpdesk.test', 'agata@helpdesk.test', 'test@example.com', null, 'aleksander@helpdesk.test', 'patricia@helpdesk.test',
            'test@example.com', 'peter@helpdesk.test', 'agata@helpdesk.test', 'patricia@helpdesk.test', 'aleksander@helpdesk.test', null,
            'test@example.com', 'peter@helpdesk.test', 'agata@helpdesk.test', null, 'admin@example.com',
        ];

        DB::transaction(function () use ($agents, $subjects, $requesterNames, $statuses, $priorities, $channels, $assignees): void {
            foreach ($subjects as $index => $subject) {
                $number = $index < 6
                    ? 'HD-'.(2048 - $index)
                    : 'TKT-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);
                $requesterName = $requesterNames[$index];
                $requesterEmail = Str::slug($requesterName).($index + 1).'@example.com';
                $assignee = $assignees[$index] ? $agents->get($assignees[$index]) : null;
                $createdAt = now()->subDays(intdiv($index, 8) + 1)->subMinutes(($index % 8) * 11);
                $updatedAt = now()->subMinutes(($index + 1) * 37);

                $ticket = Ticket::query()->firstOrNew(['number' => $number]);
                $ticket->forceFill([
                    'requester_name' => $requesterName,
                    'requester_email' => $requesterEmail,
                    'subject' => $subject,
                    'assignee' => $assignee?->id,
                    'status' => $statuses[$index],
                    'priority' => $priorities[$index],
                    'channel' => $channels[$index],
                    'customer_access_token' => $ticket->customer_access_token ?: Str::random(64),
                    'sla_warning_sent_at' => null,
                    'ai_summary' => in_array($statuses[$index], ['resolved', 'closed'], true)
                        ? "- Customer reported: {$subject}.\n- Support reviewed the case and documented the outcome."
                        : null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ])->save();

                $ticket->messages()->delete();

                foreach ($this->messagesFor($ticket, $assignee) as $messageIndex => $message) {
                    $messageTime = $createdAt->copy()->addMinutes(($messageIndex + 1) * 18);

                    $ticket->messages()->create([
                        'user_id' => $message['type'] === 'agent' ? $assignee?->id : null,
                        'author_name' => $message['name'],
                        'author_email' => $message['email'],
                        'author_type' => $message['type'],
                        'body' => $message['body'],
                        'created_at' => $messageTime,
                        'updated_at' => $messageTime,
                    ]);
                }
            }
        });
    }

    private function messagesFor(Ticket $ticket, ?User $assignee): array
    {
        $agentName = $assignee?->name ?? 'Support Team';
        $agentEmail = $assignee?->email ?? 'support@example.com';
        $messages = [
            [
                'type' => 'customer',
                'name' => $ticket->requester_name,
                'email' => $ticket->requester_email,
                'body' => "Hi, we need help with this case: {$ticket->subject}. Could you check it and let us know the next step?",
            ],
        ];

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            $messages[] = [
                'type' => 'agent',
                'name' => $agentName,
                'email' => $agentEmail,
                'body' => 'Thanks for the details. I reviewed the case, updated the ticket, and documented the final outcome for the team.',
            ];
            $messages[] = [
                'type' => 'customer',
                'name' => $ticket->requester_name,
                'email' => $ticket->requester_email,
                'body' => 'Thank you, that resolves the request from our side.',
            ];

            return $messages;
        }

        $messages[] = [
            'type' => 'agent',
            'name' => $agentName,
            'email' => $agentEmail,
            'body' => 'Thanks for the report. I am reviewing the configuration and will update this ticket once I have a verified answer.',
        ];

        return $messages;
    }
}

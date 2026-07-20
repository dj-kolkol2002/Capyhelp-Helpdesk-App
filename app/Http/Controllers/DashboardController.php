<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const VIEWS = [
        'Tickets',
        'TeamChat',
        'KnowledgeBase',
        'Agents',
        'Reports',
        'Settings',
    ];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $initialView = (string) $request->route('view', 'Tickets');

        abort_unless(in_array($initialView, self::VIEWS, true), 404);

        $tickets = Ticket::query()
            ->select([
                'id',
                'number',
                'requester_name',
                'requester_email',
                'subject',
                'assignee',
                'status',
                'priority',
                'channel',
                'updated_at',
            ])
            ->with('assigneeUser:id,name,email')
            ->when($user->isAgent(), fn ($query) => $query->where('assignee', $user->id))
            ->latest('updated_at')
            ->get();

        return Inertia::render('Dashboard', [
            'initialView' => $initialView,
            'tickets' => $tickets,
            'agents' => User::query()
                ->where('role', 'agent')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'appNotifications' => $user->appNotifications()
                ->with('ticket:id,number,subject')
                ->latest()
                ->limit(20)
                ->get(),
            'unreadNotificationsCount' => $user->appNotifications()
                ->whereNull('read_at')
                ->count(),
            'teamChatMessages' => [],
            'teamChatUsers' => User::query()
                ->whereIn('role', ['admin', 'agent'])
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'avatar_path']),
            'knowledgeArticles' => KnowledgeArticle::query()
                ->where('is_published', true)
                ->orderBy('category')
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'category', 'problem', 'symptoms', 'solution', 'customer_reply', 'tags', 'updated_at']),
        ]);
    }
}

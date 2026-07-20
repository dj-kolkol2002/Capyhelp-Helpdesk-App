<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CustomerTicketController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportPdfController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TeamChatMessageController;
use App\Http\Controllers\TicketAiController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserNotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, array_keys(config('locales.supported')), true), 404);

    $request->session()->put('locale', $locale);

    return back();
})->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

Route::get('/support', [CustomerTicketController::class, 'create'])->name('support.create');
Route::post('/support/tickets', [CustomerTicketController::class, 'store'])->name('support.tickets.store');
Route::get('/support/tickets/{ticket}', [CustomerTicketController::class, 'show'])->name('support.tickets.show');
Route::post('/support/tickets/{ticket}/messages', [CustomerTicketController::class, 'message'])->name('support.tickets.messages.store');
Route::get('/attachments/ticket/{attachment}', [AttachmentDownloadController::class, 'ticket'])->name('attachments.ticket.show');

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/tickets')->name('dashboard');
    Route::get('/tickets', DashboardController::class)->defaults('view', 'Tickets')->name('tickets.index');
    Route::get('/team-chat', DashboardController::class)->defaults('view', 'TeamChat')->name('team-chat.index');
    Route::get('/knowledge-base', DashboardController::class)->defaults('view', 'KnowledgeBase')->name('knowledge-base.index');
    Route::get('/agents', DashboardController::class)->defaults('view', 'Agents')->name('agents.index');
    Route::get('/reports', DashboardController::class)->defaults('view', 'Reports')->name('reports.index');
    Route::get('/settings', DashboardController::class)->defaults('view', 'Settings')->name('settings.index');

    Route::patch('/settings/account', [AccountSettingsController::class, 'update'])->name('settings.account.update');
    Route::patch('/settings/locale', [AccountSettingsController::class, 'updateLocale'])->name('settings.locale.update');
    Route::patch('/settings/notifications', [AccountSettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::patch('/notifications/{notification}/read', [UserNotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [UserNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/team-chat/messages', [TeamChatMessageController::class, 'index'])->name('team-chat.messages.index');
    Route::post('/team-chat/messages', [TeamChatMessageController::class, 'store'])->name('team-chat.messages.store');
    Route::get('/attachments/team-chat/{attachment}', [AttachmentDownloadController::class, 'teamChat'])->name('attachments.team-chat.show');
    Route::get('/api/users/list', [UserController::class, 'list'])->name('api.users.list');

    // Admin only - User Management
    Route::middleware('admin')->group(function () {
        Route::get('/reports/tickets.pdf', ReportPdfController::class)->name('reports.tickets.pdf');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Admin only - full CRUD for tickets (must come before {ticket} route to avoid conflicts)
    Route::middleware('admin')->group(function () {
        Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket}/edit', [TicketController::class, 'edit'])->name('tickets.edit');
        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
    });

    // Agent & Admin - can view and comment on tickets
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->name('tickets.messages.store');
    Route::post('/tickets/{ticket}/ai/tone', [TicketAiController::class, 'tone'])->name('tickets.ai.tone');
    Route::post('/tickets/{ticket}/ai/summary', [TicketAiController::class, 'summary'])->name('tickets.ai.summary');
    Route::patch('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');

    // API Endpoints for Vue.js (Admin only)
    Route::middleware('admin')->prefix('api')->group(function () {
        Route::post('/users', [UserController::class, 'storeJson'])->name('api.users.store');
        Route::patch('/users/{user}', [UserController::class, 'updateJson'])->name('api.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroyJson'])->name('api.users.destroy');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

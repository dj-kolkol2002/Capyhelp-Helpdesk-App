<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Tickets | {{ config('app.name', 'Helpdesk System') }}</title>

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body>
        <div class="min-h-screen bg-gray-100">
            <!-- Navigation -->
            <nav class="bg-white shadow">
                <div class="max-w-7xl mx-auto px-4 py-4">
                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-bold">Tickets Management</h1>
                        <div class="space-x-4">
                            <a href="{{ route('dashboard') }}" class="text-blue-600 hover:text-blue-900">Dashboard</a>
                            <a href="{{ route('tickets.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Ticket</a>
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="text-red-600 hover:text-red-900">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="max-w-7xl mx-auto py-6">
                @if (session('success'))
                    <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="bg-white rounded shadow overflow-hidden">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Requester</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($tickets as $ticket)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-blue-600">
                                        <a href="{{ route('tickets.show', $ticket) }}">{{ $ticket->number }}</a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $ticket->subject }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $ticket->requester_name }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="px-2 py-1 rounded text-xs font-medium
                                            @if ($ticket->status === 'open')
                                                bg-blue-100 text-blue-800
                                            @elseif ($ticket->status === 'in_progress')
                                                bg-yellow-100 text-yellow-800
                                            @elseif ($ticket->status === 'resolved')
                                                bg-green-100 text-green-800
                                            @else
                                                bg-gray-100 text-gray-800
                                            @endif
                                        ">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="px-2 py-1 rounded text-xs font-medium
                                            @if ($ticket->priority === 'urgent')
                                                bg-red-100 text-red-800
                                            @elseif ($ticket->priority === 'high')
                                                bg-orange-100 text-orange-800
                                            @elseif ($ticket->priority === 'medium')
                                                bg-yellow-100 text-yellow-800
                                            @else
                                                bg-green-100 text-green-800
                                            @endif
                                        ">{{ ucfirst($ticket->priority) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm space-x-2">
                                        <a href="{{ route('tickets.edit', $ticket) }}" class="text-blue-600 hover:text-blue-900">Edit</a>
                                        <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="inline" onsubmit="return confirm('Are you sure?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No tickets found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $tickets->links() }}
                </div>
            </main>
        </div>
    </body>
</html>

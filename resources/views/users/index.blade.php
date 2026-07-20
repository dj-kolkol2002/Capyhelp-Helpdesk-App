<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Users Management | {{ config('app.name', 'Helpdesk System') }}</title>

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body>
        <div class="min-h-screen bg-gray-100">
            <!-- Navigation -->
            <nav class="bg-white shadow">
                <div class="max-w-7xl mx-auto px-4 py-4">
                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-bold">Users Management</h1>
                        <div class="space-x-4">
                            <a href="{{ route('dashboard') }}" class="text-blue-600 hover:text-blue-900">Dashboard</a>
                            <a href="{{ route('tickets.index') }}" class="text-blue-600 hover:text-blue-900">Tickets</a>
                            <a href="{{ route('users.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add User</a>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($users as $user)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $user->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $user->email }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="px-2 py-1 rounded text-xs font-medium
                                            @if ($user->role === 'admin')
                                                bg-purple-100 text-purple-800
                                            @else
                                                bg-blue-100 text-blue-800
                                            @endif
                                        ">{{ ucfirst($user->role) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $user->created_at->format('Y-m-d') }}</td>
                                    <td class="px-6 py-4 text-sm space-x-2">
                                        <a href="{{ route('users.edit', $user) }}" class="text-blue-600 hover:text-blue-900">Edit</a>
                                        @if (auth()->user()->id !== $user->id)
                                            <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('Are you sure?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </main>
        </div>
    </body>
</html>

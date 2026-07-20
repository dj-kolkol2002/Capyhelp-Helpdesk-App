<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Create Ticket | {{ config('app.name', 'Helpdesk System') }}</title>

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body>
        <div class="min-h-screen bg-gray-100">
            <!-- Navigation -->
            <nav class="bg-white shadow">
                <div class="max-w-7xl mx-auto px-4 py-4">
                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-bold">Create Ticket</h1>
                        <div class="space-x-4">
                            <a href="{{ route('tickets.index') }}" class="text-blue-600 hover:text-blue-900">Back to Tickets</a>
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="text-red-600 hover:text-red-900">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="max-w-2xl mx-auto py-6">
                <div class="bg-white rounded shadow p-6">
                    <form method="POST" action="{{ route('tickets.store') }}">
                        @csrf

                        <!-- Requester Name -->
                        <div class="mb-4">
                            <label for="requester_name" class="block text-sm font-medium text-gray-700">Requester Name</label>
                            <input type="text" id="requester_name" name="requester_name" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded @error('requester_name') border-red-500 @enderror" value="{{ old('requester_name') }}" required>
                            @error('requester_name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Requester Email -->
                        <div class="mb-4">
                            <label for="requester_email" class="block text-sm font-medium text-gray-700">Requester Email</label>
                            <input type="email" id="requester_email" name="requester_email" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded @error('requester_email') border-red-500 @enderror" value="{{ old('requester_email') }}" required>
                            @error('requester_email')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Subject -->
                        <div class="mb-4">
                            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                            <input type="text" id="subject" name="subject" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded @error('subject') border-red-500 @enderror" value="{{ old('subject') }}" required>
                            @error('subject')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Priority -->
                        <div class="mb-4">
                            <label for="priority" class="block text-sm font-medium text-gray-700">Priority</label>
                            <select id="priority" name="priority" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded @error('priority') border-red-500 @enderror" required>
                                <option value="">Select Priority</option>
                                <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Low</option>
                                <option value="medium" {{ old('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                                <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>High</option>
                                <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                            @error('priority')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Channel -->
                        <div class="mb-4">
                            <label for="channel" class="block text-sm font-medium text-gray-700">Channel</label>
                            <select id="channel" name="channel" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded @error('channel') border-red-500 @enderror" required>
                                <option value="">Select Channel</option>
                                <option value="email" {{ old('channel') === 'email' ? 'selected' : '' }}>Email</option>
                                <option value="phone" {{ old('channel') === 'phone' ? 'selected' : '' }}>Phone</option>
                                <option value="chat" {{ old('channel') === 'chat' ? 'selected' : '' }}>Chat</option>
                                <option value="in-person" {{ old('channel') === 'in-person' ? 'selected' : '' }}>In-Person</option>
                            </select>
                            @error('channel')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Assignee -->
                        <div class="mb-6">
                            <label for="assignee" class="block text-sm font-medium text-gray-700">Assign to Agent</label>
                            <select id="assignee" name="assignee" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded @error('assignee') border-red-500 @enderror">
                                <option value="">Unassigned</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ old('assignee') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                @endforeach
                            </select>
                            @error('assignee')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Ticket</button>
                            <a href="{{ route('tickets.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </body>
</html>

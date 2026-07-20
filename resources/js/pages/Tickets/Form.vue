<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    agents: {
        type: Array,
        default: () => [],
    },
    ticket: {
        type: Object,
        default: null,
    },
    mode: {
        type: String,
        default: 'create',
    },
});

const page = usePage();
const isEdit = computed(() => props.mode === 'edit');
const isDark = computed(() => localStorage.getItem('helpdesk-theme') === 'dark');

const form = useForm({
    requester_name: props.ticket?.requester_name ?? '',
    requester_email: props.ticket?.requester_email ?? '',
    subject: props.ticket?.subject ?? '',
    priority: props.ticket?.priority ?? 'medium',
    channel: props.ticket?.channel ?? 'email',
    assignee: props.ticket?.assignee ?? '',
    status: props.ticket?.status ?? 'open',
});

const statusOptions = [
    { value: 'open', label: 'Otwarte' },
    { value: 'in_progress', label: 'W toku' },
    { value: 'resolved', label: 'Rozwiązane' },
    { value: 'closed', label: 'Zamknięte' },
];

const priorityOptions = [
    { value: 'low', label: 'Niski' },
    { value: 'medium', label: 'Średni' },
    { value: 'high', label: 'Wysoki' },
    { value: 'urgent', label: 'Pilny' },
];

const channelOptions = [
    { value: 'email', label: 'Email' },
    { value: 'phone', label: 'Telefon' },
    { value: 'chat', label: 'Chat' },
    { value: 'in-person', label: 'Osobiście' },
];

const submit = () => {
    if (isEdit.value) {
        form.patch(`/tickets/${props.ticket.id}`, {
            preserveScroll: true,
        });
        return;
    }

    form.post('/tickets');
};
</script>

<template>
    <main :class="['min-h-screen', isDark ? 'bg-slate-950 text-slate-200' : 'bg-[#f4f6f8] text-slate-700']">
        <header :class="['border-b', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
            <div class="mx-auto flex min-h-16 max-w-5xl items-center justify-between px-5 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tickety</p>
                    <h1 :class="['mt-1 text-xl font-semibold', isDark ? 'text-white' : 'text-slate-950']">
                        {{ isEdit ? 'Edycja zgłoszenia' : 'Nowe zgłoszenie' }}
                    </h1>
                </div>
                <Link
                    :href="isEdit ? `/tickets/${ticket.id}` : '/tickets'"
                    :class="['rounded-md border px-3 py-2 text-sm font-semibold', isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                >
                    Anuluj
                </Link>
            </div>
        </header>

        <div class="mx-auto max-w-5xl px-5 py-6">
            <form
                :class="['rounded-md border p-5 shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']"
                @submit.prevent="submit"
            >
                <div v-if="Object.keys(form.errors).length" class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Sprawdź pola formularza i spróbuj ponownie.
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-500" for="requester-name">Imię i nazwisko klienta</label>
                        <input
                            id="requester-name"
                            v-model="form.requester_name"
                            :disabled="isEdit"
                            type="text"
                            :class="['mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-70', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                        >
                        <p v-if="form.errors.requester_name" class="mt-1 text-xs text-red-600">{{ form.errors.requester_name }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500" for="requester-email">Email klienta</label>
                        <input
                            id="requester-email"
                            v-model="form.requester_email"
                            :disabled="isEdit"
                            type="email"
                            :class="['mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-70', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                        >
                        <p v-if="form.errors.requester_email" class="mt-1 text-xs text-red-600">{{ form.errors.requester_email }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-slate-500" for="subject">Temat</label>
                        <input
                            id="subject"
                            v-model="form.subject"
                            type="text"
                            :class="['mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                        >
                        <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                    </div>

                    <div v-if="isEdit">
                        <label class="text-sm font-semibold text-slate-500" for="status">Status</label>
                        <select
                            id="status"
                            v-model="form.status"
                            :class="['mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                        >
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500" for="priority">Priorytet</label>
                        <select
                            id="priority"
                            v-model="form.priority"
                            :class="['mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                        >
                            <option v-for="option in priorityOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <p v-if="form.errors.priority" class="mt-1 text-xs text-red-600">{{ form.errors.priority }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500" for="channel">Kanał</label>
                        <select
                            id="channel"
                            v-model="form.channel"
                            :class="['mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                        >
                            <option v-for="option in channelOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <p v-if="form.errors.channel" class="mt-1 text-xs text-red-600">{{ form.errors.channel }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500" for="assignee">Agent</label>
                        <select
                            id="assignee"
                            v-model="form.assignee"
                            :class="['mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                        >
                            <option value="">Nieprzypisane</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }} - {{ agent.email }}</option>
                        </select>
                        <p v-if="form.errors.assignee" class="mt-1 text-xs text-red-600">{{ form.errors.assignee }}</p>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t pt-5" :class="isDark ? 'border-slate-800' : 'border-slate-100'">
                    <Link
                        :href="isEdit ? `/tickets/${ticket.id}` : '/tickets'"
                        :class="['rounded-md border px-4 py-2 text-sm font-semibold', isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50']"
                    >
                        Anuluj
                    </Link>
                    <button
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Zapisywanie...' : isEdit ? 'Zapisz zmiany' : 'Utwórz ticket' }}
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'create',
    },
    ticket: {
        type: Object,
        default: null,
    },
    agents: {
        type: Array,
        default: () => [],
    },
    isDark: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const isEdit = computed(() => props.mode === 'edit');

const defaultValues = () => ({
    requester_name: props.ticket?.requester_name ?? '',
    requester_email: props.ticket?.requester_email ?? '',
    subject: props.ticket?.subject ?? '',
    priority: props.ticket?.priority ?? 'medium',
    channel: props.ticket?.channel ?? 'email',
    assignee: props.ticket?.assignee ?? '',
    status: props.ticket?.status ?? 'open',
});

const form = useForm(defaultValues());

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

watch(() => props.show, (show) => {
    if (!show) {
        return;
    }

    form.defaults(defaultValues());
    form.reset();
    form.clearErrors();
});

const close = () => {
    if (form.processing) {
        return;
    }

    emit('close');
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };

    form
        .transform((data) => ({
            ...data,
            assignee: data.assignee || null,
        }));

    if (isEdit.value) {
        form.patch(`/tickets/${props.ticket.id}`, options);
        return;
    }

    form.post('/tickets', options);
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-2 sm:items-center sm:p-4">
                <button class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" aria-label="Zamknij modal" @click="close"></button>

                <form
                    :class="[
                        'scrollbar-hidden relative max-h-[calc(100dvh-1rem)] w-full max-w-3xl overflow-y-auto rounded-lg border p-4 shadow-2xl sm:max-h-[calc(100dvh-2rem)] sm:p-5',
                        isDark ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-200 bg-white text-slate-800',
                    ]"
                    @submit.prevent="submit"
                >
                    <div class="flex items-start justify-between gap-4 border-b pb-4" :class="isDark ? 'border-slate-800' : 'border-slate-100'">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ticket</p>
                            <h2 :class="['mt-1 text-lg font-semibold', isDark ? 'text-white' : 'text-slate-950']">
                                {{ isEdit ? 'Edytuj zgłoszenie' : 'Nowe zgłoszenie' }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            :class="['grid size-8 place-items-center rounded-md text-lg', isDark ? 'text-slate-300 hover:bg-slate-800' : 'text-slate-500 hover:bg-slate-100']"
                            aria-label="Zamknij"
                            @click="close"
                        >
                            ×
                        </button>
                    </div>

                    <div v-if="Object.keys(form.errors).length" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        Sprawdź pola formularza i spróbuj ponownie.
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-semibold text-slate-500" for="modal-requester-name">Imię i nazwisko klienta</label>
                            <input
                                id="modal-requester-name"
                                v-model="form.requester_name"
                                :disabled="isEdit"
                                type="text"
                                :class="['mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-70', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                            >
                            <p v-if="form.errors.requester_name" class="mt-1 text-xs text-red-600">{{ form.errors.requester_name }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-500" for="modal-requester-email">Email klienta</label>
                            <input
                                id="modal-requester-email"
                                v-model="form.requester_email"
                                :disabled="isEdit"
                                type="email"
                                :class="['mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-70', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                            >
                            <p v-if="form.errors.requester_email" class="mt-1 text-xs text-red-600">{{ form.errors.requester_email }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold text-slate-500" for="modal-subject">Temat</label>
                            <input
                                id="modal-subject"
                                v-model="form.subject"
                                type="text"
                                :class="['mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                            >
                            <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                        </div>

                        <div v-if="isEdit">
                            <label class="text-sm font-semibold text-slate-500" for="modal-status">Status</label>
                            <select
                                id="modal-status"
                                v-model="form.status"
                                :class="['mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                            >
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-500" for="modal-priority">Priorytet</label>
                            <select
                                id="modal-priority"
                                v-model="form.priority"
                                :class="['mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                            >
                                <option v-for="option in priorityOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-500" for="modal-channel">Kanał</label>
                            <select
                                id="modal-channel"
                                v-model="form.channel"
                                :class="['mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                            >
                                <option v-for="option in channelOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-500" for="modal-assignee">Agent</label>
                            <select
                                id="modal-assignee"
                                v-model="form.assignee"
                                :class="['mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100', isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900']"
                            >
                                <option value="">Nieprzypisane</option>
                                <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }} - {{ agent.email }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-2 border-t pt-5 sm:flex-row sm:items-center sm:justify-end sm:gap-3" :class="isDark ? 'border-slate-800' : 'border-slate-100'">
                        <button
                            type="button"
                            :class="['w-full rounded-md border px-4 py-2 text-sm font-semibold sm:w-auto', isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50']"
                            @click="close"
                        >
                            Anuluj
                        </button>
                        <button
                            class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Zapisywanie...' : isEdit ? 'Zapisz zmiany' : 'Utwórz ticket' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

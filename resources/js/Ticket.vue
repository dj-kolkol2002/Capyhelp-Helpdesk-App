<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import 'vue3-emoji-picker/css';

const EmojiPicker = defineAsyncComponent(() => import('vue3-emoji-picker'));
import TicketFormModal from './components/TicketFormModal.vue';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    agents: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const { t, locale } = useI18n();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const currentUser = computed(() => page.props.auth?.user ?? {
    name: 'Agent',
    email: 'agent@example.com',
});

const ticket = ref({
    ...props.ticket,
    messages: props.ticket.messages ?? [],
});
const chatMessage = ref('');
const ticketFiles = ref([]);
const ticketFileInput = ref(null);
const chatError = ref('');
const isSendingMessage = ref(false);
const isChangingTone = ref(false);
const isGeneratingSummary = ref(false);
const aiSummary = ref(props.ticket.ai_summary ?? '');
const showEmojiPicker = ref(false);
const ticketChatScroll = ref(null);
const theme = ref(localStorage.getItem('helpdesk-theme') === 'dark' ? 'dark' : 'light');
const showEditTicketModal = ref(false);

const isDark = computed(() => theme.value === 'dark');

const statusLabels = computed(() => ({
    open: t('tickets.open'),
    in_progress: t('tickets.inProgress'),
    resolved: t('tickets.resolved'),
    closed: t('tickets.closed'),
}));

const priorityLabels = computed(() => ({
    low: t('tickets.low'),
    medium: t('tickets.medium'),
    high: t('tickets.high'),
    urgent: t('tickets.urgent'),
}));

const channelLabels = computed(() => ({
    email: t('tickets.email'),
    phone: t('tickets.phone'),
    chat: t('tickets.chat'),
    'in-person': t('tickets.inPerson'),
}));

const assigneeName = computed(() => ticket.value.assignee_user?.name ?? t('ticketDetail.unassigned'));
const canGenerateAiSummary = computed(() => ['resolved', 'closed'].includes(ticket.value.status));

const statusStyles = {
    open: 'border-blue-200 bg-blue-50 text-blue-700',
    in_progress: 'border-amber-200 bg-amber-50 text-amber-700',
    resolved: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    closed: 'border-slate-200 bg-slate-100 text-slate-700',
};

const priorityStyles = {
    low: 'text-slate-500',
    medium: 'text-slate-600',
    high: 'text-orange-600',
    urgent: 'text-red-600',
};

const formatRelativeDate = (date) => {
    const diffInSeconds = Math.max(1, Math.floor((Date.now() - new Date(date).getTime()) / 1000));

    if (diffInSeconds < 60) {
        return t('relative.secondsAgo', { count: diffInSeconds });
    }

    const diffInMinutes = Math.floor(diffInSeconds / 60);

    if (diffInMinutes < 60) {
        return t('relative.minutesAgo', { count: diffInMinutes });
    }

    const diffInHours = Math.floor(diffInMinutes / 60);

    if (diffInHours < 24) {
        return t('relative.hoursAgo', { count: diffInHours });
    }

    return t('relative.daysAgo', { count: Math.floor(diffInHours / 24) });
};

const normalizeTicketMessage = (message) => ({
    ...message,
    attachments: message.attachments ?? [],
    time: message.created_at ? formatRelativeDate(message.created_at) : '',
});

const messages = computed(() => (ticket.value.messages ?? []).map(normalizeTicketMessage));

const ticketAttachments = computed(() => messages.value
    .flatMap((message) => (message.attachments ?? []).map((attachment) => ({
        ...attachment,
        message,
        isImage: String(attachment.mime_type ?? '').startsWith('image/'),
    })))
    .reverse());

const requesterInitials = computed(() => ticket.value.initials ?? ticket.value.requester_name
    ?.split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase() ?? 'K');

const agentInitials = (name = currentUser.value.name ?? 'A') => name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

const isOwnTicketMessage = (message) => message.author_type === 'agent';

const escapeHtml = (value = '') => value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const renderChatBody = (body = '') => escapeHtml(body)
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/__(.+?)__/g, '<u>$1</u>')
    .replace(/\n/g, '<br>');

const isEmojiOnlyMessage = (body = '') => {
    const trimmed = body.trim();

    return Boolean(trimmed)
        && /^[\p{Extended_Pictographic}\p{Emoji_Component}\u200d\ufe0f\s]+$/u.test(trimmed)
        && /\p{Extended_Pictographic}/u.test(trimmed);
};

const scrollTicketChatToBottom = () => nextTick(() => {
    if (ticketChatScroll.value) {
        ticketChatScroll.value.scrollTop = ticketChatScroll.value.scrollHeight;
    }
});

const appendMessage = (message) => {
    const existingMessage = ticket.value.messages?.some((item) => item.id === message.id);

    if (existingMessage) {
        return;
    }

    ticket.value.messages = [...(ticket.value.messages ?? []), normalizeTicketMessage(message)];
    scrollTicketChatToBottom();
};

const wrapTicketSelection = (prefix, suffix = prefix) => {
    const textarea = document.querySelector('#ticket-chat-body');
    const start = textarea?.selectionStart ?? chatMessage.value.length;
    const end = textarea?.selectionEnd ?? chatMessage.value.length;
    const selected = chatMessage.value.slice(start, end);

    const fallback = t('ticketDetail.sampleText');

    chatMessage.value = `${chatMessage.value.slice(0, start)}${prefix}${selected || fallback}${suffix}${chatMessage.value.slice(end)}`;

    nextTick(() => {
        textarea?.focus();
        textarea?.setSelectionRange(start + prefix.length, start + prefix.length + (selected || fallback).length);
    });
};

const appendEmoji = (emoji) => {
    chatMessage.value = `${chatMessage.value}${emoji.i ?? ''}`;
    showEmojiPicker.value = false;
};

const handleTicketFiles = (event) => {
    ticketFiles.value = Array.from(event.target.files ?? []).slice(0, 5);
};

const removeTicketFile = (index) => {
    ticketFiles.value = ticketFiles.value.filter((_, currentIndex) => currentIndex !== index);

    if (!ticketFiles.value.length && ticketFileInput.value) {
        ticketFileInput.value.value = '';
    }
};

const improveTone = async () => {
    const message = chatMessage.value.trim();

    if (!message || isChangingTone.value) {
        chatError.value = !message ? t('ticketDetail.aiToneEmpty') : '';
        return;
    }

    isChangingTone.value = true;
    chatError.value = '';

    try {
        const response = await fetch(`/tickets/${ticket.value.id}/ai/tone`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ message }),
        });
        const data = await response.json();

        if (!response.ok) {
            chatError.value = data.message ?? t('ticketDetail.aiToneError');
            return;
        }

        chatMessage.value = data.message ?? chatMessage.value;
    } catch (error) {
        chatError.value = t('ticketDetail.aiUnavailable');
    } finally {
        isChangingTone.value = false;
    }
};

const generateSummary = async () => {
    if (!canGenerateAiSummary.value || isGeneratingSummary.value) {
        return;
    }

    isGeneratingSummary.value = true;
    chatError.value = '';

    try {
        const response = await fetch(`/tickets/${ticket.value.id}/ai/summary`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();

        if (!response.ok) {
            chatError.value = data.message ?? t('ticketDetail.aiSummaryError');
            return;
        }

        aiSummary.value = data.summary ?? '';
    } catch (error) {
        chatError.value = t('ticketDetail.aiUnavailable');
    } finally {
        isGeneratingSummary.value = false;
    }
};

onMounted(() => {
    scrollTicketChatToBottom();

    window.Echo
        ?.private(`tickets.${ticket.value.id}`)
        .listen('.ticket.message.created', (event) => {
            appendMessage(event.message);
        });
});

onBeforeUnmount(() => {
    window.Echo?.leave(`tickets.${ticket.value.id}`);
});

const sendMessage = async () => {
    const body = chatMessage.value.trim();

    if (!body || isSendingMessage.value) {
        chatError.value = !body ? t('ticketDetail.emptyMessage') : '';
        return;
    }

    isSendingMessage.value = true;
    chatError.value = '';

    const payload = new FormData();
    payload.append('body', body);
    payload.append('author_type', 'agent');
    ticketFiles.value.forEach((file) => payload.append('attachments[]', file));

    try {
        const response = await fetch(`/tickets/${ticket.value.id}/messages`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'X-Socket-ID': window.Echo?.socketId() ?? '',
            },
            body: payload,
        });
        const data = await response.json();

        if (!response.ok) {
            chatError.value = data.message ?? t('ticketDetail.sendError');
            return;
        }

        appendMessage(data.message);
        chatMessage.value = '';
        ticketFiles.value = [];
        if (ticketFileInput.value) {
            ticketFileInput.value.value = '';
        }
    } catch (error) {
        chatError.value = t('ticketDetail.sendError');
    } finally {
        isSendingMessage.value = false;
    }
};
</script>

<template>
    <main :class="['min-h-screen', isDark ? 'bg-slate-950 text-slate-200' : 'bg-[#f4f6f8] text-slate-700']">
        <header :class="['border-b', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
            <div class="flex min-h-16 flex-col gap-3 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <a class="flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-700" href="/tickets">
                            <font-awesome-icon icon="fa-solid fa-arrow-left" class="text-xs" />
                            {{ t('ticketDetail.back') }}
                        </a>
                        <span :class="['text-xs', isDark ? 'text-slate-500' : 'text-slate-300']">/</span>
                        <span :class="['text-xs font-semibold', isDark ? 'text-slate-400' : 'text-slate-400']">{{ ticket.number }}</span>
                    </div>
                    <h1 :class="['mt-2 line-clamp-2 text-xl font-semibold sm:truncate', isDark ? 'text-slate-100' : 'text-slate-950']">{{ ticket.subject }}</h1>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        :class="['rounded-md border px-3 py-1.5 text-xs font-semibold', isDark ? 'border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                        @click="showEditTicketModal = true"
                    >
                        {{ t('ticketDetail.edit') }}
                    </button>
                    <button
                        v-if="canGenerateAiSummary"
                        type="button"
                        :class="[
                            'inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60',
                            isDark ? 'border-blue-500/30 bg-blue-500/10 text-blue-200 hover:bg-blue-500/20' : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100',
                        ]"
                        :disabled="isGeneratingSummary"
                        @click="generateSummary"
                    >
                        <font-awesome-icon icon="fa-solid fa-wand-magic-sparkles" />
                        {{ isGeneratingSummary ? t('ticketDetail.summarizing') : t('ticketDetail.aiSummary') }}
                    </button>
                    <span :class="['rounded-full border px-2.5 py-1 text-xs font-semibold', statusStyles[ticket.status]]">
                        {{ statusLabels[ticket.status] ?? ticket.status }}
                    </span>
                    <span :class="['rounded-md px-2.5 py-1 text-xs font-semibold', isDark ? 'bg-slate-800 text-slate-200' : 'bg-white text-slate-600', priorityStyles[ticket.priority]]">
                        {{ priorityLabels[ticket.priority] ?? ticket.priority }}
                    </span>
                    <span :class="['rounded-md border px-2.5 py-1 text-xs font-semibold', isDark ? 'border-slate-700 bg-slate-800 text-slate-300' : 'border-slate-200 bg-white text-slate-600']">
                        {{ channelLabels[ticket.channel] ?? ticket.channel }}
                    </span>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 2xl:h-[calc(100vh-81px)] 2xl:min-h-0 2xl:grid-cols-[320px_minmax(0,1fr)_320px] 2xl:overflow-hidden">
            <aside :class="['border-b p-4 2xl:overflow-y-auto 2xl:border-b-0 2xl:border-r', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                <section>
                    <div class="flex items-center gap-3">
                        <div class="grid size-11 place-items-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                            {{ requesterInitials }}
                        </div>
                        <div class="min-w-0">
                            <p :class="['truncate text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ ticket.requester_name }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ ticket.requester_email }}</p>
                        </div>
                    </div>
                </section>

                <section :class="['mt-5 rounded-md border p-4', isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-slate-50']">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p :class="['text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('ticketDetail.service') }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ t('ticketDetail.serviceDescription') }}</p>
                        </div>
                        <span :class="['grid size-9 place-items-center rounded-full', isDark ? 'bg-slate-800 text-blue-300' : 'bg-blue-50 text-blue-600']">
                            <font-awesome-icon icon="fa-solid fa-ticket" />
                        </span>
                    </div>

                    <dl class="mt-5 space-y-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">{{ t('ticketDetail.assignment') }}</dt>
                            <dd :class="['truncate font-semibold', isDark ? 'text-slate-200' : 'text-slate-800']">{{ assigneeName }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">{{ t('ticketDetail.status') }}</dt>
                            <dd :class="['font-semibold', isDark ? 'text-slate-200' : 'text-slate-800']">{{ statusLabels[ticket.status] ?? ticket.status }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">{{ t('ticketDetail.priority') }}</dt>
                            <dd :class="['font-semibold', priorityStyles[ticket.priority]]">{{ priorityLabels[ticket.priority] ?? ticket.priority }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">{{ t('ticketDetail.channel') }}</dt>
                            <dd :class="['font-semibold', isDark ? 'text-slate-200' : 'text-slate-800']">{{ channelLabels[ticket.channel] ?? ticket.channel }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">{{ t('ticketDetail.agent') }}</dt>
                            <dd :class="['truncate font-semibold', isDark ? 'text-slate-200' : 'text-slate-800']">{{ currentUser.name }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>

            <section :class="['min-h-[620px] flex flex-col border-b 2xl:min-h-0 2xl:border-b-0', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                <div :class="['border-b px-4 py-3 sm:px-5', isDark ? 'border-slate-800' : 'border-slate-200']">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="grid size-10 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-semibold text-white">
                                {{ requesterInitials }}
                            </div>
                            <div class="min-w-0">
                                <p :class="['truncate text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">
                                    {{ ticket.requester_name }}
                                </p>
                                <p class="mt-1 truncate text-xs text-slate-500">
                                    {{ t('ticketDetail.conversationIn', { number: ticket.number }) }}
                                </p>
                            </div>
                        </div>
                        <span :class="['shrink-0 rounded-md border px-3 py-1.5 text-xs font-semibold', isDark ? 'border-slate-700 text-slate-300' : 'border-slate-200 text-slate-500']">
                            {{ t('ticketDetail.messages', { count: messages.length }) }}
                        </span>
                    </div>
                </div>

                <div ref="ticketChatScroll" class="scrollbar-hidden min-h-0 flex-1 overflow-auto px-4 py-5 sm:px-5">
                    <div v-if="messages.length" class="space-y-4">
                        <article
                            v-for="message in messages"
                            :key="message.id"
                            :class="['flex gap-3', isOwnTicketMessage(message) ? 'justify-end' : 'justify-start']"
                        >
                            <div
                                v-if="!isOwnTicketMessage(message)"
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-semibold text-white"
                            >
                                {{ requesterInitials }}
                            </div>

                            <div :class="['max-w-[calc(100vw-5rem)] sm:max-w-[780px]', isOwnTicketMessage(message) ? 'items-end' : 'items-start']">
                                <div :class="['mb-1 flex items-center gap-2 text-xs', isOwnTicketMessage(message) ? 'justify-end' : 'justify-start']">
                                    <span :class="['font-semibold', isDark ? 'text-slate-200' : 'text-slate-700']">{{ message.author_name ?? t('ticketDetail.user') }}</span>
                                    <span class="text-slate-400">{{ message.time }}</span>
                                </div>

                                <div
                                    :class="[
                                        isEmojiOnlyMessage(message.body) && !message.attachments?.length
                                            ? 'px-1 py-0 text-4xl leading-none'
                                            : 'rounded-md border px-4 py-3 text-sm leading-6 shadow-sm',
                                        isEmojiOnlyMessage(message.body) && !message.attachments?.length
                                            ? ''
                                            : isOwnTicketMessage(message)
                                                ? 'border-blue-600 bg-blue-600 text-white'
                                                : isDark ? 'border-slate-800 bg-slate-950 text-slate-200' : 'border-slate-200 bg-slate-50 text-slate-800',
                                    ]"
                                >
                                    <div :class="isEmojiOnlyMessage(message.body) && !message.attachments?.length ? 'drop-shadow-sm' : 'break-words'" v-html="renderChatBody(message.body)"></div>

                                    <div v-if="message.attachments?.length" class="mt-3 grid gap-2">
                                        <div
                                            v-for="attachment in message.attachments"
                                            :key="attachment.id"
                                            class="grid gap-2"
                                        >
                                            <a
                                                v-if="String(attachment.mime_type ?? '').startsWith('image/')"
                                                :href="attachment.url"
                                                target="_blank"
                                                :title="attachment.original_name"
                                                :class="[
                                                    'block max-w-[260px] overflow-hidden rounded-md border',
                                                    isOwnTicketMessage(message)
                                                        ? 'border-white/25 bg-white/10'
                                                        : isDark ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-white',
                                                ]"
                                            >
                                                <img :src="attachment.url" :alt="attachment.original_name" class="max-h-52 w-full object-cover">
                                            </a>
                                            <a
                                                :href="attachment.url"
                                                target="_blank"
                                                :class="[
                                                    'flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-xs font-semibold',
                                                    isOwnTicketMessage(message)
                                                        ? 'border-white/25 bg-white/10 text-white hover:bg-white/15'
                                                        : isDark ? 'border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                                                ]"
                                            >
                                                <span class="min-w-0 truncate">
                                                    <font-awesome-icon icon="fa-solid fa-paperclip" class="mr-2" />
                                                    {{ attachment.original_name }}
                                                </span>
                                                <span class="shrink-0 opacity-70">{{ attachment.human_size }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="isOwnTicketMessage(message)"
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-500 text-xs font-semibold text-white"
                            >
                                {{ agentInitials(message.author_name) }}
                            </div>
                        </article>
                    </div>

                    <div v-else class="grid h-full min-h-80 place-items-center text-center">
                        <div>
                            <div :class="['mx-auto grid size-12 place-items-center rounded-full', isDark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-500']">
                                <font-awesome-icon icon="fa-solid fa-comments" />
                            </div>
                            <p :class="['mt-4 text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">{{ t('ticketDetail.noMessages') }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ t('ticketDetail.writeFirst') }}</p>
                        </div>
                    </div>
                </div>

                <form :class="['border-t p-4 sm:p-5', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']" @submit.prevent="sendMessage">
                    <p v-if="chatError" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                        {{ chatError }}
                    </p>

                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <button type="button" class="grid size-9 place-items-center rounded-md border text-sm font-semibold hover:bg-slate-50" :class="isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-700'" :title="t('ticketDetail.bold')" @click="wrapTicketSelection('**')">
                            <font-awesome-icon icon="fa-solid fa-bold" />
                        </button>
                        <button type="button" class="grid size-9 place-items-center rounded-md border text-sm font-semibold hover:bg-slate-50" :class="isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-700'" :title="t('ticketDetail.italic')" @click="wrapTicketSelection('*')">
                            <font-awesome-icon icon="fa-solid fa-italic" />
                        </button>
                        <button type="button" class="grid size-9 place-items-center rounded-md border text-sm font-semibold hover:bg-slate-50" :class="isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-700'" :title="t('ticketDetail.underline')" @click="wrapTicketSelection('__')">
                            <font-awesome-icon icon="fa-solid fa-underline" />
                        </button>

                        <div class="relative">
                            <button type="button" class="grid size-9 place-items-center rounded-md border text-sm font-semibold hover:bg-slate-50" :class="isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-700'" :title="t('ticketDetail.emoji')" @click="showEmojiPicker = !showEmojiPicker">
                                <font-awesome-icon icon="fa-solid fa-face-smile" />
                            </button>
                            <div
                                v-if="showEmojiPicker"
                                :class="[
                                    'absolute bottom-11 left-0 z-20 overflow-hidden rounded-md border shadow-xl',
                                    isDark ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-white',
                                ]"
                            >
                                <EmojiPicker
                                    :native="true"
                                    :theme="isDark ? 'dark' : 'light'"
                                    :hide-search="false"
                                    :disable-skin-tones="false"
                                    :display-recent="true"
                                    :static-texts="{ placeholder: t('ticketDetail.emojiSearch'), skinTone: t('ticketDetail.skinTone') }"
                                    @select="appendEmoji"
                                />
                            </div>
                        </div>

                        <label class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border px-3 text-sm font-semibold hover:bg-slate-50" :class="isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-700'">
                            <font-awesome-icon icon="fa-solid fa-paperclip" />
                            {{ t('ticketDetail.files') }}
                            <input ref="ticketFileInput" class="sr-only" type="file" multiple @change="handleTicketFiles">
                        </label>

                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                            :class="isDark ? 'border-blue-500/30 bg-blue-500/10 text-blue-200 hover:bg-blue-500/20' : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100'"
                            :disabled="isChangingTone || !chatMessage.trim()"
                            :title="t('ticketDetail.changeTone')"
                            @click="improveTone"
                        >
                            <font-awesome-icon icon="fa-solid fa-wand-magic-sparkles" />
                            {{ isChangingTone ? 'AI...' : t('ticketDetail.aiTone') }}
                        </button>
                    </div>

                    <textarea
                        id="ticket-chat-body"
                        v-model="chatMessage"
                        rows="3"
                        :class="[
                            'w-full resize-none rounded-md border px-3 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                            isDark ? 'border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500' : 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-400',
                        ]"
                        :placeholder="t('ticketDetail.placeholder')"
                    ></textarea>

                    <div v-if="ticketFiles.length" class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="(file, index) in ticketFiles"
                            :key="`${file.name}-${index}`"
                            :class="['inline-flex max-w-full items-center gap-2 rounded-md border px-3 py-1.5 text-xs font-semibold', isDark ? 'border-slate-700 bg-slate-950 text-slate-200' : 'border-slate-200 bg-slate-50 text-slate-700']"
                        >
                            <span class="truncate">{{ file.name }}</span>
                            <button type="button" class="text-slate-400 hover:text-red-500" @click="removeTicketFile(index)">×</button>
                        </span>
                    </div>

                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-slate-500">{{ t('ticketDetail.formatting') }}</p>
                        <button
                            class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                            :disabled="isSendingMessage || !chatMessage.trim()"
                        >
                            {{ isSendingMessage ? t('ticketDetail.sending') : t('ticketDetail.send') }}
                        </button>
                    </div>
                </form>
            </section>

            <aside :class="['min-h-0 border-t 2xl:flex 2xl:flex-col 2xl:overflow-hidden 2xl:border-l 2xl:border-t-0', isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-slate-50']">
                <section
                    v-if="canGenerateAiSummary"
                    :class="['shrink-0 border-b p-4', isDark ? 'border-slate-800' : 'border-slate-200']"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p :class="['text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('ticketDetail.aiSummary') }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ t('ticketDetail.aiSummaryDescription') }}</p>
                        </div>
                        <button
                            type="button"
                            :class="[
                                'grid size-9 shrink-0 place-items-center rounded-full transition disabled:cursor-not-allowed disabled:opacity-60',
                                isDark ? 'bg-blue-500/10 text-blue-200 hover:bg-blue-500/20' : 'bg-blue-50 text-blue-600 hover:bg-blue-100',
                            ]"
                            :disabled="isGeneratingSummary"
                            :title="t('ticketDetail.generateAiSummary')"
                            @click="generateSummary"
                        >
                            <font-awesome-icon icon="fa-solid fa-wand-magic-sparkles" />
                        </button>
                    </div>

                    <div
                        v-if="aiSummary"
                        :class="[
                            'mt-4 max-h-56 overflow-y-auto whitespace-pre-line rounded-md border p-3 text-sm leading-6',
                            isDark ? 'border-slate-800 bg-slate-900 text-slate-200' : 'border-slate-200 bg-white text-slate-700',
                        ]"
                    >
                        {{ aiSummary }}
                    </div>
                    <p v-else class="mt-4 text-xs leading-5 text-slate-500">
                        {{ t('ticketDetail.aiSummaryEmpty') }}
                    </p>
                </section>

                <div :class="['shrink-0 border-b px-4 py-4', isDark ? 'border-slate-800' : 'border-slate-200']">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p :class="['text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('ticketDetail.mediaFiles') }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ t('ticketDetail.attachments', { count: ticketAttachments.length }) }}</p>
                        </div>
                        <span :class="['grid size-9 place-items-center rounded-full', isDark ? 'bg-slate-800 text-blue-300' : 'bg-blue-50 text-blue-600']">
                            <font-awesome-icon icon="fa-solid fa-paperclip" />
                        </span>
                    </div>
                </div>

                <div class="scrollbar-hidden max-h-72 overflow-y-auto p-4 2xl:min-h-0 2xl:flex-1 2xl:max-h-none">
                    <div v-if="ticketAttachments.length" class="space-y-5">
                        <div v-if="ticketAttachments.some((attachment) => attachment.isImage)">
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('ticketDetail.multimedia') }}</p>
                            <div class="grid grid-cols-3 gap-2">
                                <a
                                    v-for="attachment in ticketAttachments.filter((item) => item.isImage)"
                                    :key="`media-${attachment.id}`"
                                    :href="attachment.url"
                                    target="_blank"
                                    :title="attachment.original_name"
                                    :class="['block aspect-square overflow-hidden rounded-md border', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']"
                                >
                                    <img :src="attachment.url" :alt="attachment.original_name" class="size-full object-cover">
                                </a>
                            </div>
                        </div>

                        <div>
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('ticketDetail.files') }}</p>
                            <div class="space-y-2">
                                <a
                                    v-for="attachment in ticketAttachments"
                                    :key="`file-${attachment.id}`"
                                    :href="attachment.url"
                                    target="_blank"
                                    :class="[
                                        'flex items-center gap-3 rounded-md border px-3 py-2 text-left transition',
                                        isDark ? 'border-slate-800 bg-slate-900 text-slate-200 hover:bg-slate-800' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                                    ]"
                                >
                                    <span :class="['grid size-9 shrink-0 place-items-center rounded-md', attachment.isImage ? 'bg-blue-50 text-blue-600' : 'bg-slate-100 text-slate-500']">
                                        <font-awesome-icon :icon="attachment.isImage ? 'fa-solid fa-image' : 'fa-solid fa-file-lines'" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-xs font-semibold">{{ attachment.original_name }}</span>
                                        <span class="mt-0.5 block truncate text-[11px] text-slate-500">
                                            {{ attachment.human_size }} · {{ attachment.message.author_name ?? t('ticketDetail.user') }}
                                        </span>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid h-full min-h-48 place-items-center text-center">
                        <div>
                            <div :class="['mx-auto grid size-11 place-items-center rounded-full', isDark ? 'bg-slate-900 text-slate-400' : 'bg-white text-slate-400']">
                                <font-awesome-icon icon="fa-solid fa-folder-open" />
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-500">{{ t('ticketDetail.noFiles') }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ t('ticketDetail.noFilesDescription') }}</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <TicketFormModal
            :show="showEditTicketModal"
            mode="edit"
            :ticket="ticket"
            :agents="agents"
            :is-dark="isDark"
            @close="showEditTicketModal = false"
        />
    </main>
</template>

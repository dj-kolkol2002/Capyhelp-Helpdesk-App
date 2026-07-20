<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import 'vue3-emoji-picker/css';
import { supportedLocales } from '../../i18n/messages';

const EmojiPicker = defineAsyncComponent(() => import('vue3-emoji-picker'));

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const page = usePage();
const { t } = useI18n();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const ticket = ref({
    ...props.ticket,
    messages: props.ticket.messages ?? [],
});
const body = ref('');
const files = ref([]);
const fileInput = ref(null);
const isSending = ref(false);
const errorMessage = ref('');
const chatScroll = ref(null);
const showEmojiPicker = ref(false);
const currentLocale = computed(() => page.props.localization?.locale ?? 'pl');
const supportedLocaleOptions = computed(() => Object.entries(page.props.localization?.supported ?? supportedLocales));

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

const messages = computed(() => (ticket.value.messages ?? []).map((message) => ({
    ...message,
    attachments: message.attachments ?? [],
    time: message.created_at ? formatRelativeDate(message.created_at) : '',
})));

const ticketAttachments = computed(() => messages.value
    .flatMap((message) => (message.attachments ?? []).map((attachment) => ({
        ...attachment,
        message,
        isImage: String(attachment.mime_type ?? '').startsWith('image/'),
    })))
    .reverse());

const attachmentUrl = (attachment) => {
    const separator = String(attachment.url ?? '').includes('?') ? '&' : '?';

    return `${attachment.url}${separator}token=${encodeURIComponent(props.token)}`;
};

const requesterInitials = computed(() => ticket.value.initials ?? ticket.value.requester_name
    ?.split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase() ?? 'K');

const escapeHtml = (value = '') => value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const renderBody = (value = '') => escapeHtml(value)
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/__(.+?)__/g, '<u>$1</u>')
    .replace(/\n/g, '<br>');

const isEmojiOnlyMessage = (value = '') => {
    const trimmed = value.trim();

    return Boolean(trimmed)
        && /^[\p{Extended_Pictographic}\p{Emoji_Component}\u200d\ufe0f\s]+$/u.test(trimmed)
        && /\p{Extended_Pictographic}/u.test(trimmed);
};

const scrollToBottom = () => nextTick(() => {
    if (chatScroll.value) {
        chatScroll.value.scrollTop = chatScroll.value.scrollHeight;
    }
});

const handleFiles = (event) => {
    files.value = Array.from(event.target.files ?? []).slice(0, 5);
};

const removeFile = (index) => {
    files.value = files.value.filter((_, currentIndex) => currentIndex !== index);

    if (!files.value.length && fileInput.value) {
        fileInput.value.value = '';
    }
};

const wrapSelection = (prefix, suffix = prefix) => {
    const textarea = document.querySelector('#support-chat-body');
    const start = textarea?.selectionStart ?? body.value.length;
    const end = textarea?.selectionEnd ?? body.value.length;
    const selected = body.value.slice(start, end);

    body.value = `${body.value.slice(0, start)}${prefix}${selected || t('ticketDetail.sampleText')}${suffix}${body.value.slice(end)}`;

    nextTick(() => {
        textarea?.focus();
        textarea?.setSelectionRange(start + prefix.length, start + prefix.length + (selected || t('ticketDetail.sampleText')).length);
    });
};

const appendEmoji = (emoji) => {
    body.value = `${body.value}${emoji.i ?? ''}`;
    showEmojiPicker.value = false;
};

const appendMessage = (message) => {
    if (ticket.value.messages.some((item) => String(item.id) === String(message.id))) {
        return;
    }

    ticket.value.messages = [...ticket.value.messages, {
        ...message,
        attachments: message.attachments ?? [],
    }];
    scrollToBottom();
};

const sendMessage = async () => {
    const messageBody = body.value.trim();

    if (!messageBody || isSending.value) {
        errorMessage.value = !messageBody ? t('ticketDetail.emptyMessage') : '';
        return;
    }

    isSending.value = true;
    errorMessage.value = '';

    const payload = new FormData();
    payload.append('body', messageBody);
    files.value.forEach((file) => payload.append('attachments[]', file));

    try {
        const response = await fetch(`/support/tickets/${ticket.value.id}/messages?token=${encodeURIComponent(props.token)}`, {
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
            errorMessage.value = data.message ?? t('ticketDetail.sendError');
            return;
        }

        appendMessage(data.message);
        body.value = '';
        files.value = [];
        if (fileInput.value) {
            fileInput.value.value = '';
        }
        scrollToBottom();
    } catch (error) {
        errorMessage.value = t('ticketDetail.sendError');
    } finally {
        isSending.value = false;
    }
};

onMounted(() => {
    scrollToBottom();

    window.Echo
        ?.channel(`customer-tickets.${ticket.value.id}.${props.token}`)
        .listen('.ticket.message.created', (event) => {
            appendMessage(event.message);
        });
});

onBeforeUnmount(() => {
    window.Echo?.leave(`customer-tickets.${ticket.value.id}.${props.token}`);
});
</script>

<template>
    <Head :title="ticket.number" />

    <main class="min-h-screen bg-[#f3f6f9] text-slate-800">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-4 px-5 py-3">
                <a href="/support" class="flex items-center gap-3 text-sm font-semibold text-slate-800">
                    <img :src="'/images/capyhelp-smaller.png'" alt="CAPYHELP" class="h-11 w-11 rounded-md border border-slate-200 bg-white object-contain p-1">
                    <span>CAPYHELP</span>
                </a>
                <div class="flex items-center justify-end gap-3">
                    <div class="hidden max-w-sm flex-wrap justify-end gap-1.5 md:flex">
                        <a
                            v-for="[code, label] in supportedLocaleOptions"
                            :key="code"
                            :href="`/locale/${code}`"
                            :class="[
                                'rounded-md border px-2 py-1 text-xs font-semibold transition',
                                currentLocale === code
                                    ? 'border-blue-200 bg-blue-50 text-blue-700'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50',
                            ]"
                        >
                            {{ label }}
                        </a>
                    </div>
                    <span class="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ ticket.number }}</span>
                </div>
            </div>
        </header>

        <section class="mx-auto grid min-h-[calc(100vh-65px)] max-w-[1500px] grid-cols-1 gap-4 px-4 py-4 sm:gap-5 sm:px-5 sm:py-5 lg:grid-cols-[320px_minmax(0,1fr)] 2xl:grid-cols-[320px_minmax(0,1fr)_320px]">
            <aside class="space-y-4">
                <div class="flex flex-wrap gap-1.5 md:hidden">
                    <a
                        v-for="[code, label] in supportedLocaleOptions"
                        :key="code"
                        :href="`/locale/${code}`"
                        :class="[
                            'rounded-md border px-2 py-1 text-xs font-semibold transition',
                            currentLocale === code
                                ? 'border-blue-200 bg-blue-50 text-blue-700'
                                : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50',
                        ]"
                    >
                        {{ label }}
                    </a>
                </div>

                <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase text-blue-600">{{ t('supportShow.yourTicket') }}</p>
                    <h1 class="mt-2 line-clamp-2 text-xl font-semibold text-slate-950">{{ ticket.subject }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ t('supportShow.ticketCopy') }}</p>
                </div>

                <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <dl class="space-y-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">{{ t('ticketDetail.status') }}</dt>
                            <dd class="font-semibold text-slate-900">{{ statusLabels[ticket.status] ?? ticket.status }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">{{ t('ticketDetail.priority') }}</dt>
                            <dd class="font-semibold text-slate-900">{{ priorityLabels[ticket.priority] ?? ticket.priority }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">{{ t('ticketDetail.agent') }}</dt>
                            <dd class="truncate font-semibold text-slate-900">{{ ticket.assignee_user?.name ?? t('ticketDetail.unassigned') }}</dd>
                        </div>
                    </dl>
                </div>
            </aside>

            <section class="flex min-h-[620px] flex-col overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm lg:min-h-[calc(100vh-105px)]">
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="grid size-10 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-semibold text-white">
                                {{ requesterInitials }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-950">{{ ticket.requester_name }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ ticket.requester_email }}</p>
                            </div>
                        </div>
                        <span class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500">{{ t('ticketDetail.messages', { count: messages.length }) }}</span>
                    </div>
                </div>

                <div ref="chatScroll" class="scrollbar-hidden min-h-0 flex-1 overflow-auto bg-white px-4 py-5 sm:px-5">
                    <div class="space-y-4">
                        <article v-for="message in messages" :key="message.id" :class="['flex gap-3', message.author_type === 'requester' ? 'justify-end' : 'justify-start']">
                            <div v-if="message.author_type !== 'requester'" class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-500 text-xs font-semibold text-white">
                                A
                            </div>
                            <div class="max-w-[calc(100vw-5rem)] sm:max-w-[780px]">
                                <div :class="['mb-1 flex items-center gap-2 text-xs', message.author_type === 'requester' ? 'justify-end' : 'justify-start']">
                                    <span class="font-semibold text-slate-700">{{ message.author_name }}</span>
                                    <span class="text-slate-400">{{ message.time }}</span>
                                </div>
                                <div
                                    :class="[
                                        isEmojiOnlyMessage(message.body) && !message.attachments?.length
                                            ? 'px-1 py-0 text-4xl leading-none'
                                            : 'rounded-md border px-4 py-3 text-sm leading-6 shadow-sm',
                                        isEmojiOnlyMessage(message.body) && !message.attachments?.length
                                            ? ''
                                            : message.author_type === 'requester' ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-slate-50 text-slate-800',
                                    ]"
                                >
                                    <div :class="isEmojiOnlyMessage(message.body) && !message.attachments?.length ? 'drop-shadow-sm' : 'break-words'" v-html="renderBody(message.body)"></div>
                                    <div v-if="message.attachments?.length" class="mt-3 grid gap-2">
                                        <div v-for="attachment in message.attachments" :key="attachment.id" class="grid gap-2">
                                            <a
                                                v-if="String(attachment.mime_type ?? '').startsWith('image/')"
                                                :href="attachmentUrl(attachment)"
                                                target="_blank"
                                                :title="attachment.original_name"
                                                :class="['block max-w-[260px] overflow-hidden rounded-md border', message.author_type === 'requester' ? 'border-white/25 bg-white/10' : 'border-slate-200 bg-white']"
                                            >
                                                <img :src="attachmentUrl(attachment)" :alt="attachment.original_name" class="max-h-52 w-full object-cover">
                                            </a>
                                            <a :href="attachmentUrl(attachment)" target="_blank" :class="['flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-xs font-semibold', message.author_type === 'requester' ? 'border-white/25 bg-white/10 text-white hover:bg-white/15' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50']">
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
                            <div v-if="message.author_type === 'requester'" class="grid size-9 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-semibold text-white">
                                {{ requesterInitials }}
                            </div>
                        </article>
                    </div>
                </div>

                <form class="border-t border-slate-200 bg-white p-4 sm:p-5" @submit.prevent="sendMessage">
                    <p v-if="errorMessage" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">{{ errorMessage }}</p>

                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <button type="button" class="grid size-9 place-items-center rounded-md border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50" :title="t('ticketDetail.bold')" @click="wrapSelection('**')">
                            <font-awesome-icon icon="fa-solid fa-bold" />
                        </button>
                        <button type="button" class="grid size-9 place-items-center rounded-md border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50" :title="t('ticketDetail.italic')" @click="wrapSelection('*')">
                            <font-awesome-icon icon="fa-solid fa-italic" />
                        </button>
                        <button type="button" class="grid size-9 place-items-center rounded-md border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50" :title="t('ticketDetail.underline')" @click="wrapSelection('__')">
                            <font-awesome-icon icon="fa-solid fa-underline" />
                        </button>

                        <div class="relative">
                            <button type="button" class="grid size-9 place-items-center rounded-md border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50" :title="t('ticketDetail.emoji')" @click="showEmojiPicker = !showEmojiPicker">
                                <font-awesome-icon icon="fa-solid fa-face-smile" />
                            </button>
                            <div v-if="showEmojiPicker" class="absolute bottom-11 left-0 z-20 overflow-hidden rounded-md border border-slate-200 bg-white shadow-xl">
                                <EmojiPicker
                                    :native="true"
                                    theme="light"
                                    :hide-search="false"
                                    :disable-skin-tones="false"
                                    :display-recent="true"
                                    :static-texts="{ placeholder: t('ticketDetail.emojiSearch'), skinTone: t('ticketDetail.skinTone') }"
                                    @select="appendEmoji"
                                />
                            </div>
                        </div>

                        <label class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border border-slate-200 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <font-awesome-icon icon="fa-solid fa-paperclip" />
                            {{ t('ticketDetail.files') }}
                            <input ref="fileInput" class="sr-only" type="file" multiple @change="handleFiles">
                        </label>
                    </div>

                    <textarea id="support-chat-body" v-model="body" rows="3" class="w-full resize-none rounded-md border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100" :placeholder="t('supportShow.placeholder')"></textarea>

                    <div v-if="files.length" class="mt-3 flex flex-wrap gap-2">
                        <span v-for="(file, index) in files" :key="`${file.name}-${index}`" class="inline-flex max-w-full items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
                            <span class="truncate">{{ file.name }}</span>
                            <button type="button" class="text-slate-400 hover:text-red-500" :aria-label="t('supportCreate.removeFile')" @click="removeFile(index)">×</button>
                        </span>
                    </div>

                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-slate-500">{{ t('ticketDetail.formatting') }}</p>
                        <button class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto" :disabled="isSending || !body.trim()">
                            {{ isSending ? t('ticketDetail.sending') : t('ticketDetail.send') }}
                        </button>
                    </div>
                </form>
            </section>

            <aside class="min-h-0 overflow-hidden rounded-md border border-slate-200 bg-slate-50 shadow-sm lg:col-span-2 2xl:col-span-1">
                <div class="border-b border-slate-200 bg-white px-4 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ t('ticketDetail.mediaFiles') }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ t('ticketDetail.attachments', { count: ticketAttachments.length }) }}</p>
                        </div>
                        <span class="grid size-9 place-items-center rounded-full bg-blue-50 text-blue-600">
                            <font-awesome-icon icon="fa-solid fa-paperclip" />
                        </span>
                    </div>
                </div>

                <div class="scrollbar-hidden max-h-72 overflow-y-auto p-4 2xl:h-[calc(100%-73px)] 2xl:max-h-none">
                    <div v-if="ticketAttachments.length" class="space-y-5">
                        <div v-if="ticketAttachments.some((attachment) => attachment.isImage)">
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('ticketDetail.multimedia') }}</p>
                            <div class="grid grid-cols-3 gap-2">
                                <a
                                    v-for="attachment in ticketAttachments.filter((item) => item.isImage)"
                                    :key="`media-${attachment.id}`"
                                    :href="attachmentUrl(attachment)"
                                    target="_blank"
                                    :title="attachment.original_name"
                                    class="block aspect-square overflow-hidden rounded-md border border-slate-200 bg-white"
                                >
                                    <img :src="attachmentUrl(attachment)" :alt="attachment.original_name" class="size-full object-cover">
                                </a>
                            </div>
                        </div>

                        <div>
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('ticketDetail.files') }}</p>
                            <div class="space-y-2">
                                <a
                                    v-for="attachment in ticketAttachments"
                                    :key="`file-${attachment.id}`"
                                    :href="attachmentUrl(attachment)"
                                    target="_blank"
                                    class="flex items-center gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-slate-700 transition hover:bg-slate-50"
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
                            <div class="mx-auto grid size-11 place-items-center rounded-full bg-white text-slate-400">
                                <font-awesome-icon icon="fa-solid fa-folder-open" />
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-500">{{ t('ticketDetail.noFiles') }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ t('ticketDetail.noFilesDescription') }}</p>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    </main>
</template>

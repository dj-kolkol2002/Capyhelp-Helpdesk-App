<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { supportedLocales } from '../../i18n/messages';

const page = usePage();
const { t } = useI18n();
const fileInput = ref(null);
const currentLocale = computed(() => page.props.localization?.locale ?? 'pl');
const supportedLocaleOptions = computed(() => Object.entries(page.props.localization?.supported ?? supportedLocales));

const form = useForm({
    requester_name: '',
    requester_email: '',
    subject: '',
    priority: 'medium',
    body: '',
    attachments: [],
});

const priorities = computed(() => [
    { value: 'low', label: t('supportCreate.priorities.low') },
    { value: 'medium', label: t('supportCreate.priorities.medium') },
    { value: 'high', label: t('supportCreate.priorities.high') },
    { value: 'urgent', label: t('supportCreate.priorities.urgent') },
]);

const handleFiles = (event) => {
    form.attachments = Array.from(event.target.files ?? []).slice(0, 5);
};

const removeFile = (index) => {
    form.attachments = form.attachments.filter((_, currentIndex) => currentIndex !== index);

    if (!form.attachments.length && fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.post('/support/tickets', {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('supportCreate.title')" />

    <main class="min-h-screen bg-[#f3f6f9] text-slate-800">
        <section class="mx-auto grid min-h-screen w-full max-w-6xl items-start gap-8 px-4 py-6 sm:px-5 sm:py-8 lg:grid-cols-[minmax(0,1fr)_480px] lg:items-center lg:px-8">
            <div class="space-y-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <a href="/" class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700">
                        <img :src="'/images/capyhelp-smaller.png'" alt="CAPYHELP" class="h-14 w-14 rounded-md border border-slate-200 bg-white object-contain p-1">
                        <span>CAPYHELP</span>
                    </a>

                    <div class="flex max-w-sm flex-wrap gap-1.5">
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
                </div>

                <div>
                    <p class="text-sm font-semibold uppercase text-blue-600">{{ t('supportCreate.helpCenter') }}</p>
                    <h1 class="mt-3 max-w-xl text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
                        {{ t('supportCreate.heroTitle') }}
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-7 text-slate-600">
                        {{ t('supportCreate.heroCopy') }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-md border border-slate-200 bg-white p-4">
                        <p class="text-sm font-semibold text-slate-950">{{ t('supportCreate.historyTitle') }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ t('supportCreate.historyCopy') }}</p>
                    </div>
                    <div class="rounded-md border border-slate-200 bg-white p-4">
                        <p class="text-sm font-semibold text-slate-950">{{ t('supportCreate.attachmentsTitle') }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ t('supportCreate.attachmentsCopy') }}</p>
                    </div>
                    <div class="rounded-md border border-slate-200 bg-white p-4">
                        <p class="text-sm font-semibold text-slate-950">{{ t('supportCreate.notificationsTitle') }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ t('supportCreate.notificationsCopy') }}</p>
                    </div>
                </div>
            </div>

            <form class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-6" @submit.prevent="submit">
                <div>
                    <p class="text-sm font-semibold text-blue-600">{{ t('supportCreate.newTicket') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('supportCreate.formHeading') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ t('supportCreate.formSubheading') }}</p>
                </div>

                <div v-if="form.hasErrors" class="mt-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    {{ t('supportCreate.formError') }}
                </div>

                <div class="mt-6 grid gap-4">
                    <div>
                        <label for="requester_name" class="text-sm font-semibold text-slate-700">{{ t('supportCreate.name') }}</label>
                        <input id="requester_name" v-model="form.requester_name" class="mt-2 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" :class="{ 'border-red-400': form.errors.requester_name }" required>
                        <p v-if="form.errors.requester_name" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.requester_name }}</p>
                    </div>

                    <div>
                        <label for="requester_email" class="text-sm font-semibold text-slate-700">{{ t('supportCreate.email') }}</label>
                        <input id="requester_email" v-model="form.requester_email" type="email" class="mt-2 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" :class="{ 'border-red-400': form.errors.requester_email }" required>
                        <p v-if="form.errors.requester_email" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.requester_email }}</p>
                    </div>

                    <div>
                        <label for="subject" class="text-sm font-semibold text-slate-700">{{ t('supportCreate.subject') }}</label>
                        <input id="subject" v-model="form.subject" class="mt-2 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" :class="{ 'border-red-400': form.errors.subject }" required>
                        <p v-if="form.errors.subject" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.subject }}</p>
                    </div>

                    <div>
                        <label for="priority" class="text-sm font-semibold text-slate-700">{{ t('supportCreate.priority') }}</label>
                        <select id="priority" v-model="form.priority" class="mt-2 h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option v-for="priority in priorities" :key="priority.value" :value="priority.value">{{ priority.label }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="body" class="text-sm font-semibold text-slate-700">{{ t('supportCreate.body') }}</label>
                        <textarea id="body" v-model="form.body" rows="6" class="mt-2 w-full resize-none rounded-md border border-slate-300 px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" :class="{ 'border-red-400': form.errors.body }" required></textarea>
                        <p v-if="form.errors.body" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.body }}</p>
                    </div>

                    <div>
                        <label class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <font-awesome-icon icon="fa-solid fa-paperclip" />
                            {{ t('supportCreate.addFiles') }}
                            <input ref="fileInput" class="sr-only" type="file" multiple @change="handleFiles">
                        </label>

                        <div v-if="form.attachments.length" class="mt-3 flex flex-wrap gap-2">
                            <span v-for="(file, index) in form.attachments" :key="`${file.name}-${index}`" class="inline-flex max-w-full items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                <span class="truncate">{{ file.name }}</span>
                                <button type="button" class="text-slate-400 hover:text-red-500" :aria-label="t('supportCreate.removeFile')" @click="removeFile(index)">×</button>
                            </span>
                        </div>
                    </div>
                </div>

                <button class="mt-6 h-11 w-full rounded-md bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70" :disabled="form.processing">
                    {{ form.processing ? t('supportCreate.submitting') : t('supportCreate.submit') }}
                </button>
            </form>
        </section>
    </main>
</template>

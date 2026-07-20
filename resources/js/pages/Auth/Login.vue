<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { supportedLocales } from '../../i18n/messages';

const { t } = useI18n();
const page = usePage();
const currentLocale = computed(() => page.props.localization?.locale ?? 'pl');
const supportedLocaleOptions = computed(() => Object.entries(page.props.localization?.supported ?? supportedLocales));
const socialAuth = computed(() => page.props.socialAuth ?? {});
const hasSocialAuth = computed(() => Boolean(socialAuth.value.google || socialAuth.value.facebook));
const flashStatus = computed(() => page.props.flash?.status ?? '');

const form = useForm({
    email: 'test@example.com',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="t('login.title')" />

    <main class="min-h-screen bg-[#f3f6f9] font-sans text-slate-800">
        <section class="mx-auto flex min-h-screen w-full max-w-xl items-center px-5 py-8">
            <div class="w-full rounded-md border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex justify-center">
                    <img :src="'/images/capyhelp-smaller.png'" alt="CAPYHELP Helpdesk App" class="block w-full max-w-[220px] object-contain">
                </div>

                <div class="mt-7 flex justify-center">
                    <div class="flex max-w-sm flex-wrap justify-center gap-1.5">
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

                <div class="mt-7 text-center">
                    <p class="text-sm font-semibold text-blue-600">{{ t('login.welcome') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('login.heading') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ t('login.subheading') }}</p>
                </div>

                <div
                    v-if="form.hasErrors || flashStatus"
                    class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
                >
                    {{ form.errors.email || form.errors.password || flashStatus || t('login.formError') }}
                </div>

                <div v-if="hasSocialAuth" class="mt-7 grid gap-3 sm:grid-cols-2">
                    <a
                        v-if="socialAuth.google"
                        href="/auth/google/redirect"
                        class="flex h-11 items-center justify-center gap-3 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        <span class="grid size-5 place-items-center rounded-full bg-red-500 text-xs font-bold text-white">G</span>
                        Google
                    </a>
                    <a
                        v-if="socialAuth.facebook"
                        href="/auth/facebook/redirect"
                        class="flex h-11 items-center justify-center gap-3 rounded-md bg-[#1877f2] px-4 text-sm font-semibold text-white transition hover:bg-[#166fe5]"
                    >
                        <span class="grid size-5 place-items-center rounded-full bg-white text-xs font-bold text-[#1877f2]">f</span>
                        Facebook
                    </a>
                </div>

                <div v-if="hasSocialAuth" class="mt-7 flex items-center gap-3">
                    <div class="h-px flex-1 bg-slate-200"></div>
                    <span class="text-xs font-semibold uppercase text-slate-400">{{ t('login.or') }}</span>
                    <div class="h-px flex-1 bg-slate-200"></div>
                </div>

                <form class="mt-7 space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="text-sm font-semibold text-slate-700" for="email">{{ t('login.email') }}</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            autofocus
                            class="mt-2 h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            :class="{ 'border-red-400': form.errors.email }"
                        >
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-700" for="password">{{ t('login.password') }}</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="mt-2 h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            :class="{ 'border-red-400': form.errors.password }"
                        >
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label class="flex items-center gap-3 text-sm font-medium text-slate-600">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="size-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            >
                            {{ t('login.remember') }}
                        </label>
                    </div>

                    <button
                        class="h-11 w-full rounded-md bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? t('login.submitting') : t('login.submit') }}
                    </button>
                </form>

            </div>
        </section>
    </main>
</template>

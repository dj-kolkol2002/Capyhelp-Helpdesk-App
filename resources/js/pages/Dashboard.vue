<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import DashboardView from '../App.vue';

defineProps({
    initialView: {
        type: String,
        default: 'Tickets',
    },
    tickets: {
        type: Array,
        default: () => [],
    },
    agents: {
        type: Array,
        default: () => [],
    },
    appNotifications: {
        type: Array,
        default: () => [],
    },
    unreadNotificationsCount: {
        type: Number,
        default: 0,
    },
    teamChatMessages: {
        type: Array,
        default: () => [],
    },
    teamChatUsers: {
        type: Array,
        default: () => [],
    },
    knowledgeArticles: {
        type: Array,
        default: () => [],
    },
});

onMounted(() => {
    document.documentElement.classList.add('overflow-hidden');
    document.body.classList.add('overflow-hidden');
});

onBeforeUnmount(() => {
    document.documentElement.classList.remove('overflow-hidden');
    document.body.classList.remove('overflow-hidden');
});
</script>

<template>
    <DashboardView
        :initial-view="initialView"
        :tickets="tickets"
        :agents="agents"
        :app-notifications="appNotifications"
        :unread-notifications-count="unreadNotificationsCount"
        :team-chat-messages="teamChatMessages"
        :team-chat-users="teamChatUsers"
        :knowledge-articles="knowledgeArticles"
    />
</template>

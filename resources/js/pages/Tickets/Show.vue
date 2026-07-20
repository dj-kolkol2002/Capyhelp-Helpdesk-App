<script setup>
import TicketView from '../../Ticket.vue';

defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    agents: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <TicketView :ticket="ticket" :agents="agents" />
</template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import 'vue3-emoji-picker/css';
import TicketFormModal from './components/TicketFormModal.vue';
import { supportedLocales } from './i18n/messages';

const EmojiPicker = defineAsyncComponent(() => import('vue3-emoji-picker'));

const props = defineProps({
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

const page = usePage();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const currentUser = computed(() => page.props.auth?.user ?? {
    name: 'Agent',
    email: 'agent@example.com',
    role: 'agent',
    avatar_url: null,
    locale: 'pl',
});
const { t, locale } = useI18n();
const supportedLocaleOptions = computed(() => Object.entries(page.props.localization?.supported ?? supportedLocales));
const savedStatus = computed(() => page.props.flash?.status ?? page.props.flash?.success ?? '');
const accountErrors = computed(() => page.props.errors ?? {});
const databaseTickets = computed(() => props.tickets ?? []);
const selectedLocale = ref(currentUser.value.locale ?? page.props.localization?.locale ?? 'pl');
const localeSaveStatus = ref('');
const avatarFileName = ref('');
const activeView = ref(props.initialView ?? 'Tickets');
const theme = ref(localStorage.getItem('helpdesk-theme') === 'dark' ? 'dark' : 'light');
const isNavbarCollapsed = ref(localStorage.getItem('helpdesk-navbar-collapsed') === 'true');

const isDark = computed(() => theme.value === 'dark');
const userInitials = computed(() => currentUser.value.name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase());
const currentUserAvatarUrl = computed(() => currentUser.value.avatar_url ?? null);

const notificationDefaults = [
    { key: 'newTicket', labelKey: 'notifications.newTicket.label', descriptionKey: 'notifications.newTicket.description', enabled: true },
    { key: 'assignedTicket', labelKey: 'notifications.assignedTicket.label', descriptionKey: 'notifications.assignedTicket.description', enabled: true },
    { key: 'ticketMessage', labelKey: 'notifications.ticketMessage.label', descriptionKey: 'notifications.ticketMessage.description', enabled: true },
    { key: 'ticketUpdated', labelKey: 'notifications.ticketUpdated.label', descriptionKey: 'notifications.ticketUpdated.description', enabled: true },
    { key: 'teamChat', labelKey: 'notifications.teamChat.label', descriptionKey: 'notifications.teamChat.description', enabled: true },
    { key: 'accountCreated', labelKey: 'notifications.accountCreated.label', descriptionKey: 'notifications.accountCreated.description', enabled: true },
    { key: 'accountUpdated', labelKey: 'notifications.accountUpdated.label', descriptionKey: 'notifications.accountUpdated.description', enabled: true },
    { key: 'slaWarning', labelKey: 'notifications.slaWarning.label', descriptionKey: 'notifications.slaWarning.description', enabled: true },
    { key: 'weeklyReport', labelKey: 'notifications.weeklyReport.label', descriptionKey: 'notifications.weeklyReport.description', enabled: false },
];

const storedNotifications = currentUser.value.notification_preferences ?? {};
const notifications = ref(notificationDefaults.map((item) => ({
    ...item,
    enabled: storedNotifications[item.key] ?? item.enabled,
})));
const notificationSaveStatus = ref('');

const saveNotificationPreferences = async () => {
    notificationSaveStatus.value = 'saving';

    try {
        const response = await fetch('/settings/notifications', {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                notifications: Object.fromEntries(notifications.value.map((item) => [item.key, item.enabled])),
            }),
        });

        if (!response.ok) {
            throw new Error('Notification preferences request failed.');
        }

        notificationSaveStatus.value = 'saved';
        window.setTimeout(() => {
            if (notificationSaveStatus.value === 'saved') {
                notificationSaveStatus.value = '';
            }
        }, 1800);
    } catch (error) {
        notificationSaveStatus.value = 'error';
    }
};

const saveLocalePreference = async () => {
    locale.value = selectedLocale.value;
    document.documentElement.lang = selectedLocale.value;
    localeSaveStatus.value = 'saving';

    try {
        const response = await fetch('/settings/locale', {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ locale: selectedLocale.value }),
        });

        if (!response.ok) {
            throw new Error('Locale request failed.');
        }

        const data = await response.json();
        selectedLocale.value = data.locale;
        locale.value = data.locale;
        document.documentElement.lang = data.locale;
        localeSaveStatus.value = 'saved';

        window.setTimeout(() => {
            if (localeSaveStatus.value === 'saved') {
                localeSaveStatus.value = '';
            }
        }, 1800);
    } catch (error) {
        localeSaveStatus.value = 'error';
    }
};

const handleAvatarFileChange = (event) => {
    avatarFileName.value = event.target.files?.[0]?.name ?? '';
};

const navigation = [
    { labelKey: 'navigation.tickets', view: 'Tickets', href: '/tickets', icon: 'fa-solid fa-ticket' },
    { labelKey: 'navigation.chat', view: 'TeamChat', href: '/team-chat', icon: 'fa-solid fa-comments' },
    { labelKey: 'navigation.knowledgeBase', view: 'KnowledgeBase', href: '/knowledge-base', icon: 'fa-solid fa-book-open' },
    { labelKey: 'navigation.agents', view: 'Agents', href: '/agents', icon: 'fa-solid fa-users' },
    { labelKey: 'navigation.reports', view: 'Reports', href: '/reports', icon: 'fa-solid fa-chart-bar' },
    { labelKey: 'navigation.settings', view: 'Settings', href: '/settings', icon: 'fa-solid fa-cog' },
];

const viewTitles = {
    Tickets: { eyebrowKey: 'views.tickets.eyebrow', titleKey: 'views.tickets.title' },
    TeamChat: { eyebrowKey: 'views.teamChat.eyebrow', titleKey: 'views.teamChat.title' },
    KnowledgeBase: { eyebrowKey: 'views.knowledgeBase.eyebrow', titleKey: 'views.knowledgeBase.title' },
    Agents: { eyebrowKey: 'views.agents.eyebrow', titleKey: 'views.agents.title' },
    Reports: { eyebrowKey: 'views.reports.eyebrow', titleKey: 'views.reports.title' },
    Settings: { eyebrowKey: 'views.settings.eyebrow', titleKey: 'views.settings.title' },
};

const activeTitle = computed(() => viewTitles[activeView.value] ?? viewTitles.Tickets);

const navigateTo = (item) => {
    if (activeView.value === item.view && window.location.pathname === item.href) {
        return;
    }

    activeView.value = item.view;
    router.visit(item.href, {
        preserveScroll: false,
    });
};

const views = [
    { key: 'all', labelKey: 'tickets.all', descriptionKey: 'tickets.allDescription' },
    { key: 'to_handle', labelKey: 'tickets.toHandle', descriptionKey: 'tickets.toHandleDescription' },
    { key: 'my_open', labelKey: 'tickets.myOpen', descriptionKey: 'tickets.myOpenDescription' },
];
const statuses = ['open', 'in_progress', 'resolved', 'closed'];
const priorities = ['urgent', 'high', 'medium', 'low'];
const channels = ['email', 'phone', 'chat', 'in-person'];
const unassignedAssigneeValue = 'unassigned';
const avatarColors = ['bg-indigo-500', 'bg-rose-500', 'bg-teal-500', 'bg-amber-500', 'bg-violet-500', 'bg-lime-600'];

const fallbackTickets = [
    {
        id: 'HD-2048',
        requester: 'Viola Holmes',
        email: 'viola@acme.com',
        initials: 'VH',
        subject: 'Invoice 25032019/B/567 requires correction',
        assignee: 'Peter',
        status: 'resolved',
        priority: 'medium',
        channel: 'email',
        updated: '4 seconds ago',
        color: 'bg-indigo-500',
    },
    {
        id: 'HD-2047',
        requester: 'Earl McDonald',
        email: 'earlmc@yahoo.com',
        initials: 'EM',
        subject: 'Forwarding configuration for sales mailbox',
        assignee: 'Patricia',
        status: 'open',
        priority: 'high',
        channel: 'chat',
        updated: '45 seconds ago',
        color: 'bg-rose-500',
    },
    {
        id: 'HD-2046',
        requester: 'Marian Logan',
        email: 'mlogan@logina.biz',
        initials: 'ML',
        subject: 'LiveChat - pre-chat survey is missing',
        assignee: 'Aleksander',
        status: 'in_progress',
        priority: 'medium',
        channel: 'chat',
        updated: '50 seconds ago',
        color: 'bg-teal-500',
    },
    {
        id: 'HD-2045',
        requester: 'Douglas Olson',
        email: 'douglas@olson.tv',
        initials: 'DO',
        subject: 'Automatic responder sends outdated signature',
        assignee: 'unassigned',
        status: 'open',
        priority: 'low',
        channel: 'email',
        updated: '1 minute ago',
        color: 'bg-stone-500',
    },
    {
        id: 'HD-2044',
        requester: 'Estelle Nguyen',
        email: 'estelle@gmail.com',
        initials: 'EN',
        subject: 'Case #678234 - missing attachment',
        assignee: 'unassigned',
        status: 'closed',
        priority: 'medium',
        channel: 'email',
        updated: '1 minute ago',
        color: 'bg-amber-500',
    },
    {
        id: 'HD-2043',
        requester: 'Bobby Huff',
        email: 'bobbyhuff@yahoo.com',
        initials: 'BH',
        subject: 'Next license renewal and billing details',
        assignee: 'Agata',
        status: 'in_progress',
        priority: 'high',
        channel: 'email',
        updated: '2 minutes ago',
        color: 'bg-violet-500',
    },
    {
        id: 'HD-2042',
        requester: 'Julian Tran',
        email: 'juliantran@hotmail.com',
        initials: 'JT',
        subject: 'Settings loading after password reset',
        assignee: 'unassigned',
        status: 'resolved',
        priority: 'low',
        channel: 'email',
        updated: '3 minutes ago',
        color: 'bg-lime-600',
    },
    {
        id: 'HD-2041',
        requester: 'Jerry Castro',
        email: 'jcastro@yahoo.com',
        initials: 'JC',
        subject: 'Past due account, 10160 - payment confirmation',
        assignee: 'Jan',
        status: 'open',
        priority: 'urgent',
        channel: 'email',
        updated: '4 minutes ago',
        color: 'bg-fuchsia-500',
    },
];

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

const requesterInitials = (name = '') => name
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

const assigneeValue = (ticket) => ticket.assignee_user?.name
    ?? ticket.assigneeName
    ?? (ticket.assignee && !Number.isInteger(Number(ticket.assignee)) ? ticket.assignee : null);

const normalizeTicket = (ticket, index) => ({
    id: ticket.number ?? ticket.id,
    databaseId: ticket.databaseId ?? ticket.id,
    requester: ticket.requester_name ?? ticket.requester,
    email: ticket.requester_email ?? ticket.email,
    initials: ticket.initials ?? requesterInitials(ticket.requester_name ?? ticket.requester),
    subject: ticket.subject,
    assignee: ticket.assignee,
    assigneeName: assigneeValue(ticket) ?? unassignedAssigneeValue,
    status: ticket.status,
    priority: ticket.priority,
    channel: ticket.channel,
    updatedAt: ticket.updated_at ?? ticket.updatedAt ?? null,
    updated: ticket.updated_at ? formatRelativeDate(ticket.updated_at) : ticket.updated,
    color: avatarColors[index % avatarColors.length],
    messages: (ticket.messages ?? []).map((message) => ({
        id: message.id,
        authorName: message.author_name,
        authorEmail: message.author_email,
        authorType: message.author_type,
        body: message.body,
        createdAt: message.created_at,
        time: message.created_at ? formatRelativeDate(message.created_at) : '',
    })),
});

const tickets = ref((databaseTickets.value.length ? databaseTickets.value : fallbackTickets).map(normalizeTicket));
const ticketUrl = (ticket) => ticket.databaseId ? `/tickets/${ticket.databaseId}` : '#';
const notificationItems = ref(props.appNotifications.map((notification) => ({
    ...notification,
    isRead: Boolean(notification.read_at),
})));
const showNotificationsPanel = ref(false);
const unreadAppNotificationsCount = ref(props.unreadNotificationsCount);
const notificationUrl = (notification) => notification.ticket_id ? `/tickets/${notification.ticket_id}` : '#';
const notificationIconClasses = {
    new_ticket: 'bg-blue-500 text-white',
    ticket_assigned: 'bg-emerald-500 text-white',
    requester_message: 'bg-amber-400 text-slate-950',
    ticket_updated: 'bg-violet-500 text-white',
    team_chat_message: 'bg-sky-500 text-white',
    account_created: 'bg-emerald-500 text-white',
    account_updated: 'bg-indigo-500 text-white',
    sla_warning: 'bg-red-500 text-white',
    weekly_report: 'bg-blue-500 text-white',
};

const notificationTicketNumber = (notification) => notification.ticket?.number
    ?? notification.ticket_number
    ?? '';

const notificationText = (notification, field) => {
    const key = `notificationsPanel.types.${notification.type}.${field}`;
    const translated = t(key, {
        ticket: notificationTicketNumber(notification),
        subject: notification.ticket?.subject ?? '',
    });

    return translated === key ? notification[field] : translated;
};

const notificationTitle = (notification) => notificationText(notification, 'title');
const notificationBody = (notification) => notificationText(notification, 'body');

const upsertTicket = (rawTicket) => {
    if (!rawTicket) {
        return;
    }

    const existingIndex = tickets.value.findIndex((ticket) => String(ticket.databaseId) === String(rawTicket.id));
    const normalizedTicket = normalizeTicket(rawTicket, existingIndex >= 0 ? existingIndex : tickets.value.length);

    if (existingIndex >= 0) {
        normalizedTicket.color = tickets.value[existingIndex].color;
        tickets.value.splice(existingIndex, 1, normalizedTicket);
        return;
    }

    tickets.value.unshift(normalizedTicket);
};

const appendNotification = (notification) => {
    if (!notification || notificationItems.value.some((item) => String(item.id) === String(notification.id))) {
        return;
    }

    notificationItems.value.unshift({
        ...notification,
        isRead: Boolean(notification.read_at),
    });

    if (!notification.read_at) {
        unreadAppNotificationsCount.value += 1;
    }

    upsertTicket(notification.ticket);
};

const markNotificationAsRead = async (notification) => {
    if (notification.isRead) {
        return;
    }

    const response = await fetch(`/notifications/${notification.id}/read`, {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (response.ok) {
        notification.isRead = true;
        notification.read_at = new Date().toISOString();
        unreadAppNotificationsCount.value = Math.max(0, unreadAppNotificationsCount.value - 1);
    }
};

const markAllNotificationsAsRead = async () => {
    const response = await fetch('/notifications/read-all', {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (response.ok) {
        notificationItems.value = notificationItems.value.map((notification) => ({
            ...notification,
            isRead: true,
            read_at: notification.read_at ?? new Date().toISOString(),
        }));
        unreadAppNotificationsCount.value = 0;
    }
};

const selectedTicketView = ref('all');
const selectedStatus = ref(null);
const selectedAssignee = ref(null);
const selectedPriority = ref(null);
const selectedChannel = ref(null);
const searchQuery = ref('');
const onlyActiveLast7Days = ref(false);
const showAdvancedFilters = ref(false);
const showCreateTicketModal = ref(false);
const currentTicketPage = ref(1);
const ticketsPerPage = 12;

const isUnassigned = (ticket) => !ticket.assignee || ticket.assigneeName === 'Nieprzypisane' || ticket.assigneeName === unassignedAssigneeValue;
const assigneeLabel = (name) => (name === 'Nieprzypisane' || name === unassignedAssigneeValue ? t('tickets.unassigned') : name);
const isAssignedToCurrentUser = (ticket) => {
    const user = currentUser.value;

    return String(ticket.assignee) === String(user.id) || ticket.assigneeName === user.name;
};

const isActiveInLast7Days = (ticket) => {
    if (!ticket.updatedAt) {
        return true;
    }

    const updatedAt = new Date(ticket.updatedAt).getTime();
    const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);

    return updatedAt >= sevenDaysAgo;
};

const uniqueAssignees = computed(() => [...new Set(tickets.value.map((ticket) => ticket.assigneeName).filter(Boolean))].sort());

const activeFilterCount = computed(() => [
    selectedStatus.value,
    selectedAssignee.value,
    selectedPriority.value,
    selectedChannel.value,
    searchQuery.value.trim(),
    onlyActiveLast7Days.value,
    selectedTicketView.value !== 'all',
].filter(Boolean).length);

const clearFilters = () => {
    selectedTicketView.value = 'all';
    selectedStatus.value = null;
    selectedAssignee.value = null;
    selectedPriority.value = null;
    selectedChannel.value = null;
    searchQuery.value = '';
    onlyActiveLast7Days.value = false;
};

const toggleMetricFilter = (metric) => {
    if (metric.status) {
        selectedStatus.value = selectedStatus.value === metric.status ? null : metric.status;
        return;
    }

    if (metric.assignee) {
        selectedAssignee.value = selectedAssignee.value === metric.assignee ? null : metric.assignee;
    }
};

const statusStyles = {
    open: 'border-blue-200 bg-blue-50 text-blue-700',
    in_progress: 'border-amber-200 bg-amber-50 text-amber-700',
    resolved: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    closed: 'border-slate-200 bg-slate-100 text-slate-600',
};

const priorityStyles = {
    low: 'text-slate-500',
    medium: 'text-slate-600',
    high: 'text-orange-600',
    urgent: 'text-red-600',
};

const filteredTickets = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return tickets.value.filter((ticket) => {
        if (selectedTicketView.value === 'to_handle' && !['open', 'in_progress'].includes(ticket.status)) {
            return false;
        }
        if (selectedTicketView.value === 'my_open' && (!isAssignedToCurrentUser(ticket) || ticket.status !== 'open')) {
            return false;
        }
        if (selectedStatus.value && ticket.status !== selectedStatus.value) {
            return false;
        }
        if (selectedAssignee.value && ticket.assigneeName !== selectedAssignee.value) {
            return false;
        }
        if (selectedPriority.value && ticket.priority !== selectedPriority.value) {
            return false;
        }
        if (selectedChannel.value && ticket.channel !== selectedChannel.value) {
            return false;
        }
        if (onlyActiveLast7Days.value && !isActiveInLast7Days(ticket)) {
            return false;
        }
        if (query) {
            const searchable = [
                ticket.id,
                ticket.requester,
                ticket.email,
                ticket.subject,
                ticket.assigneeName,
                statusLabels.value[ticket.status],
                priorityLabels.value[ticket.priority],
                channelLabels.value[ticket.channel],
            ].filter(Boolean).join(' ').toLowerCase();

            if (!searchable.includes(query)) {
                return false;
            }
        }
        return true;
    });
});

const totalTicketPages = computed(() => Math.max(1, Math.ceil(filteredTickets.value.length / ticketsPerPage)));
const paginatedTickets = computed(() => {
    const safePage = Math.min(currentTicketPage.value, totalTicketPages.value);
    const start = (safePage - 1) * ticketsPerPage;

    return filteredTickets.value.slice(start, start + ticketsPerPage);
});
const paginationStart = computed(() => filteredTickets.value.length ? ((currentTicketPage.value - 1) * ticketsPerPage) + 1 : 0);
const paginationEnd = computed(() => Math.min(currentTicketPage.value * ticketsPerPage, filteredTickets.value.length));

const setTicketPage = (page) => {
    currentTicketPage.value = Math.min(Math.max(page, 1), totalTicketPages.value);
};

const metrics = computed(() => [
    { label: statusLabels.value.open, value: tickets.value.filter((ticket) => ticket.status === 'open').length, accent: 'bg-blue-500', status: 'open' },
    { label: statusLabels.value.in_progress, value: tickets.value.filter((ticket) => ticket.status === 'in_progress').length, accent: 'bg-amber-400', status: 'in_progress' },
    { label: statusLabels.value.resolved, value: tickets.value.filter((ticket) => ticket.status === 'resolved').length, accent: 'bg-emerald-500', status: 'resolved' },
    { label: t('tickets.unassigned'), value: tickets.value.filter(isUnassigned).length, accent: 'bg-slate-500', assignee: unassignedAssigneeValue },
]);

const percentage = (value, total = tickets.value.length) => total ? Math.round((value / total) * 100) : 0;

const reportKpis = computed(() => {
    const total = tickets.value.length;
    const open = tickets.value.filter((ticket) => ticket.status === 'open').length;
    const inProgress = tickets.value.filter((ticket) => ticket.status === 'in_progress').length;
    const resolved = tickets.value.filter((ticket) => ticket.status === 'resolved').length;
    const closed = tickets.value.filter((ticket) => ticket.status === 'closed').length;
    const unassigned = tickets.value.filter(isUnassigned).length;
    const urgent = tickets.value.filter((ticket) => ticket.priority === 'urgent').length;

    return [
        { label: t('reports.allTickets'), value: total, detail: `${percentage(total, total)}%`, accent: 'bg-blue-500' },
        { label: t('reports.active'), value: open + inProgress, detail: `${percentage(open + inProgress)}%`, accent: 'bg-amber-400' },
        { label: t('reports.closedResolved'), value: resolved + closed, detail: `${percentage(resolved + closed)}%`, accent: 'bg-emerald-500' },
        { label: t('reports.urgent'), value: urgent, detail: `${percentage(urgent)}%`, accent: 'bg-red-500' },
        { label: t('reports.unassigned'), value: unassigned, detail: `${percentage(unassigned)}%`, accent: 'bg-slate-500' },
    ];
});

const countBy = (items, key) => items.reduce((result, item) => {
    const value = item[key] ?? 'unknown';
    result[value] = (result[value] ?? 0) + 1;
    return result;
}, {});

const statusReport = computed(() => {
    const counts = countBy(tickets.value, 'status');
    return statuses.map((status) => ({
        key: status,
        label: statusLabels.value[status],
        value: counts[status] ?? 0,
        percent: percentage(counts[status] ?? 0),
    }));
});

const priorityReport = computed(() => {
    const counts = countBy(tickets.value, 'priority');
    return priorities.map((priority) => ({
        key: priority,
        label: priorityLabels.value[priority],
        value: counts[priority] ?? 0,
        percent: percentage(counts[priority] ?? 0),
    }));
});

const channelReport = computed(() => {
    const counts = countBy(tickets.value, 'channel');
    return channels.map((channel) => ({
        key: channel,
        label: channelLabels.value[channel],
        value: counts[channel] ?? 0,
        percent: percentage(counts[channel] ?? 0),
    }));
});

const agentReport = computed(() => {
    const workload = tickets.value.reduce((result, ticket) => {
        const name = ticket.assigneeName || unassignedAssigneeValue;
        const current = result[name] ?? { name, total: 0, active: 0, resolved: 0, urgent: 0 };

        current.total += 1;
        current.active += ['open', 'in_progress'].includes(ticket.status) ? 1 : 0;
        current.resolved += ['resolved', 'closed'].includes(ticket.status) ? 1 : 0;
        current.urgent += ticket.priority === 'urgent' ? 1 : 0;
        result[name] = current;

        return result;
    }, {});

    return Object.values(workload)
        .sort((first, second) => second.active - first.active || second.total - first.total)
        .slice(0, 8);
});

const knowledgeArticles = ref((props.knowledgeArticles ?? []).map((article) => ({
    ...article,
    tags: article.tags ?? [],
})));
const knowledgeArticleTranslations = {
    en: {
        'nie-dziala-reset-hasla': {
            title: 'Password reset does not work',
            category: 'Account and login',
            problem: 'The customer does not receive the password reset email or the reset link has expired.',
            symptoms: 'No password reset email\nThe link opens an error or is inactive\nThe customer tries to reset the password several times in a row',
            solution: '1. Check whether the email address in the ticket matches the customer account.\n2. Ask the customer to check Spam and Promotions folders.\n3. Generate a new password reset link if the previous one is older than 30 minutes.\n4. If the email is not sent, check the mail queue and Mailpit.\n5. After a successful reset, ask the customer to log in again in incognito mode.',
            customer_reply: 'Hello,\n\nI prepared a new password reset link. Please use the latest email and ignore earlier links, because they may have expired. If the message is not in your inbox, please check Spam or Promotions.\n\nAfter changing the password, it is best to log in again in a new tab or in incognito mode.',
            tags: ['password', 'reset', 'login', 'email'],
        },
        'brak-zalacznika-w-zgloszeniu': {
            title: 'Attachment missing from ticket',
            category: 'Attachments',
            problem: 'The customer says they added a file, but the attachment is not visible on the ticket.',
            symptoms: 'The conversation mentions a file, but the files panel is empty\nThe file has an unusual format or large size\nThe customer sent the message from a phone',
            solution: '1. Check the Media and files panel on the ticket.\n2. Verify whether the customer message was sent without a file.\n3. Ask the customer to upload the file again as PDF, PNG, JPG or ZIP.\n4. If the file is large, ask for compression or splitting it into parts.\n5. Confirm the file is visible in the ticket after receiving it.',
            customer_reply: 'Hello,\n\nI do not see an attachment on the ticket. Please upload the file again, preferably as PDF, PNG, JPG or ZIP. If the file is large, please compress it or split it into smaller parts.\n\nOnce the file appears in the ticket, I will confirm receipt and continue verification.',
            tags: ['attachment', 'file', 'upload', 'pdf'],
        },
        'przekierowanie-poczty-nie-dziala': {
            title: 'Mail forwarding does not work',
            category: 'Mail',
            problem: 'Messages are not forwarded to the selected address or remain only in the source mailbox.',
            symptoms: 'The customer does not receive message copies\nThe forwarding rule exists but does not work\nMessages go to archive or spam',
            solution: '1. Check the destination address and typos.\n2. Verify the order of mail rules.\n3. Disable archive or filtering rules that may capture messages.\n4. Send a test message and check the delivery log.\n5. If the domain blocks forwarding, suggest an alias or recipient group.',
            customer_reply: 'Hello,\n\nI checked the forwarding configuration. The most common cause is a conflict with another mail rule or a domain-side block. I will run a delivery test and verify the rule order.\n\nAfter the test I will let you know whether the rule only needs correction or whether an alias or recipient group will be better.',
            tags: ['mail', 'forwarding', 'email'],
        },
        'bledne-dane-na-fakturze': {
            title: 'Incorrect invoice details',
            category: 'Billing',
            problem: 'The customer reports incorrect invoice details, for example tax ID, address, company name or amount.',
            symptoms: 'The customer provides an invoice number\nThe message concerns company data correction\nAccounting verification is required',
            solution: '1. Ask for the invoice number and correct details.\n2. Verify whether the invoice has already been booked.\n3. Forward the case to billing or accounting.\n4. If a correction is possible, prepare a confirmation of the timeline.\n5. After issuing the correction, attach the document to the ticket.',
            customer_reply: 'Hello,\n\nI accepted the request to correct invoice details. Please send the invoice number and the correct data that should appear on the document.\n\nAfter verification I will forward the case to billing and come back with information about the correction.',
            tags: ['invoice', 'correction', 'billing', 'tax id'],
        },
        'widget-czatu-brak-formularza': {
            title: 'Chat widget does not show the pre-chat form',
            category: 'Chat',
            problem: 'The pre-chat form does not appear in the chat widget after a configuration change.',
            symptoms: 'The widget starts a conversation without initial questions\nThe issue occurs only on a selected page\nThe widget configuration was recently changed',
            solution: '1. Ask the customer for the page URL and a screenshot of widget settings.\n2. Check whether the pre-chat form is active for that channel.\n3. Verify that visibility rules do not bypass the form.\n4. Clear page cache or ask for a test in incognito mode.\n5. After changing configuration, perform a test conversation.',
            customer_reply: 'Hello,\n\nI will check the pre-chat form configuration. Please send the page URL where the issue occurs and a screenshot of the widget settings.\n\nAfter verification I will run a test conversation and confirm whether the form appears correctly.',
            tags: ['chat', 'widget', 'form', 'pre-chat'],
        },
        'autoresponder-stara-stopka': {
            title: 'Autoresponder sends an old signature',
            category: 'Mail',
            problem: 'The automatic reply contains an old signature, phone number or outdated company details.',
            symptoms: 'The customer receives the correct message but with a wrong signature\nThe signature differs from the one in the user panel\nThe issue affects only the autoresponder',
            solution: '1. Check the autoresponder template, not only the user signature.\n2. Verify whether the correct language or template variant is active.\n3. Clear configuration cache if the system uses one.\n4. Send a test message to the customer mailbox.\n5. Confirm the change with the customer.',
            customer_reply: 'Hello,\n\nI will check the autoresponder template, because automatic replies may use a separate signature from regular messages. After updating it I will run a test and confirm whether the new signature is sent correctly.',
            tags: ['autoresponder', 'signature', 'email', 'template'],
        },
        'klient-nie-otrzymuje-powiadomien-email': {
            title: 'Customer does not receive email notifications',
            category: 'Notifications',
            problem: 'The customer does not receive messages about new replies or ticket changes.',
            symptoms: 'No emails despite new ticket messages\nMessages are visible in the system\nThe problem may affect one domain',
            solution: '1. Check whether the customer email address is correct.\n2. Verify Mailpit or delivery logs.\n3. Ask the customer to check spam and filters.\n4. If the domain rejects messages, check the SMTP response.\n5. Send a test notification after the fix.',
            customer_reply: 'Hello,\n\nI will check notification delivery to the provided email address. Please also verify Spam and any mail rules that could move messages outside the inbox.\n\nOn the system side I will check the delivery log and return with the test result.',
            tags: ['notifications', 'email', 'mailpit', 'smtp'],
        },
        'zgloszenie-ma-nieprawidlowy-priorytet': {
            title: 'Ticket has an incorrect priority',
            category: 'Ticket handling',
            problem: 'The ticket was created with too high or too low priority.',
            symptoms: 'The customer asks for faster handling\nPriority does not match the issue type\nThe ticket went to the wrong SLA queue',
            solution: '1. Assess the impact of the issue on the customer work.\n2. Check whether it blocks the whole team, one user or is cosmetic.\n3. Change the priority according to SLA rules.\n4. Add a short note explaining why the priority changed.\n5. If the problem is urgent, assign it to an available agent.',
            customer_reply: 'Hello,\n\nI verified the ticket priority and will adjust it to the impact on work. If the case blocks several people or prevents use of the service, we will raise the priority and speed up handling.',
            tags: ['priority', 'sla', 'queue', 'ticket'],
        },
    },
};
const knowledgeQuery = ref('');
const selectedKnowledgeCategory = ref('all');
const selectedKnowledgeArticleId = ref(knowledgeArticles.value[0]?.id ?? null);
const copiedKnowledgeArticleId = ref(null);

const splitKnowledgeLines = (value = '') => String(value)
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);

const localizedKnowledgeArticle = (article) => {
    if (locale.value === 'pl') {
        return article;
    }

    return {
        ...article,
        ...(knowledgeArticleTranslations[locale.value]?.[article.slug] ?? knowledgeArticleTranslations.en?.[article.slug] ?? {}),
    };
};

const localizedKnowledgeArticles = computed(() => knowledgeArticles.value.map(localizedKnowledgeArticle));

const knowledgeCategories = computed(() => ['all', ...new Set(localizedKnowledgeArticles.value.map((article) => article.category).filter(Boolean))]);

const filteredKnowledgeArticles = computed(() => {
    const query = knowledgeQuery.value.trim().toLowerCase();

    return localizedKnowledgeArticles.value.filter((article) => {
        if (selectedKnowledgeCategory.value !== 'all' && article.category !== selectedKnowledgeCategory.value) {
            return false;
        }

        if (!query) {
            return true;
        }

        const searchable = [
            article.title,
            article.category,
            article.problem,
            article.symptoms,
            article.solution,
            article.customer_reply,
            ...(article.tags ?? []),
        ].filter(Boolean).join(' ').toLowerCase();

        return searchable.includes(query);
    });
});

const selectedKnowledgeArticle = computed(() => {
    const selected = filteredKnowledgeArticles.value.find((article) => String(article.id) === String(selectedKnowledgeArticleId.value));

    return selected ?? filteredKnowledgeArticles.value[0] ?? null;
});

const selectKnowledgeArticle = (article) => {
    selectedKnowledgeArticleId.value = article.id;
};

const copyKnowledgeReply = async (article) => {
    if (!article?.customer_reply) {
        return;
    }

    await navigator.clipboard?.writeText(article.customer_reply);
    copiedKnowledgeArticleId.value = article.id;

    window.setTimeout(() => {
        if (String(copiedKnowledgeArticleId.value) === String(article.id)) {
            copiedKnowledgeArticleId.value = null;
        }
    }, 1800);
};

watch(filteredKnowledgeArticles, (articles) => {
    if (!articles.length) {
        selectedKnowledgeArticleId.value = null;
        return;
    }

    if (!articles.some((article) => String(article.id) === String(selectedKnowledgeArticleId.value))) {
        selectedKnowledgeArticleId.value = articles[0].id;
    }
});

watch(locale, () => {
    selectedKnowledgeCategory.value = 'all';
});

watch(() => page.props.localization?.locale, (newLocale) => {
    if (!newLocale) {
        return;
    }

    selectedLocale.value = newLocale;
    locale.value = newLocale;
    document.documentElement.lang = newLocale;
}, { immediate: true });

watch(() => currentUser.value.locale, (newLocale) => {
    if (newLocale && newLocale !== selectedLocale.value && !localeSaveStatus.value) {
        selectedLocale.value = newLocale;
    }
});


watch(theme, (value) => {
    localStorage.setItem('helpdesk-theme', value);
    document.documentElement.dataset.theme = value;
});

watch(isNavbarCollapsed, (value) => {
    localStorage.setItem('helpdesk-navbar-collapsed', String(value));
});

watch([
    selectedTicketView,
    selectedStatus,
    selectedAssignee,
    selectedPriority,
    selectedChannel,
    searchQuery,
    onlyActiveLast7Days,
], () => {
    currentTicketPage.value = 1;
});

// Users/Agents Management
const userRole = computed(() => currentUser.value.role ?? 'agent');
const isAdmin = computed(() => userRole.value === 'admin');
const agents = ref([]);
const isLoadingAgents = ref(false);
const showUserForm = ref(false);
const editingUser = ref(null);
const userForm = ref({
    name: '',
    email: '',
    password: '',
    role: 'agent',
});
const formError = ref('');
const formSuccess = ref('');

const loadAgents = async () => {
    isLoadingAgents.value = true;
    try {
        const response = await fetch('/api/users/list', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (response.ok) {
            agents.value = await response.json();
        }
    } catch (error) {
        console.error('Error loading agents:', error);
    } finally {
        isLoadingAgents.value = false;
    }
};

const agentDirectoryStats = computed(() => ({
    admins: agents.value.filter((agent) => agent.role === 'admin').length,
    agents: agents.value.filter((agent) => agent.role === 'agent').length,
}));

const resetForm = () => {
    userForm.value = { name: '', email: '', password: '', role: 'agent' };
    editingUser.value = null;
    formError.value = '';
    formSuccess.value = '';
};

const openAddForm = () => {
    resetForm();
    showUserForm.value = true;
};

const openEditForm = (user) => {
    editingUser.value = user;
    userForm.value = {
        name: user.name,
        email: user.email,
        password: '',
        role: user.role,
    };
    showUserForm.value = true;
};

const submitUserForm = async () => {
    formError.value = '';
    formSuccess.value = '';

    try {
        const url = editingUser.value ? `/api/users/${editingUser.value.id}` : '/api/users';
        const method = editingUser.value ? 'PATCH' : 'POST';
        const payload = { ...userForm.value };

        if (!payload.password) {
            delete payload.password;
        }

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.ok) {
            formSuccess.value = data.message || (editingUser.value ? 'User updated' : 'User created');
            await loadAgents();
            setTimeout(() => {
                showUserForm.value = false;
                resetForm();
            }, 1500);
        } else {
            formError.value = data.message || 'An error occurred';
        }
    } catch (error) {
        formError.value = error.message;
    }
};

const deleteUser = async (user) => {
    if (!confirm(`Are you sure you want to delete ${user.name}?`)) return;

    try {
        const response = await fetch(`/api/users/${user.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            await loadAgents();
        }
    } catch (error) {
        console.error('Error deleting user:', error);
    }
};

const normalizeChatMessage = (message) => ({
    ...message,
    user: message.user ?? {},
    recipient: message.recipient ?? {},
    attachments: message.attachments ?? [],
});
const teamChatMessages = ref(props.teamChatMessages.map(normalizeChatMessage));
const teamChatUsers = ref(props.teamChatUsers ?? []);
const selectedTeamChatUserId = ref(teamChatUsers.value[0]?.id ?? null);
const teamChatBody = ref('');
const teamChatFiles = ref([]);
const teamChatFileInput = ref(null);
const teamChatError = ref('');
const teamChatSearch = ref('');
const isLoadingTeamChat = ref(false);
const isSendingTeamChat = ref(false);
const showEmojiPicker = ref(false);
const teamChatScroll = ref(null);

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

const teamChatUserInitials = (user = {}) => requesterInitials(user.name ?? 'U');
const isOwnTeamChatMessage = (message) => String(message.user_id ?? message.user?.id) === String(currentUser.value.id);
const selectedTeamChatUser = computed(() => teamChatUsers.value.find((user) => String(user.id) === String(selectedTeamChatUserId.value)) ?? null);
const filteredTeamChatUsers = computed(() => {
    const query = teamChatSearch.value.trim().toLowerCase();

    if (!query) {
        return teamChatUsers.value;
    }

    return teamChatUsers.value.filter((user) => [
        user.name,
        user.email,
        user.role,
    ].some((value) => String(value ?? '').toLowerCase().includes(query)));
});
const selectedTeamChatAttachments = computed(() => teamChatMessages.value
    .flatMap((message) => (message.attachments ?? []).map((attachment) => ({
        ...attachment,
        message,
        isImage: String(attachment.mime_type ?? '').startsWith('image/'),
    })))
    .reverse());
const isMessageInSelectedTeamChat = (message) => {
    if (!selectedTeamChatUserId.value) {
        return false;
    }

    const currentUserId = String(currentUser.value.id);
    const selectedUserId = String(selectedTeamChatUserId.value);

    return (
        String(message.user_id) === currentUserId && String(message.recipient_id) === selectedUserId
    ) || (
        String(message.user_id) === selectedUserId && String(message.recipient_id) === currentUserId
    );
};

const scrollTeamChatToBottom = () => nextTick(() => {
    if (teamChatScroll.value) {
        teamChatScroll.value.scrollTop = teamChatScroll.value.scrollHeight;
    }
});

const wrapTeamChatSelection = (prefix, suffix = prefix) => {
    const textarea = document.querySelector('#team-chat-body');
    const start = textarea?.selectionStart ?? teamChatBody.value.length;
    const end = textarea?.selectionEnd ?? teamChatBody.value.length;
    const selected = teamChatBody.value.slice(start, end);

    teamChatBody.value = `${teamChatBody.value.slice(0, start)}${prefix}${selected || 'tekst'}${suffix}${teamChatBody.value.slice(end)}`;

    nextTick(() => {
        textarea?.focus();
        textarea?.setSelectionRange(start + prefix.length, start + prefix.length + (selected || 'tekst').length);
    });
};

const appendEmoji = (emoji) => {
    teamChatBody.value = `${teamChatBody.value}${emoji.i ?? ''}`;
    showEmojiPicker.value = false;
};

const handleTeamChatFiles = (event) => {
    teamChatFiles.value = Array.from(event.target.files ?? []).slice(0, 5);
};

const removeTeamChatFile = (index) => {
    teamChatFiles.value = teamChatFiles.value.filter((_, currentIndex) => currentIndex !== index);

    if (!teamChatFiles.value.length && teamChatFileInput.value) {
        teamChatFileInput.value.value = '';
    }
};

const loadTeamChatConversation = async () => {
    if (!selectedTeamChatUserId.value) {
        teamChatMessages.value = [];
        return;
    }

    isLoadingTeamChat.value = true;
    teamChatError.value = '';

    try {
        const params = new URLSearchParams({ recipient_id: selectedTeamChatUserId.value });
        const response = await fetch(`/team-chat/messages?${params}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();

        if (!response.ok) {
            teamChatError.value = data.message ?? t('teamChat.fetchError');
            return;
        }

        teamChatMessages.value = data.messages.map(normalizeChatMessage);
        scrollTeamChatToBottom();
    } catch (error) {
        teamChatError.value = t('teamChat.fetchError');
    } finally {
        isLoadingTeamChat.value = false;
    }
};

const selectTeamChatUser = (user) => {
    selectedTeamChatUserId.value = user.id;
    loadTeamChatConversation();
};

const sendTeamChatMessage = async () => {
    const body = teamChatBody.value.trim();

    if (!selectedTeamChatUserId.value) {
        teamChatError.value = t('teamChat.chooseWorker');
        return;
    }

    if (!body) {
        teamChatError.value = t('teamChat.emptyMessage');
        return;
    }

    isSendingTeamChat.value = true;
    teamChatError.value = '';

    const payload = new FormData();
    payload.append('recipient_id', selectedTeamChatUserId.value);
    payload.append('body', body);
    teamChatFiles.value.forEach((file) => payload.append('attachments[]', file));

    try {
        const response = await fetch('/team-chat/messages', {
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
            teamChatError.value = data.message ?? t('teamChat.sendError');
            return;
        }

        teamChatMessages.value.push(normalizeChatMessage(data.message));
        teamChatBody.value = '';
        teamChatFiles.value = [];
        if (teamChatFileInput.value) {
            teamChatFileInput.value.value = '';
        }
        scrollTeamChatToBottom();
    } catch (error) {
        teamChatError.value = t('teamChat.sendError');
    } finally {
        isSendingTeamChat.value = false;
    }
};

onMounted(() => {
    if (activeView.value === 'Agents' && agents.value.length === 0) {
        loadAgents();
    }

    if (selectedTeamChatUserId.value) {
        loadTeamChatConversation();
    }

    window.Echo
        ?.private(`team-chat.users.${currentUser.value.id}`)
        .listen('.team-chat.message.created', (event) => {
            const message = normalizeChatMessage(event.message);

            if (isMessageInSelectedTeamChat(message)) {
                teamChatMessages.value.push(message);
                scrollTeamChatToBottom();
            }
        });

    window.Echo
        ?.private(`users.${currentUser.value.id}`)
        .listen('.app.notification.created', (event) => {
            appendNotification(event.notification);
        });
});

onBeforeUnmount(() => {
    window.Echo?.leave(`team-chat.users.${currentUser.value.id}`);
    window.Echo?.leave(`users.${currentUser.value.id}`);
});

watch(activeView, (newView) => {
    if (newView === 'Agents' && agents.value.length === 0) {
        loadAgents();
    }

    if (newView === 'TeamChat') {
        loadTeamChatConversation();
    }
});

watch(() => props.initialView, (newView) => {
    if (newView && activeView.value !== newView) {
        activeView.value = newView;
    }
});
</script>

<template>
    <main :class="['fixed inset-0 flex h-dvh flex-col overflow-hidden', isDark ? 'bg-slate-950 text-slate-200' : 'bg-[#f4f6f8] text-slate-700']">
        <div class="flex min-h-0 flex-1">
            <aside
                :class="[
                    'hidden h-full shrink-0 flex-col bg-[#30333a] text-slate-300 transition-[width] duration-200 lg:flex',
                    isNavbarCollapsed ? 'w-16' : 'w-64',
                ]"
            >
                <div :class="['flex h-14 items-center border-b border-white/10', isNavbarCollapsed ? 'justify-center px-2' : 'gap-3 px-4']">
                    <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md bg-white p-1 shadow-sm">
                        <img :src="'/images/capyhelp-smaller.png'" alt="CAPYHELP" class="h-full w-full object-contain">
                    </span>
                    <div v-if="!isNavbarCollapsed" class="min-w-0">
                        <p class="truncate text-sm font-semibold text-white">{{ t('app.brand') }}</p>
                        <p class="truncate text-xs text-slate-400">{{ t('app.panel') }}</p>
                    </div>
                    <button
                        v-if="!isNavbarCollapsed"
                        class="ml-auto grid size-8 place-items-center rounded-md text-slate-300 hover:bg-white/10 hover:text-white"
                        aria-label="Collapse navbar"
                        title="Collapse navbar"
                        @click="isNavbarCollapsed = true"
                    >
                        ‹
                    </button>
                </div>

                <nav :class="['flex flex-1 flex-col gap-1 py-4', isNavbarCollapsed ? 'items-center px-2' : 'px-3']">
                    <button
                        v-if="isNavbarCollapsed"
                        class="mb-2 grid size-10 place-items-center rounded-md text-slate-300 hover:bg-white/10 hover:text-white"
                        aria-label="Expand navbar"
                        title="Expand navbar"
                        @click="isNavbarCollapsed = false"
                    >
                        ›
                    </button>
                    <a
                        v-for="item in navigation"
                        :key="item.labelKey"
                        :href="item.href"
                        :class="[
                            'group flex h-10 items-center rounded-md text-left text-sm font-semibold transition hover:bg-white/10 hover:text-white',
                            isNavbarCollapsed ? 'w-10 justify-center px-0' : 'w-full gap-3 px-3',
                            activeView === item.view ? 'bg-white/10 text-white' : 'text-slate-300',
                        ]"
                        :aria-label="t(item.labelKey)"
                        :title="t(item.labelKey)"
                        @click.prevent="navigateTo(item)"
                    >
                        <span class="grid size-6 shrink-0 place-items-center rounded bg-white/5 text-sm text-slate-200">
                            <font-awesome-icon :icon="item.icon" />
                        </span>
                        <span v-if="!isNavbarCollapsed" class="min-w-0 flex-1 truncate">{{ t(item.labelKey) }}</span>
                        <span
                            v-if="item.badge && !isNavbarCollapsed"
                            class="rounded-full bg-red-500 px-2 text-[10px] font-semibold leading-5 text-white"
                        >
                            {{ item.badge }}
                        </span>
                        <span
                            v-else-if="item.badge"
                            class="absolute ml-7 mb-6 size-2 rounded-full bg-red-500"
                        >
                        </span>
                    </a>
                </nav>

                <div :class="['border-t border-white/10 p-3', isNavbarCollapsed ? 'flex flex-col items-center' : '']">
                    <button
                        :class="[
                            'mb-3 flex h-9 items-center rounded-md text-sm font-semibold text-slate-300 hover:bg-white/10 hover:text-white',
                            isNavbarCollapsed ? 'w-10 justify-center px-0' : 'w-full gap-3 px-3 text-left',
                        ]"
                        aria-label="Pomoc"
                        title="Pomoc"
                    >
                        <span class="grid size-6 place-items-center rounded bg-white/5">
                            <font-awesome-icon icon="fa-solid fa-circle-question" class="text-sm" />
                        </span>
                        <span v-if="!isNavbarCollapsed">{{ t('app.help') }}</span>
                    </button>
                    <div
                        :class="[
                            'rounded-md border border-white/10 bg-white/[0.06]',
                            isNavbarCollapsed ? 'p-2' : 'p-3',
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <img
                                v-if="currentUserAvatarUrl"
                                :src="currentUserAvatarUrl"
                                :alt="currentUser.name"
                                class="size-10 shrink-0 rounded-full object-cover ring-1 ring-white/20"
                            >
                            <div v-else class="grid size-10 shrink-0 place-items-center rounded-full bg-emerald-500 text-xs font-semibold text-white">
                                {{ userInitials }}
                            </div>
                            <div v-if="!isNavbarCollapsed" class="min-w-0">
                                <p class="truncate text-sm font-semibold text-white">{{ currentUser.name }}</p>
                                <p class="truncate text-xs text-slate-400">{{ currentUser.email }}</p>
                            </div>
                        </div>
                        <form v-if="!isNavbarCollapsed" class="mt-3" action="/logout" method="POST">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <button class="w-full rounded border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">
                                {{ t('app.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <section
                v-if="activeView === 'Tickets'"
                :class="[
                    'hidden h-full w-72 shrink-0 overflow-hidden border-r xl:flex xl:flex-col',
                    isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-white',
                ]"
            >
                <div :class="['flex h-14 items-center justify-between border-b px-5', isDark ? 'border-slate-800' : 'border-slate-200']">
                    <h1 :class="['text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-900']">{{ t('navigation.tickets') }}</h1>
                    <button
                        v-if="isAdmin"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700"
                        @click="showCreateTicketModal = true"
                    >
                        + {{ t('tickets.new') }}
                    </button>
                </div>

                <div class="px-5 py-4">
                    <label class="relative block">
                        <font-awesome-icon icon="fa-solid fa-search" class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" />
                        <input
                            v-model="searchQuery"
                            :class="[
                                'h-10 w-full rounded-md border pl-9 pr-3 text-xs outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100',
                                isDark ? 'border-slate-700 bg-slate-900 text-slate-100 focus:ring-blue-500/20' : 'border-slate-200 bg-slate-50 text-slate-800',
                            ]"
                            :placeholder="t('tickets.search')"
                            type="search"
                        >
                    </label>
                </div>

                <div class="scrollbar-hidden min-h-0 flex-1 space-y-6 overflow-y-auto overscroll-contain px-5 pb-6 text-sm">
                    <div>
                        <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('tickets.views') }}</p>
                        <button
                            v-for="view in views"
                            :key="view.key"
                            :class="[
                                'mb-1 block w-full rounded-md px-3 py-2 text-left transition',
                                selectedTicketView === view.key
                                    ? isDark ? 'bg-blue-500/15 text-blue-200 shadow-sm' : 'bg-blue-50 text-blue-700 shadow-sm'
                                    : isDark ? 'text-slate-300 hover:bg-slate-900' : 'text-slate-600 hover:bg-slate-50',
                            ]"
                            @click="selectedTicketView = view.key"
                        >
                            <span class="block text-xs font-semibold">{{ t(view.labelKey) }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-400">{{ t(view.descriptionKey) }}</span>
                        </button>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('tickets.myFilters') }}</p>
                            <button class="text-[11px] font-medium text-blue-600" @click="clearFilters">{{ t('tickets.clear') }}</button>
                        </div>
                        <button
                            :class="[
                                'block w-full rounded-md px-3 py-2 text-left text-xs font-medium transition',
                                onlyActiveLast7Days
                                    ? isDark ? 'bg-blue-500/15 text-blue-200' : 'bg-blue-50 text-blue-700'
                                    : isDark ? 'text-slate-300 hover:bg-slate-900' : 'text-slate-600 hover:bg-slate-50',
                            ]"
                            @click="onlyActiveLast7Days = !onlyActiveLast7Days"
                        >
                            {{ t('tickets.activeLast7Days') }}
                        </button>
                        <button
                            :class="[
                                'mt-1 block w-full rounded-md px-3 py-2 text-left text-xs font-medium transition',
                                selectedAssignee === unassignedAssigneeValue
                                    ? isDark ? 'bg-blue-500/15 text-blue-200' : 'bg-blue-50 text-blue-700'
                                    : isDark ? 'text-slate-300 hover:bg-slate-900' : 'text-slate-600 hover:bg-slate-50',
                            ]"
                            @click="selectedAssignee = selectedAssignee === unassignedAssigneeValue ? null : unassignedAssigneeValue"
                        >
                            {{ t('tickets.unassigned') }}
                        </button>
                    </div>

                    <div>
                        <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('tickets.statuses') }}</p>
                        <button
                            v-for="status in statuses"
                            :key="status"
                            :class="[
                                'mb-1 flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-xs font-medium transition',
                                selectedStatus === status
                                    ? isDark ? 'bg-blue-500/15 text-blue-200' : 'bg-blue-50 text-blue-700'
                                    : isDark ? 'text-slate-300 hover:bg-slate-900' : 'text-slate-600 hover:bg-slate-50',
                            ]"
                            @click="selectedStatus = selectedStatus === status ? null : status"
                        >
                            <span>{{ statusLabels[status] }}</span>
                            <span class="text-[11px] text-slate-400">{{ tickets.filter((ticket) => ticket.status === status).length }}</span>
                        </button>
                    </div>

                    <div>
                        <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('tickets.priority') }}</p>
                        <button
                            v-for="priority in priorities"
                            :key="priority"
                            :class="[
                                'mb-1 flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-xs font-medium transition',
                                selectedPriority === priority
                                    ? isDark ? 'bg-blue-500/15 text-blue-200' : 'bg-blue-50 text-blue-700'
                                    : isDark ? 'text-slate-300 hover:bg-slate-900' : 'text-slate-600 hover:bg-slate-50',
                            ]"
                            @click="selectedPriority = selectedPriority === priority ? null : priority"
                        >
                            <span>{{ priorityLabels[priority] }}</span>
                            <span class="text-[11px] text-slate-400">{{ tickets.filter((ticket) => ticket.priority === priority).length }}</span>
                        </button>
                    </div>

                    <div>
                        <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('tickets.channels') }}</p>
                        <button
                            v-for="channel in channels"
                            :key="channel"
                            :class="[
                                'mb-1 flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-xs font-medium transition',
                                selectedChannel === channel
                                    ? isDark ? 'bg-blue-500/15 text-blue-200' : 'bg-blue-50 text-blue-700'
                                    : isDark ? 'text-slate-300 hover:bg-slate-900' : 'text-slate-600 hover:bg-slate-50',
                            ]"
                            @click="selectedChannel = selectedChannel === channel ? null : channel"
                        >
                            <span>{{ channelLabels[channel] }}</span>
                            <span class="text-[11px] text-slate-400">{{ tickets.filter((ticket) => ticket.channel === channel).length }}</span>
                        </button>
                    </div>
                </div>
            </section>

            <section class="flex min-h-0 min-w-0 flex-1 flex-col">
                <header
                    :class="[
                        'flex min-h-12 items-start justify-between gap-3 border-b px-4 py-3 lg:items-center',
                        isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white',
                    ]"
                >
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-slate-400">{{ t(activeTitle.eyebrowKey) }}</p>
                        <h2 :class="['text-lg font-semibold', isDark ? 'text-white' : 'text-slate-900']">
                            {{ t(activeTitle.titleKey) }}
                        </h2>
                        <p v-if="activeView === 'Tickets'" class="mt-1 text-xs text-slate-500">
                            {{ t('tickets.count', { shown: filteredTickets.length, total: tickets.length }) }}
                            <span v-if="activeFilterCount">- {{ t('tickets.activeFilters', { count: activeFilterCount }) }}</span>
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                        <div class="relative">
                            <button
                                :class="[
                                    'relative grid size-9 place-items-center rounded-md border text-sm transition',
                                    isDark ? 'border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                                ]"
                                :title="t('notificationsPanel.title')"
                                @click="showNotificationsPanel = !showNotificationsPanel"
                            >
                                <font-awesome-icon icon="fa-solid fa-bell" />
                                <span
                                    v-if="unreadAppNotificationsCount"
                                    class="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold leading-5 text-white ring-2"
                                    :class="isDark ? 'ring-slate-900' : 'ring-white'"
                                >
                                    {{ unreadAppNotificationsCount > 9 ? '9+' : unreadAppNotificationsCount }}
                                </span>
                            </button>

                            <div
                                v-if="showNotificationsPanel"
                                :class="[
                                    'fixed inset-x-3 top-20 z-50 max-h-[calc(100dvh-6rem)] overflow-hidden rounded-md border shadow-xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-11 sm:w-[min(360px,calc(100vw-2rem))] sm:max-h-none',
                                    isDark ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-white',
                                ]"
                            >
                                <div :class="['flex items-center justify-between gap-3 border-b px-4 py-3', isDark ? 'border-slate-800' : 'border-slate-100']">
                                    <div>
                                        <p :class="['text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('notificationsPanel.title') }}</p>
                                        <p class="text-xs text-slate-500">{{ t('notificationsPanel.unread', { count: unreadAppNotificationsCount }) }}</p>
                                    </div>
                                    <button
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-700 disabled:text-slate-400"
                                        :disabled="!unreadAppNotificationsCount"
                                        @click="markAllNotificationsAsRead"
                                    >
                                        {{ t('notificationsPanel.markAll') }}
                                    </button>
                                </div>

                                <div v-if="notificationItems.length" class="scrollbar-hidden max-h-[calc(100dvh-12rem)] overflow-y-auto sm:max-h-96">
                                    <a
                                        v-for="notification in notificationItems"
                                        :key="notification.id"
                                        :href="notificationUrl(notification)"
                                        :class="[
                                            'flex gap-3 border-b px-4 py-3 text-left transition last:border-b-0',
                                            !notification.isRead ? isDark ? 'bg-blue-500/10' : 'bg-blue-50/70' : '',
                                            isDark ? 'border-slate-800 hover:bg-slate-800' : 'border-slate-100 hover:bg-slate-50',
                                        ]"
                                        @click="markNotificationAsRead(notification)"
                                    >
                                        <span :class="['mt-0.5 grid size-8 shrink-0 place-items-center rounded-full text-xs', notificationIconClasses[notification.type] ?? 'bg-slate-500 text-white']">
                                            <font-awesome-icon icon="fa-solid fa-bell" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span :class="['block truncate text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">
                                                {{ notificationTitle(notification) }}
                                            </span>
                                            <span class="mt-1 line-clamp-2 block text-xs leading-5 text-slate-500">
                                                {{ notificationBody(notification) }}
                                            </span>
                                            <span class="mt-2 block text-[11px] font-medium text-slate-400">
                                                {{ formatRelativeDate(notification.created_at) }}
                                            </span>
                                        </span>
                                        <span v-if="!notification.isRead" class="mt-2 size-2 shrink-0 rounded-full bg-blue-600"></span>
                                    </a>
                                </div>

                                <div v-else class="px-4 py-8 text-center">
                                    <div :class="['mx-auto grid size-10 place-items-center rounded-full', isDark ? 'bg-slate-800 text-slate-400' : 'bg-slate-100 text-slate-500']">
                                        <font-awesome-icon icon="fa-solid fa-bell" />
                                    </div>
                                    <p :class="['mt-3 text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">{{ t('notificationsPanel.emptyTitle') }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ t('notificationsPanel.emptyDescription') }}</p>
                                </div>
                            </div>
                        </div>

                        <a
                            v-if="activeView === 'Reports'"
                            href="/reports/tickets.pdf"
                            target="_blank"
                            :class="[
                                'inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs font-semibold transition',
                                isDark ? 'border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700' : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100',
                            ]"
                        >
                            <font-awesome-icon icon="fa-solid fa-file-pdf" />
                            PDF
                        </a>
                        <button
                            v-if="activeView === 'Tickets'"
                            :class="[
                                'rounded-md border px-3 py-1.5 text-xs font-semibold transition',
                                showAdvancedFilters
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : isDark ? 'border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700' : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100',
                            ]"
                            @click="showAdvancedFilters = !showAdvancedFilters"
                        >
                            + {{ t('tickets.addFilters') }}
                        </button>
                        <button
                            v-if="activeView === 'Tickets'"
                            @click="selectedStatus = selectedStatus === 'open' ? null : 'open'"
                            :class="[
                                'hidden rounded-md border px-3 py-1.5 text-xs font-semibold transition sm:inline-flex',
                                selectedStatus === 'open'
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : isDark ? 'border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                            ]"
                        >
                            {{ t('tickets.open') }}
                        </button>
                        <button
                            v-if="activeView === 'Tickets'"
                            @click="selectedAssignee = selectedAssignee === unassignedAssigneeValue ? null : unassignedAssigneeValue"
                            :class="[
                                'hidden rounded-md border px-3 py-1.5 text-xs font-semibold transition sm:inline-flex',
                                selectedAssignee === unassignedAssigneeValue
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : isDark ? 'border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                            ]"
                        >
                            {{ t('tickets.unassigned') }}
                        </button>
                        <button
                            v-if="activeView === 'Tickets'"
                            @click="onlyActiveLast7Days = !onlyActiveLast7Days"
                            :class="[
                                'hidden rounded-md border px-3 py-1.5 text-xs font-semibold transition sm:inline-flex',
                                onlyActiveLast7Days
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : isDark ? 'border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                            ]"
                        >
                            {{ t('tickets.last7Days') }}
                        </button>
                        <button
                            v-if="activeView === 'Tickets'"
                            @click="clearFilters"
                            :class="[
                                'hidden rounded-md border px-3 py-1.5 text-xs font-semibold sm:inline-flex',
                                activeFilterCount
                                    ? isDark ? 'border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                    : isDark ? 'border-slate-800 bg-slate-900 text-slate-600' : 'border-slate-200 bg-slate-50 text-slate-400',
                            ]"
                            :disabled="!activeFilterCount"
                        >
                            {{ t('tickets.clear') }}
                        </button>
                    </div>
                </header>

                <div v-if="activeView === 'Tickets'" class="min-h-0 flex-1 overflow-hidden">
                    <section :class="['flex h-full min-w-0 flex-col overflow-hidden', isDark ? 'bg-slate-900' : 'bg-white']">
                        <div :class="['border-b p-3 xl:hidden', isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-white']">
                            <label class="relative block">
                                <font-awesome-icon icon="fa-solid fa-search" class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" />
                                <input
                                    v-model="searchQuery"
                                    :class="[
                                        'h-10 w-full rounded-md border pl-9 pr-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100',
                                        isDark ? 'border-slate-700 bg-slate-900 text-slate-100 focus:ring-blue-500/20' : 'border-slate-200 bg-slate-50 text-slate-800',
                                    ]"
                                    :placeholder="t('tickets.search')"
                                    type="search"
                                >
                            </label>
                        </div>

                        <div
                            v-if="showAdvancedFilters"
                            :class="['grid gap-3 border-b p-3 sm:p-4 md:grid-cols-4', isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-slate-50']"
                        >
                            <label class="block">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('tickets.assignment') }}</span>
                                <select
                                    v-model="selectedAssignee"
                                    :class="[
                                        'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                        isDark ? 'border-slate-700 bg-slate-900 text-slate-100 focus:ring-blue-500/20' : 'border-slate-200 bg-white text-slate-800',
                                    ]"
                                >
                                    <option :value="null">{{ t('tickets.allAssignees') }}</option>
                                    <option v-for="assignee in uniqueAssignees" :key="assignee" :value="assignee">{{ assigneeLabel(assignee) }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('tickets.priority') }}</span>
                                <select
                                    v-model="selectedPriority"
                                    :class="[
                                        'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                        isDark ? 'border-slate-700 bg-slate-900 text-slate-100 focus:ring-blue-500/20' : 'border-slate-200 bg-white text-slate-800',
                                    ]"
                                >
                                    <option :value="null">{{ t('knowledge.all') }}</option>
                                    <option v-for="priority in priorities" :key="priority" :value="priority">{{ priorityLabels[priority] }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('tickets.channels') }}</span>
                                <select
                                    v-model="selectedChannel"
                                    :class="[
                                        'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                        isDark ? 'border-slate-700 bg-slate-900 text-slate-100 focus:ring-blue-500/20' : 'border-slate-200 bg-white text-slate-800',
                                    ]"
                                >
                                    <option :value="null">{{ t('knowledge.all') }}</option>
                                    <option v-for="channel in channels" :key="channel" :value="channel">{{ channelLabels[channel] }}</option>
                                </select>
                            </label>
                            <label
                                :class="[
                                    'mt-5 flex h-10 cursor-pointer items-center justify-between rounded-md border px-3 text-sm font-semibold',
                                    onlyActiveLast7Days
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : isDark ? 'border-slate-700 bg-slate-900 text-slate-200' : 'border-slate-200 bg-white text-slate-600',
                                ]"
                            >
                                <span>{{ t('tickets.last7Days') }}</span>
                                <input v-model="onlyActiveLast7Days" class="sr-only" type="checkbox">
                                <span :class="['h-5 w-9 rounded-full p-0.5 transition', onlyActiveLast7Days ? 'bg-white/25' : 'bg-slate-300']">
                                    <span :class="['block size-4 rounded-full bg-white transition', onlyActiveLast7Days ? 'translate-x-4' : 'translate-x-0']"></span>
                                </span>
                            </label>
                        </div>

                        <div :class="['grid grid-cols-2 gap-3 border-b p-3 sm:p-4 lg:grid-cols-4', isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-[#f8fafc]']">
                            <article
                                v-for="metric in metrics"
                                :key="metric.label"
                                :class="[
                                    'rounded-md border p-3 text-left shadow-sm transition hover:-translate-y-0.5',
                                    (metric.status && selectedStatus === metric.status) || (metric.assignee && selectedAssignee === metric.assignee)
                                        ? 'border-blue-500 ring-2 ring-blue-500/20'
                                        : isDark ? 'border-slate-800 bg-slate-900 hover:border-slate-700' : 'border-slate-200 bg-white hover:border-blue-200',
                                ]"
                                role="button"
                                tabindex="0"
                                @click="toggleMetricFilter(metric)"
                            >
                                <div class="flex items-center justify-between">
                                    <p :class="['text-xs font-medium', isDark ? 'text-slate-400' : 'text-slate-500']">{{ metric.label }}</p>
                                    <span :class="['size-2 rounded-full', metric.accent]"></span>
                                </div>
                                <p :class="['mt-2 text-2xl font-semibold', isDark ? 'text-slate-100' : 'text-slate-900']">{{ metric.value }}</p>
                            </article>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 border-b px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between" :class="isDark ? 'border-slate-800 text-slate-400' : 'border-slate-200 text-slate-500'">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">
                                    {{ t('tickets.count', { shown: `${paginationStart}-${paginationEnd}`, total: filteredTickets.length }) }}
                                </span>
                                <span v-if="selectedStatus" class="rounded-full bg-blue-500/10 px-2 py-1 text-blue-600">{{ statusLabels[selectedStatus] }}</span>
                                <span v-if="selectedPriority" class="rounded-full bg-orange-500/10 px-2 py-1 text-orange-600">{{ priorityLabels[selectedPriority] }}</span>
                                <span v-if="selectedChannel" class="rounded-full bg-emerald-500/10 px-2 py-1 text-emerald-600">{{ channelLabels[selectedChannel] }}</span>
                                <span v-if="selectedAssignee" class="rounded-full bg-slate-500/10 px-2 py-1">{{ assigneeLabel(selectedAssignee) }}</span>
                            </div>
                            <div class="flex items-center gap-2" v-if="filteredTickets.length">
                                <button
                                    :class="['grid size-7 place-items-center rounded border disabled:cursor-not-allowed disabled:opacity-40', isDark ? 'border-slate-700 text-slate-400 hover:bg-slate-800' : 'border-slate-200 text-slate-500 hover:bg-slate-50']"
                                    :disabled="currentTicketPage === 1"
                                    @click="setTicketPage(currentTicketPage - 1)"
                                >
                                    ‹
                                </button>
                                <span class="rounded bg-blue-600 px-2 py-1 font-semibold text-white">{{ currentTicketPage }}</span>
                                <span>{{ t('tickets.pageOf', { count: totalTicketPages }) }}</span>
                                <button
                                    :class="['grid size-7 place-items-center rounded border disabled:cursor-not-allowed disabled:opacity-40', isDark ? 'border-slate-700 text-slate-400 hover:bg-slate-800' : 'border-slate-200 text-slate-500 hover:bg-slate-50']"
                                    :disabled="currentTicketPage === totalTicketPages"
                                    @click="setTicketPage(currentTicketPage + 1)"
                                >
                                    ›
                                </button>
                            </div>
                        </div>

                        <div class="scrollbar-hidden min-h-0 flex-1 overflow-auto overscroll-contain">
                            <div class="lg:hidden">
                                <div
                                    v-if="!filteredTickets.length"
                                    :class="['px-4 py-16 text-center', isDark ? 'bg-slate-900 text-slate-400' : 'bg-white text-slate-500']"
                                >
                                    <div :class="['mx-auto grid size-12 place-items-center rounded-full', isDark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-500']">
                                        <font-awesome-icon icon="fa-solid fa-search" />
                                    </div>
                                    <p :class="['mt-4 text-sm font-semibold', isDark ? 'text-slate-200' : 'text-slate-800']">{{ t('tickets.noResults') }}</p>
                                    <p class="mt-1 text-sm">{{ t('tickets.changeFilters') }}</p>
                                    <button class="mt-4 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="clearFilters">
                                        {{ t('tickets.clearFilters') }}
                                    </button>
                                </div>

                                <div v-else class="space-y-2 p-3">
                                    <a
                                        v-for="ticket in paginatedTickets"
                                        :key="`mobile-${ticket.id}`"
                                        :href="ticketUrl(ticket)"
                                        :class="[
                                            'block rounded-md border p-3 shadow-sm transition',
                                            isDark ? 'border-slate-800 bg-slate-900 hover:bg-slate-800' : 'border-slate-200 bg-white hover:border-blue-200',
                                        ]"
                                    >
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span :class="['grid size-9 shrink-0 place-items-center rounded-full text-xs font-semibold text-white', ticket.color]">
                                                {{ ticket.initials }}
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span :class="['block truncate text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-900']">{{ ticket.requester }}</span>
                                                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ ticket.email }}</span>
                                            </span>
                                            <span :class="['shrink-0 text-xs', isDark ? 'text-slate-500' : 'text-slate-500']">{{ ticket.updated }}</span>
                                        </div>

                                        <p :class="['mt-3 line-clamp-2 text-sm font-medium leading-5', isDark ? 'text-slate-200' : 'text-slate-800']">
                                            {{ ticket.subject }}
                                        </p>

                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <span :class="['inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold', statusStyles[ticket.status]]">
                                                {{ statusLabels[ticket.status] ?? ticket.status }}
                                            </span>
                                            <span :class="['text-xs font-semibold', priorityStyles[ticket.priority]]">{{ priorityLabels[ticket.priority] ?? ticket.priority }}</span>
                                            <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', isDark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-500']">
                                                {{ assigneeLabel(ticket.assigneeName) }}
                                            </span>
                                        </div>
                                    </a>
                                </div>
                            </div>

                            <div class="hidden min-w-[820px] lg:block">
                            <div :class="['sticky top-0 z-10 grid grid-cols-[1.5fr_2fr_1fr_120px_110px_130px] border-b px-4 py-2 text-[11px] font-semibold uppercase tracking-wide', isDark ? 'border-slate-800 bg-slate-950 text-slate-400' : 'border-slate-200 bg-[#f8fafc] text-slate-400']">
                                <span>{{ t('tickets.requester') }}</span>
                                <span>{{ t('tickets.subject') }}</span>
                                <span>{{ t('tickets.assignment') }}</span>
                                <span>{{ t('ticketDetail.status') }}</span>
                                <span>{{ t('tickets.priority') }}</span>
                                <span>{{ t('tickets.lastMessage') }}</span>
                            </div>

                            <div
                                v-if="!filteredTickets.length"
                                :class="['px-4 py-16 text-center', isDark ? 'bg-slate-900 text-slate-400' : 'bg-white text-slate-500']"
                            >
                                <div :class="['mx-auto grid size-12 place-items-center rounded-full', isDark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-500']">
                                    <font-awesome-icon icon="fa-solid fa-search" />
                                </div>
                                <p :class="['mt-4 text-sm font-semibold', isDark ? 'text-slate-200' : 'text-slate-800']">{{ t('tickets.noResults') }}</p>
                                <p class="mt-1 text-sm">{{ t('tickets.changeFilters') }}</p>
                                <button class="mt-4 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="clearFilters">
                                    {{ t('tickets.clearFilters') }}
                                </button>
                            </div>

                            <a
                                v-for="ticket in paginatedTickets"
                                :key="ticket.id"
                                :href="ticketUrl(ticket)"
                                :class="[
                                    'grid w-full grid-cols-[1.5fr_2fr_1fr_120px_110px_130px] items-center border-b px-4 py-3 text-left transition',
                                    isDark ? 'border-slate-800 bg-slate-900 hover:bg-slate-800' : 'border-slate-100 bg-white hover:bg-blue-50/50',
                                ]"
                            >
                                <span class="flex min-w-0 items-center gap-3">
                                    <span :class="['grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold text-white', ticket.color]">
                                        {{ ticket.initials }}
                                    </span>
                                    <span class="min-w-0">
                                        <span :class="['block truncate text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">{{ ticket.requester }}</span>
                                        <span :class="['block truncate text-xs', isDark ? 'text-slate-500' : 'text-slate-400']">{{ ticket.email }}</span>
                                    </span>
                                </span>
                                <span :class="['truncate pr-6 text-sm font-medium', isDark ? 'text-slate-300' : 'text-slate-700']">{{ ticket.subject }}</span>
                                <span :class="['truncate text-sm', isDark ? 'text-slate-400' : 'text-slate-600']">{{ assigneeLabel(ticket.assigneeName) }}</span>
                                <span>
                                    <span :class="['inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold', statusStyles[ticket.status]]">
                                        {{ statusLabels[ticket.status] ?? ticket.status }}
                                    </span>
                                </span>
                                <span :class="['text-sm font-semibold', priorityStyles[ticket.priority]]">{{ priorityLabels[ticket.priority] ?? ticket.priority }}</span>
                                <span :class="['text-xs', isDark ? 'text-slate-500' : 'text-slate-500']">{{ ticket.updated }}</span>
                            </a>
                            </div>
                        </div>
                    </section>
                </div>

                <div
                    v-else-if="activeView === 'TeamChat'"
                    :class="[
                        'min-h-0 flex-1 overflow-hidden',
                        isDark ? 'bg-slate-950' : 'bg-[#f4f6f8]',
                    ]"
                >
                    <section
                        :class="[
                            'flex h-full min-h-0 w-full flex-col overflow-hidden lg:grid lg:grid-cols-[320px_minmax(0,1fr)] 2xl:grid-cols-[320px_minmax(0,1fr)_320px]',
                            isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white',
                        ]"
                    >
                        <aside
                            :class="[
                                'shrink-0 border-b lg:min-h-0 lg:border-b-0 lg:border-r',
                                isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-slate-50',
                            ]"
                        >
                            <div :class="['border-b px-3 py-3 sm:px-4 sm:py-4', isDark ? 'border-slate-800' : 'border-slate-200']">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p :class="['text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">Rozmowy</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ t('teamChat.people', { count: teamChatUsers.length }) }}</p>
                                    </div>
                                    <span :class="['grid size-9 place-items-center rounded-full', isDark ? 'bg-slate-800 text-blue-300' : 'bg-blue-50 text-blue-600']">
                                        <font-awesome-icon icon="fa-solid fa-comments" />
                                    </span>
                                </div>
                                <label class="relative mt-3 block sm:mt-4">
                                    <font-awesome-icon icon="fa-solid fa-search" class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" />
                                    <input
                                        v-model="teamChatSearch"
                                        :class="[
                                            'h-10 w-full rounded-md border pl-9 pr-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                            isDark ? 'border-slate-700 bg-slate-900 text-slate-100 focus:ring-blue-500/20' : 'border-slate-200 bg-white text-slate-800',
                                        ]"
                                        placeholder="Szukaj pracownika..."
                                        type="search"
                                    >
                                </label>
                            </div>

                            <div class="scrollbar-hidden flex gap-2 overflow-x-auto p-2 lg:block lg:h-[calc(100%-113px)] lg:overflow-x-hidden lg:overflow-y-auto">
                                <button
                                    v-for="user in filteredTeamChatUsers"
                                    :key="user.id"
                                    :class="[
                                        'mb-0 flex w-24 shrink-0 flex-col items-center gap-1 rounded-md px-2 py-2 text-center transition sm:w-32 lg:mb-1 lg:w-full lg:flex-row lg:gap-3 lg:px-3 lg:py-3 lg:text-left',
                                        String(selectedTeamChatUserId) === String(user.id)
                                            ? isDark ? 'bg-blue-500/15 text-blue-100' : 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                            : isDark ? 'text-slate-300 hover:bg-slate-900' : 'text-slate-700 hover:bg-white',
                                    ]"
                                    @click="selectTeamChatUser(user)"
                                >
                                    <img
                                        v-if="user.avatar_url"
                                        :src="user.avatar_url"
                                        :alt="user.name"
                                        class="size-9 shrink-0 rounded-full object-cover ring-1 ring-slate-200 lg:size-11"
                                    >
                                    <span
                                        v-else
                                        class="grid size-9 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-semibold text-white lg:size-11"
                                    >
                                        {{ teamChatUserInitials(user) }}
                                    </span>
                                    <span class="min-w-0 lg:flex-1">
                                        <span :class="['block w-full truncate text-xs font-semibold lg:text-sm', isDark ? 'text-slate-100' : 'text-slate-900']">{{ user.name }}</span>
                                        <span class="mt-0.5 hidden truncate text-xs text-slate-500 lg:block">{{ user.email }}</span>
                                    </span>
                                    <span
                                        v-if="String(selectedTeamChatUserId) === String(user.id)"
                                        class="size-2 shrink-0 rounded-full bg-blue-600"
                                    ></span>
                                </button>

                                <div v-if="!filteredTeamChatUsers.length" class="px-3 py-8 text-center">
                                    <div :class="['mx-auto grid size-10 place-items-center rounded-full', isDark ? 'bg-slate-900 text-slate-400' : 'bg-white text-slate-400']">
                                        <font-awesome-icon icon="fa-solid fa-user-group" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-slate-500">{{ t('teamChat.noPeople') }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ t('teamChat.changeSearch') }}</p>
                                </div>
                            </div>
                        </aside>

                        <div class="min-h-0 flex flex-1 flex-col">
                            <div :class="['border-b px-4 py-3 sm:px-5', isDark ? 'border-slate-800' : 'border-slate-200']">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <img
                                            v-if="selectedTeamChatUser?.avatar_url"
                                            :src="selectedTeamChatUser.avatar_url"
                                            :alt="selectedTeamChatUser.name"
                                            class="size-10 shrink-0 rounded-full object-cover ring-1 ring-slate-200"
                                        >
                                        <div
                                            v-else
                                            :class="[
                                                'grid size-10 shrink-0 place-items-center rounded-full text-xs font-semibold text-white',
                                                selectedTeamChatUser ? 'bg-blue-600' : 'bg-slate-400',
                                            ]"
                                        >
                                            {{ selectedTeamChatUser ? teamChatUserInitials(selectedTeamChatUser) : '?' }}
                                        </div>
                                        <div class="min-w-0">
                                            <p :class="['truncate text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">
                                                {{ selectedTeamChatUser?.name ?? t('teamChat.chooseConversation') }}
                                            </p>
                                            <p class="mt-1 truncate text-xs text-slate-500">
                                                {{ selectedTeamChatUser?.email ?? t('teamChat.privateChat') }}
                                            </p>
                                        </div>
                                    </div>
                                    <span :class="['shrink-0 rounded-md border px-3 py-1.5 text-xs font-semibold', isDark ? 'border-slate-700 text-slate-300' : 'border-slate-200 text-slate-500']">
                                        {{ t('teamChat.messages', { count: teamChatMessages.length }) }}
                                    </span>
                                </div>
                            </div>

                            <div ref="teamChatScroll" class="scrollbar-hidden min-h-0 flex-1 overflow-auto px-3 py-4 sm:px-5">
                                <div v-if="isLoadingTeamChat" class="grid h-full min-h-80 place-items-center text-center">
                                    <div>
                                        <font-awesome-icon icon="fa-solid fa-spinner" class="text-blue-600" spin />
                                        <p class="mt-3 text-sm font-semibold text-slate-500">{{ t('teamChat.loading') }}</p>
                                    </div>
                                </div>
                                <div v-else-if="teamChatMessages.length" class="space-y-4">
                                    <article
                                        v-for="message in teamChatMessages"
                                        :key="message.id"
                                        :class="['flex gap-3', isOwnTeamChatMessage(message) ? 'justify-end' : 'justify-start']"
                                    >
                                        <div
                                            v-if="!isOwnTeamChatMessage(message)"
                                            class="grid size-9 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-semibold text-white"
                                        >
                                            {{ teamChatUserInitials(message.user) }}
                                        </div>

                                        <div :class="['max-w-[calc(100vw-5rem)] sm:max-w-[780px]', isOwnTeamChatMessage(message) ? 'items-end' : 'items-start']">
                                            <div :class="['mb-1 flex items-center gap-2 text-xs', isOwnTeamChatMessage(message) ? 'justify-end' : 'justify-start']">
                                                <span :class="['font-semibold', isDark ? 'text-slate-200' : 'text-slate-700']">{{ message.user?.name ?? t('ticketDetail.user') }}</span>
                                                <span class="text-slate-400">{{ formatRelativeDate(message.created_at) }}</span>
                                            </div>

                                            <div
                                                :class="[
                                                    isEmojiOnlyMessage(message.body) && !message.attachments?.length
                                                        ? 'px-1 py-0 text-4xl leading-none'
                                                        : 'rounded-md border px-4 py-3 text-sm leading-6 shadow-sm',
                                                    isEmojiOnlyMessage(message.body) && !message.attachments?.length
                                                        ? ''
                                                        : isOwnTeamChatMessage(message)
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
                                                                'block max-w-[220px] overflow-hidden rounded-md border sm:max-w-[260px]',
                                                                isOwnTeamChatMessage(message)
                                                                    ? 'border-white/25 bg-white/10'
                                                                    : isDark ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-white',
                                                            ]"
                                                        >
                                                            <img :src="attachment.url" :alt="attachment.original_name" class="max-h-44 w-full object-cover sm:max-h-52">
                                                        </a>
                                                        <a
                                                            :href="attachment.url"
                                                            target="_blank"
                                                            :class="[
                                                                'flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-xs font-semibold',
                                                                isOwnTeamChatMessage(message)
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
                                            v-if="isOwnTeamChatMessage(message)"
                                            class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-500 text-xs font-semibold text-white"
                                        >
                                            {{ teamChatUserInitials(message.user) }}
                                        </div>
                                    </article>
                                </div>

                                <div v-else class="grid h-full min-h-80 place-items-center text-center">
                                    <div>
                                        <div :class="['mx-auto grid size-12 place-items-center rounded-full', isDark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-500']">
                                            <font-awesome-icon icon="fa-solid fa-comments" />
                                        </div>
                                    <p :class="['mt-4 text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">{{ t('teamChat.noMessages') }}</p>
                                    <p class="mt-1 text-sm text-slate-500">
                                            {{ selectedTeamChatUser ? t('teamChat.writeFirst') : t('teamChat.chooseEmployee') }}
                                    </p>
                                    </div>
                                </div>
                            </div>

                            <form :class="['shrink-0 border-t p-3 sm:p-5', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']" @submit.prevent="sendTeamChatMessage">
                                <p v-if="teamChatError" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                                    {{ teamChatError }}
                                </p>

                                <div class="mb-3 flex flex-wrap items-center gap-1.5 sm:gap-2">
                                    <button type="button" class="grid size-9 place-items-center rounded-md border text-sm font-semibold hover:bg-slate-50" :class="isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-700'" :title="t('ticketDetail.bold')" @click="wrapTeamChatSelection('**')">
                                        <font-awesome-icon icon="fa-solid fa-bold" />
                                    </button>
                                    <button type="button" class="grid size-9 place-items-center rounded-md border text-sm font-semibold hover:bg-slate-50" :class="isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-700'" :title="t('ticketDetail.italic')" @click="wrapTeamChatSelection('*')">
                                        <font-awesome-icon icon="fa-solid fa-italic" />
                                    </button>
                                    <button type="button" class="grid size-9 place-items-center rounded-md border text-sm font-semibold hover:bg-slate-50" :class="isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-700'" :title="t('ticketDetail.underline')" @click="wrapTeamChatSelection('__')">
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
                                        <input ref="teamChatFileInput" class="sr-only" type="file" multiple @change="handleTeamChatFiles">
                                    </label>
                                </div>

                                <textarea
                                    id="team-chat-body"
                                    v-model="teamChatBody"
                                    rows="2"
                                    :placeholder="selectedTeamChatUser ? t('teamChat.placeholder', { name: selectedTeamChatUser.name }) : t('teamChat.chooseFirst')"
                                    :disabled="!selectedTeamChatUser"
                                    :class="[
                                        'w-full resize-none rounded-md border px-3 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                        isDark ? 'border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500' : 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-400',
                                    ]"
                                ></textarea>

                                <div v-if="teamChatFiles.length" class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="(file, index) in teamChatFiles"
                                        :key="`${file.name}-${index}`"
                                        :class="['inline-flex max-w-full items-center gap-2 rounded-md border px-3 py-1.5 text-xs font-semibold', isDark ? 'border-slate-700 bg-slate-950 text-slate-200' : 'border-slate-200 bg-slate-50 text-slate-700']"
                                    >
                                        <span class="truncate">{{ file.name }}</span>
                                        <button type="button" class="text-slate-400 hover:text-red-500" @click="removeTeamChatFile(index)">×</button>
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs text-slate-500">{{ t('ticketDetail.formatting') }}</p>
                                    <button
                                        class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                                        :disabled="isSendingTeamChat || !teamChatBody.trim() || !selectedTeamChatUser"
                                    >
                                        {{ isSendingTeamChat ? t('ticketDetail.sending') : t('ticketDetail.send') }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <aside
                            :class="[
                                'hidden min-h-0 border-t 2xl:block 2xl:border-l 2xl:border-t-0',
                                isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-slate-50',
                            ]"
                        >
                            <div :class="['border-b px-4 py-4', isDark ? 'border-slate-800' : 'border-slate-200']">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p :class="['text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('ticketDetail.mediaFiles') }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ t('ticketDetail.attachments', { count: selectedTeamChatAttachments.length }) }}</p>
                                    </div>
                                    <span :class="['grid size-9 place-items-center rounded-full', isDark ? 'bg-slate-800 text-blue-300' : 'bg-blue-50 text-blue-600']">
                                        <font-awesome-icon icon="fa-solid fa-paperclip" />
                                    </span>
                                </div>
                            </div>

                            <div class="scrollbar-hidden max-h-72 overflow-y-auto p-4 2xl:h-[calc(100%-73px)] 2xl:max-h-none">
                                <div v-if="selectedTeamChatAttachments.length" class="space-y-5">
                                    <div v-if="selectedTeamChatAttachments.some((attachment) => attachment.isImage)">
                                        <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ t('ticketDetail.multimedia') }}</p>
                                        <div class="grid grid-cols-3 gap-2">
                                            <a
                                                v-for="attachment in selectedTeamChatAttachments.filter((item) => item.isImage)"
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
                                                v-for="attachment in selectedTeamChatAttachments"
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
                                                        {{ attachment.human_size }} · {{ attachment.message.user?.name ?? t('ticketDetail.user') }}
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
                                        <p class="mt-1 text-xs text-slate-400">{{ t('teamChat.attachmentsDescription') }}</p>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </section>
                </div>

                <div
                    v-else-if="activeView === 'KnowledgeBase'"
                    :class="[
                        'min-h-0 flex-1 overflow-auto xl:overflow-hidden',
                        isDark ? 'bg-slate-950' : 'bg-[#f4f6f8]',
                    ]"
                >
                    <div class="grid min-h-0 grid-cols-1 xl:h-full xl:grid-cols-[360px_minmax(0,1fr)]">
                        <aside :class="['min-h-0 border-b xl:border-b-0 xl:border-r', isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-white']">
                            <div :class="['border-b p-4', isDark ? 'border-slate-800' : 'border-slate-200']">
                                <label class="relative block">
                                    <font-awesome-icon icon="fa-solid fa-search" class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" />
                                    <input
                                        v-model="knowledgeQuery"
                                        :class="[
                                            'h-10 w-full rounded-md border pl-9 pr-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100',
                                            isDark ? 'border-slate-700 bg-slate-900 text-slate-100 focus:ring-blue-500/20' : 'border-slate-200 bg-slate-50 text-slate-800',
                                        ]"
                                        :placeholder="t('knowledge.search')"
                                        type="search"
                                    >
                                </label>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="category in knowledgeCategories"
                                        :key="category"
                                        :class="[
                                            'rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                                            selectedKnowledgeCategory === category
                                                ? 'border-blue-600 bg-blue-600 text-white'
                                                : isDark ? 'border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                                        ]"
                                        @click="selectedKnowledgeCategory = category"
                                    >
                                        {{ category === 'all' ? t('knowledge.all') : category }}
                                    </button>
                                </div>
                            </div>

                            <div class="scrollbar-hidden min-h-0 max-h-80 overflow-y-auto p-3 xl:h-[calc(100%-129px)] xl:max-h-none">
                                <button
                                    v-for="article in filteredKnowledgeArticles"
                                    :key="article.id"
                                    :class="[
                                        'mb-2 block w-full rounded-md border p-4 text-left transition',
                                        selectedKnowledgeArticle?.id === article.id
                                            ? isDark ? 'border-blue-500 bg-blue-500/10' : 'border-blue-300 bg-blue-50'
                                            : isDark ? 'border-slate-800 bg-slate-900 hover:border-slate-700' : 'border-slate-200 bg-white hover:border-blue-200',
                                    ]"
                                    @click="selectKnowledgeArticle(article)"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p :class="['truncate text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-900']">{{ article.title }}</p>
                                            <p class="mt-1 text-xs font-semibold text-blue-600">{{ article.category }}</p>
                                        </div>
                                        <span :class="['grid size-8 shrink-0 place-items-center rounded-full', isDark ? 'bg-slate-800 text-blue-300' : 'bg-blue-50 text-blue-600']">
                                            <font-awesome-icon icon="fa-solid fa-lightbulb" />
                                        </span>
                                    </div>
                                    <p class="mt-3 line-clamp-2 text-xs leading-5 text-slate-500">{{ article.problem }}</p>
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        <span
                                            v-for="tag in article.tags?.slice(0, 3)"
                                            :key="`${article.id}-${tag}`"
                                            :class="['rounded-full px-2 py-1 text-[11px] font-semibold', isDark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-500']"
                                        >
                                            {{ tag }}
                                        </span>
                                    </div>
                                </button>

                                <div v-if="!filteredKnowledgeArticles.length" class="px-4 py-12 text-center">
                                    <div :class="['mx-auto grid size-12 place-items-center rounded-full', isDark ? 'bg-slate-900 text-slate-400' : 'bg-slate-100 text-slate-500']">
                                        <font-awesome-icon icon="fa-solid fa-search" />
                                    </div>
                                    <p :class="['mt-4 text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">{{ t('knowledge.emptyTitle') }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ t('knowledge.emptyDescription') }}</p>
                                </div>
                            </div>
                        </aside>

                        <section class="min-h-0 overflow-visible p-4 xl:overflow-y-auto xl:p-5">
                            <div v-if="selectedKnowledgeArticle" class="mx-auto max-w-5xl space-y-5">
                                <div :class="['rounded-md border p-5 shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">{{ selectedKnowledgeArticle.category }}</p>
                                            <h3 :class="['mt-2 text-2xl font-semibold', isDark ? 'text-white' : 'text-slate-950']">{{ selectedKnowledgeArticle.title }}</h3>
                                            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-500">{{ selectedKnowledgeArticle.problem }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <span
                                            v-for="tag in selectedKnowledgeArticle.tags"
                                            :key="`selected-${tag}`"
                                            :class="['rounded-full px-2.5 py-1 text-xs font-semibold', isDark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-500']"
                                        >
                                            {{ tag }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
                                    <div class="space-y-5">
                                        <article :class="['rounded-md border p-5 shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('knowledge.symptoms') }}</p>
                                            <ul class="mt-4 space-y-2 text-sm leading-6">
                                                <li
                                                    v-for="line in splitKnowledgeLines(selectedKnowledgeArticle.symptoms)"
                                                    :key="line"
                                                    class="flex gap-3"
                                                >
                                                    <span class="mt-2 size-1.5 shrink-0 rounded-full bg-amber-400"></span>
                                                    <span :class="isDark ? 'text-slate-300' : 'text-slate-700'">{{ line }}</span>
                                                </li>
                                            </ul>
                                        </article>

                                        <article :class="['rounded-md border p-5 shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('knowledge.solution') }}</p>
                                            <ol class="mt-4 space-y-3 text-sm leading-6">
                                                <li
                                                    v-for="line in splitKnowledgeLines(selectedKnowledgeArticle.solution)"
                                                    :key="line"
                                                    class="flex gap-3"
                                                >
                                                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-blue-600 text-[11px] font-semibold text-white">
                                                        {{ line.match(/^(\d+)/)?.[1] ?? '•' }}
                                                    </span>
                                                    <span :class="isDark ? 'text-slate-300' : 'text-slate-700'">{{ line.replace(/^\d+\.\s*/, '') }}</span>
                                                </li>
                                            </ol>
                                        </article>
                                    </div>

                                    <aside :class="['h-fit rounded-md border p-5 shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('knowledge.readyReply') }}</p>
                                                <h4 :class="['mt-1 text-base font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('knowledge.toCustomer') }}</h4>
                                            </div>
                                            <button
                                                type="button"
                                                :class="[
                                                    'grid size-9 place-items-center rounded-md border transition',
                                                    copiedKnowledgeArticleId === selectedKnowledgeArticle.id
                                                        ? 'border-emerald-500 bg-emerald-500 text-white'
                                                        : isDark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                                                ]"
                                                :title="t('knowledge.copyReply')"
                                                @click="copyKnowledgeReply(selectedKnowledgeArticle)"
                                            >
                                                <font-awesome-icon icon="fa-solid fa-copy" />
                                            </button>
                                        </div>
                                        <div :class="['mt-4 whitespace-pre-line rounded-md border p-4 text-sm leading-6', isDark ? 'border-slate-800 bg-slate-950 text-slate-200' : 'border-slate-200 bg-slate-50 text-slate-700']">
                                            {{ selectedKnowledgeArticle.customer_reply }}
                                        </div>
                                    </aside>
                                </div>
                            </div>

                            <div v-else class="grid h-full min-h-96 place-items-center text-center">
                                <div>
                                    <div :class="['mx-auto grid size-14 place-items-center rounded-full', isDark ? 'bg-slate-900 text-slate-400' : 'bg-white text-slate-400']">
                                        <font-awesome-icon icon="fa-solid fa-book-open" />
                                    </div>
                                    <p :class="['mt-4 text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">{{ t('knowledge.emptyBaseTitle') }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ t('knowledge.emptyBaseDescription') }}</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div
                    v-else-if="activeView === 'Reports'"
                    :class="[
                        'min-h-0 flex-1 overflow-auto p-4 sm:p-6',
                        isDark ? 'bg-slate-950' : 'bg-[#f4f6f8]',
                    ]"
                >
                    <div class="mx-auto max-w-7xl space-y-5">
                        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            <article
                                v-for="item in reportKpis"
                                :key="item.label"
                                :class="['rounded-md border p-4 shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-medium text-slate-500">{{ item.label }}</p>
                                        <p :class="['mt-2 text-2xl font-semibold', isDark ? 'text-white' : 'text-slate-950']">{{ item.value }}</p>
                                    </div>
                                    <span :class="['mt-1 size-2.5 rounded-full', item.accent]"></span>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">{{ item.detail }}</p>
                            </article>
                        </section>

                        <section class="grid gap-5 xl:grid-cols-3">
                            <article :class="['rounded-md border p-5 shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                                <div class="mb-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('reports.statuses') }}</p>
                                    <h3 :class="['mt-1 text-base font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('reports.statusDistribution') }}</h3>
                                </div>
                                <div class="space-y-4">
                                    <div v-for="item in statusReport" :key="item.key">
                                        <div class="mb-1 flex items-center justify-between text-sm">
                                            <span :class="isDark ? 'text-slate-300' : 'text-slate-700'">{{ item.label }}</span>
                                            <span class="font-semibold">{{ item.value }}</span>
                                        </div>
                                        <div :class="['h-2 overflow-hidden rounded-full', isDark ? 'bg-slate-800' : 'bg-slate-100']">
                                            <div class="h-full rounded-full bg-blue-600" :style="{ width: `${item.percent}%` }"></div>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <article :class="['rounded-md border p-5 shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                                <div class="mb-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('reports.priorities') }}</p>
                                    <h3 :class="['mt-1 text-base font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('reports.queueRisk') }}</h3>
                                </div>
                                <div class="space-y-4">
                                    <div v-for="item in priorityReport" :key="item.key">
                                        <div class="mb-1 flex items-center justify-between text-sm">
                                            <span :class="isDark ? 'text-slate-300' : 'text-slate-700'">{{ item.label }}</span>
                                            <span class="font-semibold">{{ item.value }}</span>
                                        </div>
                                        <div :class="['h-2 overflow-hidden rounded-full', isDark ? 'bg-slate-800' : 'bg-slate-100']">
                                            <div
                                                :class="['h-full rounded-full', item.key === 'urgent' ? 'bg-red-500' : item.key === 'high' ? 'bg-orange-500' : 'bg-slate-500']"
                                                :style="{ width: `${item.percent}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <article :class="['rounded-md border p-5 shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                                <div class="mb-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('reports.channels') }}</p>
                                    <h3 :class="['mt-1 text-base font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('reports.contactSources') }}</h3>
                                </div>
                                <div class="space-y-4">
                                    <div v-for="item in channelReport" :key="item.key">
                                        <div class="mb-1 flex items-center justify-between text-sm">
                                            <span :class="isDark ? 'text-slate-300' : 'text-slate-700'">{{ item.label }}</span>
                                            <span class="font-semibold">{{ item.value }}</span>
                                        </div>
                                        <div :class="['h-2 overflow-hidden rounded-full', isDark ? 'bg-slate-800' : 'bg-slate-100']">
                                            <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${item.percent}%` }"></div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </section>

                        <section :class="['rounded-md border shadow-sm', isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white']">
                            <div class="border-b px-5 py-4" :class="isDark ? 'border-slate-800' : 'border-slate-100'">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('reports.agents') }}</p>
                                <h3 :class="['mt-1 text-base font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ t('reports.teamLoad') }}</h3>
                            </div>
                            <div class="divide-y md:hidden" :class="isDark ? 'divide-slate-800' : 'divide-slate-100'">
                                <article
                                    v-for="agent in agentReport"
                                    :key="`mobile-report-${agent.name}`"
                                    class="p-4"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-sm font-semibold" :class="isDark ? 'text-slate-100' : 'text-slate-900'">{{ assigneeLabel(agent.name) }}</p>
                                        <span class="text-xs font-semibold" :class="agent.urgent ? 'text-red-500' : 'text-slate-500'">
                                            {{ t('reports.urgent') }}: {{ agent.urgent }}
                                        </span>
                                    </div>
                                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                        <div :class="['rounded-md px-3 py-2', isDark ? 'bg-slate-950' : 'bg-slate-50']">
                                            <dt class="text-slate-500">{{ t('reports.all') }}</dt>
                                            <dd class="mt-1 font-semibold">{{ agent.total }}</dd>
                                        </div>
                                        <div :class="['rounded-md px-3 py-2', isDark ? 'bg-slate-950' : 'bg-slate-50']">
                                            <dt class="text-slate-500">{{ t('reports.active') }}</dt>
                                            <dd class="mt-1 font-semibold">{{ agent.active }}</dd>
                                        </div>
                                        <div :class="['rounded-md px-3 py-2', isDark ? 'bg-slate-950' : 'bg-slate-50']">
                                            <dt class="text-slate-500">{{ t('reports.closedResolved') }}</dt>
                                            <dd class="mt-1 font-semibold">{{ agent.resolved }}</dd>
                                        </div>
                                    </dl>
                                </article>
                            </div>

                            <div class="hidden overflow-x-auto md:block">
                                <table class="w-full min-w-[720px]">
                                    <thead :class="isDark ? 'bg-slate-950 text-slate-400' : 'bg-slate-50 text-slate-500'">
                                        <tr class="text-left text-xs font-semibold uppercase tracking-wide">
                                            <th class="px-5 py-3">{{ t('reports.agent') }}</th>
                                            <th class="px-5 py-3">{{ t('reports.all') }}</th>
                                            <th class="px-5 py-3">{{ t('reports.active') }}</th>
                                            <th class="px-5 py-3">{{ t('reports.closedResolved') }}</th>
                                            <th class="px-5 py-3">{{ t('reports.urgent') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="agent in agentReport"
                                            :key="agent.name"
                                            class="border-t"
                                            :class="isDark ? 'border-slate-800' : 'border-slate-100'"
                                        >
                                            <td class="px-5 py-4 text-sm font-semibold" :class="isDark ? 'text-slate-100' : 'text-slate-900'">{{ assigneeLabel(agent.name) }}</td>
                                            <td class="px-5 py-4 text-sm">{{ agent.total }}</td>
                                            <td class="px-5 py-4 text-sm">{{ agent.active }}</td>
                                            <td class="px-5 py-4 text-sm">{{ agent.resolved }}</td>
                                            <td class="px-5 py-4 text-sm font-semibold" :class="agent.urgent ? 'text-red-500' : 'text-slate-500'">{{ agent.urgent }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>

                <div
                    v-else-if="activeView === 'Settings'"
                    :class="[
                        'min-h-0 flex-1 overflow-auto',
                        isDark ? 'bg-slate-950' : 'bg-[#f4f6f8]',
                    ]"
                >
                    <div class="mx-auto flex min-h-full w-full max-w-7xl gap-4 p-3 sm:p-4 xl:h-full xl:overflow-hidden">
                        <section class="min-h-0 flex-1 space-y-4 pb-6 xl:overflow-y-auto xl:overscroll-contain xl:pr-1">
                            <div
                                v-if="savedStatus"
                                :class="[
                                    'rounded-md border px-4 py-3 text-sm font-semibold',
                                    isDark ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200' : 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                ]"
                            >
                                {{ savedStatus }}
                            </div>

                            <div
                                :class="[
                                    'rounded-md border p-4 shadow-sm',
                                    isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white',
                                ]"
                            >
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('settings.appearance') }}</p>
                                        <h3 :class="['mt-1 text-base font-semibold', isDark ? 'text-white' : 'text-slate-900']">
                                            {{ t('settings.systemTheme') }}
                                        </h3>
                                    </div>

                                    <div
                                        :class="[
                                            'grid grid-cols-2 rounded-md border p-1',
                                            isDark ? 'border-slate-700 bg-slate-950' : 'border-slate-200 bg-slate-100',
                                        ]"
                                    >
                                        <button
                                            :class="[
                                                'rounded px-4 py-2 text-sm font-semibold transition',
                                                !isDark ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-300 hover:bg-slate-800',
                                            ]"
                                            @click="theme = 'light'"
                                        >
                                            {{ t('settings.light') }}
                                        </button>
                                        <button
                                            :class="[
                                                'rounded px-4 py-2 text-sm font-semibold transition',
                                                isDark ? 'bg-slate-800 text-blue-300 shadow-sm' : 'text-slate-600 hover:bg-white',
                                            ]"
                                            @click="theme = 'dark'"
                                        >
                                            {{ t('settings.dark') }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                :class="[
                                    'rounded-md border p-4 shadow-sm',
                                    isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white',
                                ]"
                            >
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('settings.accountSection') }}</p>
                                    <h3 :class="['mt-1 text-base font-semibold', isDark ? 'text-white' : 'text-slate-900']">
                                        {{ t('settings.accountData') }}
                                    </h3>
                                </div>

                                <form class="mt-4 grid gap-3 sm:grid-cols-2" action="/settings/account" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="_token" :value="csrfToken">
                                    <input type="hidden" name="_method" value="PATCH">

                                    <div class="sm:col-span-2">
                                        <label class="text-sm font-semibold text-slate-500" for="account-avatar">{{ t('settings.profilePhoto') }}</label>
                                        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center">
                                            <img
                                                v-if="currentUserAvatarUrl"
                                                :src="currentUserAvatarUrl"
                                                :alt="currentUser.name"
                                                class="size-14 rounded-full object-cover ring-2"
                                                :class="isDark ? 'ring-slate-700' : 'ring-slate-200'"
                                            >
                                            <div
                                                v-else
                                                class="grid size-14 place-items-center rounded-full bg-emerald-500 text-base font-semibold text-white"
                                            >
                                                {{ userInitials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <input
                                                    id="account-avatar"
                                                    name="avatar"
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    class="sr-only"
                                                    @change="handleAvatarFileChange"
                                                >
                                                <div
                                                    :class="[
                                                        'flex min-h-10 overflow-hidden rounded-md border text-sm',
                                                        isDark ? 'border-slate-700 bg-slate-950 text-slate-300' : 'border-slate-300 bg-white text-slate-700',
                                                    ]"
                                                >
                                                    <label
                                                        for="account-avatar"
                                                        class="flex cursor-pointer items-center bg-blue-600 px-4 font-semibold text-white hover:bg-blue-700"
                                                    >
                                                        {{ t('settings.chooseFile') }}
                                                    </label>
                                                    <span class="flex min-w-0 flex-1 items-center px-3">
                                                        <span class="truncate">{{ avatarFileName || t('settings.noFileChosen') }}</span>
                                                    </span>
                                                </div>
                                                <p class="mt-2 text-xs text-slate-500">{{ t('settings.avatarHint') }}</p>
                                                <p v-if="accountErrors.avatar" class="mt-2 text-xs font-semibold text-red-500">{{ accountErrors.avatar }}</p>
                                                <label v-if="currentUserAvatarUrl" class="mt-3 flex items-center gap-2 text-sm text-slate-500">
                                                    <input name="remove_avatar" value="1" type="checkbox" class="rounded border-slate-300 text-blue-600">
                                                    {{ t('settings.removeAvatar') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="text-sm font-semibold text-slate-500" for="account-name">{{ t('settings.name') }}</label>
                                        <input
                                            id="account-name"
                                            name="name"
                                            type="text"
                                            :value="currentUser.name"
                                            required
                                            :class="[
                                                'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                                isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900',
                                            ]"
                                        >
                                        <p v-if="accountErrors.name" class="mt-2 text-xs font-semibold text-red-500">{{ accountErrors.name }}</p>
                                    </div>

                                    <div>
                                        <label class="text-sm font-semibold text-slate-500" for="account-email">{{ t('settings.email') }}</label>
                                        <input
                                            id="account-email"
                                            name="email"
                                            type="email"
                                            :value="currentUser.email"
                                            required
                                            :class="[
                                                'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                                isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900',
                                            ]"
                                        >
                                        <p v-if="accountErrors.email" class="mt-2 text-xs font-semibold text-red-500">{{ accountErrors.email }}</p>
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label class="text-sm font-semibold text-slate-500" for="account-locale">{{ t('settings.language') }}</label>
                                        <select
                                            id="account-locale"
                                            name="locale"
                                            v-model="selectedLocale"
                                            :class="[
                                                'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                                isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900',
                                            ]"
                                            @change="saveLocalePreference"
                                        >
                                            <option
                                                v-for="[code, label] in supportedLocaleOptions"
                                                :key="code"
                                                :value="code"
                                            >
                                                {{ label }}
                                            </option>
                                        </select>
                                        <p class="mt-2 text-xs text-slate-500">{{ t('settings.languageHint') }}</p>
                                        <p v-if="accountErrors.locale" class="mt-2 text-xs font-semibold text-red-500">{{ accountErrors.locale }}</p>
                                        <p v-else-if="localeSaveStatus" class="mt-2 text-xs font-semibold" :class="localeSaveStatus === 'error' ? 'text-red-500' : 'text-emerald-600'">
                                            {{ localeSaveStatus === 'saving' ? t('app.saving') : localeSaveStatus === 'saved' ? t('app.saved') : t('app.notSaved') }}
                                        </p>
                                    </div>

                                    <div class="sm:col-span-2">
                                        <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                                            {{ t('settings.saveData') }}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div
                                :class="[
                                    'rounded-md border p-4 shadow-sm',
                                    isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white',
                                ]"
                            >
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('settings.notifications') }}</p>
                                    <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                                        <h3 :class="['text-base font-semibold', isDark ? 'text-white' : 'text-slate-900']">
                                            {{ t('settings.notificationPreferences') }}
                                        </h3>
                                        <span
                                            v-if="notificationSaveStatus"
                                            :class="[
                                                'text-xs font-semibold',
                                                notificationSaveStatus === 'saved' ? 'text-emerald-500' : notificationSaveStatus === 'error' ? 'text-red-500' : 'text-slate-400',
                                            ]"
                                        >
                                            {{ notificationSaveStatus === 'saving' ? t('app.saving') : notificationSaveStatus === 'saved' ? t('app.saved') : t('app.notSaved') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-2 md:grid-cols-2">
                                    <label
                                        v-for="item in notifications"
                                        :key="item.key"
                                        :class="[
                                            'flex min-h-20 cursor-pointer items-center justify-between gap-4 rounded-md border p-3 transition',
                                            item.enabled
                                                ? isDark ? 'border-blue-500/30 bg-blue-500/10' : 'border-blue-100 bg-blue-50/40'
                                                : isDark ? 'border-slate-800 bg-slate-950' : 'border-slate-200 bg-white',
                                        ]"
                                    >
                                        <span>
                                            <span :class="['block text-sm font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">
                                                {{ t(item.labelKey) }}
                                            </span>
                                            <span class="mt-1 block text-xs leading-5 text-slate-500">{{ t(item.descriptionKey) }}</span>
                                        </span>
                                        <input
                                            v-model="item.enabled"
                                            class="sr-only"
                                            type="checkbox"
                                            @change="saveNotificationPreferences"
                                        >
                                        <span
                                            :class="[
                                                'relative h-6 w-11 shrink-0 rounded-full transition',
                                                item.enabled ? 'bg-blue-600' : isDark ? 'bg-slate-700' : 'bg-slate-300',
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1 size-4 rounded-full bg-white transition',
                                                    item.enabled ? 'left-6' : 'left-1',
                                                ]"
                                            ></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </section>

                        <aside
                            :class="[
                                'hidden h-fit w-80 shrink-0 rounded-md border p-5 shadow-sm xl:block',
                                isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white',
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="currentUserAvatarUrl"
                                    :src="currentUserAvatarUrl"
                                    :alt="currentUser.name"
                                    class="size-12 shrink-0 rounded-full object-cover ring-2"
                                    :class="isDark ? 'ring-slate-700' : 'ring-slate-200'"
                                >
                                <div v-else class="grid size-12 place-items-center rounded-full bg-emerald-500 text-sm font-semibold text-white">
                                    {{ userInitials }}
                                </div>
                                <div class="min-w-0">
                                    <p :class="['truncate text-sm font-semibold', isDark ? 'text-white' : 'text-slate-900']">{{ currentUser.name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ currentUser.email }}</p>
                                </div>
                            </div>

                            <dl class="mt-6 space-y-4 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">{{ t('settings.profileSummaryTheme') }}</dt>
                                    <dd :class="['font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">
                                        {{ isDark ? t('settings.dark') : t('settings.light') }}
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">{{ t('settings.activeNotifications') }}</dt>
                                    <dd :class="['font-semibold', isDark ? 'text-slate-100' : 'text-slate-800']">
                                        {{ notifications.filter((item) => item.enabled).length }}/{{ notifications.length }}
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">{{ t('settings.session') }}</dt>
                                    <dd class="font-semibold text-emerald-500">{{ t('settings.active') }}</dd>
                                </div>
                            </dl>
                        </aside>
                    </div>
                </div>

                <div
                    v-else-if="activeView === 'Agents'"
                    :class="[
                        'min-h-0 flex-1 overflow-auto p-4 sm:p-6',
                        isDark ? 'bg-slate-950' : 'bg-[#f4f6f8]',
                    ]"
                >
                    <div class="mx-auto max-w-6xl">
                        <!-- Header with Add Button -->
                        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h3 :class="['text-xl font-semibold', isDark ? 'text-white' : 'text-slate-900']">
                                    {{ isAdmin ? t('agentsView.adminTitle') : t('agentsView.directoryTitle') }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ isAdmin ? t('agentsView.adminDescription') : t('agentsView.directoryDescription') }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span :class="['rounded-full border px-3 py-1 text-xs font-semibold', isDark ? 'border-slate-700 bg-slate-900 text-slate-300' : 'border-slate-200 bg-white text-slate-600']">
                                        {{ t('agentsView.admin') }}: {{ agentDirectoryStats.admins }}
                                    </span>
                                    <span :class="['rounded-full border px-3 py-1 text-xs font-semibold', isDark ? 'border-slate-700 bg-slate-900 text-slate-300' : 'border-slate-200 bg-white text-slate-600']">
                                        {{ t('agentsView.agent') }}: {{ agentDirectoryStats.agents }}
                                    </span>
                                </div>
                            </div>
                            <button
                                v-if="isAdmin"
                                @click="openAddForm"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                            >
                                + {{ t('agentsView.add') }}
                            </button>
                        </div>

                        <!-- User Form Modal -->
                        <div
                            v-if="showUserForm && isAdmin"
                            :class="[
                                'mb-6 rounded-md border p-6 shadow-sm',
                                isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white',
                            ]"
                        >
                            <h4 :class="['mb-4 text-lg font-semibold', isDark ? 'text-white' : 'text-slate-900']">
                                {{ editingUser ? t('agentsView.editAgent') : t('agentsView.addAgent') }}
                            </h4>

                            <div v-if="formError" class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
                                {{ formError }}
                            </div>

                            <div v-if="formSuccess" class="mb-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-700">
                                {{ formSuccess }}
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium" :class="isDark ? 'text-slate-300' : 'text-slate-700'">
                                        {{ t('settings.name') }}
                                    </label>
                                    <input
                                        v-model="userForm.name"
                                        type="text"
                                        required
                                        :class="[
                                            'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                            isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900',
                                        ]"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium" :class="isDark ? 'text-slate-300' : 'text-slate-700'">
                                        Email
                                    </label>
                                    <input
                                        v-model="userForm.email"
                                        type="email"
                                        required
                                        :class="[
                                            'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                            isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900',
                                        ]"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium" :class="isDark ? 'text-slate-300' : 'text-slate-700'">
                                        {{ t('login.password') }} {{ editingUser ? t('agentsView.passwordHint') : '' }}
                                    </label>
                                    <input
                                        v-model="userForm.password"
                                        type="password"
                                        :required="!editingUser"
                                        :class="[
                                            'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                            isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900',
                                        ]"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium" :class="isDark ? 'text-slate-300' : 'text-slate-700'">
                                        {{ t('agentsView.role') }}
                                    </label>
                                    <select
                                        v-model="userForm.role"
                                        :class="[
                                            'mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                            isDark ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-900',
                                        ]"
                                    >
                                        <option value="agent">{{ t('agentsView.agent') }}</option>
                                        <option value="admin">{{ t('agentsView.admin') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-6 flex gap-2">
                                <button
                                    @click="submitUserForm"
                                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                                >
                                    {{ editingUser ? t('agentsView.saveChanges') : t('agentsView.add') }}
                                </button>
                                <button
                                    @click="showUserForm = false; resetForm()"
                                    :class="[
                                        'rounded-md border px-4 py-2 text-sm font-semibold',
                                        isDark ? 'border-slate-700 text-slate-300 hover:bg-slate-800' : 'border-slate-300 text-slate-600 hover:bg-slate-100',
                                    ]"
                                >
                                    {{ t('agentsView.cancel') }}
                                </button>
                            </div>
                        </div>

                        <!-- Agents Table -->
                        <div
                            :class="[
                                'rounded-md border shadow-sm overflow-hidden',
                                isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white',
                            ]"
                        >
                            <div v-if="isLoadingAgents" class="p-6 text-center text-slate-500">
                                {{ t('agentsView.loading') }}
                            </div>

                            <div v-else-if="agents.length === 0" class="p-6 text-center text-slate-500">
                                {{ t('agentsView.empty') }}
                            </div>

                            <template v-else>
                                <div class="divide-y md:hidden" :class="isDark ? 'divide-slate-800' : 'divide-slate-100'">
                                    <article
                                        v-for="agent in agents"
                                        :key="`mobile-agent-${agent.id}`"
                                        class="p-4"
                                    >
                                        <div class="flex items-start gap-3">
                                            <img
                                                v-if="agent.avatar_url"
                                                :src="agent.avatar_url"
                                                :alt="agent.name"
                                                class="size-11 shrink-0 rounded-full object-cover"
                                            >
                                            <span
                                                v-else
                                                class="grid size-11 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-semibold text-white"
                                            >
                                                {{ teamChatUserInitials(agent) }}
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-semibold" :class="isDark ? 'text-slate-100' : 'text-slate-900'">
                                                    {{ agent.name }}
                                                </p>
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ agent.email }}</p>
                                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                                    <span
                                                        :class="[
                                                            'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                                            agent.role === 'admin'
                                                                ? 'border-purple-200 bg-purple-50 text-purple-700'
                                                                : 'border-blue-200 bg-blue-50 text-blue-700',
                                                        ]"
                                                    >
                                                        {{ agent.role === 'admin' ? t('agentsView.admin') : t('agentsView.agent') }}
                                                    </span>
                                                    <span class="text-xs text-slate-500">
                                                        {{ new Date(agent.created_at).toLocaleDateString(locale) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="isAdmin" class="mt-4 flex gap-3 text-sm font-semibold">
                                            <button
                                                @click="openEditForm(agent)"
                                                class="text-blue-600 hover:text-blue-900"
                                            >
                                                {{ t('ticketDetail.edit') }}
                                            </button>
                                            <button
                                                @click="deleteUser(agent)"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                {{ t('agentsView.delete') }}
                                            </button>
                                        </div>
                                    </article>
                                </div>

                                <div class="hidden overflow-x-auto md:block">
                            <table class="w-full">
                                <thead :class="isDark ? 'bg-slate-800' : 'bg-slate-50'">
                                    <tr class="border-b" :class="isDark ? 'border-slate-800' : 'border-slate-200'">
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                                            {{ t('agentsView.name') }}
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                                            Email
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                                            {{ t('agentsView.role') }}
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                                            {{ t('agentsView.addedAt') }}
                                        </th>
                                        <th v-if="isAdmin" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                                            {{ t('agentsView.actions') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="agent in agents"
                                        :key="agent.id"
                                        class="border-b transition hover:bg-blue-50/50"
                                        :class="[isDark ? 'border-slate-800 hover:bg-slate-800' : 'border-slate-100']"
                                    >
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <img
                                                    v-if="agent.avatar_url"
                                                    :src="agent.avatar_url"
                                                    :alt="agent.name"
                                                    class="size-9 rounded-full object-cover"
                                                >
                                                <span
                                                    v-else
                                                    class="grid size-9 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-semibold text-white"
                                                >
                                                    {{ teamChatUserInitials(agent) }}
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold" :class="isDark ? 'text-slate-100' : 'text-slate-900'">
                                                        {{ agent.name }}
                                                    </p>
                                                    <p class="mt-0.5 text-xs text-slate-500">
                                                        {{ agent.role === 'admin' ? t('agentsView.systemAdmin') : t('agentsView.supportAgent') }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                                            {{ agent.email }}
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <span
                                                :class="[
                                                    'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                                    agent.role === 'admin'
                                                        ? 'border-purple-200 bg-purple-50 text-purple-700'
                                                        : 'border-blue-200 bg-blue-50 text-blue-700',
                                                ]"
                                            >
                                                {{ agent.role === 'admin' ? t('agentsView.admin') : t('agentsView.agent') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-500">
                                            {{ new Date(agent.created_at).toLocaleDateString(locale) }}
                                        </td>
                                        <td v-if="isAdmin" class="px-6 py-4 space-x-2 text-sm">
                                            <button
                                                @click="openEditForm(agent)"
                                                class="text-blue-600 hover:text-blue-900 font-medium"
                                            >
                                                {{ t('ticketDetail.edit') }}
                                            </button>
                                            <button
                                                @click="deleteUser(agent)"
                                                class="text-red-600 hover:text-red-900 font-medium"
                                            >
                                                {{ t('agentsView.delete') }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <nav
            :class="[
                'shrink-0 border-t px-2 pb-[max(env(safe-area-inset-bottom),0.5rem)] pt-2 lg:hidden',
                isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-white',
            ]"
        >
            <div class="grid grid-cols-6 gap-1">
                <a
                    v-for="item in navigation"
                    :key="`mobile-${item.labelKey}`"
                    :href="item.href"
                    :class="[
                        'relative flex min-w-0 flex-col items-center gap-1 rounded-md px-1 py-2 text-[10px] font-semibold transition',
                        activeView === item.view
                            ? 'bg-blue-600 text-white'
                            : isDark ? 'text-slate-300 hover:bg-slate-800' : 'text-slate-500 hover:bg-slate-50',
                    ]"
                    :aria-label="t(item.labelKey)"
                    :title="t(item.labelKey)"
                    @click.prevent="navigateTo(item)"
                >
                    <font-awesome-icon :icon="item.icon" class="text-sm" />
                    <span class="w-full truncate text-center">{{ t(item.labelKey) }}</span>
                    <span
                        v-if="item.badge"
                        class="absolute right-1 top-1 size-2 rounded-full bg-red-500 ring-2"
                        :class="activeView === item.view ? 'ring-blue-600' : isDark ? 'ring-slate-900' : 'ring-white'"
                    ></span>
                </a>
            </div>
        </nav>

        <TicketFormModal
            :show="showCreateTicketModal"
            mode="create"
            :agents="agents"
            :is-dark="isDark"
            @close="showCreateTicketModal = false"
        />
    </main>
</template>

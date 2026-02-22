<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type FinanceLine = {
    assignment_id: number;
    employee: {
        id: number;
        name: string;
        email: string;
    } | null;
    hours_worked: number;
    wage_total: number;
    price_total: number;
    margin_total: number;
    timesheet_status: 'draft' | 'submitted' | 'approved' | null;
};

type FinanceBooking = {
    id: number;
    title: string;
    starts_at: string;
    ends_at: string;
    executed_at: string | null;
    workflow_status: 'open' | 'approved' | 'executed' | 'invoiced' | 'paid';
    is_invoiced: boolean;
    is_paid: boolean;
    can_mark_invoiced: boolean;
    can_unmark_invoiced: boolean;
    can_mark_paid: boolean;
    can_unmark_paid: boolean;
    company: {
        id: number;
        name: string;
    } | null;
    company_address: {
        id: number;
        label: string;
        formatted: string;
    } | null;
    totals: {
        wage_total: number;
        price_total: number;
        margin_total: number;
    };
    lines: FinanceLine[];
};

const props = defineProps<{
    bookings: FinanceBooking[];
    filters: {
        stage: 'all' | 'invoicing' | 'payroll';
        from_date: string | null;
        to_date: string | null;
    };
}>();

const openBookingId = ref<number | null>(null);
const selectedBookingIds = ref<number[]>([]);

const filterState = ref({
    stage: props.filters.stage ?? 'invoicing',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
});

watch(() => props.bookings, () => {
    const visibleIds = new Set(props.bookings.map((booking) => booking.id));
    selectedBookingIds.value = selectedBookingIds.value.filter((id) => visibleIds.has(id));
}, { deep: true });

const totalPrice = computed(() => props.bookings.reduce((sum, booking) => sum + booking.totals.price_total, 0));
const totalWage = computed(() => props.bookings.reduce((sum, booking) => sum + booking.totals.wage_total, 0));
const totalMargin = computed(() => totalPrice.value - totalWage.value);

const selectedCount = computed(() => selectedBookingIds.value.length);
const allSelected = computed(() => props.bookings.length > 0 && selectedBookingIds.value.length === props.bookings.length);

const toggleDetails = (bookingId: number) => {
    openBookingId.value = openBookingId.value === bookingId ? null : bookingId;
};

const applyFilters = () => {
    router.get(route('admin.finance.index'), {
        stage: filterState.value.stage,
        from_date: filterState.value.from_date || null,
        to_date: filterState.value.to_date || null,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterState.value = {
        stage: 'invoicing',
        from_date: '',
        to_date: '',
    };
    applyFilters();
};

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedBookingIds.value = [];
        return;
    }

    selectedBookingIds.value = props.bookings.map((booking) => booking.id);
};

const toggleSelected = (bookingId: number) => {
    if (selectedBookingIds.value.includes(bookingId)) {
        selectedBookingIds.value = selectedBookingIds.value.filter((id) => id !== bookingId);
        return;
    }

    selectedBookingIds.value = [...selectedBookingIds.value, bookingId];
};

const markInvoiced = (bookingId: number) => {
    router.post(route('admin.finance.bookings.mark-invoiced', bookingId), {}, { preserveScroll: true });
};

const unmarkInvoiced = (bookingId: number) => {
    router.post(route('admin.finance.bookings.unmark-invoiced', bookingId), {}, { preserveScroll: true });
};

const markPaid = (bookingId: number) => {
    router.post(route('admin.finance.bookings.mark-paid', bookingId), {}, { preserveScroll: true });
};

const unmarkPaid = (bookingId: number) => {
    router.post(route('admin.finance.bookings.unmark-paid', bookingId), {}, { preserveScroll: true });
};

const bulkMarkInvoiced = () => {
    if (!selectedCount.value) {
        return;
    }

    router.post(route('admin.finance.bookings.bulk-mark-invoiced'), {
        booking_ids: selectedBookingIds.value,
    }, { preserveScroll: true });
};

const bulkMarkPaid = () => {
    if (!selectedCount.value) {
        return;
    }

    router.post(route('admin.finance.bookings.bulk-mark-paid'), {
        booking_ids: selectedBookingIds.value,
    }, { preserveScroll: true });
};

const buildExportQuery = () => {
    const params = new URLSearchParams();

    selectedBookingIds.value.forEach((id) => params.append('booking_ids[]', String(id)));

    if (filterState.value.stage) {
        params.append('stage', filterState.value.stage);
    }

    if (filterState.value.from_date) {
        params.append('from_date', filterState.value.from_date);
    }

    if (filterState.value.to_date) {
        params.append('to_date', filterState.value.to_date);
    }

    return params.toString();
};

const exportSelectedInvoices = () => {
    if (!selectedCount.value) {
        return;
    }

    window.location.href = `${route('admin.finance.export.invoices-csv')}?${buildExportQuery()}`;
};

const exportSelectedPayroll = () => {
    if (!selectedCount.value) {
        return;
    }

    window.location.href = `${route('admin.finance.export.payroll-csv')}?${buildExportQuery()}`;
};

const formatMoney = (value: number) => new Intl.NumberFormat('da-DK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(value);

const formatDateTime = (value: string | null) => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('da-DK', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
};

const workflowLabel = (status: FinanceBooking['workflow_status']) => {
    if (status === 'paid') {
        return 'Betalt';
    }

    if (status === 'invoiced') {
        return 'Faktureret';
    }

    if (status === 'executed') {
        return 'Eksekveret';
    }

    if (status === 'approved') {
        return 'Godkendt';
    }

    return 'Åben';
};
</script>

<template>
    <Head title="Økonomi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Økonomi: Faktura og løn</h2>
                <div class="flex flex-wrap gap-2">
                    <a
                        :href="route('admin.finance.export.csv', {
                            stage: filterState.stage,
                            from_date: filterState.from_date || null,
                            to_date: filterState.to_date || null,
                        })"
                        class="rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        download
                    >
                        Eksportér booking CSV
                    </a>
                    <a
                        :href="route('admin.finance.export.csv-lines', {
                            stage: filterState.stage,
                            from_date: filterState.from_date || null,
                            to_date: filterState.to_date || null,
                        })"
                        class="rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        download
                    >
                        Eksportér linje CSV
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <div class="grid gap-3 md:grid-cols-5">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Stage
                            <select v-model="filterState.stage" class="mt-1 block w-full rounded border-gray-300 text-sm">
                                <option value="invoicing">Klar til fakturering</option>
                                <option value="payroll">Klar til løn</option>
                                <option value="all">Alle eksekverede</option>
                            </select>
                        </label>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Fra dato
                            <input v-model="filterState.from_date" class="mt-1 block w-full rounded border-gray-300 text-sm" type="date" />
                        </label>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Til dato
                            <input v-model="filterState.to_date" class="mt-1 block w-full rounded border-gray-300 text-sm" type="date" />
                        </label>
                        <div class="flex items-end gap-2 md:col-span-2">
                            <button class="rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" type="button" @click="applyFilters">
                                Opdater filter
                            </button>
                            <button class="rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" type="button" @click="clearFilters">
                                Nulstil
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Samlet pris</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ formatMoney(totalPrice) }}</p>
                    </div>
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Samlet løn</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ formatMoney(totalWage) }}</p>
                    </div>
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Samlet margin</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ formatMoney(totalMargin) }}</p>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">Eksekverede bookinger</h3>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                class="rounded border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                type="button"
                                @click="toggleSelectAll"
                            >
                                {{ allSelected ? 'Fravælg alle' : 'Vælg alle' }}
                            </button>
                            <span class="text-xs text-gray-600">Valgt: {{ selectedCount }}</span>
                            <button
                                class="rounded border border-cyan-300 px-3 py-2 text-xs font-semibold text-cyan-800 hover:bg-cyan-50 disabled:opacity-40"
                                type="button"
                                :disabled="!selectedCount"
                                @click="exportSelectedInvoices"
                            >
                                Eksportér faktura-CSV (valgte)
                            </button>
                            <button
                                class="rounded border border-cyan-300 px-3 py-2 text-xs font-semibold text-cyan-800 hover:bg-cyan-50 disabled:opacity-40"
                                type="button"
                                :disabled="!selectedCount"
                                @click="bulkMarkInvoiced"
                            >
                                Markér valgte faktureret
                            </button>
                            <button
                                class="rounded border border-emerald-300 px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-50 disabled:opacity-40"
                                type="button"
                                :disabled="!selectedCount"
                                @click="exportSelectedPayroll"
                            >
                                Eksportér løn-CSV (valgte)
                            </button>
                            <button
                                class="rounded border border-emerald-300 px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-50 disabled:opacity-40"
                                type="button"
                                :disabled="!selectedCount"
                                @click="bulkMarkPaid"
                            >
                                Markér valgte løn udbetalt
                            </button>
                        </div>
                    </div>

                    <div v-if="bookings.length" class="space-y-3">
                        <div v-for="booking in bookings" :key="booking.id" class="rounded-lg border p-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <input
                                        class="mt-1 h-4 w-4 rounded border-gray-300"
                                        type="checkbox"
                                        :checked="selectedBookingIds.includes(booking.id)"
                                        @change="toggleSelected(booking.id)"
                                    />
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ booking.title }}</p>
                                        <p class="text-xs text-gray-600">{{ booking.company?.name ?? '-' }} • Slut: {{ formatDateTime(booking.ends_at) }}</p>
                                        <p class="text-xs text-gray-600">Adresse: {{ booking.company_address?.formatted ?? '-' }}</p>
                                        <p class="text-xs text-gray-600">Eksekveret: {{ formatDateTime(booking.executed_at) }}</p>
                                        <p class="text-xs text-gray-600">Workflow: {{ workflowLabel(booking.workflow_status) }}</p>
                                    </div>
                                </div>

                                <div class="text-sm text-gray-800">
                                    <p>Pris: <span class="font-semibold">{{ formatMoney(booking.totals.price_total) }}</span></p>
                                    <p>Løn: <span class="font-semibold">{{ formatMoney(booking.totals.wage_total) }}</span></p>
                                    <p>Margin: <span class="font-semibold">{{ formatMoney(booking.totals.margin_total) }}</span></p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-if="booking.can_mark_invoiced"
                                        class="rounded-full border border-cyan-400/40 bg-cyan-500/20 px-3 py-1.5 text-xs font-semibold text-cyan-100 hover:bg-cyan-500/30"
                                        type="button"
                                        @click="markInvoiced(booking.id)"
                                    >
                                        Markér faktureret
                                    </button>
                                    <button
                                        v-else-if="booking.can_unmark_invoiced"
                                        class="rounded-full border border-cyan-400/40 px-3 py-1.5 text-xs font-semibold text-cyan-100 hover:bg-cyan-500/20"
                                        type="button"
                                        @click="unmarkInvoiced(booking.id)"
                                    >
                                        Fjern faktureret
                                    </button>

                                    <button
                                        v-if="booking.can_mark_paid"
                                        class="rounded-full border border-emerald-400/40 bg-emerald-500/20 px-3 py-1.5 text-xs font-semibold text-emerald-100 hover:bg-emerald-500/30"
                                        type="button"
                                        @click="markPaid(booking.id)"
                                    >
                                        Markér løn udbetalt
                                    </button>
                                    <button
                                        v-else-if="booking.can_unmark_paid"
                                        class="rounded-full border border-emerald-400/40 px-3 py-1.5 text-xs font-semibold text-emerald-100 hover:bg-emerald-500/20"
                                        type="button"
                                        @click="unmarkPaid(booking.id)"
                                    >
                                        Fjern løn udbetalt
                                    </button>

                                    <button
                                        class="rounded-full border border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-800/80"
                                        type="button"
                                        @click="toggleDetails(booking.id)"
                                    >
                                        {{ openBookingId === booking.id ? 'Skjul linjer' : 'Vis linjer' }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="openBookingId === booking.id" class="mt-3 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead>
                                        <tr class="text-left text-gray-600">
                                            <th class="px-2 py-1">Medarbejder</th>
                                            <th class="px-2 py-1">Timer</th>
                                            <th class="px-2 py-1">Løn total</th>
                                            <th class="px-2 py-1">Pris total</th>
                                            <th class="px-2 py-1">Margin</th>
                                            <th class="px-2 py-1">Timesheet status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="line in booking.lines" :key="line.assignment_id">
                                            <td class="px-2 py-1">{{ line.employee?.name ?? '-' }}</td>
                                            <td class="px-2 py-1">{{ line.hours_worked }}</td>
                                            <td class="px-2 py-1">{{ formatMoney(line.wage_total) }}</td>
                                            <td class="px-2 py-1">{{ formatMoney(line.price_total) }}</td>
                                            <td class="px-2 py-1">{{ formatMoney(line.margin_total) }}</td>
                                            <td class="px-2 py-1">{{ line.timesheet_status ?? 'calculated' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <p v-else class="text-sm text-gray-600">Ingen eksekverede bookinger for valgt filter.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

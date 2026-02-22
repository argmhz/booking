<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Booking = {
    id: number;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    status: string;
    workflow_status?: 'open' | 'approved' | 'executed' | 'invoiced' | 'paid';
    approved_at: string | null;
    executed_at: string | null;
    is_invoiced: boolean;
    is_paid: boolean;
    required_workers: number;
    assigned_workers: number;
    company: {
        id: number;
        name: string;
    } | null;
    company_address: {
        id: number;
        label: string;
        formatted: string;
    } | null;
    employees: {
        id: number | null;
        name: string;
    }[];
    show_employee_names_to_company: boolean;
};

const props = defineProps<{
    bookings: Booking[];
    preview_mode?: boolean;
    preview_company?: {
        id: number;
        name: string;
    } | null;
}>();

const filter = ref<'all' | 'upcoming' | 'past'>('all');

const filteredBookings = computed(() => {
    const now = new Date();

    return props.bookings.filter((booking) => {
        if (filter.value === 'all') {
            return true;
        }

        const start = new Date(booking.starts_at);

        return filter.value === 'upcoming' ? start >= now : start < now;
    });
});

const formatDateTime = (value: string) => {
    return new Intl.DateTimeFormat('da-DK', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
};

const bookingStatus = (booking: Booking): string => {
    const status = booking.workflow_status
        ?? (booking.is_paid ? 'paid' : booking.is_invoiced ? 'invoiced' : booking.executed_at ? 'executed' : booking.approved_at ? 'approved' : 'open');

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

const bookingStatusClass = (booking: Booking): string => {
    const status = booking.workflow_status
        ?? (booking.is_paid ? 'paid' : booking.is_invoiced ? 'invoiced' : booking.executed_at ? 'executed' : booking.approved_at ? 'approved' : 'open');

    if (status === 'paid') {
        return 'inline-flex rounded-full border border-fuchsia-400/40 bg-fuchsia-500/15 px-2.5 py-1 text-xs font-semibold text-fuchsia-200';
    }

    if (status === 'invoiced') {
        return 'inline-flex rounded-full border border-cyan-400/40 bg-cyan-500/15 px-2.5 py-1 text-xs font-semibold text-cyan-200';
    }

    if (status === 'executed') {
        return 'inline-flex rounded-full border border-emerald-400/40 bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-200';
    }

    if (status === 'approved') {
        return 'inline-flex rounded-full border border-blue-400/40 bg-blue-500/15 px-2.5 py-1 text-xs font-semibold text-blue-200';
    }

    return 'inline-flex rounded-full border border-amber-400/40 bg-amber-500/15 px-2.5 py-1 text-xs font-semibold text-amber-200';
};
</script>

<template>
    <Head title="Mine bookinger" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-100">Mine bookinger</h2>
                    <p v-if="preview_mode" class="text-sm text-cyan-300">Preview som virksomhed: {{ preview_company?.name ?? '-' }}</p>
                </div>
                <div class="flex gap-2">
                    <button
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="filter === 'all' ? 'border border-slate-500/50 bg-slate-700/80 text-white' : 'border border-slate-600/80 bg-slate-800/60 text-slate-300 hover:bg-slate-700/70'"
                        type="button"
                        @click="filter = 'all'"
                    >
                        Alle
                    </button>
                    <button
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="filter === 'upcoming' ? 'border border-cyan-400/40 bg-cyan-500/20 text-cyan-100' : 'border border-cyan-500/30 bg-cyan-500/10 text-cyan-200 hover:bg-cyan-500/20'"
                        type="button"
                        @click="filter = 'upcoming'"
                    >
                        Kommende
                    </button>
                    <button
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="filter === 'past' ? 'border border-emerald-400/40 bg-emerald-500/20 text-emerald-100' : 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 hover:bg-emerald-500/20'"
                        type="button"
                        @click="filter = 'past'"
                    >
                        Tidligere
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-3 px-4 sm:px-6 lg:px-8">
                <div v-if="filteredBookings.length" class="space-y-3">
                    <div v-for="booking in filteredBookings" :key="booking.id" class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-5 shadow-xl shadow-black/20">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-gray-100">{{ booking.title }}</p>
                                    <span :class="bookingStatusClass(booking)">{{ bookingStatus(booking) }}</span>
                                </div>
                                <p class="text-xs text-gray-300">{{ booking.company?.name ?? '-' }}</p>
                                <p class="text-xs text-gray-300">{{ booking.company_address?.formatted ?? '-' }}</p>
                                <p class="text-xs text-gray-300">{{ formatDateTime(booking.starts_at) }} - {{ formatDateTime(booking.ends_at) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-700/70 bg-slate-800/60 p-3 text-xs text-gray-200">
                                <p class="font-semibold">Bemanding: {{ booking.assigned_workers }} / {{ booking.required_workers }}</p>
                                <p v-if="booking.show_employee_names_to_company">
                                    Medarbejdere:
                                    {{ booking.employees.length ? booking.employees.map((employee) => employee.name).join(', ') : '-' }}
                                </p>
                                <p v-else>Medarbejdernavne skjult</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-6 text-sm text-gray-300 shadow-xl shadow-black/20">
                    Ingen bookinger at vise.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

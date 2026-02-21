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
</script>

<template>
    <Head title="Mine bookinger" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Mine bookinger</h2>
                    <p v-if="preview_mode" class="text-sm text-indigo-700">Preview som virksomhed: {{ preview_company?.name ?? '-' }}</p>
                </div>
                <div class="flex gap-2">
                    <button class="rounded px-3 py-1.5 text-xs font-semibold" :class="filter === 'all' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'" type="button" @click="filter = 'all'">Alle</button>
                    <button class="rounded px-3 py-1.5 text-xs font-semibold" :class="filter === 'upcoming' ? 'bg-blue-600 text-white' : 'border border-blue-300 text-blue-700 hover:bg-blue-50'" type="button" @click="filter = 'upcoming'">Kommende</button>
                    <button class="rounded px-3 py-1.5 text-xs font-semibold" :class="filter === 'past' ? 'bg-emerald-600 text-white' : 'border border-emerald-300 text-emerald-700 hover:bg-emerald-50'" type="button" @click="filter = 'past'">Tidligere</button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-3 px-4 sm:px-6 lg:px-8">
                <div v-if="filteredBookings.length" class="space-y-3">
                    <div v-for="booking in filteredBookings" :key="booking.id" class="rounded-xl border bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-gray-900">{{ booking.title }}</p>
                                <p class="text-xs text-gray-600">{{ booking.company?.name ?? '-' }}</p>
                                <p class="text-xs text-gray-600">{{ booking.company_address?.formatted ?? '-' }}</p>
                                <p class="text-xs text-gray-600">{{ formatDateTime(booking.starts_at) }} - {{ formatDateTime(booking.ends_at) }}</p>
                                <p class="text-xs text-gray-600">Status: {{ bookingStatus(booking) }}</p>
                            </div>
                            <div class="text-xs text-gray-700">
                                <p>Bemanding: {{ booking.assigned_workers }} / {{ booking.required_workers }}</p>
                                <p v-if="booking.show_employee_names_to_company">
                                    Medarbejdere:
                                    {{ booking.employees.length ? booking.employees.map((employee) => employee.name).join(', ') : '-' }}
                                </p>
                                <p v-else>Medarbejdernavne skjult</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-xl border bg-white p-6 text-sm text-gray-600 shadow-sm">
                    Ingen bookinger at vise.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

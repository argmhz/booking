<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

type EmployeeRequest = {
    id: number;
    status: 'pending' | 'accepted' | 'declined' | 'cancelled';
    responded_at: string | null;
    booking: {
        id: number;
        title: string;
        starts_at: string;
        ends_at: string;
        required_workers: number;
        status: string;
        company_name: string | null;
    };
    waitlist: {
        id: number;
        position: number;
    } | null;
};

defineProps<{
    requests: EmployeeRequest[];
}>();

const respond = (requestId: number, response: 'accepted' | 'declined') => {
    router.post(route('employee.requests.respond', requestId), { response });
};

const leaveWaitlist = (waitlistId: number) => {
    router.delete(route('employee.waitlist.leave', waitlistId));
};

const requestStatusLabel = (status: EmployeeRequest['status']) => {
    if (status === 'accepted') {
        return 'Accepteret';
    }

    if (status === 'declined') {
        return 'Afvist';
    }

    if (status === 'cancelled') {
        return 'Annulleret';
    }

    return 'Afventer';
};

const requestStatusClass = (status: EmployeeRequest['status']) => {
    if (status === 'accepted') {
        return 'inline-flex rounded-full border border-emerald-400/40 bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-200';
    }

    if (status === 'declined' || status === 'cancelled') {
        return 'inline-flex rounded-full border border-rose-400/40 bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-200';
    }

    return 'inline-flex rounded-full border border-amber-400/40 bg-amber-500/15 px-2.5 py-1 text-xs font-semibold text-amber-200';
};
</script>

<template>
    <Head title="Mine forespørgsler" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-100">Mine forespørgsler</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-5 shadow-xl shadow-black/20">
                    <div class="overflow-x-auto rounded-xl border border-slate-700/70">
                        <table class="min-w-full divide-y divide-slate-700 text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                    <th class="px-3 py-3">Booking</th>
                                    <th class="px-3 py-3">Virksomhed</th>
                                    <th class="px-3 py-3">Tid</th>
                                    <th class="px-3 py-3">Status</th>
                                    <th class="px-3 py-3">Venteliste</th>
                                    <th class="px-3 py-3">Handling</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/80">
                                <tr v-for="entry in requests" :key="entry.id">
                                    <td class="px-3 py-3 font-medium text-gray-100">{{ entry.booking.title }}</td>
                                    <td class="px-3 py-3 text-gray-300">{{ entry.booking.company_name ?? '-' }}</td>
                                    <td class="px-3 py-3 text-gray-300">
                                        {{ new Date(entry.booking.starts_at).toLocaleString() }}
                                        -
                                        {{ new Date(entry.booking.ends_at).toLocaleString() }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span :class="requestStatusClass(entry.status)">{{ requestStatusLabel(entry.status) }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-gray-300">
                                        <span v-if="entry.waitlist" class="inline-flex rounded-full border border-cyan-400/40 bg-cyan-500/15 px-2.5 py-1 text-xs font-semibold text-cyan-200">
                                            Plads {{ entry.waitlist.position }}
                                        </span>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex gap-2">
                                            <button
                                                v-if="entry.status === 'pending'"
                                                class="rounded-full border border-emerald-400/40 bg-emerald-500/20 px-3 py-1.5 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-500/30"
                                                @click="respond(entry.id, 'accepted')"
                                            >
                                                Accept
                                            </button>
                                            <button
                                                v-if="entry.status === 'pending'"
                                                class="rounded-full border border-rose-400/40 bg-rose-500/20 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-500/30"
                                                @click="respond(entry.id, 'declined')"
                                            >
                                                Afvis
                                            </button>
                                            <button
                                                v-if="entry.waitlist"
                                                class="rounded-full border border-slate-500/50 bg-slate-700/60 px-3 py-1.5 text-xs font-semibold text-slate-100 transition hover:bg-slate-600/70"
                                                @click="leaveWaitlist(entry.waitlist.id)"
                                            >
                                                Forlad venteliste
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!requests.length">
                                    <td class="px-3 py-6 text-center text-sm text-gray-400" colspan="6">
                                        Ingen forespørgsler endnu.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

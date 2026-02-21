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
</script>

<template>
    <Head title="Mine forespørgsler" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Mine forespørgsler</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-600">
                                    <th class="px-3 py-2">Booking</th>
                                    <th class="px-3 py-2">Virksomhed</th>
                                    <th class="px-3 py-2">Tid</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Venteliste</th>
                                    <th class="px-3 py-2">Handling</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="entry in requests" :key="entry.id">
                                    <td class="px-3 py-2">{{ entry.booking.title }}</td>
                                    <td class="px-3 py-2">{{ entry.booking.company_name ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        {{ new Date(entry.booking.starts_at).toLocaleString() }}
                                        -
                                        {{ new Date(entry.booking.ends_at).toLocaleString() }}
                                    </td>
                                    <td class="px-3 py-2">{{ entry.status }}</td>
                                    <td class="px-3 py-2">
                                        <span v-if="entry.waitlist">Plads {{ entry.waitlist.position }}</span>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-2">
                                            <button
                                                v-if="entry.status === 'pending'"
                                                class="rounded bg-green-600 px-2 py-1 text-xs font-semibold text-white"
                                                @click="respond(entry.id, 'accepted')"
                                            >
                                                Accept
                                            </button>
                                            <button
                                                v-if="entry.status === 'pending'"
                                                class="rounded bg-red-600 px-2 py-1 text-xs font-semibold text-white"
                                                @click="respond(entry.id, 'declined')"
                                            >
                                                Afvis
                                            </button>
                                            <button
                                                v-if="entry.waitlist"
                                                class="rounded bg-gray-800 px-2 py-1 text-xs font-semibold text-white"
                                                @click="leaveWaitlist(entry.waitlist.id)"
                                            >
                                                Forlad venteliste
                                            </button>
                                        </div>
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

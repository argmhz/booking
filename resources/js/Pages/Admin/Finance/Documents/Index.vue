<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type DocumentLine = {
    id: number;
    booking_id: number;
    description: string;
    hours_worked: number;
    wage_total: number;
    price_total: number;
    margin_total: number;
    company: { id: number; name: string } | null;
    employee: { id: number; name: string; email: string } | null;
};

type FinanceDocument = {
    id: number;
    type: 'invoice' | 'payroll';
    status: 'draft' | 'finalized' | 'cancelled';
    period_from: string | null;
    period_to: string | null;
    wage_total: number;
    price_total: number;
    margin_total: number;
    created_at: string;
    finalized_at: string | null;
    creator: { id: number; name: string; email: string } | null;
    finalizer: { id: number; name: string; email: string } | null;
    lines: DocumentLine[];
};

const props = defineProps<{ documents: FinanceDocument[] }>();
const openDocumentId = ref<number | null>(null);

const formatMoney = (value: number) => new Intl.NumberFormat('da-DK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(value);

const formatDate = (value: string | null) => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('da-DK', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
};

const typeLabel = (type: FinanceDocument['type']) => (type === 'invoice' ? 'Faktura' : 'Løn');
const statusLabel = (status: FinanceDocument['status']) => (
    status === 'draft' ? 'Kladde' : status === 'finalized' ? 'Finaliseret' : 'Annulleret'
);

const finalizeDocument = (documentId: number) => {
    router.post(route('admin.finance.documents.finalize', documentId), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Faktura/Løn kladder" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Faktura/Løn kladder</h2>
                <a
                    :href="route('admin.finance.index')"
                    class="rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Tilbage til Økonomi
                </a>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Dokumenter</h3>
                    <div v-if="documents.length" class="space-y-3">
                        <div v-for="document in documents" :key="document.id" class="rounded-lg border p-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">
                                        #{{ document.id }} • {{ typeLabel(document.type) }} • {{ statusLabel(document.status) }}
                                    </p>
                                    <p class="text-xs text-gray-600">
                                        Periode: {{ document.period_from ?? '-' }} - {{ document.period_to ?? '-' }}
                                    </p>
                                    <p class="text-xs text-gray-600">Oprettet: {{ formatDate(document.created_at) }}</p>
                                    <p class="text-xs text-gray-600">Finaliseret: {{ formatDate(document.finalized_at) }}</p>
                                </div>

                                <div class="text-sm text-gray-800">
                                    <p>Pris: <span class="font-semibold">{{ formatMoney(document.price_total) }}</span></p>
                                    <p>Løn: <span class="font-semibold">{{ formatMoney(document.wage_total) }}</span></p>
                                    <p>Margin: <span class="font-semibold">{{ formatMoney(document.margin_total) }}</span></p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-if="document.status === 'draft'"
                                        class="rounded-full border border-emerald-400/40 bg-emerald-500/20 px-3 py-1.5 text-xs font-semibold text-emerald-100 hover:bg-emerald-500/30"
                                        type="button"
                                        @click="finalizeDocument(document.id)"
                                    >
                                        Finalisér
                                    </button>
                                    <a
                                        :href="route('admin.finance.documents.export-csv', document.id)"
                                        class="rounded-full border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                        download
                                    >
                                        Eksportér CSV
                                    </a>
                                    <button
                                        class="rounded-full border border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-800/80"
                                        type="button"
                                        @click="openDocumentId = openDocumentId === document.id ? null : document.id"
                                    >
                                        {{ openDocumentId === document.id ? 'Skjul linjer' : 'Vis linjer' }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="openDocumentId === document.id" class="mt-3 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead>
                                        <tr class="text-left text-gray-600">
                                            <th class="px-2 py-1">Booking</th>
                                            <th class="px-2 py-1">Beskrivelse</th>
                                            <th class="px-2 py-1">Virksomhed</th>
                                            <th class="px-2 py-1">Medarbejder</th>
                                            <th class="px-2 py-1">Timer</th>
                                            <th class="px-2 py-1">Løn</th>
                                            <th class="px-2 py-1">Pris</th>
                                            <th class="px-2 py-1">Margin</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="line in document.lines" :key="line.id">
                                            <td class="px-2 py-1">#{{ line.booking_id }}</td>
                                            <td class="px-2 py-1">{{ line.description }}</td>
                                            <td class="px-2 py-1">{{ line.company?.name ?? '-' }}</td>
                                            <td class="px-2 py-1">{{ line.employee?.name ?? '-' }}</td>
                                            <td class="px-2 py-1">{{ line.hours_worked }}</td>
                                            <td class="px-2 py-1">{{ formatMoney(line.wage_total) }}</td>
                                            <td class="px-2 py-1">{{ formatMoney(line.price_total) }}</td>
                                            <td class="px-2 py-1">{{ formatMoney(line.margin_total) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <p v-else class="text-sm text-gray-600">Ingen kladder endnu.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


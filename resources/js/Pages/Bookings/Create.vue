<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import vSelect from 'vue-select';

type Company = {
    id: number;
    name: string;
    addresses: CompanyAddress[];
};

type CompanyAddress = {
    id: number;
    label: string;
    address_line_1: string;
    address_line_2: string | null;
    postal_code: string | null;
    city: string;
    country: string;
    is_default: boolean;
};

type AddressOption = {
    id: number | null;
    name: string;
};

const props = defineProps<{
    companies: Company[];
    initialDate: string | null;
}>();

const startsAtDefault = props.initialDate ? `${props.initialDate}T08:00` : '';
const endsAtDefault = props.initialDate ? `${props.initialDate}T16:00` : '';

const form = useForm({
    company_id: props.companies[0]?.id ?? null,
    company_address_id: (
        props.companies[0]?.addresses.find((address) => address.is_default)?.id
        ?? props.companies[0]?.addresses[0]?.id
        ?? null
    ) as number | null,
    title: '',
    description: '',
    starts_at: startsAtDefault,
    ends_at: endsAtDefault,
    required_workers: 1,
    assignment_mode: 'first_come_first_served',
    show_employee_names_to_company: false,
});

const selectedCompany = computed<Company | null>({
    get: () => props.companies.find((company) => company.id === form.company_id) ?? null,
    set: (company) => {
        if (!company) {
            return;
        }

        form.company_id = company.id;
    },
});

const availableAddresses = computed(() => selectedCompany.value?.addresses ?? []);
const addressOptions = computed<AddressOption[]>(() => {
    const options = availableAddresses.value.map((address) => ({
        id: address.id,
        name: `${address.label} - ${address.address_line_1}, ${address.postal_code ?? ''} ${address.city}`.trim(),
    }));

    return [{ id: null, name: 'Ingen specifik adresse' }, ...options];
});
const addressOptionValue = (option: AddressOption): number | null => option.id;
const fieldClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';
const checkboxClass = 'h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';

watch(() => form.company_id, () => {
    const addresses = availableAddresses.value;

    if (!addresses.length) {
        form.company_address_id = null;

        return;
    }

    if (addresses.some((address) => address.id === form.company_address_id)) {
        return;
    }

    form.company_address_id = addresses.find((address) => address.is_default)?.id ?? addresses[0].id;
});

const submit = () => {
    form.post(route('bookings.store'));
};
</script>

<template>
    <Head title="Opret booking" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Opret booking</h2>
                <Link
                    :href="route('bookings.calendar')"
                    class="rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Tilbage til kalender
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <form class="grid gap-5 sm:grid-cols-2" @submit.prevent="submit">
                        <label class="block text-sm text-gray-700 sm:col-span-2">
                            <span class="font-medium">Virksomhed</span>
                            <v-select
                                v-model="selectedCompany"
                                :options="companies"
                                class="booking-v-select mt-1"
                                label="name"
                                :clearable="false"
                                placeholder="Søg virksomhed..."
                            />
                            <InputError class="mt-1" :message="form.errors.company_id" />
                        </label>

                        <label class="block text-sm text-gray-700 sm:col-span-2">
                            <span class="font-medium">Adresse</span>
                            <v-select
                                v-model="form.company_address_id"
                                :options="addressOptions"
                                :reduce="addressOptionValue"
                                class="booking-v-select mt-1"
                                label="name"
                                :clearable="false"
                                placeholder="Søg adresse..."
                            />
                            <InputError class="mt-1" :message="form.errors.company_address_id" />
                        </label>

                        <label class="block text-sm text-gray-700 sm:col-span-2">
                            <span class="font-medium">Titel</span>
                            <input v-model="form.title" :class="fieldClass" required type="text" />
                            <InputError class="mt-1" :message="form.errors.title" />
                        </label>

                        <label class="block text-sm text-gray-700 sm:col-span-2">
                            <span class="font-medium">Beskrivelse</span>
                            <textarea v-model="form.description" :class="fieldClass" rows="3" />
                            <InputError class="mt-1" :message="form.errors.description" />
                        </label>

                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Start</span>
                            <input v-model="form.starts_at" :class="fieldClass" required type="datetime-local" />
                            <InputError class="mt-1" :message="form.errors.starts_at" />
                        </label>

                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Slut</span>
                            <input v-model="form.ends_at" :class="fieldClass" required type="datetime-local" />
                            <InputError class="mt-1" :message="form.errors.ends_at" />
                        </label>

                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Antal medarbejdere</span>
                            <input v-model="form.required_workers" :class="fieldClass" min="1" required type="number" />
                            <InputError class="mt-1" :message="form.errors.required_workers" />
                        </label>

                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Tildelingsmode</span>
                            <select v-model="form.assignment_mode" :class="fieldClass">
                                <option value="first_come_first_served">Først til mølle</option>
                                <option value="specific_employees">Specifikke medarbejdere</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.assignment_mode" />
                        </label>

                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 sm:col-span-2">
                            <input v-model="form.show_employee_names_to_company" :class="checkboxClass" type="checkbox" />
                            <span>Vis medarbejdernavne til virksomhed</span>
                        </label>
                        <InputError class="sm:col-span-2" :message="form.errors.show_employee_names_to_company" />

                        <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-3 sm:col-span-2">
                            <Link
                                :href="route('bookings.calendar')"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Annuller
                            </Link>
                            <button
                                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black disabled:opacity-50"
                                :disabled="form.processing"
                                type="submit"
                            >
                                Opret booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
:deep(.booking-v-select .vs__dropdown-toggle) {
    border-radius: 0.5rem;
    border-color: rgb(209 213 219);
    min-height: 42px;
}

:deep(.booking-v-select .vs__search),
:deep(.booking-v-select .vs__selected),
:deep(.booking-v-select .vs__dropdown-option) {
    font-size: 0.875rem;
}
</style>

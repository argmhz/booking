<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type CompanyAddress = {
    id: number | null;
    label: string;
    address_line_1: string;
    address_line_2: string | null;
    postal_code: string | null;
    city: string;
    country: string;
    is_default: boolean;
};

type Company = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    cvr: string | null;
    is_active: boolean;
    created_at: string;
    addresses: CompanyAddress[];
    users: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
    }[];
};

type CompaniesPage = {
    data: Company[];
    current_page: number;
    last_page: number;
    total: number;
};

const props = defineProps<{
    companies: CompaniesPage;
    filters: {
        q: string;
        status: 'all' | 'active' | 'inactive';
        per_page: 15 | 25 | 50;
    };
}>();

const selectedCompanyId = ref<number | null>(null);
const showCreateModal = ref(false);
const activeEditTab = ref<'general' | 'addresses' | 'users'>('general');
const emptyAddress = (): CompanyAddress => ({
    id: null,
    label: '',
    address_line_1: '',
    address_line_2: '',
    postal_code: '',
    city: '',
    country: 'Denmark',
    is_default: false,
});

const filterForm = useForm({
    q: props.filters.q ?? '',
    status: props.filters.status ?? 'all',
    per_page: props.filters.per_page ?? 15,
});

const createForm = useForm({
    name: '',
    email: '',
    phone: '',
    cvr: '',
    is_active: true,
    addresses: [emptyAddress()],
});

const editForm = useForm({
    name: '',
    email: '',
    phone: '',
    cvr: '',
    is_active: true,
    addresses: [emptyAddress()],
});

const attachUserForm = useForm({
    email: '',
    name: '',
    password: '',
    password_confirmation: '',
});

const selectedCompany = computed(() => props.companies.data.find((company) => company.id === selectedCompanyId.value) ?? null);
const fieldClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';
const checkboxClass = 'h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';
const filterFieldClass = 'w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';

const visiblePages = computed(() => {
    const total = props.companies.last_page;
    const current = props.companies.current_page;

    if (total <= 7) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }

    const start = Math.max(1, current - 2);
    const end = Math.min(total, start + 4);
    const adjustedStart = Math.max(1, end - 4);

    return Array.from({ length: end - adjustedStart + 1 }, (_, index) => adjustedStart + index);
});

const openEdit = (company: Company) => {
    selectedCompanyId.value = company.id;
    attachUserForm.reset();
    activeEditTab.value = 'general';
    editForm.defaults({
        name: company.name,
        email: company.email ?? '',
        phone: company.phone ?? '',
        cvr: company.cvr ?? '',
        is_active: company.is_active,
        addresses: company.addresses.length
            ? company.addresses.map((address) => ({
                id: address.id,
                label: address.label,
                address_line_1: address.address_line_1,
                address_line_2: address.address_line_2 ?? '',
                postal_code: address.postal_code ?? '',
                city: address.city,
                country: address.country,
                is_default: address.is_default,
            }))
            : [emptyAddress()],
    });
    editForm.reset();
};

const closeEdit = () => {
    selectedCompanyId.value = null;
    activeEditTab.value = 'general';
    attachUserForm.reset();
};

const applyFilters = (page = 1) => {
    router.get(route('admin.companies.index'), {
        q: filterForm.q,
        status: filterForm.status,
        per_page: filterForm.per_page,
        page,
    }, {
        preserveState: true,
        replace: true,
    });
};

const createCompany = () => {
    createForm.post(route('admin.companies.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.addresses = [emptyAddress()];
            showCreateModal.value = false;
        },
    });
};

const openCreateModal = () => {
    createForm.reset();
    createForm.addresses = [emptyAddress()];
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};

const updateCompany = () => {
    if (!selectedCompany.value) {
        return;
    }

    editForm.patch(route('admin.companies.update', selectedCompany.value.id), {
        preserveScroll: true,
    });
};

const attachCompanyUser = () => {
    if (!selectedCompany.value) {
        return;
    }

    attachUserForm.post(route('admin.companies.users.attach', selectedCompany.value.id), {
        preserveScroll: true,
        onSuccess: () => attachUserForm.reset(),
    });
};

const detachCompanyUser = (company: Company, userId: number) => {
    if (!window.confirm('Er du sikker på, at du vil frakoble denne bruger?')) {
        return;
    }

    router.delete(route('admin.companies.users.detach', {
        company: company.id,
        user: userId,
    }), { preserveScroll: true });
};

const toggleCompany = (company: Company) => {
    const text = company.is_active ? 'deaktivere' : 'aktivere';

    if (!window.confirm(`Er du sikker på, at du vil ${text} virksomheden?`)) {
        return;
    }

    router.patch(route('admin.companies.toggle-active', company.id), {}, { preserveScroll: true });
};

const deleteCompany = (company: Company) => {
    if (!window.confirm('Er du sikker på, at du vil slette virksomheden?')) {
        return;
    }

    router.delete(route('admin.companies.destroy', company.id), { preserveScroll: true });
};

const addCreateAddress = () => {
    createForm.addresses.push(emptyAddress());
};

const removeCreateAddress = (index: number) => {
    if (createForm.addresses.length <= 1) {
        createForm.addresses[0] = emptyAddress();

        return;
    }

    createForm.addresses.splice(index, 1);
};

const setCreateDefaultAddress = (index: number) => {
    createForm.addresses = createForm.addresses.map((address, addressIndex) => ({
        ...address,
        is_default: addressIndex === index,
    }));
};

const addEditAddress = () => {
    editForm.addresses.push(emptyAddress());
};

const removeEditAddress = (index: number) => {
    if (editForm.addresses.length <= 1) {
        editForm.addresses[0] = emptyAddress();

        return;
    }

    editForm.addresses.splice(index, 1);
};

const setEditDefaultAddress = (index: number) => {
    editForm.addresses = editForm.addresses.map((address, addressIndex) => ({
        ...address,
        is_default: addressIndex === index,
    }));
};
</script>

<template>
    <Head title="Virksomheder" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Virksomheder</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Opret virksomhed</h3>
                            <p class="text-sm text-gray-600">Formularen vises i modal.</p>
                        </div>
                        <button class="rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" type="button" @click="openCreateModal">
                            Opret virksomhed
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <form class="mb-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4" @submit.prevent="applyFilters(1)">
                        <div class="mb-3">
                            <h3 class="text-lg font-semibold text-gray-900">Alle virksomheder</h3>
                            <p class="text-xs text-gray-600">Filtrér på navn, kontaktinfo og status.</p>
                        </div>
                        <div class="grid gap-3 md:grid-cols-4">
                            <label class="block text-xs font-medium uppercase tracking-wide text-gray-600 md:col-span-2">
                                Søgning
                                <input v-model="filterForm.q" :class="filterFieldClass" placeholder="Søg navn, email, CVR..." type="text" />
                            </label>
                            <label class="block text-xs font-medium uppercase tracking-wide text-gray-600">
                                Status
                                <select v-model="filterForm.status" :class="filterFieldClass" @change="applyFilters(1)">
                                    <option value="all">Alle</option>
                                    <option value="active">Aktive</option>
                                    <option value="inactive">Inaktive</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium uppercase tracking-wide text-gray-600">
                                Vis pr. side
                                <select v-model="filterForm.per_page" :class="filterFieldClass" @change="applyFilters(1)">
                                    <option :value="15">15 / side</option>
                                    <option :value="25">25 / side</option>
                                    <option :value="50">50 / side</option>
                                </select>
                            </label>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <button class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-100" type="submit">Filtrer</button>
                        </div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-600">
                                    <th class="px-3 py-2">Navn</th>
                                    <th class="px-3 py-2">Email</th>
                                    <th class="px-3 py-2">Telefon</th>
                                    <th class="px-3 py-2">CVR</th>
                                    <th class="px-3 py-2">Adresser</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Handling</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="company in companies.data" :key="company.id">
                                    <td class="px-3 py-2">{{ company.name }}</td>
                                    <td class="px-3 py-2">{{ company.email ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ company.phone ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ company.cvr ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ company.addresses.length }}</td>
                                    <td class="px-3 py-2">{{ company.is_active ? 'Aktiv' : 'Inaktiv' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-1">
                                            <Link class="rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs text-indigo-700" :href="route('admin.companies.bookings-preview', company.id)">Se virksomheds side</Link>
                                            <button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" type="button" @click="openEdit(company)">Rediger</button>
                                            <button class="rounded border border-amber-300 bg-amber-50 px-2 py-1 text-xs text-amber-800" type="button" @click="toggleCompany(company)">{{ company.is_active ? 'Deaktiver' : 'Aktiver' }}</button>
                                            <button class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700" type="button" @click="deleteCompany(company)">Slet</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-gray-600">{{ companies.total }} resultater</p>
                        <div class="flex items-center gap-2">
                            <button
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50"
                                :disabled="companies.current_page <= 1"
                                type="button"
                                @click="applyFilters(companies.current_page - 1)"
                            >
                                Forrige
                            </button>
                            <button
                                v-for="page in visiblePages"
                                :key="page"
                                class="rounded border px-3 py-1.5 text-sm"
                                :class="page === companies.current_page ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-700'"
                                type="button"
                                @click="applyFilters(page)"
                            >
                                {{ page }}
                            </button>
                            <button
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50"
                                :disabled="companies.current_page >= companies.last_page"
                                type="button"
                                @click="applyFilters(companies.current_page + 1)"
                            >
                                Næste
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="closeCreateModal">
            <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Opret virksomhed</h3>
                    <button class="rounded border border-gray-300 px-2 py-1 text-sm" type="button" @click="closeCreateModal">Luk</button>
                </div>

                <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="createCompany">
                    <label class="block text-sm text-gray-700 sm:col-span-2 lg:col-span-2">
                        <span class="font-medium">Navn</span>
                        <input v-model="createForm.name" :class="fieldClass" placeholder="Navn" required type="text" />
                        <InputError class="mt-1" :message="createForm.errors.name" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Email</span>
                        <input v-model="createForm.email" :class="fieldClass" placeholder="Email" type="email" />
                        <InputError class="mt-1" :message="createForm.errors.email" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Telefon</span>
                        <input v-model="createForm.phone" :class="fieldClass" placeholder="Telefon" type="text" />
                        <InputError class="mt-1" :message="createForm.errors.phone" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">CVR</span>
                        <input v-model="createForm.cvr" :class="fieldClass" placeholder="CVR" type="text" />
                        <InputError class="mt-1" :message="createForm.errors.cvr" />
                    </label>
                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                        <input v-model="createForm.is_active" :class="checkboxClass" type="checkbox" />
                        Aktiv
                    </label>

                    <div class="space-y-2 sm:col-span-2 lg:col-span-5">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-900">Adresser</h4>
                            <button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" type="button" @click="addCreateAddress">Tilføj adresse</button>
                        </div>
                        <div v-for="(address, index) in createForm.addresses" :key="`create-address-${index}`" class="grid gap-2 rounded border p-3 sm:grid-cols-2 lg:grid-cols-7">
                            <input v-model="address.label" :class="fieldClass" class="lg:col-span-2" placeholder="Label (fx Hovedkontor)" type="text" />
                            <input v-model="address.address_line_1" :class="fieldClass" class="lg:col-span-3" placeholder="Adresse linje 1" type="text" />
                            <input v-model="address.address_line_2" :class="fieldClass" class="lg:col-span-2" placeholder="Adresse linje 2" type="text" />
                            <input v-model="address.postal_code" :class="fieldClass" placeholder="Postnr." type="text" />
                            <input v-model="address.city" :class="fieldClass" class="lg:col-span-2" placeholder="By" type="text" />
                            <input v-model="address.country" :class="fieldClass" class="lg:col-span-2" placeholder="Land" type="text" />
                            <label class="flex items-center gap-2 text-xs lg:col-span-1">
                                <input :checked="address.is_default" :class="checkboxClass" type="checkbox" @change="setCreateDefaultAddress(index)" />
                                Standard
                            </label>
                            <button class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700 lg:col-span-1" type="button" @click="removeCreateAddress(index)">Fjern</button>
                        </div>
                    </div>

                    <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black sm:col-span-2 lg:col-span-1" :disabled="createForm.processing" type="submit">
                        Opret
                    </button>
                </form>
            </div>
        </div>

        <div v-if="selectedCompany" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="closeEdit">
            <div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Rediger virksomhed</h3>
                    <button class="rounded border border-gray-300 px-2 py-1 text-sm" type="button" @click="closeEdit">Luk</button>
                </div>

                <div class="mb-4 flex gap-2">
                    <button class="rounded px-3 py-1.5 text-sm font-medium" :class="activeEditTab === 'general' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-700'" type="button" @click="activeEditTab = 'general'">Generelt</button>
                    <button class="rounded px-3 py-1.5 text-sm font-medium" :class="activeEditTab === 'addresses' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-700'" type="button" @click="activeEditTab = 'addresses'">Adresser</button>
                    <button class="rounded px-3 py-1.5 text-sm font-medium" :class="activeEditTab === 'users' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-700'" type="button" @click="activeEditTab = 'users'">Virksomhedsbrugere</button>
                </div>

                <form v-if="activeEditTab === 'general'" class="grid gap-4" @submit.prevent="updateCompany">
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Navn</span>
                        <input v-model="editForm.name" :class="fieldClass" placeholder="Navn" required type="text" />
                        <InputError class="mt-1" :message="editForm.errors.name" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Email</span>
                        <input v-model="editForm.email" :class="fieldClass" placeholder="Email" type="email" />
                        <InputError class="mt-1" :message="editForm.errors.email" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Telefon</span>
                        <input v-model="editForm.phone" :class="fieldClass" placeholder="Telefon" type="text" />
                        <InputError class="mt-1" :message="editForm.errors.phone" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">CVR</span>
                        <input v-model="editForm.cvr" :class="fieldClass" placeholder="CVR" type="text" />
                        <InputError class="mt-1" :message="editForm.errors.cvr" />
                    </label>
                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                        <input v-model="editForm.is_active" :class="checkboxClass" type="checkbox" />
                        Aktiv
                    </label>

                    <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" :disabled="editForm.processing" type="submit">
                        Gem ændringer
                    </button>
                </form>

                <form v-else-if="activeEditTab === 'addresses'" class="space-y-3" @submit.prevent="updateCompany">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-900">Adresser</h4>
                            <button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" type="button" @click="addEditAddress">Tilføj adresse</button>
                        </div>
                        <div v-for="(address, index) in editForm.addresses" :key="`edit-address-${address.id ?? index}`" class="grid gap-2 rounded border p-3 sm:grid-cols-2 lg:grid-cols-7">
                            <input v-model="address.label" :class="fieldClass" class="lg:col-span-2" placeholder="Label (fx Hovedkontor)" type="text" />
                            <input v-model="address.address_line_1" :class="fieldClass" class="lg:col-span-3" placeholder="Adresse linje 1" type="text" />
                            <input v-model="address.address_line_2" :class="fieldClass" class="lg:col-span-2" placeholder="Adresse linje 2" type="text" />
                            <input v-model="address.postal_code" :class="fieldClass" placeholder="Postnr." type="text" />
                            <input v-model="address.city" :class="fieldClass" class="lg:col-span-2" placeholder="By" type="text" />
                            <input v-model="address.country" :class="fieldClass" class="lg:col-span-2" placeholder="Land" type="text" />
                            <label class="flex items-center gap-2 text-xs lg:col-span-1">
                                <input :checked="address.is_default" :class="checkboxClass" type="checkbox" @change="setEditDefaultAddress(index)" />
                                Standard
                            </label>
                            <button class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700 lg:col-span-1" type="button" @click="removeEditAddress(index)">Fjern</button>
                        </div>
                    </div>

                    <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" :disabled="editForm.processing" type="submit">
                        Gem adresser
                    </button>
                </form>

                <div v-else class="space-y-3 rounded border p-3">
                    <h4 class="text-sm font-semibold text-gray-900">Virksomhedsbrugere</h4>
                    <div v-if="selectedCompany.users.length" class="space-y-1">
                        <div v-for="user in selectedCompany.users" :key="user.id" class="flex items-center justify-between gap-2 rounded border border-gray-200 px-2 py-1.5 text-sm">
                            <div>
                                <p class="font-medium text-gray-900">{{ user.name }}</p>
                                <p class="text-xs text-gray-600">{{ user.email }}</p>
                            </div>
                            <button class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700" type="button" @click="detachCompanyUser(selectedCompany, user.id)">
                                Frakobl
                            </button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">Ingen tilknyttede brugere endnu.</p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Bruger email</span>
                            <input v-model="attachUserForm.email" :class="fieldClass" placeholder="Bruger email" required type="email" />
                            <InputError class="mt-1" :message="attachUserForm.errors.email" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Navn (ny bruger)</span>
                            <input v-model="attachUserForm.name" :class="fieldClass" placeholder="Navn" type="text" />
                            <InputError class="mt-1" :message="attachUserForm.errors.name" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Password (ny bruger)</span>
                            <input v-model="attachUserForm.password" :class="fieldClass" placeholder="Password" type="password" />
                            <InputError class="mt-1" :message="attachUserForm.errors.password" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Gentag password</span>
                            <input v-model="attachUserForm.password_confirmation" :class="fieldClass" placeholder="Gentag password" type="password" />
                            <InputError class="mt-1" :message="attachUserForm.errors.password_confirmation" />
                        </label>
                        <button class="rounded-lg bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-black sm:col-span-2" :disabled="attachUserForm.processing || !attachUserForm.email" type="button" @click="attachCompanyUser">
                            Tilknyt bruger
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

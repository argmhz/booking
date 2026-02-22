<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Admin = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
};

type AdminsPage = {
    data: Admin[];
    current_page: number;
    last_page: number;
    total: number;
};

const props = defineProps<{
    admins: AdminsPage;
    filters: {
        q: string;
        status: 'all' | 'active' | 'inactive';
        per_page: 15 | 25 | 50;
    };
}>();

const selectedAdminId = ref<number | null>(null);
const showCreateModal = ref(false);

const filterForm = useForm({
    q: props.filters.q ?? '',
    status: props.filters.status ?? 'all',
    per_page: props.filters.per_page ?? 15,
});

const createForm = useForm({
    name: '',
    email: '',
    is_active: true,
    password: '',
    password_confirmation: '',
});

const editForm = useForm({
    name: '',
    email: '',
    is_active: true,
    password: '',
    password_confirmation: '',
});

const selectedAdmin = computed(() => props.admins.data.find((admin) => admin.id === selectedAdminId.value) ?? null);
const fieldClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';
const checkboxClass = 'h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';
const filterFieldClass = 'w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';
const statusBadgeClass = (isActive: boolean) => isActive
    ? 'inline-flex rounded-full border border-emerald-400/40 bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-200'
    : 'inline-flex rounded-full border border-rose-400/40 bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-200';

const visiblePages = computed(() => {
    const total = props.admins.last_page;
    const current = props.admins.current_page;

    if (total <= 7) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }

    const start = Math.max(1, current - 2);
    const end = Math.min(total, start + 4);
    const adjustedStart = Math.max(1, end - 4);

    return Array.from({ length: end - adjustedStart + 1 }, (_, index) => adjustedStart + index);
});

const applyFilters = (page = 1) => {
    router.get(route('admin.admins.index'), {
        q: filterForm.q,
        status: filterForm.status,
        per_page: filterForm.per_page,
        page,
    }, {
        preserveState: true,
        replace: true,
    });
};

const openCreateModal = () => {
    createForm.reset();
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};

const createAdmin = () => {
    createForm.post(route('admin.admins.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreateModal.value = false;
        },
    });
};

const openEdit = (admin: Admin) => {
    selectedAdminId.value = admin.id;
    editForm.defaults({
        name: admin.name,
        email: admin.email,
        is_active: admin.is_active,
        password: '',
        password_confirmation: '',
    });
    editForm.reset();
};

const closeEdit = () => {
    selectedAdminId.value = null;
};

const updateAdmin = () => {
    if (!selectedAdmin.value) {
        return;
    }

    editForm.patch(route('admin.admins.update', selectedAdmin.value.id), {
        preserveScroll: true,
    });
};

const toggleAdmin = (admin: Admin) => {
    const text = admin.is_active ? 'deaktivere' : 'aktivere';

    if (!window.confirm(`Er du sikker på, at du vil ${text} administratoren?`)) {
        return;
    }

    router.patch(route('admin.admins.toggle-active', admin.id), {}, { preserveScroll: true });
};

const removeAdminRole = (admin: Admin) => {
    if (!window.confirm('Er du sikker på, at du vil fjerne adminrollen fra brugeren?')) {
        return;
    }

    router.delete(route('admin.admins.remove-role', admin.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Administratorer" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Administratorer</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Opret administrator</h3>
                            <p class="text-sm text-gray-600">Formularen vises i modal.</p>
                        </div>
                        <button class="rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" type="button" @click="openCreateModal">
                            Opret administrator
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <form class="mb-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4" @submit.prevent="applyFilters(1)">
                        <div class="mb-3">
                            <h3 class="text-lg font-semibold text-gray-900">Alle administratorer</h3>
                            <p class="text-xs text-gray-600">Filtrér efter navn/email og kontostatus.</p>
                        </div>
                        <div class="grid gap-3 md:grid-cols-4">
                            <label class="block text-xs font-medium uppercase tracking-wide text-gray-600 md:col-span-2">
                                Søgning
                                <input v-model="filterForm.q" :class="filterFieldClass" placeholder="Søg navn eller email..." type="text" />
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

                    <div class="overflow-x-auto rounded-xl border border-slate-700/70">
                        <table class="min-w-full divide-y divide-slate-700 text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                    <th class="px-3 py-3">Navn</th>
                                    <th class="px-3 py-3">Email</th>
                                    <th class="px-3 py-3">Status</th>
                                    <th class="px-3 py-3">Handling</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/80">
                                <tr v-for="admin in admins.data" :key="admin.id">
                                    <td class="px-3 py-3 font-medium text-gray-900">{{ admin.name }}</td>
                                    <td class="px-3 py-3 text-gray-700">{{ admin.email }}</td>
                                    <td class="px-3 py-3">
                                        <span :class="statusBadgeClass(admin.is_active)">{{ admin.is_active ? 'Aktiv' : 'Inaktiv' }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            <button class="rounded-full border border-slate-600 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-800/80" type="button" @click="openEdit(admin)">Rediger</button>
                                            <button class="rounded-full border border-amber-400/40 bg-amber-500/15 px-2.5 py-1 text-xs font-medium text-amber-100" type="button" @click="toggleAdmin(admin)">{{ admin.is_active ? 'Deaktiver' : 'Aktiver' }}</button>
                                            <button class="rounded-full border border-rose-400/40 bg-rose-500/15 px-2.5 py-1 text-xs font-medium text-rose-100" type="button" @click="removeAdminRole(admin)">Fjern adminrolle</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-gray-600">{{ admins.total }} resultater</p>
                        <div class="flex items-center gap-2">
                            <button class="rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50" :disabled="admins.current_page <= 1" type="button" @click="applyFilters(admins.current_page - 1)">Forrige</button>
                            <button v-for="page in visiblePages" :key="page" class="rounded border px-3 py-1.5 text-sm" :class="page === admins.current_page ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-700'" type="button" @click="applyFilters(page)">
                                {{ page }}
                            </button>
                            <button class="rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50" :disabled="admins.current_page >= admins.last_page" type="button" @click="applyFilters(admins.current_page + 1)">Naeste</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="closeCreateModal">
            <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Opret administrator</h3>
                    <button class="rounded border border-gray-300 px-2 py-1 text-sm" type="button" @click="closeCreateModal">Luk</button>
                </div>

                <form class="grid gap-4" @submit.prevent="createAdmin">
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Navn</span>
                        <input v-model="createForm.name" :class="fieldClass" placeholder="Navn" required type="text" />
                        <InputError class="mt-1" :message="createForm.errors.name" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Email</span>
                        <input v-model="createForm.email" :class="fieldClass" placeholder="Email" required type="email" />
                        <InputError class="mt-1" :message="createForm.errors.email" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Password</span>
                        <input v-model="createForm.password" :class="fieldClass" placeholder="Password (min. 8 tegn)" required type="password" />
                        <InputError class="mt-1" :message="createForm.errors.password" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Gentag password</span>
                        <input v-model="createForm.password_confirmation" :class="fieldClass" placeholder="Gentag password" required type="password" />
                        <InputError class="mt-1" :message="createForm.errors.password_confirmation" />
                    </label>

                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                        <input v-model="createForm.is_active" :class="checkboxClass" type="checkbox" />
                        Aktiv
                    </label>

                    <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" :disabled="createForm.processing" type="submit">
                        Opret administrator
                    </button>
                </form>
            </div>
        </div>

        <div v-if="selectedAdmin" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="closeEdit">
            <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Rediger administrator</h3>
                    <button class="rounded border border-gray-300 px-2 py-1 text-sm" type="button" @click="closeEdit">Luk</button>
                </div>

                <form class="grid gap-4" @submit.prevent="updateAdmin">
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Navn</span>
                        <input v-model="editForm.name" :class="fieldClass" placeholder="Navn" required type="text" />
                        <InputError class="mt-1" :message="editForm.errors.name" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Email</span>
                        <input v-model="editForm.email" :class="fieldClass" placeholder="Email" required type="email" />
                        <InputError class="mt-1" :message="editForm.errors.email" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Nyt password (valgfrit)</span>
                        <input v-model="editForm.password" :class="fieldClass" placeholder="Nyt password" type="password" />
                        <InputError class="mt-1" :message="editForm.errors.password" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Gentag nyt password</span>
                        <input v-model="editForm.password_confirmation" :class="fieldClass" placeholder="Gentag nyt password" type="password" />
                        <InputError class="mt-1" :message="editForm.errors.password_confirmation" />
                    </label>

                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                        <input v-model="editForm.is_active" :class="checkboxClass" type="checkbox" />
                        Aktiv
                    </label>

                    <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" :disabled="editForm.processing" type="submit">
                        Gem aendringer
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

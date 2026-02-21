<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Skill = {
    id: number;
    name: string;
};

type Employee = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
    phone: string | null;
    hourly_wage: string | null;
    hourly_customer_rate: string | null;
    skills: Skill[];
};

type EmployeesPage = {
    data: Employee[];
    current_page: number;
    last_page: number;
    total: number;
};

const props = defineProps<{
    employees: EmployeesPage;
    skills: Skill[];
    filters: {
        q: string;
        status: 'all' | 'active' | 'inactive';
        per_page: 15 | 25 | 50;
    };
}>();

const selectedEmployeeId = ref<number | null>(null);
const showCreateModal = ref(false);
const filterForm = useForm({
    q: props.filters.q ?? '',
    status: props.filters.status ?? 'all',
    per_page: props.filters.per_page ?? 15,
});

const createForm = useForm({
    name: '',
    email: '',
    phone: '',
    hourly_wage: '',
    hourly_customer_rate: '',
    is_active: true,
    skill_ids: [] as number[],
    password: '',
    password_confirmation: '',
});

const editForm = useForm({
    name: '',
    email: '',
    phone: '',
    hourly_wage: '',
    hourly_customer_rate: '',
    is_active: true,
    skill_ids: [] as number[],
    password: '',
    password_confirmation: '',
});

const selectedEmployee = computed(() => props.employees.data.find((employee) => employee.id === selectedEmployeeId.value) ?? null);
const fieldClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';
const checkboxClass = 'h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';
const filterFieldClass = 'w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';

const visiblePages = computed(() => {
    const total = props.employees.last_page;
    const current = props.employees.current_page;

    if (total <= 7) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }

    const start = Math.max(1, current - 2);
    const end = Math.min(total, start + 4);
    const adjustedStart = Math.max(1, end - 4);

    return Array.from({ length: end - adjustedStart + 1 }, (_, index) => adjustedStart + index);
});

const openEdit = (employee: Employee) => {
    selectedEmployeeId.value = employee.id;
    editForm.defaults({
        name: employee.name,
        email: employee.email,
        phone: employee.phone ?? '',
        hourly_wage: employee.hourly_wage ?? '',
        hourly_customer_rate: employee.hourly_customer_rate ?? '',
        is_active: employee.is_active,
        skill_ids: employee.skills.map((skill) => skill.id),
        password: '',
        password_confirmation: '',
    });
    editForm.reset();
};

const closeEdit = () => {
    selectedEmployeeId.value = null;
};

const applyFilters = (page = 1) => {
    router.get(route('admin.employees.index'), {
        q: filterForm.q,
        status: filterForm.status,
        per_page: filterForm.per_page,
        page,
    }, {
        preserveState: true,
        replace: true,
    });
};

const createEmployee = () => {
    createForm.post(route('admin.employees.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreateModal.value = false;
        },
    });
};

const openCreateModal = () => {
    createForm.reset();
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};

const updateEmployee = () => {
    if (!selectedEmployee.value) {
        return;
    }

    editForm.patch(route('admin.employees.update', selectedEmployee.value.id), {
        preserveScroll: true,
    });
};

const toggleEmployee = (employee: Employee) => {
    const text = employee.is_active ? 'deaktivere' : 'aktivere';

    if (!window.confirm(`Er du sikker på, at du vil ${text} medarbejderen?`)) {
        return;
    }

    router.patch(route('admin.employees.toggle-active', employee.id), {}, { preserveScroll: true });
};

const deleteEmployee = (employee: Employee) => {
    if (!window.confirm('Er du sikker på, at du vil slette medarbejderen?')) {
        return;
    }

    router.delete(route('admin.employees.destroy', employee.id), { preserveScroll: true });
};

</script>

<template>
    <Head title="Medarbejdere" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Medarbejdere</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Opret medarbejder</h3>
                            <p class="text-sm text-gray-600">Formularen vises i modal.</p>
                        </div>
                        <button class="rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" type="button" @click="openCreateModal">
                            Opret medarbejder
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <h3 class="mb-2 text-lg font-semibold text-gray-900">Kompetencer</h3>
                    <p class="mb-4 text-sm text-gray-600">Administrer kompetencer centralt med redigering, søgning og sletning.</p>
                    <Link :href="route('admin.skills.index')" class="inline-flex rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Gå til kompetencer
                    </Link>
                </div>

                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <form class="mb-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4" @submit.prevent="applyFilters(1)">
                        <div class="mb-3">
                            <h3 class="text-lg font-semibold text-gray-900">Alle medarbejdere</h3>
                            <p class="text-xs text-gray-600">Filtrér listen efter navn, status og antal pr. side.</p>
                        </div>
                        <div class="grid gap-3 md:grid-cols-4">
                            <label class="block text-xs font-medium uppercase tracking-wide text-gray-600 md:col-span-2">
                                Søgning
                                <input v-model="filterForm.q" :class="filterFieldClass" placeholder="Søg navn, email, kompetence..." type="text" />
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
                                    <th class="px-3 py-2">Timeløn</th>
                                    <th class="px-3 py-2">Kundepris/time</th>
                                    <th class="px-3 py-2">Kompetencer</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Handling</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="employee in employees.data" :key="employee.id">
                                    <td class="px-3 py-2">{{ employee.name }}</td>
                                    <td class="px-3 py-2">{{ employee.email }}</td>
                                    <td class="px-3 py-2">{{ employee.phone ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ employee.hourly_wage ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ employee.hourly_customer_rate ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ employee.skills.map((skill) => skill.name).join(', ') || '-' }}</td>
                                    <td class="px-3 py-2">{{ employee.is_active ? 'Aktiv' : 'Inaktiv' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-1">
                                            <button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" type="button" @click="openEdit(employee)">Rediger</button>
                                            <button class="rounded border border-amber-300 bg-amber-50 px-2 py-1 text-xs text-amber-800" type="button" @click="toggleEmployee(employee)">{{ employee.is_active ? 'Deaktiver' : 'Aktiver' }}</button>
                                            <button class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700" type="button" @click="deleteEmployee(employee)">Slet</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-gray-600">{{ employees.total }} resultater</p>
                        <div class="flex items-center gap-2">
                            <button
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50"
                                :disabled="employees.current_page <= 1"
                                type="button"
                                @click="applyFilters(employees.current_page - 1)"
                            >
                                Forrige
                            </button>
                            <button
                                v-for="page in visiblePages"
                                :key="page"
                                class="rounded border px-3 py-1.5 text-sm"
                                :class="page === employees.current_page ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-700'"
                                type="button"
                                @click="applyFilters(page)"
                            >
                                {{ page }}
                            </button>
                            <button
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50"
                                :disabled="employees.current_page >= employees.last_page"
                                type="button"
                                @click="applyFilters(employees.current_page + 1)"
                            >
                                Næste
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="selectedEmployee" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="closeEdit">
            <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Rediger medarbejder</h3>
                    <button class="rounded border border-gray-300 px-2 py-1 text-sm" type="button" @click="closeEdit">Luk</button>
                </div>

                <form class="grid gap-4" @submit.prevent="updateEmployee">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Navn</span>
                            <input v-model="editForm.name" :class="fieldClass" placeholder="Navn" type="text" />
                            <InputError class="mt-1" :message="editForm.errors.name" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Email</span>
                            <input v-model="editForm.email" :class="fieldClass" placeholder="Email" required type="email" />
                            <InputError class="mt-1" :message="editForm.errors.email" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Telefon</span>
                            <input v-model="editForm.phone" :class="fieldClass" placeholder="Telefon" type="text" />
                            <InputError class="mt-1" :message="editForm.errors.phone" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Timeløn</span>
                            <input v-model="editForm.hourly_wage" :class="fieldClass" min="0" placeholder="Timeløn" required step="0.01" type="number" />
                            <InputError class="mt-1" :message="editForm.errors.hourly_wage" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Kundepris/time</span>
                            <input v-model="editForm.hourly_customer_rate" :class="fieldClass" min="0" placeholder="Kundepris/time" required step="0.01" type="number" />
                            <InputError class="mt-1" :message="editForm.errors.hourly_customer_rate" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Nyt password (valgfrit)</span>
                            <input v-model="editForm.password" :class="fieldClass" placeholder="Nyt password" type="password" />
                            <InputError class="mt-1" :message="editForm.errors.password" />
                        </label>
                        <label class="block text-sm text-gray-700 sm:col-span-2">
                            <span class="font-medium">Gentag nyt password</span>
                            <input v-model="editForm.password_confirmation" :class="fieldClass" placeholder="Gentag nyt password" type="password" />
                            <InputError class="mt-1" :message="editForm.errors.password_confirmation" />
                        </label>
                    </div>

                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                        <input v-model="editForm.is_active" :class="checkboxClass" type="checkbox" />
                        Aktiv
                    </label>

                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Kompetencer</span>
                        <select v-model="editForm.skill_ids" :class="fieldClass" multiple>
                            <option v-for="skill in skills" :key="skill.id" :value="skill.id">
                                {{ skill.name }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="editForm.errors.skill_ids" />
                    </label>

                    <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" :disabled="editForm.processing" type="submit">
                        Gem ændringer
                    </button>
                </form>
            </div>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="closeCreateModal">
            <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Opret medarbejder</h3>
                    <button class="rounded border border-gray-300 px-2 py-1 text-sm" type="button" @click="closeCreateModal">Luk</button>
                </div>

                <form class="grid gap-4" @submit.prevent="createEmployee">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Navn</span>
                            <input v-model="createForm.name" :class="fieldClass" placeholder="Navn" type="text" />
                            <InputError class="mt-1" :message="createForm.errors.name" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Email</span>
                            <input v-model="createForm.email" :class="fieldClass" placeholder="Email" required type="email" />
                            <InputError class="mt-1" :message="createForm.errors.email" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Telefon</span>
                            <input v-model="createForm.phone" :class="fieldClass" placeholder="Telefon" type="text" />
                            <InputError class="mt-1" :message="createForm.errors.phone" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Timeløn</span>
                            <input v-model="createForm.hourly_wage" :class="fieldClass" min="0" placeholder="Timeløn" required step="0.01" type="number" />
                            <InputError class="mt-1" :message="createForm.errors.hourly_wage" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Kundepris/time</span>
                            <input v-model="createForm.hourly_customer_rate" :class="fieldClass" min="0" placeholder="Kundepris/time" required step="0.01" type="number" />
                            <InputError class="mt-1" :message="createForm.errors.hourly_customer_rate" />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Password</span>
                            <input v-model="createForm.password" :class="fieldClass" placeholder="Password (min. 8 tegn)" required type="password" />
                            <InputError class="mt-1" :message="createForm.errors.password" />
                        </label>
                        <label class="block text-sm text-gray-700 sm:col-span-2">
                            <span class="font-medium">Gentag password</span>
                            <input v-model="createForm.password_confirmation" :class="fieldClass" placeholder="Gentag password" required type="password" />
                            <InputError class="mt-1" :message="createForm.errors.password_confirmation" />
                        </label>
                    </div>

                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                        <input v-model="createForm.is_active" :class="checkboxClass" type="checkbox" />
                        Aktiv
                    </label>

                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Kompetencer</span>
                        <select v-model="createForm.skill_ids" :class="fieldClass" multiple>
                            <option v-for="skill in skills" :key="skill.id" :value="skill.id">
                                {{ skill.name }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="createForm.errors.skill_ids" />
                    </label>

                    <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" :disabled="createForm.processing" type="submit">
                        Opret medarbejder
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

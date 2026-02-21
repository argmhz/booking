<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Skill = {
    id: number;
    name: string;
    description: string | null;
    employee_profiles_count: number;
    created_at: string;
};

type SkillsPage = {
    data: Skill[];
    current_page: number;
    last_page: number;
    total: number;
};

const props = defineProps<{
    skills: SkillsPage;
    filters: {
        q: string;
        per_page: 20 | 50 | 100;
    };
}>();

const selectedSkillId = ref<number | null>(null);

const filterForm = useForm({
    q: props.filters.q ?? '',
    per_page: props.filters.per_page ?? 20,
});

const createForm = useForm({
    name: '',
    description: '',
});

const editForm = useForm({
    name: '',
    description: '',
});

const selectedSkill = computed(() => props.skills.data.find((skill) => skill.id === selectedSkillId.value) ?? null);
const fieldClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';
const filterFieldClass = 'w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';

const visiblePages = computed(() => {
    const total = props.skills.last_page;
    const current = props.skills.current_page;

    if (total <= 7) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }

    const start = Math.max(1, current - 2);
    const end = Math.min(total, start + 4);
    const adjustedStart = Math.max(1, end - 4);

    return Array.from({ length: end - adjustedStart + 1 }, (_, index) => adjustedStart + index);
});

const applyFilters = (page = 1) => {
    router.get(route('admin.skills.index'), {
        q: filterForm.q,
        per_page: filterForm.per_page,
        page,
    }, {
        preserveState: true,
        replace: true,
    });
};

const createSkill = () => {
    createForm.post(route('admin.skills.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const openEdit = (skill: Skill) => {
    selectedSkillId.value = skill.id;
    editForm.defaults({
        name: skill.name,
        description: skill.description ?? '',
    });
    editForm.reset();
};

const closeEdit = () => {
    selectedSkillId.value = null;
};

const updateSkill = () => {
    if (!selectedSkill.value) {
        return;
    }

    editForm.patch(route('admin.skills.update', selectedSkill.value.id), {
        preserveScroll: true,
    });
};

const deleteSkill = (skill: Skill) => {
    if (skill.employee_profiles_count > 0) {
        window.alert('Denne kompetence er tildelt medarbejdere og kan ikke slettes.');
        return;
    }

    if (!window.confirm('Er du sikker pa, at du vil slette kompetencen?')) {
        return;
    }

    router.delete(route('admin.skills.destroy', skill.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Kompetencer" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Kompetencer</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Opret kompetence</h3>
                    <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="createSkill">
                        <label class="block text-sm text-gray-700 sm:col-span-1">
                            <span class="font-medium">Kompetencenavn</span>
                            <input v-model="createForm.name" :class="fieldClass" placeholder="Kompetencenavn" required type="text" />
                            <InputError class="mt-1" :message="createForm.errors.name" />
                        </label>
                        <label class="block text-sm text-gray-700 sm:col-span-2">
                            <span class="font-medium">Beskrivelse</span>
                            <input v-model="createForm.description" :class="fieldClass" placeholder="Beskrivelse (valgfrit)" type="text" />
                            <InputError class="mt-1" :message="createForm.errors.description" />
                        </label>
                        <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black sm:col-span-3 sm:justify-self-start" :disabled="createForm.processing" type="submit">
                            Opret kompetence
                        </button>
                    </form>
                </div>

                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <form class="mb-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4" @submit.prevent="applyFilters(1)">
                        <div class="mb-3">
                            <h3 class="text-lg font-semibold text-gray-900">Alle kompetencer</h3>
                            <p class="text-xs text-gray-600">Find kompetencer hurtigt og tilpas antal pr. side.</p>
                        </div>
                        <div class="grid gap-3 md:grid-cols-4">
                            <label class="block text-xs font-medium uppercase tracking-wide text-gray-600 md:col-span-3">
                                Søgning
                                <input v-model="filterForm.q" :class="filterFieldClass" placeholder="Søg kompetence..." type="text" />
                            </label>
                            <label class="block text-xs font-medium uppercase tracking-wide text-gray-600">
                                Vis pr. side
                                <select v-model="filterForm.per_page" :class="filterFieldClass" @change="applyFilters(1)">
                                    <option :value="20">20 / side</option>
                                    <option :value="50">50 / side</option>
                                    <option :value="100">100 / side</option>
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
                                    <th class="px-3 py-2">Beskrivelse</th>
                                    <th class="px-3 py-2">Tildelt medarbejdere</th>
                                    <th class="px-3 py-2">Handling</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="skill in skills.data" :key="skill.id">
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ skill.name }}</td>
                                    <td class="px-3 py-2">{{ skill.description || '-' }}</td>
                                    <td class="px-3 py-2">{{ skill.employee_profiles_count }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-1">
                                            <button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" type="button" @click="openEdit(skill)">Rediger</button>
                                            <button class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="skill.employee_profiles_count > 0" type="button" @click="deleteSkill(skill)">
                                                Slet
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-gray-600">{{ skills.total }} resultater</p>
                        <div class="flex items-center gap-2">
                            <button
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50"
                                :disabled="skills.current_page <= 1"
                                type="button"
                                @click="applyFilters(skills.current_page - 1)"
                            >
                                Forrige
                            </button>
                            <button
                                v-for="page in visiblePages"
                                :key="page"
                                class="rounded border px-3 py-1.5 text-sm"
                                :class="page === skills.current_page ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-700'"
                                type="button"
                                @click="applyFilters(page)"
                            >
                                {{ page }}
                            </button>
                            <button
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50"
                                :disabled="skills.current_page >= skills.last_page"
                                type="button"
                                @click="applyFilters(skills.current_page + 1)"
                            >
                                Næste
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="selectedSkill" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="closeEdit">
            <div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Rediger kompetence</h3>
                    <button class="rounded border border-gray-300 px-2 py-1 text-sm" type="button" @click="closeEdit">Luk</button>
                </div>

                <form class="grid gap-4" @submit.prevent="updateSkill">
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Kompetencenavn</span>
                        <input v-model="editForm.name" :class="fieldClass" placeholder="Kompetencenavn" required type="text" />
                        <InputError class="mt-1" :message="editForm.errors.name" />
                    </label>
                    <label class="block text-sm text-gray-700">
                        <span class="font-medium">Beskrivelse</span>
                        <textarea v-model="editForm.description" :class="fieldClass" placeholder="Beskrivelse (valgfrit)" rows="4" />
                        <InputError class="mt-1" :message="editForm.errors.description" />
                    </label>

                    <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black" :disabled="editForm.processing" type="submit">
                        Gem aendringer
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

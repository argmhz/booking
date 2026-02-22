<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type Company = {
    id: number;
    name: string;
    addresses: {
        id: number;
        label: string;
        address_line_1: string;
        address_line_2: string | null;
        postal_code: string | null;
        city: string;
        country: string;
        is_default: boolean;
    }[];
};

type Employee = {
    id: number;
    name: string;
    email: string;
};

type UserRef = {
    id: number;
    name: string;
};

type Booking = {
    id: number;
    company_id: number;
    company_address_id: number | null;
    created_by: number;
    created_at: string;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    required_workers: number;
    assignment_mode: 'specific_employees' | 'first_come_first_served';
    status: string;
    closed_at: string | null;
    closed_by: number | null;
    approved_at: string | null;
    approved_by: number | null;
    executed_at: string | null;
    executed_by: number | null;
    is_invoiced: boolean;
    is_paid: boolean;
    workflow_status?: 'open' | 'approved' | 'executed' | 'invoiced' | 'paid';
    show_employee_names_to_company: boolean;
    creator: UserRef | null;
    approver: UserRef | null;
    closer: UserRef | null;
    executor: UserRef | null;
    company: Company | null;
    company_address: {
        id: number;
        label: string;
        address_line_1: string;
        address_line_2: string | null;
        postal_code: string | null;
        city: string;
        country: string;
        is_default: boolean;
    } | null;
    assignments: {
        id: number;
        status: string;
        assigned_at: string | null;
        worker_rate: string | number | null;
        customer_rate: string | number | null;
        employee: Employee | null;
    }[];
    waitlist_entries: {
        id: number;
        position: number;
        employee: Employee | null;
    }[];
};

const props = defineProps<{
    bookings: Booking[];
    companies: Company[];
    employees: Employee[];
}>();

const currentMonth = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const selectedBookingId = ref<number | null>(null);
const activeModalTab = ref<'details' | 'staff' | 'waitlist'>('details');
const stateFilter = ref<'all' | 'open' | 'approved' | 'executed' | 'invoiced' | 'paid'>('all');
const fieldClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';
const checkboxClass = 'h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';

const monthFormatter = new Intl.DateTimeFormat('da-DK', {
    month: 'long',
    year: 'numeric',
});

const weekdayLabels = ['man', 'tir', 'ons', 'tor', 'fre', 'lør', 'søn'];

const toLocalDateKey = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const toLocalDateTimeInput = (value: string): string => {
    const date = new Date(value);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const formatBookingAddress = (booking: Booking): string => {
    if (!booking.company_address) {
        return '-';
    }

    const address = booking.company_address;

    return `${address.label} - ${address.address_line_1}, ${address.postal_code ?? ''} ${address.city}`.trim();
};

const formatAuditDateTime = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('da-DK');
};

const monthTitle = computed(() => {
    const value = monthFormatter.format(currentMonth.value);

    return value.charAt(0).toUpperCase() + value.slice(1);
});

const bookingsInView = computed(() => {
    return props.bookings.filter((booking) => {
        const start = new Date(booking.starts_at);

        return start.getFullYear() === currentMonth.value.getFullYear()
            && start.getMonth() === currentMonth.value.getMonth();
    });
});

const filteredBookingsInView = computed(() => {
    return bookingsInView.value.filter((booking) => {
        if (stateFilter.value === 'all') {
            return true;
        }

        return bookingWorkflowStatus(booking) === stateFilter.value;
    });
});

const bookingsByDate = computed(() => {
    const mapped: Record<string, Booking[]> = {};

    for (const booking of filteredBookingsInView.value) {
        const date = toLocalDateKey(new Date(booking.starts_at));

        if (!mapped[date]) {
            mapped[date] = [];
        }

        mapped[date].push(booking);
    }

    return mapped;
});

const bookingWorkflowStatus = (booking: Booking): 'open' | 'approved' | 'executed' | 'invoiced' | 'paid' => {
    if (booking.workflow_status) {
        return booking.workflow_status;
    }

    if (booking.is_paid) {
        return 'paid';
    }

    if (booking.is_invoiced) {
        return 'invoiced';
    }

    if (booking.executed_at) {
        return 'executed';
    }

    if (booking.approved_at) {
        return 'approved';
    }

    return 'open';
};

const bookingStateLabel = (booking: Booking): string => {
    const status = bookingWorkflowStatus(booking);

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

const bookingStateClass = (booking: Booking): string => {
    const status = bookingWorkflowStatus(booking);

    if (status === 'paid') {
        return 'border-fuchsia-300 bg-fuchsia-50 text-fuchsia-900';
    }

    if (status === 'invoiced') {
        return 'border-cyan-300 bg-cyan-50 text-cyan-900';
    }

    if (status === 'executed') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-900';
    }

    if (status === 'approved') {
        return 'border-blue-300 bg-blue-50 text-blue-900';
    }

    return 'border-amber-300 bg-amber-50 text-amber-900';
};

const calendarDays = computed(() => {
    const first = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth(), 1);
    const firstWeekdayMondayBased = (first.getDay() + 6) % 7;
    const gridStart = new Date(first);
    gridStart.setDate(first.getDate() - firstWeekdayMondayBased);

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + index);
        const iso = toLocalDateKey(date);

        return {
            iso,
            dayNumber: date.getDate(),
            inCurrentMonth: date.getMonth() === currentMonth.value.getMonth(),
            bookings: bookingsByDate.value[iso] ?? [],
        };
    });
});

const selectedBooking = computed(() => {
    if (!selectedBookingId.value) {
        return null;
    }

    return props.bookings.find((booking) => booking.id === selectedBookingId.value) ?? null;
});

const isSelectedBookingLocked = computed(() => !!selectedBooking.value?.executed_at);
const selectedBookingWorkflowStatus = computed(() => {
    if (!selectedBooking.value) {
        return 'open';
    }

    return bookingWorkflowStatus(selectedBooking.value);
});
const canApproveSelectedBooking = computed(() => selectedBookingWorkflowStatus.value === 'open' && !isSelectedBookingLocked.value);
const canRevokeSelectedBookingApproval = computed(() => selectedBookingWorkflowStatus.value === 'approved' && !isSelectedBookingLocked.value);

const editForm = useForm({
    company_id: props.companies[0]?.id ?? null,
    company_address_id: (
        props.companies[0]?.addresses.find((address) => address.is_default)?.id
        ?? props.companies[0]?.addresses[0]?.id
        ?? null
    ) as number | null,
    title: '',
    description: '',
    starts_at: '',
    ends_at: '',
    required_workers: 1,
    assignment_mode: 'first_come_first_served' as 'specific_employees' | 'first_come_first_served',
    show_employee_names_to_company: false,
});

const requestForm = useForm({
    employee_user_ids: [] as number[],
});

const addEmployeeForm = useForm({
    employee_user_id: null as number | null,
});
const addEmployeeSearch = ref('');
const assignmentRateForms = ref<Record<number, { worker_rate: string; customer_rate: string }>>({});

const availableEmployees = computed(() => {
    const booking = selectedBooking.value;

    if (!booking) {
        return props.employees;
    }

    const blocked = new Set<number>();

    for (const assignment of booking.assignments) {
        if (assignment.employee) {
            blocked.add(assignment.employee.id);
        }
    }

    for (const entry of booking.waitlist_entries) {
        if (entry.employee) {
            blocked.add(entry.employee.id);
        }
    }

    return props.employees.filter((employee) => !blocked.has(employee.id));
});

const filteredAvailableEmployees = computed(() => {
    const term = addEmployeeSearch.value.trim().toLowerCase();

    if (!term) {
        return availableEmployees.value;
    }

    return availableEmployees.value.filter((employee) => {
        const haystack = `${employee.name} ${employee.email}`.toLowerCase();

        return haystack.includes(term);
    });
});

const selectedCompanyAddresses = computed(() => {
    const company = props.companies.find((item) => item.id === editForm.company_id);

    return company?.addresses ?? [];
});

const bookingDurationHours = (booking: Booking): number => {
    const start = new Date(booking.starts_at).getTime();
    const end = new Date(booking.ends_at).getTime();
    const diff = (end - start) / (1000 * 60 * 60);

    return Number.isFinite(diff) && diff > 0 ? Number(diff.toFixed(2)) : 0;
};

const toNumber = (value: string | number | null): number => {
    if (value === null || value === '') {
        return 0;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
};

watch(selectedBooking, (booking) => {
    if (!booking) {
        return;
    }

    editForm.defaults({
        company_id: booking.company_id,
        company_address_id: booking.company_address_id ?? undefined,
        title: booking.title,
        description: booking.description ?? '',
        starts_at: toLocalDateTimeInput(booking.starts_at),
        ends_at: toLocalDateTimeInput(booking.ends_at),
        required_workers: booking.required_workers,
        assignment_mode: booking.assignment_mode,
        show_employee_names_to_company: booking.show_employee_names_to_company,
    });
    editForm.reset();
    requestForm.reset();
    addEmployeeForm.reset();
    addEmployeeSearch.value = '';
    assignmentRateForms.value = Object.fromEntries(
        booking.assignments.map((assignment) => [
            assignment.id,
            {
                worker_rate: assignment.worker_rate === null ? '' : String(assignment.worker_rate),
                customer_rate: assignment.customer_rate === null ? '' : String(assignment.customer_rate),
            },
        ]),
    );
}, { immediate: true });

watch(() => editForm.company_id, () => {
    const addresses = selectedCompanyAddresses.value;

    if (!addresses.length) {
        editForm.company_address_id = null;

        return;
    }

    if (addresses.some((address) => address.id === editForm.company_address_id)) {
        return;
    }

    editForm.company_address_id = addresses.find((address) => address.is_default)?.id ?? addresses[0].id;
});

const previousMonth = () => {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 1);
};

const openBookingModal = (bookingId: number) => {
    selectedBookingId.value = bookingId;
    activeModalTab.value = 'details';
};

const closeBookingModal = () => {
    selectedBookingId.value = null;
};

const submitBookingUpdate = () => {
    if (!selectedBooking.value) {
        return;
    }

    editForm.patch(route('bookings.update', selectedBooking.value.id), { preserveScroll: true });
};

const deleteBooking = () => {
    if (!selectedBooking.value) {
        return;
    }

    if (!window.confirm('Er du sikker på, at du vil slette denne booking?')) {
        return;
    }

    router.delete(route('bookings.destroy', selectedBooking.value.id));
};

const submitRequests = () => {
    if (!selectedBooking.value) {
        return;
    }

    requestForm.post(route('bookings.requests.store', selectedBooking.value.id), { preserveScroll: true });
};

const addEmployee = () => {
    if (!selectedBooking.value || !addEmployeeForm.employee_user_id) {
        return;
    }

    addEmployeeForm.post(route('bookings.employees.add', selectedBooking.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            addEmployeeSearch.value = '';
            addEmployeeForm.reset();
        },
    });
};

const cancelAssignment = (bookingId: number, assignmentId: number) => {
    if (!window.confirm('Er du sikker på, at du vil afbryde denne tildeling?')) {
        return;
    }

    router.delete(route('bookings.assignments.cancel', {
        booking: bookingId,
        assignment: assignmentId,
    }), { preserveScroll: true });
};

const updateAssignmentRates = (bookingId: number, assignmentId: number) => {
    const rates = assignmentRateForms.value[assignmentId];

    if (!rates) {
        return;
    }

    router.patch(route('bookings.assignments.rates.update', {
        booking: bookingId,
        assignment: assignmentId,
    }), rates, {
        preserveScroll: true,
    });
};

const removeWaitlistEntry = (bookingId: number, waitlistId: number) => {
    if (!window.confirm('Er du sikker på, at du vil fjerne denne fra ventelisten?')) {
        return;
    }

    router.delete(route('bookings.waitlist.remove', {
        booking: bookingId,
        waitlistEntry: waitlistId,
    }), { preserveScroll: true });
};

const promoteWaitlistEntry = (bookingId: number, waitlistId: number) => {
    if (!window.confirm('Er du sikker på, at du vil promovere denne ventelisteplads?')) {
        return;
    }

    router.post(route('bookings.waitlist.promote', {
        booking: bookingId,
        waitlistEntry: waitlistId,
    }), {}, { preserveScroll: true });
};

const approveBooking = (bookingId: number) => {
    router.post(route('bookings.approve', bookingId), {}, { preserveScroll: true });
};

const revokeBookingApproval = (bookingId: number) => {
    if (!window.confirm('Vil du fjerne godkendelsen på denne booking?')) {
        return;
    }

    router.post(route('bookings.revoke-approval', bookingId), {}, { preserveScroll: true });
};

</script>

<template>
    <Head title="Booking Kalender" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Booking Kalender</h2>
                <Link :href="route('bookings.create')" class="rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black">
                    Opret booking
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-4 shadow-sm sm:p-6">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <button class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" type="button" @click="previousMonth">Forrige</button>
                        <h3 class="text-lg font-semibold text-gray-900">{{ monthTitle }}</h3>
                        <button class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" type="button" @click="nextMonth">Næste</button>
                    </div>

                    <div class="mb-4 flex flex-wrap gap-2">
                        <button
                            class="rounded px-3 py-1.5 text-xs font-semibold"
                            :class="stateFilter === 'all' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                            type="button"
                            @click="stateFilter = 'all'"
                        >
                            Alle
                        </button>
                        <button
                            class="rounded px-3 py-1.5 text-xs font-semibold"
                            :class="stateFilter === 'open' ? 'bg-amber-600 text-white' : 'border border-amber-300 text-amber-800 hover:bg-amber-50'"
                            type="button"
                            @click="stateFilter = 'open'"
                        >
                            Åbne
                        </button>
                        <button
                            class="rounded px-3 py-1.5 text-xs font-semibold"
                            :class="stateFilter === 'approved' ? 'bg-blue-600 text-white' : 'border border-blue-300 text-blue-800 hover:bg-blue-50'"
                            type="button"
                            @click="stateFilter = 'approved'"
                        >
                            Godkendte
                        </button>
                        <button
                            class="rounded px-3 py-1.5 text-xs font-semibold"
                            :class="stateFilter === 'executed' ? 'bg-emerald-600 text-white' : 'border border-emerald-300 text-emerald-800 hover:bg-emerald-50'"
                            type="button"
                            @click="stateFilter = 'executed'"
                        >
                            Eksekverede
                        </button>
                        <button
                            class="rounded px-3 py-1.5 text-xs font-semibold"
                            :class="stateFilter === 'invoiced' ? 'bg-cyan-600 text-white' : 'border border-cyan-300 text-cyan-800 hover:bg-cyan-50'"
                            type="button"
                            @click="stateFilter = 'invoiced'"
                        >
                            Fakturerede
                        </button>
                        <button
                            class="rounded px-3 py-1.5 text-xs font-semibold"
                            :class="stateFilter === 'paid' ? 'bg-fuchsia-600 text-white' : 'border border-fuchsia-300 text-fuchsia-800 hover:bg-fuchsia-50'"
                            type="button"
                            @click="stateFilter = 'paid'"
                        >
                            Betalte
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <div v-for="weekday in weekdayLabels" :key="weekday">{{ weekday }}</div>
                    </div>

                    <div class="mt-2 grid grid-cols-7 gap-2">
                        <div v-for="day in calendarDays" :key="day.iso" class="min-h-28 rounded-lg border p-2" :class="day.inCurrentMonth ? 'bg-white' : 'bg-gray-50 text-gray-400'">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-semibold">{{ day.dayNumber }}</span>
                                <Link
                                    v-if="day.inCurrentMonth"
                                    :href="route('bookings.create', { date: day.iso })"
                                    class="rounded border border-gray-300 px-1.5 py-0.5 text-xs text-gray-600 hover:bg-gray-100"
                                >
                                    +
                                </Link>
                            </div>

                            <div class="space-y-1">
                                <button
                                    v-for="booking in day.bookings"
                                    :key="booking.id"
                                    class="w-full rounded border px-2 py-1 text-left text-xs hover:brightness-95"
                                    :class="bookingStateClass(booking)"
                                    type="button"
                                    @click="openBookingModal(booking.id)"
                                >
                                    <div class="truncate font-semibold">{{ booking.title }}</div>
                                    <div class="truncate text-[11px]">{{ booking.company?.name ?? '-' }}</div>
                                    <div class="truncate text-[11px]">{{ formatBookingAddress(booking) }}</div>
                                    <div class="mt-1 text-[10px] font-medium uppercase tracking-wide">{{ bookingStateLabel(booking) }}</div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="selectedBooking" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="closeBookingModal">
            <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Rediger booking: {{ selectedBooking.title }}</h3>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span
                                class="rounded border px-2 py-1 font-medium uppercase tracking-wide"
                                :class="bookingStateClass(selectedBooking)"
                            >
                                {{ bookingStateLabel(selectedBooking) }}
                            </span>
                            <span class="rounded border border-gray-300 px-2 py-1 text-gray-700">
                                {{ formatBookingAddress(selectedBooking) }}
                            </span>
                            <span v-if="isSelectedBookingLocked" class="rounded border border-red-300 bg-red-50 px-2 py-1 font-medium text-red-700">
                                Låst (eksekveret)
                            </span>
                        </div>
                    </div>
                    <button class="rounded border border-gray-300 px-2 py-1 text-sm" type="button" @click="closeBookingModal">Luk</button>
                </div>

                <div class="mb-4 flex flex-wrap gap-2">
                    <button class="rounded px-3 py-1.5 text-sm font-medium" :class="activeModalTab === 'details' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-700'" type="button" @click="activeModalTab = 'details'">Detaljer</button>
                    <button class="rounded px-3 py-1.5 text-sm font-medium" :class="activeModalTab === 'staff' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-700'" type="button" @click="activeModalTab = 'staff'">Medarbejdere</button>
                    <button class="rounded px-3 py-1.5 text-sm font-medium" :class="activeModalTab === 'waitlist' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-700'" type="button" @click="activeModalTab = 'waitlist'">Venteliste</button>
                </div>

                <div v-if="activeModalTab === 'details'" class="space-y-6">
                    <div class="rounded border bg-gray-50 p-3">
                        <h4 class="mb-2 text-sm font-semibold text-gray-900">Audit-log</h4>
                        <div class="grid gap-2 text-xs text-gray-700 sm:grid-cols-2">
                            <p>
                                Oprettet af:
                                <span class="font-medium">{{ selectedBooking.creator?.name ?? `Bruger #${selectedBooking.created_by}` }}</span>
                                ({{ formatAuditDateTime(selectedBooking.created_at) }})
                            </p>
                            <p>
                                Godkendt af:
                                <span class="font-medium">{{ selectedBooking.approver?.name ?? (selectedBooking.approved_at ? `Bruger #${selectedBooking.approved_by}` : '-') }}</span>
                                ({{ formatAuditDateTime(selectedBooking.approved_at) }})
                            </p>
                            <p>
                                Lukket af:
                                <span class="font-medium">{{ selectedBooking.closer?.name ?? (selectedBooking.closed_by ? `Bruger #${selectedBooking.closed_by}` : '-') }}</span>
                                ({{ formatAuditDateTime(selectedBooking.closed_at) }})
                            </p>
                            <p>
                                Eksekveret af:
                                <span class="font-medium">{{ selectedBooking.executor?.name ?? (selectedBooking.executed_at ? 'System (automatisk)' : '-') }}</span>
                                ({{ formatAuditDateTime(selectedBooking.executed_at) }})
                            </p>
                        </div>
                    </div>

                    <div class="rounded border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-900">
                        <p class="font-semibold">Workflow: open -> approved -> executed -> invoiced -> paid</p>
                        <p class="mt-1">
                            Nuværende status: <span class="font-semibold">{{ bookingStateLabel(selectedBooking) }}</span>.
                            Næste mulige handling:
                            <span v-if="canApproveSelectedBooking" class="font-semibold">Godkend booking</span>
                            <span v-else-if="canRevokeSelectedBookingApproval" class="font-semibold">Fjern godkendelse</span>
                            <span v-else-if="selectedBookingWorkflowStatus === 'executed'" class="font-semibold">Markér faktureret i Økonomi</span>
                            <span v-else-if="selectedBookingWorkflowStatus === 'invoiced'" class="font-semibold">Markér betalt i Økonomi</span>
                            <span v-else class="font-semibold">Ingen (booking er afsluttet)</span>
                        </p>
                    </div>

                    <form class="grid gap-4 lg:grid-cols-2" @submit.prevent="submitBookingUpdate">
                        <label class="block text-sm text-gray-700 lg:col-span-2">
                            <span class="font-medium">Virksomhed</span>
                            <select v-model="editForm.company_id" :class="fieldClass">
                                <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                            </select>
                            <InputError class="mt-1" :message="editForm.errors.company_id" />
                        </label>

                        <label class="block text-sm text-gray-700 lg:col-span-2">
                            <span class="font-medium">Adresse</span>
                            <select v-model="editForm.company_address_id" :class="fieldClass">
                                <option :value="null">Ingen specifik adresse</option>
                                <option v-for="address in selectedCompanyAddresses" :key="address.id" :value="address.id">
                                    {{ address.label }} - {{ address.address_line_1 }}, {{ address.postal_code ?? '' }} {{ address.city }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="editForm.errors.company_address_id" />
                        </label>

                        <label class="block text-sm text-gray-700 lg:col-span-2">
                            <span class="font-medium">Titel</span>
                            <input v-model="editForm.title" :class="fieldClass" required type="text" />
                            <InputError class="mt-1" :message="editForm.errors.title" />
                        </label>

                        <label class="block text-sm text-gray-700 lg:col-span-2">
                            <span class="font-medium">Beskrivelse</span>
                            <textarea v-model="editForm.description" :class="fieldClass" rows="3" />
                            <InputError class="mt-1" :message="editForm.errors.description" />
                        </label>

                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Start</span>
                            <input v-model="editForm.starts_at" :class="fieldClass" required type="datetime-local" />
                            <InputError class="mt-1" :message="editForm.errors.starts_at" />
                        </label>

                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Slut</span>
                            <input v-model="editForm.ends_at" :class="fieldClass" required type="datetime-local" />
                            <InputError class="mt-1" :message="editForm.errors.ends_at" />
                        </label>

                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Antal medarbejdere</span>
                            <input v-model="editForm.required_workers" :class="fieldClass" min="1" required type="number" />
                            <InputError class="mt-1" :message="editForm.errors.required_workers" />
                        </label>

                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Tildelingsmode</span>
                            <select v-model="editForm.assignment_mode" :class="fieldClass">
                                <option value="first_come_first_served">Først til mølle</option>
                                <option value="specific_employees">Specifikke medarbejdere</option>
                            </select>
                            <InputError class="mt-1" :message="editForm.errors.assignment_mode" />
                        </label>

                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 lg:col-span-2">
                            <input v-model="editForm.show_employee_names_to_company" :class="checkboxClass" type="checkbox" />
                            Vis medarbejdernavne til virksomhed
                        </label>

                        <div class="flex gap-2 lg:col-span-2">
                            <button class="rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black disabled:opacity-50" :disabled="editForm.processing || isSelectedBookingLocked" type="submit">Gem booking</button>
                            <button
                                v-if="canApproveSelectedBooking"
                                class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                                type="button"
                                @click="approveBooking(selectedBooking.id)"
                            >
                                Godkend booking
                            </button>
                            <button
                                v-else-if="canRevokeSelectedBookingApproval"
                                class="rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                type="button"
                                @click="revokeBookingApproval(selectedBooking.id)"
                            >
                                Fjern godkendelse
                            </button>
                            <button class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50" :disabled="isSelectedBookingLocked" type="button" @click="deleteBooking">Slet booking</button>
                        </div>
                    </form>

                </div>

                <div v-else-if="activeModalTab === 'staff'" class="space-y-6">
                    <form class="space-y-3 rounded border p-3" @submit.prevent="submitRequests">
                        <h4 class="text-sm font-semibold text-gray-900">Send booking-forespørgsel</h4>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Medarbejdere (valgfrit for først-til-mølle)</span>
                            <select v-model="requestForm.employee_user_ids" :class="fieldClass" multiple>
                                <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }} ({{ employee.email }})</option>
                            </select>
                            <InputError class="mt-1" :message="requestForm.errors.employee_user_ids" />
                        </label>

                        <button class="rounded bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-black disabled:opacity-50" :disabled="requestForm.processing || selectedBookingWorkflowStatus === 'open' || isSelectedBookingLocked" type="submit">Send forespørgsler</button>
                        <p v-if="selectedBookingWorkflowStatus === 'open'" class="text-xs text-amber-700">Bookingen skal godkendes før forespørgsler kan sendes.</p>
                        <p v-if="isSelectedBookingLocked" class="text-xs text-red-700">Bookingen er låst og kan ikke ændres.</p>
                    </form>

                    <form class="space-y-3 rounded border p-3" @submit.prevent="addEmployee">
                        <h4 class="text-sm font-semibold text-gray-900">Tilføj medarbejder til booking</h4>
                        <p class="text-xs text-gray-600">
                            Tildelt: {{ selectedBooking.assignments.length }} / {{ selectedBooking.required_workers }}.
                            Ekstra medarbejdere sættes automatisk på venteliste.
                        </p>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Søg medarbejder</span>
                            <input
                                v-model="addEmployeeSearch"
                                :class="fieldClass"
                                autocomplete="off"
                                placeholder="Søg på navn eller email"
                                type="text"
                            />
                        </label>
                        <label class="block text-sm text-gray-700">
                            <span class="font-medium">Medarbejder ({{ filteredAvailableEmployees.length }})</span>
                            <select v-model="addEmployeeForm.employee_user_id" :class="fieldClass">
                                <option :value="null">Vælg medarbejder</option>
                                <option v-for="employee in filteredAvailableEmployees" :key="employee.id" :value="employee.id">
                                    {{ employee.name }} ({{ employee.email }})
                                </option>
                            </select>
                            <InputError class="mt-1" :message="addEmployeeForm.errors.employee_user_id" />
                        </label>
                        <p v-if="!filteredAvailableEmployees.length" class="text-xs text-amber-700">
                            Ingen medarbejdere matcher din søgning.
                        </p>
                        <button class="rounded bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-black disabled:opacity-50" :disabled="addEmployeeForm.processing || !addEmployeeForm.employee_user_id || isSelectedBookingLocked" type="submit">Tilføj medarbejder</button>
                        <p v-if="isSelectedBookingLocked" class="text-xs text-red-700">Bookingen er låst og kan ikke ændres.</p>
                    </form>

                    <div class="rounded border p-3">
                        <h4 class="mb-3 text-sm font-semibold text-gray-900">Tildelte medarbejdere</h4>
                        <div v-if="selectedBooking.assignments.length" class="space-y-2">
                            <div v-for="assignment in selectedBooking.assignments" :key="assignment.id" class="space-y-2 rounded border border-gray-200 px-2 py-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm">{{ assignment.employee?.name ?? 'Ukendt medarbejder' }}</span>
                                    <button class="rounded bg-red-600 px-2 py-1 text-xs font-semibold text-white disabled:opacity-50" :disabled="isSelectedBookingLocked" type="button" @click="cancelAssignment(selectedBooking.id, assignment.id)">Fjern</button>
                                </div>
                                <div v-if="assignmentRateForms[assignment.id]" class="grid gap-2 sm:grid-cols-3">
                                    <input
                                        v-model="assignmentRateForms[assignment.id].worker_rate"
                                        class="rounded-lg border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-200"
                                        min="0"
                                        placeholder="Løn/time"
                                        step="0.01"
                                        type="number"
                                    />
                                    <input
                                        v-model="assignmentRateForms[assignment.id].customer_rate"
                                        class="rounded-lg border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-200"
                                        min="0"
                                        placeholder="Kundepris/time"
                                        step="0.01"
                                        type="number"
                                    />
                                    <button
                                        class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50 disabled:opacity-50"
                                        type="button"
                                        :disabled="isSelectedBookingLocked"
                                        @click="updateAssignmentRates(selectedBooking.id, assignment.id)"
                                    >
                                        Gem satser
                                    </button>
                                </div>
                                <p class="text-xs text-gray-600">
                                    Timer (fra booking): {{ bookingDurationHours(selectedBooking) }}
                                </p>
                                <p class="text-xs text-gray-600">
                                    Margin/time:
                                    {{
                                        assignmentRateForms[assignment.id].worker_rate !== ''
                                        && assignmentRateForms[assignment.id].customer_rate !== ''
                                            ? (
                                                Number(assignmentRateForms[assignment.id].customer_rate)
                                                - Number(assignmentRateForms[assignment.id].worker_rate)
                                            ).toFixed(2)
                                            : '-'
                                    }}
                                </p>
                                <div class="grid gap-2 text-xs text-gray-600 sm:grid-cols-3">
                                    <div>
                                        Løn total:
                                        {{
                                            (bookingDurationHours(selectedBooking) * toNumber(assignmentRateForms[assignment.id].worker_rate)).toFixed(2)
                                        }}
                                    </div>
                                    <div>
                                        Pris total:
                                        {{
                                            (bookingDurationHours(selectedBooking) * toNumber(assignmentRateForms[assignment.id].customer_rate)).toFixed(2)
                                        }}
                                    </div>
                                    <div>
                                        Margin total:
                                        {{
                                            (
                                                (bookingDurationHours(selectedBooking) * toNumber(assignmentRateForms[assignment.id].customer_rate))
                                                - (bookingDurationHours(selectedBooking) * toNumber(assignmentRateForms[assignment.id].worker_rate))
                                            ).toFixed(2)
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500">Ingen tildelte medarbejdere.</p>
                    </div>

                    <div v-if="selectedBooking.waitlist_entries.length" class="rounded border border-amber-200 bg-amber-50 p-3">
                        <h4 class="mb-2 text-sm font-semibold text-amber-900">Venteliste i denne booking</h4>
                        <p class="mb-2 text-xs text-amber-800">
                            {{ selectedBooking.waitlist_entries.length }} medarbejdere venter på plads.
                        </p>
                        <p class="text-xs text-amber-800">
                            {{ selectedBooking.waitlist_entries.map((entry) => entry.employee?.name ?? 'Ukendt').join(', ') }}
                        </p>
                    </div>
                </div>

                <div v-else class="space-y-6">
                    <div class="rounded border p-3">
                        <h4 class="mb-3 text-sm font-semibold text-gray-900">Venteliste</h4>
                        <p v-if="selectedBookingWorkflowStatus === 'open'" class="mb-3 text-xs text-amber-700">Bookingen skal godkendes før venteliste kan promoveres.</p>
                        <div v-if="selectedBooking.waitlist_entries.length" class="space-y-2">
                            <div v-for="entry in selectedBooking.waitlist_entries" :key="entry.id" class="flex items-center justify-between gap-2 rounded border border-gray-200 px-2 py-2">
                                <span class="text-sm">#{{ entry.position }} {{ entry.employee?.name ?? 'Ukendt medarbejder' }}</span>
                                <div class="flex gap-1">
                                    <button class="rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white disabled:opacity-50" :disabled="selectedBookingWorkflowStatus === 'open' || isSelectedBookingLocked" type="button" @click="promoteWaitlistEntry(selectedBooking.id, entry.id)">Promover</button>
                                    <button class="rounded bg-red-600 px-2 py-1 text-xs font-semibold text-white disabled:opacity-50" :disabled="isSelectedBookingLocked" type="button" @click="removeWaitlistEntry(selectedBooking.id, entry.id)">Fjern</button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500">Ventelisten er tom.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

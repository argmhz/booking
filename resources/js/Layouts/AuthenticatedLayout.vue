<script setup lang="ts">
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ToastNotifications from '@/Components/ToastNotifications.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const page = usePage();

const currentUser = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.user?.roles ?? []);
const isAdmin = computed(() => roles.value.includes('admin'));
const isEmployee = computed(() => roles.value.includes('employee'));
const isCompany = computed(() => roles.value.includes('company'));
const notificationItems = computed(() => currentUser.value?.notifications?.items ?? []);
const unreadNotificationCount = computed(() => currentUser.value?.notifications?.unread_count ?? 0);

const formatNotificationTime = (value: string): string => {
    return new Intl.DateTimeFormat('da-DK', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <div class="app-theme">
        <ToastNotifications />
        <div class="min-h-screen bg-gray-100 pb-8">
            <nav
                class="sticky top-0 z-40 border-b border-slate-700/90 bg-slate-950/95 backdrop-blur-xl"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden items-center gap-6 sm:ms-10 sm:flex">
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    v-if="isAdmin"
                                    :href="route('bookings.calendar')"
                                    :active="route().current('bookings.calendar')"
                                >
                                    Bookings
                                </NavLink>
                                <NavLink
                                    v-if="isAdmin"
                                    :href="route('admin.companies.index')"
                                    :active="route().current('admin.companies.*')"
                                >
                                    Virksomheder
                                </NavLink>
                                <NavLink
                                    v-if="isAdmin"
                                    :href="route('admin.employees.index')"
                                    :active="route().current('admin.employees.*')"
                                >
                                    Medarbejdere
                                </NavLink>
                                <NavLink
                                    v-if="isAdmin"
                                    :href="route('admin.admins.index')"
                                    :active="route().current('admin.admins.*')"
                                >
                                    Administratorer
                                </NavLink>
                                <NavLink
                                    v-if="isAdmin"
                                    :href="route('admin.skills.index')"
                                    :active="route().current('admin.skills.*')"
                                >
                                    Kompetencer
                                </NavLink>
                                <NavLink
                                    v-if="isAdmin"
                                    :href="route('admin.finance.index')"
                                    :active="route().current('admin.finance.*')"
                                >
                                    Økonomi
                                </NavLink>
                                <NavLink
                                    v-if="isEmployee"
                                    :href="route('employee.requests.index')"
                                    :active="route().current('employee.requests.*')"
                                >
                                    Mine forespørgsler
                                </NavLink>
                                <NavLink
                                    v-if="isCompany"
                                    :href="route('company.bookings.index')"
                                    :active="route().current('company.bookings.*')"
                                >
                                    Mine bookinger
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden gap-3 sm:ms-6 sm:flex sm:items-center">
                            <div class="relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="relative inline-flex items-center rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-sm font-medium leading-4 text-slate-200 transition duration-150 ease-in-out hover:bg-slate-800 focus:outline-none"
                                            >
                                                Notifikationer
                                                <span
                                                    v-if="unreadNotificationCount > 0"
                                                    class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-xs font-semibold text-white"
                                                >
                                                    {{ unreadNotificationCount }}
                                                </span>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="max-h-96 overflow-y-auto">
                                            <div class="flex items-center justify-between px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                <span>Seneste</span>
                                                <Link
                                                    :href="route('notifications.read-all')"
                                                    method="post"
                                                    as="button"
                                                    class="rounded border border-slate-600 px-2 py-1 text-[11px] text-slate-300 hover:bg-slate-800"
                                                >
                                                    Marker alle laest
                                                </Link>
                                            </div>
                                            <div v-if="notificationItems.length === 0" class="px-4 py-3 text-sm text-slate-400">
                                                Ingen notifikationer.
                                            </div>
                                            <div v-for="notification in notificationItems" :key="notification.id" class="border-t border-slate-700 px-4 py-2">
                                                <p class="text-sm font-semibold text-slate-100">
                                                    {{ notification.title }}
                                                </p>
                                                <p class="text-xs text-slate-300">
                                                    {{ notification.message }}
                                                </p>
                                                <div class="mt-1 flex items-center justify-between gap-2">
                                                    <p class="text-[11px] text-slate-400">
                                                        {{ formatNotificationTime(notification.created_at) }}
                                                    </p>
                                                    <Link
                                                        :href="route('notifications.read', notification.id)"
                                                        method="post"
                                                        as="button"
                                                    class="rounded border border-slate-600 px-2 py-1 text-[11px] text-slate-300 hover:bg-slate-800"
                                                    >
                                                        {{ notification.read_at ? 'Aabn' : 'Laes' }}
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-medium leading-4 text-slate-200 transition duration-150 ease-in-out hover:text-white focus:outline-none"
                                            >
                                                {{ currentUser?.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-slate-400 transition duration-150 ease-in-out hover:bg-slate-800 hover:text-slate-200 focus:bg-slate-800 focus:text-slate-200 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="border-t border-slate-800 sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isAdmin"
                            :href="route('bookings.calendar')"
                            :active="route().current('bookings.calendar')"
                        >
                            Bookings
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isAdmin"
                            :href="route('admin.companies.index')"
                            :active="route().current('admin.companies.*')"
                        >
                            Virksomheder
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isAdmin"
                            :href="route('admin.employees.index')"
                            :active="route().current('admin.employees.*')"
                        >
                            Medarbejdere
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isAdmin"
                            :href="route('admin.admins.index')"
                            :active="route().current('admin.admins.*')"
                        >
                            Administratorer
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isAdmin"
                            :href="route('admin.skills.index')"
                            :active="route().current('admin.skills.*')"
                        >
                            Kompetencer
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isAdmin"
                            :href="route('admin.finance.index')"
                            :active="route().current('admin.finance.*')"
                        >
                            Økonomi
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isEmployee"
                            :href="route('employee.requests.index')"
                            :active="route().current('employee.requests.*')"
                        >
                            Mine forespørgsler
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isCompany"
                            :href="route('company.bookings.index')"
                            :active="route().current('company.bookings.*')"
                        >
                            Mine bookinger
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-slate-700 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ currentUser?.name }}
                            </div>
                            <div class="text-sm font-medium text-slate-300">
                                {{ currentUser?.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-transparent"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <div class="rounded-2xl border border-slate-700/80 bg-slate-900/70 px-5 py-4 shadow-sm backdrop-blur">
                        <slot name="header" />
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>

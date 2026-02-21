<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ToastNotifications from '@/Components/ToastNotifications.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <ToastNotifications />
    <div
        class="app-theme flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-slate-300" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden rounded-xl border border-slate-700 bg-slate-900/90 px-6 py-5 shadow-2xl sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>

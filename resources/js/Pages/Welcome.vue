<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
}>();
</script>

<template>
    <Head title="Human Resource Booking" />

    <div class="app-theme min-h-screen bg-gray-100 text-gray-100">
        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-8 lg:px-8">
            <header class="mb-10 flex items-center justify-between border-b border-slate-700/70 pb-5">
                <div class="flex items-center gap-3">
                    <ApplicationLogo class="h-10 w-10" />
                    <div>
                        <p class="font-display text-lg font-semibold text-gray-100">Human Resource</p>
                        <p class="text-xs uppercase tracking-[0.18em] text-gray-400">Booking Platform</p>
                    </div>
                </div>

                <nav v-if="canLogin" class="flex items-center gap-3">
                    <Link
                        :href="route('login')"
                        class="rounded-md border border-slate-600 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-100 transition hover:bg-slate-800"
                    >
                        Log ind
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-md border border-blue-600/80 bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500"
                    >
                        Opret konto
                    </Link>
                </nav>
            </header>

            <main class="grid flex-1 gap-6 lg:grid-cols-12">
                <section class="rounded-2xl border border-slate-700/80 bg-slate-900 p-8 shadow-xl shadow-black/20 lg:col-span-7">
                    <p class="mb-3 inline-flex rounded-full border border-blue-500/50 bg-blue-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-blue-200">
                        Drift med overblik
                    </p>
                    <h1 class="font-display text-4xl font-bold leading-tight text-white lg:text-5xl">
                        Booking, bemanding og økonomi samlet i ét system
                    </h1>
                    <p class="mt-5 max-w-2xl text-base text-gray-300">
                        Bygget til bureauer der udlejer medarbejdere til virksomheder. Administrér godkendelser, ventelister, tidsregistrering og fakturering i et samlet flow.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-700 bg-slate-800/80 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Roller</p>
                            <p class="mt-2 text-sm font-semibold text-gray-100">Admin, medarbejder og virksomhed</p>
                        </div>
                        <div class="rounded-xl border border-slate-700 bg-slate-800/80 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Workflow</p>
                            <p class="mt-2 text-sm font-semibold text-gray-100">Open → Approved → Executed → Invoiced → Paid</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-700/80 bg-slate-900 p-8 shadow-xl shadow-black/20 lg:col-span-5">
                    <h2 class="font-display text-xl font-semibold text-white">Nøglefunktioner</h2>
                    <ul class="mt-5 space-y-3 text-sm text-gray-300">
                        <li class="rounded-lg border border-slate-700 bg-slate-800/70 px-4 py-3">Kalender med bookingoprettelse pr. dato</li>
                        <li class="rounded-lg border border-slate-700 bg-slate-800/70 px-4 py-3">Kapacitetsstyring med venteliste og notifikationer</li>
                        <li class="rounded-lg border border-slate-700 bg-slate-800/70 px-4 py-3">Virksomhedsadgang begrænset til egne bookinger</li>
                        <li class="rounded-lg border border-slate-700 bg-slate-800/70 px-4 py-3">Pris- og lønberegning baseret på assignments</li>
                    </ul>
                </section>
            </main>
        </div>
    </div>
</template>

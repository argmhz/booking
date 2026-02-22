<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    href: string;
    active?: boolean;
}>();

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center rounded-md border border-blue-500/60 bg-blue-500/15 px-3 py-1.5 text-sm font-medium leading-5 text-blue-100 focus:outline-none transition duration-150 ease-in-out'
        : 'inline-flex items-center rounded-md border border-transparent px-3 py-1.5 text-sm font-medium leading-5 text-slate-300 hover:border-slate-600 hover:bg-slate-800/70 hover:text-slate-100 focus:outline-none focus:border-slate-600 focus:bg-slate-800/70 focus:text-slate-100 transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>

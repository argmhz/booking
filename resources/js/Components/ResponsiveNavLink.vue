<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    href: string;
    active?: boolean;
}>();

const classes = computed(() =>
    props.active
        ? 'block w-full rounded-md border border-blue-500/60 bg-blue-500/15 px-3 py-2 text-start text-base font-medium text-blue-100 focus:outline-none transition duration-150 ease-in-out'
        : 'block w-full rounded-md border border-transparent px-3 py-2 text-start text-base font-medium text-slate-300 hover:border-slate-600 hover:bg-slate-800 hover:text-slate-100 focus:outline-none focus:border-slate-600 focus:bg-slate-800 focus:text-slate-100 transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>

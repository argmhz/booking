<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        type: 'button',
    },
);
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-slate-500/70 bg-slate-800/90 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-100 shadow-sm transition duration-150 ease-in-out hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-25"
    >
        <slot />
    </button>
</template>

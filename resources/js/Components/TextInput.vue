<script setup lang="ts">
import { onMounted, ref } from 'vue';

const model = defineModel<string>({ required: true });

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value?.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        class="rounded-md border-slate-500/80 bg-slate-900 text-slate-100 shadow-sm focus:border-blue-400 focus:ring-blue-400"
        v-model="model"
        ref="input"
    />
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const sharedProps = computed(() => page.props as Record<string, any>);
const successMessage = computed(() => sharedProps.value.flash?.status as string | undefined);
const errorMessage = computed(() => {
    const errors = sharedProps.value.errors as Record<string, string | string[] | undefined> | undefined;

    if (!errors) {
        return undefined;
    }

    const first = Object.values(errors).find((value) => {
        if (Array.isArray(value)) {
            return value.length > 0;
        }

        return Boolean(value);
    });

    if (!first) {
        return undefined;
    }

    return Array.isArray(first) ? first[0] : first;
});

const showSuccess = ref(false);
const showError = ref(false);
let successTimer: ReturnType<typeof setTimeout> | null = null;
let errorTimer: ReturnType<typeof setTimeout> | null = null;

const clearSuccessTimer = () => {
    if (successTimer) {
        clearTimeout(successTimer);
        successTimer = null;
    }
};

const clearErrorTimer = () => {
    if (errorTimer) {
        clearTimeout(errorTimer);
        errorTimer = null;
    }
};

watch(successMessage, (value) => {
    clearSuccessTimer();
    showSuccess.value = Boolean(value);

    if (value) {
        successTimer = setTimeout(() => {
            showSuccess.value = false;
        }, 4000);
    }
}, { immediate: true });

watch(errorMessage, (value) => {
    clearErrorTimer();
    showError.value = Boolean(value);

    if (value) {
        errorTimer = setTimeout(() => {
            showError.value = false;
        }, 5500);
    }
}, { immediate: true });

onBeforeUnmount(() => {
    clearSuccessTimer();
    clearErrorTimer();
});
</script>

<template>
    <div class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2">
        <transition name="toast">
            <div
                v-if="showSuccess && successMessage"
                class="pointer-events-auto rounded-lg border border-emerald-500/35 bg-slate-900 px-4 py-3 text-sm text-emerald-100 shadow-xl shadow-black/30"
            >
                {{ successMessage }}
            </div>
        </transition>

        <transition name="toast">
            <div
                v-if="showError && errorMessage"
                class="pointer-events-auto rounded-lg border border-rose-500/35 bg-slate-900 px-4 py-3 text-sm text-rose-100 shadow-xl shadow-black/30"
            >
                {{ errorMessage }}
            </div>
        </transition>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>

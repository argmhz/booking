<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    mustSetPassword: boolean;
}>();

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(route('password.setup.update'), {
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Saet Password" />

        <div class="mb-4 text-sm text-gray-600">
            Saet et password, saa du baade kan logge ind med email/password og Google fremover.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="password" value="Nyt password" />
                <TextInput id="password" v-model="form.password" class="mt-1 block w-full" type="password" required autocomplete="new-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Gentag password" />
                <TextInput id="password_confirmation" v-model="form.password_confirmation" class="mt-1 block w-full" type="password" required autocomplete="new-password" />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Gem password
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>

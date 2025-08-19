<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Réinitialiser le mot de passe" />

    <div class="min-h-screen flex">
        <!-- Left side - Image/Brand -->
        <div class="hidden lg:flex lg:w-1/2 bg-amber-50 dark:bg-gray-800 items-center justify-center">
            <div class="text-center px-8">
                <AuthenticationCardLogo />
                <p class="mt-8 text-lg text-gray-600 dark:text-gray-300">
                    Créez un nouveau mot de passe sécurisé
                </p>
            </div>
        </div>

        <!-- Right side - Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center bg-white dark:bg-gray-900">
            <div class="w-full max-w-md px-8 py-8">
                <!-- Mobile Logo -->
                <div class="lg:hidden mb-8">
                    <AuthenticationCardLogo />
                </div>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                    Nouveau mot de passe
                </h2>

                <form @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" value="Email" class="text-gray-700 dark:text-gray-300" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-amber-500 dark:focus:border-amber-400 focus:ring-amber-500 dark:focus:ring-amber-400 rounded-md"
                            required
                            autofocus
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password" value="Nouveau mot de passe" class="text-gray-700 dark:text-gray-300" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-amber-500 dark:focus:border-amber-400 focus:ring-amber-500 dark:focus:ring-amber-400 rounded-md"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password_confirmation" value="Confirmer le mot de passe" class="text-gray-700 dark:text-gray-300" />
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-amber-500 dark:focus:border-amber-400 focus:ring-amber-500 dark:focus:ring-amber-400 rounded-md"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>

                    <button type="submit" 
                            class="w-full mt-6 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-md transition duration-150 ease-in-out disabled:opacity-50" 
                            :disabled="form.processing">
                        Réinitialiser le mot de passe
                    </button>

                    <div class="mt-6 text-center">
                        <Link :href="route('login')" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300">
                            ← Retour à la connexion
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

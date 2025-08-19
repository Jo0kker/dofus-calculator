<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Connexion" />

    <div class="min-h-screen flex">
        <!-- Left side - Image/Brand -->
        <div class="hidden lg:flex lg:w-1/2 bg-amber-50 dark:bg-gray-800 items-center justify-center">
            <div class="text-center px-8">
                <AuthenticationCardLogo />
                <p class="mt-8 text-lg text-gray-600 dark:text-gray-300">
                    Optimisez vos crafts et maximisez vos profits
                </p>
            </div>
        </div>

        <!-- Right side - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center bg-white dark:bg-gray-900">
            <div class="w-full max-w-md px-8 py-8">
                <!-- Mobile Logo -->
                <div class="lg:hidden mb-8">
                    <AuthenticationCardLogo />
                </div>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                    Connexion
                </h2>

                <div v-if="status" class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-sm text-green-700 dark:text-green-400">
                    {{ status }}
                </div>

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
                        <InputLabel for="password" value="Mot de passe" class="text-gray-700 dark:text-gray-300" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-amber-500 dark:focus:border-amber-400 focus:ring-amber-500 dark:focus:ring-amber-400 rounded-md"
                            required
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between mt-4">
                        <label class="flex items-center">
                            <Checkbox v-model:checked="form.remember" name="remember" class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500" />
                            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Se souvenir de moi</span>
                        </label>

                        <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300">
                            Mot de passe oublié ?
                        </Link>
                    </div>

                    <button type="submit" 
                            class="w-full mt-6 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-md transition duration-150 ease-in-out disabled:opacity-50" 
                            :disabled="form.processing">
                        Se connecter
                    </button>

                    <div class="mt-6 text-center">
                        <span class="text-gray-600 dark:text-gray-400">Pas encore de compte ?</span>
                        <Link :href="route('register')" class="ml-1 font-medium text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300">
                            S'inscrire
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

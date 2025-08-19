<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Inscription" />

    <div class="min-h-screen flex">
        <!-- Left side - Image/Brand -->
        <div class="hidden lg:flex lg:w-1/2 bg-amber-50 dark:bg-gray-800 items-center justify-center">
            <div class="text-center px-8">
                <AuthenticationCardLogo />
                <div class="mt-12 space-y-4 text-left max-w-sm mx-auto">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 mt-0.5 mr-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Calculs de rentabilité en temps réel</span>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 mt-0.5 mr-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Suivi des prix multi-serveurs</span>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 mt-0.5 mr-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Optimisation de vos bénéfices</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right side - Register Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center bg-white dark:bg-gray-900">
            <div class="w-full max-w-md px-8 py-8">
                <!-- Mobile Logo -->
                <div class="lg:hidden mb-8">
                    <AuthenticationCardLogo />
                </div>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                    Créer un compte
                </h2>

                <form @submit.prevent="submit">
                    <div>
                        <InputLabel for="name" value="Nom" class="text-gray-700 dark:text-gray-300" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-amber-500 dark:focus:border-amber-400 focus:ring-amber-500 dark:focus:ring-amber-400 rounded-md"
                            required
                            autofocus
                            autocomplete="name"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="email" value="Email" class="text-gray-700 dark:text-gray-300" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-amber-500 dark:focus:border-amber-400 focus:ring-amber-500 dark:focus:ring-amber-400 rounded-md"
                            required
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

                    <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                        <InputLabel for="terms">
                            <div class="flex items-center">
                                <Checkbox id="terms" v-model:checked="form.terms" name="terms" required class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500" />

                                <div class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                                    J'accepte les <a target="_blank" :href="route('terms.show')" class="text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300">Conditions d'utilisation</a> et la <a target="_blank" :href="route('policy.show')" class="text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300">Politique de confidentialité</a>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="form.errors.terms" />
                        </InputLabel>
                    </div>

                    <button type="submit" 
                            class="w-full mt-6 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-md transition duration-150 ease-in-out disabled:opacity-50" 
                            :disabled="form.processing">
                        S'inscrire
                    </button>

                    <div class="mt-6 text-center">
                        <span class="text-gray-600 dark:text-gray-400">Déjà un compte ?</span>
                        <Link :href="route('login')" class="ml-1 font-medium text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300">
                            Se connecter
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

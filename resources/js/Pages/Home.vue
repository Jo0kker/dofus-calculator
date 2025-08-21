<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head>
        <title>Calculateur de Craft Dofus - Optimisez vos métiers</title>
        <meta name="description" content="Calculateur de craft pour Dofus. Calculez les coûts et optimisez vos bénéfices.">
        <meta name="keywords" content="dofus, calculateur, craft, métier, prix, recette">
    </Head>

    <div class="min-h-screen bg-gray-900 text-white flex flex-col">
        <!-- Navigation simple -->
        <nav class="border-b border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <h1 class="text-xl font-bold">
                        <span class="text-blue-500">Dofus</span> Calculator
                    </h1>
                    <div class="flex items-center space-x-4">
                        <template v-if="canLogin">
                            <Link
                                v-if="!$page.props.auth?.user"
                                :href="route('login')"
                                class="text-gray-400 hover:text-white transition"
                            >
                                Connexion
                            </Link>
                            <Link
                                v-if="canRegister && !$page.props.auth?.user"
                                :href="route('register')"
                                class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded transition"
                            >
                                S'inscrire
                            </Link>
                            <Link
                                v-if="$page.props.auth?.user"
                                :href="route('calculator.index')"
                                class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded transition"
                            >
                                Mon Calculateur
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Contenu principal centré -->
        <main class="flex-1 flex items-center justify-center px-4">
            <div class="text-center max-w-2xl">
                <h2 class="text-5xl md:text-6xl font-bold mb-6">
                    Calculateur de craft pour
                    <span class="text-blue-500">Dofus</span>
                </h2>
                
                <p class="text-xl text-gray-400 mb-12">
                    Calculez vos coûts de craft et maximisez vos bénéfices
                </p>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link
                        :href="route('items.index')"
                        class="bg-blue-600 hover:bg-blue-700 px-8 py-3 rounded-lg text-lg font-semibold transition inline-block"
                    >
                        Explorer les items
                    </Link>
                    
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="bg-gray-800 hover:bg-gray-700 px-8 py-3 rounded-lg text-lg font-semibold transition inline-block border border-gray-700"
                    >
                        Créer un compte
                    </Link>
                </div>
            </div>
        </main>

        <!-- Footer minimal -->
        <footer class="border-t border-gray-800 py-6">
            <div class="text-center text-gray-500 text-sm">
                © 2024 Dofus Calculator - Outil non officiel
            </div>
        </footer>
    </div>
</template>
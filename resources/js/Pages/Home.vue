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

        <!-- Footer amélioré -->
        <footer class="border-t border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                    <!-- À propos -->
                    <div>
                        <h3 class="text-white font-semibold mb-4">À propos</h3>
                        <p class="text-gray-400 text-sm">
                            Calculateur de rentabilité pour Dofus. Analysez les coûts de craft et maximisez vos profits sur votre serveur.
                        </p>
                    </div>

                    <!-- Communauté -->
                    <div>
                        <h3 class="text-white font-semibold mb-4">Communauté</h3>
                        <a href="https://discord.gg/akaxyBsf" target="_blank" rel="noopener"
                           class="inline-flex items-center text-blue-400 hover:text-blue-300 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.892a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                            </svg>
                            Rejoindre notre Discord
                        </a>
                        <p class="text-gray-400 text-sm mt-2">
                            Partagez vos stratégies et discutez avec d'autres marchands.
                        </p>
                    </div>

                    <!-- Mentions légales -->
                    <div>
                        <h3 class="text-white font-semibold mb-4">Mentions légales</h3>
                        <p class="text-gray-400 text-sm">
                            Dofus est un MMORPG édité par <a href="https://www.ankama.com" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300">Ankama</a>. Ce site n'est pas affilié à Ankama.
                        </p>
                    </div>
                </div>

                <!-- Copyright et liens rapides -->
                <div class="pt-6 border-t border-gray-800 flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-500 text-sm">
                        © 2025 Dofus Calculator - Optimisez vos profits
                    </p>
                    <div class="flex items-center space-x-4 mt-4 md:mt-0">
                        <a href="https://discord.gg/akaxyBsf" target="_blank" rel="noopener"
                           class="text-gray-400 hover:text-blue-400 transition-colors" aria-label="Discord">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.892a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
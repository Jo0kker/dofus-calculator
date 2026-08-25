<script setup>
import { Link } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
</script>

<template>
    <footer class="mt-12 border-t border-slate-800 bg-slate-950 text-slate-300">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-12 lg:gap-8">
                <section class="sm:col-span-2 lg:col-span-6">
                    <div class="flex items-center gap-3">
                        <span class="flex size-11 items-center justify-center rounded-xl border border-slate-700 bg-slate-900">
                            <ApplicationMark class="size-8" />
                        </span>
                        <div>
                            <h2 class="font-semibold text-white">Dofus Calculator</h2>
                            <p class="text-xs text-slate-500">Calculateur communautaire de craft</p>
                        </div>
                    </div>

                    <p class="mt-5 max-w-md text-sm leading-6 text-slate-400">
                        Analysez vos coûts de fabrication, comparez les prix de votre serveur et trouvez les crafts les plus intéressants.
                    </p>

                    <a href="https://discord.gg/qCV4m7pyNz" target="_blank" rel="noopener" class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-slate-300 transition hover:text-white">
                        <svg class="size-5 text-indigo-400" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.892a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z" />
                        </svg>
                        Rejoindre la communauté
                    </a>
                </section>

                <nav class="lg:col-span-3" aria-label="Liens du footer">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Explorer</h2>
                    <div class="mt-5 grid gap-3 text-sm">
                        <Link :href="route('items.index')" class="w-fit transition hover:text-white">Base de données des items</Link>
                        <Link :href="route('support')" class="w-fit transition hover:text-white">Assistance</Link>
                        <Link :href="route('terms.show')" class="w-fit transition hover:text-white">Conditions d’utilisation</Link>
                        <Link :href="route('policy.show')" class="w-fit transition hover:text-white">Confidentialité</Link>
                    </div>
                </nav>

                <nav class="lg:col-span-3" aria-label="Liens pour les développeurs">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Développeurs</h2>
                    <div class="mt-5 grid gap-3 text-sm">
                        <a href="/docs/api" target="_blank" rel="noopener" class="w-fit transition hover:text-white">Documentation API</a>
                        <a href="/developer/applications" class="w-fit transition hover:text-white">Mes applications</a>
                    </div>
                </nav>
            </div>

            <div class="mt-10 flex flex-col gap-4 border-t border-slate-800 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ new Date().getFullYear() }} Dofus Calculator</p>
                <p class="max-w-2xl sm:text-right">
                    Données fournies par <a href="https://dofusdb.fr" target="_blank" rel="noopener" class="text-slate-400 hover:text-white">DofusDB</a>. Dofus et ses éléments graphiques appartiennent à <a href="https://www.ankama.com" target="_blank" rel="noopener" class="text-slate-400 hover:text-white">Ankama</a>. Site non affilié à Ankama.
                </p>
            </div>
        </div>
    </footer>
</template>

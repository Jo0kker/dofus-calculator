<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    applications: Object,
    filters: Object,
    stats: Object,
});

const filters = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
});

const applyFilters = () => router.get(route('admin.oauth-applications.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
});

const resetFilters = () => {
    filters.value = { search: '', status: '' };
    applyFilters();
};

const formatDate = (date) => date ? new Date(date).toLocaleString('fr-FR') : 'Jamais';

const revoke = (application) => {
    if (window.confirm(`Bloquer « ${application.name} » et révoquer toutes ses sessions ?`)) {
        router.post(route('admin.oauth-applications.revoke', application.id), {}, { preserveScroll: true });
    }
};

const restore = (application) => {
    if (window.confirm(`Réactiver « ${application.name} » ? Les anciennes sessions resteront révoquées.`)) {
        router.post(route('admin.oauth-applications.restore', application.id), {}, { preserveScroll: true });
    }
};

const revokeTokens = (application) => {
    if (window.confirm(`Déconnecter tous les utilisateurs de « ${application.name} » sans bloquer l’application ?`)) {
        router.post(route('admin.oauth-applications.revoke-tokens', application.id), {}, { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout title="Applications — Administration">
        <Head title="Applications — Administration" />

        <template #header>
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Supervision des applications</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Contrôlez les applications, leurs sessions et leur utilisation de l’API.</p>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm text-gray-500">Applications</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm text-gray-500">Applications actives</div>
                        <div class="mt-2 text-3xl font-bold text-emerald-600">{{ stats.active }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm text-gray-500">Sessions actives</div>
                        <div class="mt-2 text-3xl font-bold text-blue-600">{{ stats.active_tokens }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm text-gray-500">Requêtes / 24 h</div>
                        <div class="mt-2 text-3xl font-bold text-amber-600">{{ stats.requests_24h }}</div>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                        <input v-model="filters.search" type="search" placeholder="Nom ou Client ID" class="rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white" @keyup.enter="applyFilters">
                        <select v-model="filters.status" class="rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white" @change="applyFilters">
                            <option value="">Tous les statuts</option>
                            <option value="active">Actives</option>
                            <option value="revoked">Bloquées</option>
                        </select>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-gray-100 dark:text-gray-900" @click="applyFilters">Filtrer</button>
                            <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" @click="resetFilters">Effacer</button>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Application</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Propriétaire</th>
                                    <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Utilisateurs</th>
                                    <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Sessions</th>
                                    <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Requêtes 24 h</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Dernière activité</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="application in applications.data" :key="application.id" class="align-top">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ application.name }}</span>
                                            <span :class="application.revoked ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200'" class="rounded-full px-2 py-0.5 text-[11px] font-semibold">{{ application.revoked ? 'Bloquée' : 'Active' }}</span>
                                        </div>
                                        <code class="mt-1 block max-w-xs break-all text-[11px] text-gray-500">{{ application.id }}</code>
                                        <div class="mt-2 max-w-sm space-y-1">
                                            <div v-for="uri in application.redirect_uris" :key="uri" class="break-all font-mono text-[11px] text-gray-500">{{ uri }}</div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-sm">
                                        <template v-if="application.owner">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ application.owner.name }}</div>
                                            <div class="text-xs text-gray-500">{{ application.owner.email }}</div>
                                        </template>
                                        <span v-else class="text-gray-500">Application interne</span>
                                    </td>
                                    <td class="px-5 py-4 text-center text-lg font-semibold text-gray-900 dark:text-white">{{ application.active_users_count }}</td>
                                    <td class="px-5 py-4 text-center">
                                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ application.active_tokens_count }}</div>
                                        <div class="text-xs text-gray-500">{{ application.issued_tokens_count }} émises</div>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ application.requests_24h }}</div>
                                        <div class="text-xs text-gray-500">{{ application.total_requests }} total</div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ formatDate(application.last_used_at) }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex min-w-[190px] flex-col items-end gap-1">
                                            <Link :href="route('admin.api-monitoring', { oauth_client_id: application.id })" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950">Voir les requêtes</Link>
                                            <button v-if="application.active_tokens_count" type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-950" @click="revokeTokens(application)">Couper les sessions</button>
                                            <button v-if="!application.revoked" type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950" @click="revoke(application)">Bloquer</button>
                                            <button v-else type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950" @click="restore(application)">Réactiver</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!applications.data.length">
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">Aucune application ne correspond aux filtres.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="applications.last_page > 1" class="flex flex-wrap gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                        <Link v-for="link in applications.links" :key="link.label" :href="link.url || '#'" :class="[link.active ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200', !link.url && 'pointer-events-none opacity-40']" class="rounded-lg px-3 py-1.5 text-sm" preserve-scroll v-html="link.label" />
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

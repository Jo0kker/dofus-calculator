<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: Object,
    topUsers: Array,
    topPriceUpdaters: Array,
    logs: Object,
    users: Array,
    filters: Object,
});

const filters = ref({
    user_id: props.filters.user_id || '',
    endpoint: props.filters.endpoint || '',
    method: props.filters.method || '',
    date: props.filters.date || '',
});

const applyFilters = () => {
    router.get(route('admin.api-monitoring'), filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    filters.value = {
        user_id: '',
        endpoint: '',
        method: '',
        date: '',
    };
    applyFilters();
};

const formatDate = (date) => {
    return new Date(date).toLocaleString('fr-FR');
};

const getMethodColor = (method) => {
    const colors = {
        'GET': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'POST': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'PUT': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        'DELETE': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    };
    return colors[method] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
};

const getStatusColor = (status) => {
    if (status >= 200 && status < 300) return 'text-green-600 dark:text-green-400';
    if (status >= 400 && status < 500) return 'text-yellow-600 dark:text-yellow-400';
    if (status >= 500) return 'text-red-600 dark:text-red-400';
    return 'text-gray-600 dark:text-gray-400';
};

const expandedRows = ref({});

const toggleRow = (logId) => {
    expandedRows.value[logId] = !expandedRows.value[logId];
};
</script>

<template>
    <AppLayout title="API Monitoring">
        <Head title="API Monitoring" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">API Monitoring</h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Surveillez l'utilisation de l'API et détectez les abus</p>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Requêtes</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats.total_requests }}</div>
                                            <div class="ml-2 text-sm text-gray-500 dark:text-gray-400">({{ stats.total_requests_today }} aujourd'hui)</div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Updates de Prix</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats.total_price_updates }}</div>
                                            <div class="ml-2 text-sm text-gray-500 dark:text-gray-400">({{ stats.total_price_updates_today }} aujourd'hui)</div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Users Tables -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Top Users by Requests -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Utilisateurs (Requêtes)</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Utilisateur</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Requêtes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="item in topUsers" :key="item.user?.id">
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                            {{ item.user?.name || 'Anonyme' }}
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ item.user?.email }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ item.request_count }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Price Updaters -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Updates de Prix</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Utilisateur</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Updates</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Prix Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="item in topPriceUpdaters" :key="item.user?.id">
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                            {{ item.user?.name || 'Anonyme' }}
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ item.user?.email }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ item.update_count }}</td>
                                        <td class="px-3 py-2 text-sm text-right text-gray-600 dark:text-gray-400">{{ item.total_prices_updated }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Filtres</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Utilisateur</label>
                            <select v-model="filters.user_id" @change="applyFilters" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tous</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Endpoint</label>
                            <input v-model="filters.endpoint" @input="applyFilters" type="text" placeholder="ex: items" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Méthode</label>
                            <select v-model="filters.method" @change="applyFilters" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Toutes</option>
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                            <input v-model="filters.date" @change="applyFilters" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div class="flex items-end">
                            <button @click="clearFilters" class="w-full px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                                Réinitialiser
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Logs API Récents</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-8"></th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Utilisateur</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Token</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Méthode</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Endpoint</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Items</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <template v-for="log in logs.data" :key="log.id">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" @click="toggleRow(log.id)">
                                            <td class="px-3 py-3 text-center">
                                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform" :class="{ 'rotate-90': expandedRows[log.id] }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </td>
                                            <td class="px-3 py-3 text-xs text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                                {{ formatDate(log.created_at) }}
                                            </td>
                                            <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                <div v-if="log.user">{{ log.user.name }}</div>
                                                <div v-else class="text-gray-500 dark:text-gray-400 italic">Anonyme</div>
                                            </td>
                                            <td class="px-3 py-3 text-xs text-gray-600 dark:text-gray-400">
                                                {{ log.token_name || '-' }}
                                            </td>
                                            <td class="px-3 py-3">
                                                <span :class="getMethodColor(log.method)" class="px-2 py-1 text-xs font-semibold rounded">
                                                    {{ log.method }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100 font-mono">
                                                {{ log.endpoint }}
                                            </td>
                                            <td class="px-3 py-3 text-sm font-semibold" :class="getStatusColor(log.response_status)">
                                                {{ log.response_status }}
                                            </td>
                                            <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100 text-right">
                                                {{ log.items_affected }}
                                            </td>
                                            <td class="px-3 py-3 text-xs text-gray-600 dark:text-gray-400 font-mono">
                                                {{ log.ip_address }}
                                            </td>
                                        </tr>
                                        <tr v-if="expandedRows[log.id]" class="bg-gray-50 dark:bg-gray-900">
                                            <td colspan="9" class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <div>
                                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Données de la requête :</h4>
                                                        <pre class="bg-white dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-700 text-xs overflow-x-auto text-gray-900 dark:text-gray-100"><code>{{ JSON.stringify(log.request_data, null, 2) }}</code></pre>
                                                    </div>
                                                    <div v-if="log.user_agent" class="text-xs text-gray-600 dark:text-gray-400">
                                                        <strong>User Agent:</strong> {{ log.user_agent }}
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="logs.links" class="bg-gray-50 dark:bg-gray-900 px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Affichage de {{ logs.from }} à {{ logs.to }} sur {{ logs.total }} résultats
                            </div>
                            <div class="flex space-x-2">
                                <Link v-for="(link, index) in logs.links" :key="index" :href="link.url || '#'"
                                    :class="[
                                        'px-3 py-1 text-sm rounded',
                                        link.active
                                            ? 'bg-blue-500 text-white'
                                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                                    ]"
                                    :preserve-state="true"
                                    :preserve-scroll="true"
                                    v-html="link.label">
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

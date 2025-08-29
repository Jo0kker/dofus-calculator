<template>
    <AppLayout title="Modération des prix">
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    🛡️ Modération des prix
                </h1>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filtres -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Filtres</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                                <select v-model="filters.status" @change="applyFilters" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Tous les statuts</option>
                                    <option value="pending">En attente</option>
                                    <option value="reviewed">Traité</option>
                                    <option value="dismissed">Rejeté</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button @click="resetFilters" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                                    Réinitialiser
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Liste des signalements -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Signalements de prix ({{ reports.total }})</h3>
                        
                        <div v-if="reports.data.length === 0" class="text-center py-8 text-gray-500">
                            Aucun signalement trouvé.
                        </div>
                        
                        <div v-else class="space-y-4">
                            <div 
                                v-for="report in reports.data" 
                                :key="report.id"
                                class="border rounded-lg p-4 hover:shadow-md transition-shadow"
                                :class="{
                                    'border-yellow-300 bg-yellow-50': report.status === 'pending',
                                    'border-green-300 bg-green-50': report.status === 'reviewed',
                                    'border-gray-300 bg-gray-50': report.status === 'dismissed'
                                }"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <img v-if="report.item_price.item.image_url" 
                                                :src="report.item_price.item.image_url" 
                                                :alt="report.item_price.item.name"
                                                class="w-8 h-8"
                                            />
                                            <div>
                                                <h4 class="font-medium text-gray-900">{{ report.item_price.item.name }}</h4>
                                                <p class="text-sm text-gray-600">
                                                    Prix signalé: <span class="font-bold">{{ formatNumber(report.price_history?.price || report.item_price.price) }}K</span>
                                                    • Serveur: {{ report.item_price.server?.name }}
                                                    • Histoire ID: {{ report.price_history?.id || 'N/A' }}
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="text-sm">
                                            <div class="mb-2">
                                                <span class="font-medium">Signalé par:</span> {{ report.reporter.name }}
                                                • <span class="font-medium">Date:</span> {{ formatDate(report.created_at) }}
                                            </div>
                                            <div v-if="report.item_price.user" class="mb-2 p-2 bg-blue-50 rounded">
                                                <span class="font-medium">Prix soumis par:</span> {{ report.item_price.user.name }}
                                                <span v-if="report.item_price.user.rejected_prices_count > 0" 
                                                      :class="{
                                                          'text-yellow-600': report.item_price.user.rejected_prices_count < 3,
                                                          'text-orange-600': report.item_price.user.rejected_prices_count >= 3 && report.item_price.user.rejected_prices_count < 5,
                                                          'text-red-600': report.item_price.user.rejected_prices_count >= 5
                                                      }"
                                                      class="ml-2 font-bold"
                                                >
                                                    ({{ report.item_price.user.rejected_prices_count }} prix rejetés)
                                                </span>
                                            </div>
                                            <div>
                                                <span class="font-medium">Commentaire:</span><br>
                                                <span class="text-gray-700 italic bg-gray-50 p-2 rounded block">"{{ report.comment }}"</span>
                                            </div>
                                        </div>
                                        
                                        <div v-if="report.status !== 'pending'" class="mt-2 pt-2 border-t text-xs text-gray-600">
                                            <span class="font-medium">Traité par:</span> {{ report.reviewer?.name || 'Système' }} 
                                            le {{ formatDate(report.reviewed_at) }}
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div v-if="report.status === 'pending'" class="flex flex-col space-y-2 ml-4">
                                        <form @submit.prevent="approveReport(report.id, 'reject_price')" class="inline">
                                            <button 
                                                type="submit" 
                                                class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700"
                                                :disabled="processing"
                                            >
                                                Valider & Rejeter prix
                                            </button>
                                        </form>
                                        <form @submit.prevent="approveReport(report.id, 'approve')" class="inline">
                                            <button 
                                                type="submit" 
                                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700"
                                                :disabled="processing"
                                            >
                                                Valider signalement
                                            </button>
                                        </form>
                                        <form @submit.prevent="dismissReport(report.id)" class="inline">
                                            <button 
                                                type="submit" 
                                                class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700"
                                                :disabled="processing"
                                            >
                                                Rejeter signalement
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <!-- Statut -->
                                    <div v-else class="ml-4">
                                        <span 
                                            :class="{
                                                'bg-green-100 text-green-800': report.status === 'reviewed',
                                                'bg-gray-100 text-gray-800': report.status === 'dismissed'
                                            }"
                                            class="px-2 py-1 rounded-full text-xs font-medium"
                                        >
                                            {{ getStatusLabel(report.status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pagination -->
                        <div v-if="reports.last_page > 1" class="mt-6 flex justify-center">
                            <nav class="flex items-center space-x-2">
                                <Link 
                                    v-if="reports.prev_page_url"
                                    :href="reports.prev_page_url"
                                    class="px-3 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
                                >
                                    Précédent
                                </Link>
                                <span class="px-3 py-2 text-gray-600">
                                    Page {{ reports.current_page }} sur {{ reports.last_page }}
                                </span>
                                <Link 
                                    v-if="reports.next_page_url"
                                    :href="reports.next_page_url"
                                    class="px-3 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
                                >
                                    Suivant
                                </Link>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    reports: Object,
    filters: Object,
});

const processing = ref(false);
const filters = reactive({
    status: props.filters.status || '',
});

const applyFilters = () => {
    router.get(route('moderation.reports'), filters, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filters.status = '';
    applyFilters();
};

const approveReport = (reportId, action) => {
    processing.value = true;
    router.post(route('moderation.reports.approve', reportId), { action }, {
        onFinish: () => processing.value = false,
    });
};

const dismissReport = (reportId) => {
    processing.value = true;
    router.post(route('moderation.reports.dismiss', reportId), {}, {
        onFinish: () => processing.value = false,
    });
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('fr-FR').format(Math.round(num));
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Suppression de getReasonLabel car plus de raisons prédéfinies

const getStatusLabel = (status) => {
    const labels = {
        'pending': 'En attente',
        'reviewed': 'Traité',
        'dismissed': 'Rejeté'
    };
    return labels[status] || status;
};
</script>
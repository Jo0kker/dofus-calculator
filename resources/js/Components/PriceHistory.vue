<template>
    <div class="space-y-4">
        <div v-if="!selectedServer" class="text-center py-4">
            <p class="text-gray-500 text-sm">
                Sélectionnez un serveur pour voir l'historique des prix
            </p>
        </div>
        
        <div v-else-if="!priceHistory || priceHistory.length === 0" class="text-center py-4">
            <p class="text-gray-500 text-sm">
                Aucun historique de prix disponible pour ce serveur
            </p>
        </div>
        
        <div v-else>
            <!-- Graphique simple avec les prix récents -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Évolution sur 30 jours</h4>
                
                <!-- Version simple avec barres -->
                <div class="flex items-end space-x-1" style="height: 120px;">
                    <div 
                        v-for="(price, index) in normalizedPrices" 
                        :key="index"
                        class="flex-1 bg-blue-500 hover:bg-blue-600 transition-colors rounded-t"
                        :style="{ height: `${price.height}%` }"
                        :title="`${formatDate(price.date)}: ${formatNumber(price.value)} K`"
                    ></div>
                </div>
                
                <div class="flex justify-between mt-2 text-xs text-gray-500">
                    <span>{{ formatShortDate(oldestDate) }}</span>
                    <span>{{ formatShortDate(newestDate) }}</span>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-600">Prix actuel</div>
                    <div class="text-lg font-bold text-blue-600">
                        {{ formatNumber(currentPrice) }} K
                    </div>
                </div>
                
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-600">Variation 7j</div>
                    <div class="text-lg font-bold" :class="variation7d >= 0 ? 'text-green-600' : 'text-red-600'">
                        {{ variation7d >= 0 ? '+' : '' }}{{ variation7d.toFixed(1) }}%
                    </div>
                </div>
                
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-600">Prix min (30j)</div>
                    <div class="text-lg font-bold text-gray-700">
                        {{ formatNumber(minPrice) }} K
                    </div>
                </div>
                
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-600">Prix max (30j)</div>
                    <div class="text-lg font-bold text-gray-700">
                        {{ formatNumber(maxPrice) }} K
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useServerSelection } from '@/Composables/useServerSelection';

const props = defineProps({
    item: Object,
});

const { selectedServer, selectedServerId } = useServerSelection();

// Calculer l'historique filtré pour le serveur sélectionné
const priceHistory = computed(() => {
    if (!selectedServerId.value || !props.item.price_histories) return [];
    
    return props.item.price_histories
        .filter(h => h.server_id === selectedServerId.value)
        .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

// Prix actuel
const currentPrice = computed(() => {
    if (!selectedServerId.value || !props.item.prices) return 0;
    const price = props.item.prices.find(p => p.server_id === selectedServerId.value);
    return price ? price.price : 0;
});

// Statistiques
const minPrice = computed(() => {
    if (priceHistory.value.length === 0) return 0;
    return Math.min(...priceHistory.value.map(h => h.price));
});

const maxPrice = computed(() => {
    if (priceHistory.value.length === 0) return 0;
    return Math.max(...priceHistory.value.map(h => h.price));
});

const oldestDate = computed(() => {
    if (priceHistory.value.length === 0) return null;
    return priceHistory.value[0].created_at;
});

const newestDate = computed(() => {
    if (priceHistory.value.length === 0) return null;
    return priceHistory.value[priceHistory.value.length - 1].created_at;
});

// Normaliser les prix pour l'affichage en barres
const normalizedPrices = computed(() => {
    if (priceHistory.value.length === 0) return [];
    
    const max = maxPrice.value;
    const min = minPrice.value;
    const range = max - min || 1;
    
    return priceHistory.value.map(h => ({
        date: h.created_at,
        value: h.price,
        height: ((h.price - min) / range) * 80 + 20, // Entre 20% et 100%
    }));
});

// Variation sur 7 jours
const variation7d = computed(() => {
    if (priceHistory.value.length < 2) return 0;
    
    const now = new Date();
    const sevenDaysAgo = new Date(now - 7 * 24 * 60 * 60 * 1000);
    
    const recentPrices = priceHistory.value.filter(h => 
        new Date(h.created_at) >= sevenDaysAgo
    );
    
    if (recentPrices.length < 2) return 0;
    
    const oldPrice = recentPrices[0].price;
    const newPrice = recentPrices[recentPrices.length - 1].price;
    
    return ((newPrice - oldPrice) / oldPrice) * 100;
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('fr-FR').format(Math.round(num));
};

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleDateString('fr-FR');
};

const formatShortDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return `${d.getDate()}/${d.getMonth() + 1}`;
};
</script>
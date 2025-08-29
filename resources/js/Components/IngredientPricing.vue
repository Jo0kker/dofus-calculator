<template>
    <div v-if="!isServerSelected" class="text-xs text-gray-500 italic">
        Sélectionnez un serveur pour voir/modifier le prix
    </div>
    
    <div v-else class="space-y-3">
        <!-- Prix actuel pour le serveur sélectionné -->
        <div v-if="currentPrice" class="bg-white border rounded px-3 py-2">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-sm font-medium text-gray-700">Prix actuel:</span>
                    <div class="text-lg font-bold text-blue-600">{{ formatNumber(currentPrice.price) }} K</div>
                    <div class="text-xs text-gray-500">
                        Mis à jour {{ formatDate(currentPrice.updated_at) }}
                    </div>
                </div>
                
                <!-- Bouton de signalement -->
                <button 
                    v-if="$page.props.auth && $page.props.auth.user"
                    @click="showReportModal = true"
                    class="text-xs text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded transition-colors"
                    title="Signaler ce prix comme incorrect"
                >
                    🚨 Signaler
                </button>
            </div>
        </div>
        
        <!-- Modal de signalement -->
        <PriceReportModal 
            :show="showReportModal"
            :item-price="currentPriceWithItem"
            @close="showReportModal = false"
            @reported="onPriceReported"
        />

        <!-- Formulaire de saisie (seulement pour les utilisateurs connectés) -->
        <form v-if="$page.props.auth && $page.props.auth.user" @submit.prevent="submitPrice" class="flex items-center space-x-2">
            <input 
                type="number"
                v-model="quickPriceForm.price"
                :placeholder="currentPrice ? 'Nouveau prix...' : 'Prix...'"
                class="text-sm border-gray-300 rounded flex-1"
                min="1"
                required
            />
            
            <button 
                type="submit"
                class="text-sm bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"
                :disabled="quickPriceForm.processing"
            >
                {{ quickPriceForm.processing ? '...' : (currentPrice ? 'Modifier' : 'Ajouter') }}
            </button>
        </form>
        
        <!-- Message pour les utilisateurs non connectés -->
        <div v-else-if="!currentPrice" class="text-xs text-gray-500 italic">
            <Link :href="route('login')" class="text-blue-600 hover:underline">Connectez-vous</Link> pour ajouter un prix
        </div>

        <!-- Indication si craftable -->
        <div v-if="ingredient.recipe" class="flex items-center text-xs text-green-600">
            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
            </svg>
            Peut être crafté
        </div>
    </div>
</template>

<script setup>
import { ref, defineEmits, watch, computed } from 'vue';
import { useForm, router, Link, usePage } from '@inertiajs/vue3';
import { useServerSelection } from '@/Composables/useServerSelection';
import PriceReportModal from './PriceReportModal.vue';

const props = defineProps({
    ingredient: Object,
});

const emit = defineEmits(['price-updated']);

const { selectedServer, selectedServerId, isServerSelected } = useServerSelection();

const showReportModal = ref(false);

const quickPriceForm = useForm({
    item_id: props.ingredient.id,
    server_id: '',
    price: '',
});

// Utiliser le serveur global et trouver le prix actuel
const currentPrice = computed(() => {
    if (!selectedServerId.value || !props.ingredient.prices) return null;
    return props.ingredient.prices.find(p => p.server.id === selectedServerId.value);
});

// Créer un objet avec l'item pour le modal
const currentPriceWithItem = computed(() => {
    if (!currentPrice.value) return null;
    return {
        ...currentPrice.value,
        item: props.ingredient
    };
});

// Mettre à jour le server_id du formulaire
watch(selectedServerId, (newServerId) => {
    quickPriceForm.server_id = newServerId;
}, { immediate: true });

const submitPrice = () => {
    quickPriceForm.post(route('prices.store'), {
        preserveScroll: true,
        onSuccess: () => {
            quickPriceForm.reset('price');
            emit('price-updated');
            // Recharger toutes les données de la page courante
            setTimeout(() => {
                router.reload({ preserveScroll: true });
            }, 100);
        },
        onError: (errors) => {
            console.error('Erreur lors de la saisie du prix:', errors);
        }
    });
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('fr-FR').format(num);
};

const formatDate = (date) => {
    const d = new Date(date);
    const now = new Date();
    const diff = now - d;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    
    if (days === 0) return "aujourd'hui";
    if (days === 1) return "hier";
    if (days < 7) return `il y a ${days} jours`;
    if (days < 30) return `il y a ${Math.floor(days / 7)} semaines`;
    return `il y a ${Math.floor(days / 30)} mois`;
};

const onPriceReported = () => {
    // Optionnel: recharger les données ou afficher un message
    router.reload({ preserveScroll: true });
};
</script>
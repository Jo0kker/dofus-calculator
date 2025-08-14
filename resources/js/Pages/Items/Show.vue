<template>
    <AppLayout :title="item.name">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('items.index')" class="text-gray-400 hover:text-gray-600">
                    ← Retour aux items
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ item.name }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Item Details -->
                    <div class="lg:col-span-2">
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                            <div class="flex items-start space-x-4">
                                <img 
                                    v-if="item.image_url" 
                                    :src="item.image_url" 
                                    :alt="item.name"
                                    class="w-24 h-24 object-contain"
                                />
                                <div class="flex-1">
                                    <div class="flex items-start justify-between">
                                        <h3 class="text-2xl font-bold text-gray-900">{{ item.name }}</h3>
                                        <button 
                                            @click="toggleFavorite"
                                            :class="[
                                                'ml-4 p-2 rounded-full transition-colors',
                                                isFavorite 
                                                    ? 'bg-yellow-100 text-yellow-600 hover:bg-yellow-200' 
                                                    : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
                                            ]"
                                            :title="isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
                                        >
                                            <svg class="w-6 h-6" :class="{ 'fill-current': isFavorite }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="mt-2 space-y-1">
                                        <p v-if="item.level" class="text-sm text-gray-600">
                                            <span class="font-medium">Niveau:</span> {{ item.level }}
                                        </p>
                                        <p v-if="item.type" class="text-sm text-gray-600">
                                            <span class="font-medium">Type:</span> {{ item.type }}
                                        </p>
                                        <p v-if="item.category" class="text-sm text-gray-600">
                                            <span class="font-medium">Catégorie:</span> {{ item.category }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Recipe if exists -->
                            <div v-if="item.recipe" class="mt-6 border-t pt-6">
                                <h4 class="text-lg font-semibold mb-4">Recette</h4>
                                <div class="space-y-2">
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Métier:</span> {{ item.recipe.profession }}
                                        <span v-if="item.recipe.profession_level">
                                            (Niveau {{ item.recipe.profession_level }})
                                        </span>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Quantité produite:</span> {{ item.recipe.quantity_produced }}
                                    </p>
                                    
                                    <div class="mt-4">
                                        <h5 class="font-medium text-gray-700 mb-3">Ingrédients:</h5>
                                        <div class="space-y-3">
                                            <div 
                                                v-for="ingredient in item.recipe.ingredients" 
                                                :key="ingredient.id"
                                                class="bg-gray-50 rounded-lg p-3"
                                            >
                                                <div class="flex items-center justify-between mb-2">
                                                    <div class="flex items-center space-x-3">
                                                        <img 
                                                            v-if="ingredient.image_url" 
                                                            :src="ingredient.image_url" 
                                                            :alt="ingredient.name"
                                                            class="w-10 h-10"
                                                        />
                                                        <div>
                                                            <span class="font-medium">{{ ingredient.pivot.quantity }}x {{ ingredient.name }}</span>
                                                            <div v-if="ingredient.level" class="text-xs text-gray-500">
                                                                Niveau {{ ingredient.level }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <Link 
                                                        :href="route('items.show', ingredient.id)"
                                                        class="text-xs text-blue-600 hover:text-blue-800"
                                                    >
                                                        Voir détails →
                                                    </Link>
                                                </div>
                                                
                                                <!-- Prix de l'ingrédient -->
                                                <IngredientPricing 
                                                    :ingredient="ingredient"
                                                    @price-updated="onIngredientPriceUpdated"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Calculateur de coût -->
                                    <RecipeCostCalculator 
                                        :recipe="item.recipe"
                                        :direct-price="getDirectPrice()"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Price Management -->
                    <div class="lg:col-span-1">
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-semibold mb-4">Prix de l'item</h3>
                            
                            <IngredientPricing 
                                :ingredient="item"
                                @price-updated="onIngredientPriceUpdated"
                            />
                        </div>

                        <!-- Price History Chart -->
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                            <h3 class="text-lg font-semibold mb-4">Historique des prix</h3>
                            <PriceHistory :item="item" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import IngredientPricing from '@/Components/IngredientPricing.vue';
import RecipeCostCalculator from '@/Components/RecipeCostCalculator.vue';
import PriceHistory from '@/Components/PriceHistory.vue';

const props = defineProps({
    item: Object,
    isFavorite: Boolean,
});

const page = usePage();
const isFavorite = ref(props.isFavorite);

const priceForm = useForm({
    item_id: props.item.id,
    server_id: '',
    price: '',
});

const submitPrice = () => {
    priceForm.post(route('prices.store'), {
        preserveScroll: true,
        onSuccess: () => {
            priceForm.reset('price');
        },
    });
};

const toggleFavorite = () => {
    router.post(route('favorites.toggle', props.item.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            isFavorite.value = !isFavorite.value;
        },
    });
};

const getDirectPrice = () => {
    // Retourne le prix moyen des prix actuels, ou null
    if (!props.item.prices || props.item.prices.length === 0) {
        return null;
    }
    
    const totalPrice = props.item.prices.reduce((sum, price) => sum + price.price, 0);
    return totalPrice / props.item.prices.length;
};

const reportPrice = (price) => {
    if (confirm('Êtes-vous sûr de vouloir signaler ce prix comme incorrect ?')) {
        router.post(route('prices.report', price.id), {
            reason: prompt('Raison du signalement (optionnel):'),
        });
    }
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

const onIngredientPriceUpdated = () => {
    // Ne pas recharger ici, c'est fait dans IngredientPricing
};
</script>
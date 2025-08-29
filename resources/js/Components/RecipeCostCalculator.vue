<template>
    <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-4 mt-4">
        <h4 class="font-semibold text-gray-900 mb-3">💰 Calculateur de Coût</h4>
        
        <div class="space-y-3">
            <!-- Message si aucun serveur sélectionné -->
            <div v-if="!isServerSelected" class="text-center py-4">
                <p class="text-gray-500 text-sm">
                    Sélectionnez un serveur dans la barre de navigation pour calculer les coûts
                </p>
            </div>

            <!-- Sélecteur de mode de calcul -->
            <div v-if="isServerSelected" class="flex space-x-2 mb-3">
                <button 
                    @click="calculationMode = 'simple'"
                    :class="[
                        'flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                        calculationMode === 'simple' 
                            ? 'bg-blue-600 text-white' 
                            : 'bg-white text-gray-700 hover:bg-gray-50 border'
                    ]"
                >
                    Calcul simple
                </button>
                <button 
                    @click="calculationMode = 'recursive'"
                    :class="[
                        'flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                        calculationMode === 'recursive' 
                            ? 'bg-blue-600 text-white' 
                            : 'bg-white text-gray-700 hover:bg-gray-50 border'
                    ]"
                >
                    Calcul optimisé
                </button>
            </div>

            <!-- Résultats du calcul simple -->
            <div v-if="calculationMode === 'simple' && calculation" class="space-y-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Coût total -->
                    <div class="bg-white rounded-lg p-3 border">
                        <div class="text-xs text-gray-600 uppercase tracking-wide">Coût de craft</div>
                        <div class="text-xl font-bold" :class="calculation.canCraft ? 'text-green-600' : 'text-red-600'">
                            {{ calculation.canCraft ? formatNumber(calculation.totalCost) + ' K' : 'Impossible' }}
                        </div>
                        <div v-if="calculation.missingIngredients.length > 0" class="text-xs text-red-500 mt-1">
                            {{ calculation.missingIngredients.length }} ingrédient(s) sans prix
                        </div>
                    </div>

                    <!-- Comparaison avec prix direct -->
                    <div v-if="directPrice && calculation.canCraft" class="bg-white rounded-lg p-3 border">
                        <div class="text-xs text-gray-600 uppercase tracking-wide">vs Achat direct</div>
                        <div class="text-xl font-bold" :class="calculation.totalCost < directPrice ? 'text-green-600' : 'text-red-600'">
                            {{ calculation.totalCost < directPrice ? '✅ Craft rentable' : '❌ Achat meilleur' }}
                        </div>
                        <div class="text-xs text-gray-600">
                            Économie: {{ formatNumber(Math.abs(directPrice - calculation.totalCost)) }} K
                        </div>
                    </div>
                </div>

                <!-- Détail par ingrédient avec checkboxes -->
                <div class="bg-white rounded-lg p-3 border">
                    <div class="flex justify-between items-center mb-2">
                        <h5 class="font-medium text-gray-700">Détail des coûts:</h5>
                        <button 
                            @click="toggleAllIngredients"
                            class="text-xs text-blue-600 hover:text-blue-800"
                        >
                            {{ allIngredientsIncluded ? 'Tout décocher' : 'Tout cocher' }}
                        </button>
                    </div>
                    <div class="space-y-1">
                        <div 
                            v-for="detail in calculation.ingredientDetails" 
                            :key="detail.ingredient.id"
                            class="flex items-center space-x-2 text-sm p-1 hover:bg-gray-50 rounded"
                        >
                            <input 
                                type="checkbox"
                                :id="`ingredient-${detail.ingredient.id}`"
                                v-model="includedIngredients[detail.ingredient.id]"
                                @change="calculateCost"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label 
                                :for="`ingredient-${detail.ingredient.id}`"
                                class="flex-1 flex justify-between items-center cursor-pointer"
                            >
                                <span class="flex items-center space-x-2">
                                    <img 
                                        v-if="detail.ingredient.image_url"
                                        :src="detail.ingredient.image_url"
                                        :alt="detail.ingredient.name"
                                        class="w-4 h-4"
                                    />
                                    <span :class="!includedIngredients[detail.ingredient.id] ? 'line-through text-gray-400' : ''">
                                        {{ detail.quantity }}x {{ detail.ingredient.name }}
                                    </span>
                                </span>
                                <span :class="[
                                    detail.price ? (includedIngredients[detail.ingredient.id] ? 'text-green-600' : 'text-gray-400') : 'text-red-500',
                                    !includedIngredients[detail.ingredient.id] ? 'line-through' : ''
                                ]">
                                    {{ detail.price ? formatNumber(detail.price * detail.quantity) + ' K' : 'Prix manquant' }}
                                </span>
                            </label>
                        </div>
                    </div>
                    <div v-if="excludedCount > 0" class="mt-2 pt-2 border-t">
                        <p class="text-xs text-gray-600">
                            {{ excludedCount }} ressource(s) exclue(s) du calcul
                        </p>
                    </div>
                </div>
            </div>

            <!-- Résultats du calcul récursif -->
            <div v-if="calculationMode === 'recursive' && recursiveCalculation" class="space-y-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Coût total -->
                    <div class="bg-white rounded-lg p-3 border">
                        <div class="text-xs text-gray-600 uppercase tracking-wide">Coût de craft optimisé</div>
                        <div class="text-xl font-bold" :class="recursiveCalculation.craftCost ? 'text-green-600' : 'text-red-600'">
                            {{ recursiveCalculation.craftCost ? formatNumber(recursiveCalculation.craftCost) + ' K' : 'Impossible' }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Calcul récursif avec optimisation
                        </div>
                    </div>

                    <!-- Comparaison avec prix direct -->
                    <div v-if="recursiveCalculation.directPrice && recursiveCalculation.craftCost" class="bg-white rounded-lg p-3 border">
                        <div class="text-xs text-gray-600 uppercase tracking-wide">vs Achat direct</div>
                        <div class="text-xl font-bold" :class="recursiveCalculation.craftCost < recursiveCalculation.directPrice ? 'text-green-600' : 'text-red-600'">
                            {{ recursiveCalculation.craftCost < recursiveCalculation.directPrice ? '✅ Craft rentable' : '❌ Achat meilleur' }}
                        </div>
                        <div class="text-xs text-gray-600">
                            Économie: {{ formatNumber(Math.abs(recursiveCalculation.directPrice - recursiveCalculation.craftCost)) }} K
                        </div>
                    </div>
                </div>

                <!-- Arbre de craft optimisé -->
                <div v-if="recursiveCalculation.craftTree" class="bg-white rounded-lg p-3 border">
                    <h5 class="font-medium text-gray-700 mb-2">Stratégie optimale:</h5>
                    <div class="space-y-1">
                        <div 
                            v-for="ingredient in recursiveCalculation.craftTree.ingredients" 
                            :key="ingredient.id"
                            class="text-sm p-1"
                        >
                            <div class="flex justify-between items-center">
                                <span class="flex items-center space-x-2">
                                    <img 
                                        v-if="ingredient.image_url"
                                        :src="ingredient.image_url"
                                        :alt="ingredient.name"
                                        class="w-4 h-4"
                                    />
                                    <span>{{ ingredient.quantity }}x {{ ingredient.name }}</span>
                                    <span 
                                        v-if="ingredient.hasCraft"
                                        :class="[
                                            'text-xs px-2 py-0.5 rounded',
                                            ingredient.usedMethod === 'craft' 
                                                ? 'bg-blue-100 text-blue-700' 
                                                : 'bg-green-100 text-green-700'
                                        ]"
                                    >
                                        {{ ingredient.usedMethod === 'craft' ? '🔨 Craft' : '💰 Achat' }}
                                    </span>
                                </span>
                                <span :class="ingredient.usedPrice ? 'text-green-600' : 'text-red-500'">
                                    {{ ingredient.usedPrice ? formatNumber(ingredient.usedPrice * ingredient.quantity) + ' K' : 'Prix manquant' }}
                                </span>
                            </div>
                            <!-- Sous-ingrédients si craft -->
                            <div v-if="ingredient.craftTree && ingredient.usedMethod === 'craft'" class="ml-6 mt-1 pl-2 border-l-2 border-gray-200">
                                <div 
                                    v-for="subIngredient in ingredient.craftTree.ingredients"
                                    :key="subIngredient.id"
                                    class="text-xs text-gray-600 py-0.5"
                                >
                                    {{ subIngredient.quantity }}x {{ subIngredient.name }}: 
                                    {{ subIngredient.usedPrice ? formatNumber(subIngredient.usedPrice * subIngredient.quantity) + ' K' : 'N/A' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading state -->
                <div v-if="loadingRecursive" class="text-center py-4">
                    <p class="text-gray-500 text-sm">Calcul en cours...</p>
                </div>
            </div>

            <!-- Message d'aide -->
            <div v-if="!selectedServerId" class="text-xs text-gray-500 italic">
                Sélectionnez un serveur pour calculer les coûts
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useServerSelection } from '@/Composables/useServerSelection';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    recipe: Object,
    directPrice: Number,
    itemId: Number,
});

const { selectedServer, selectedServerId, isServerSelected } = useServerSelection();
const calculation = ref(null);
const recursiveCalculation = ref(null);
const includedIngredients = ref({});
const calculationMode = ref('recursive');
const loadingRecursive = ref(false);

// Initialiser les checkboxes pour tous les ingrédients
const initializeIngredients = () => {
    if (props.recipe && props.recipe.ingredients) {
        props.recipe.ingredients.forEach(ingredient => {
            if (!(ingredient.id in includedIngredients.value)) {
                includedIngredients.value[ingredient.id] = true;
            }
        });
    }
};

const calculateCost = () => {
    if (!selectedServerId.value) {
        calculation.value = null;
        return;
    }

    const ingredientDetails = [];
    let totalCost = 0;
    let canCraft = true;
    const missingIngredients = [];

    // Calculer le coût pour chaque ingrédient
    props.recipe.ingredients.forEach(ingredient => {
        const quantity = ingredient.pivot.quantity;
        const isIncluded = includedIngredients.value[ingredient.id] !== false;
        
        // Trouver le prix pour le serveur sélectionné
        const price = ingredient.prices.find(p => p.server.id == selectedServerId.value);
        
        if (price && isIncluded) {
            const unitPrice = price.price;
            totalCost += unitPrice * quantity;
            ingredientDetails.push({
                ingredient,
                quantity,
                price: unitPrice,
            });
        } else if (!price && isIncluded) {
            canCraft = false;
            missingIngredients.push(ingredient.name);
            ingredientDetails.push({
                ingredient,
                quantity,
                price: null,
            });
        } else {
            // Ingrédient exclu du calcul
            ingredientDetails.push({
                ingredient,
                quantity,
                price: price ? price.price : null,
            });
        }
    });

    calculation.value = {
        totalCost,
        canCraft,
        missingIngredients,
        ingredientDetails,
    };
};

// Computed pour vérifier si tous les ingrédients sont inclus
const allIngredientsIncluded = computed(() => {
    if (!props.recipe || !props.recipe.ingredients) return true;
    return props.recipe.ingredients.every(ingredient => 
        includedIngredients.value[ingredient.id] !== false
    );
});

// Computed pour compter les ressources exclues
const excludedCount = computed(() => {
    if (!props.recipe || !props.recipe.ingredients) return 0;
    return props.recipe.ingredients.filter(ingredient => 
        includedIngredients.value[ingredient.id] === false
    ).length;
});

// Fonction pour cocher/décocher tous les ingrédients
const toggleAllIngredients = () => {
    const newValue = !allIngredientsIncluded.value;
    props.recipe.ingredients.forEach(ingredient => {
        includedIngredients.value[ingredient.id] = newValue;
    });
    calculateCost();
};

// Fonction pour récupérer le calcul récursif depuis l'API
const fetchRecursiveCalculation = async () => {
    if (!selectedServerId.value || !props.itemId) {
        recursiveCalculation.value = null;
        return;
    }

    loadingRecursive.value = true;
    try {
        const response = await axios.get(`/items/${props.itemId}/calculate-recursive`, {
            params: {
                server_id: selectedServerId.value
            }
        });
        recursiveCalculation.value = response.data;
    } catch (error) {
        console.error('Error fetching recursive calculation:', error);
        recursiveCalculation.value = null;
    } finally {
        loadingRecursive.value = false;
    }
};

// Initialiser au montage
onMounted(() => {
    initializeIngredients();
});

// Calculer automatiquement quand le serveur change
watch(selectedServerId, () => {
    calculateCost();
    if (calculationMode.value === 'recursive') {
        fetchRecursiveCalculation();
    }
}, { immediate: true });

// Calculer quand le mode change
watch(calculationMode, (newMode) => {
    if (newMode === 'recursive' && !recursiveCalculation.value) {
        fetchRecursiveCalculation();
    }
});

// Écouter les changements de prix pour recalculer
watch(() => props.recipe, () => {
    initializeIngredients();
    calculateCost();
    if (calculationMode.value === 'recursive') {
        fetchRecursiveCalculation();
    }
}, { deep: true });

const formatNumber = (num) => {
    return new Intl.NumberFormat('fr-FR').format(Math.round(num));
};
</script>
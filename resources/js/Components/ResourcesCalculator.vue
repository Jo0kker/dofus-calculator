<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-lg font-semibold text-gray-900">Calculateur de ressources</h4>
                <button 
                    v-if="showResources && Object.keys(totalResources).length > 0"
                    @click="copyResourcesList"
                    class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors flex items-center gap-1"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                    </svg>
                    Copier la liste
                </button>
            </div>
            
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-gray-700">Nombre de crafts :</label>
                <input 
                    v-model.number="craftCount"
                    type="number"
                    min="1"
                    class="w-24 px-3 py-1 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                    @input="calculateResources"
                />
                <button 
                    @click="calculateResources"
                    class="px-4 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm"
                >
                    Calculer
                </button>
            </div>
        </div>

        <div v-if="showResources" class="p-4">
            <div v-if="Object.keys(totalResources).length === 0" class="text-gray-500 text-center py-4">
                Aucune ressource nécessaire pour cet item.
            </div>
            
            <div v-else class="space-y-3">
                <div class="mb-3 pb-3 border-b border-gray-200">
                    <p class="text-sm text-gray-600">
                        Pour crafter <span class="font-semibold">{{ craftCount }}x {{ itemName }}</span>, 
                        vous aurez besoin de :
                    </p>
                </div>

                <div class="grid gap-2">
                    <div 
                        v-for="(resource, index) in sortedResources" 
                        :key="index"
                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <img 
                                v-if="resource.image_url" 
                                :src="resource.image_url" 
                                :alt="resource.name"
                                class="w-10 h-10 object-contain"
                            />
                            <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center" v-else>
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">
                                    {{ resource.quantity }}x {{ resource.name }}
                                </div>
                                <div v-if="resource.level" class="text-xs text-gray-500">
                                    Niveau {{ resource.level }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <div v-if="resource.price" class="text-right">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ formatNumber(resource.totalPrice) }} K
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ formatNumber(resource.price) }} K/u
                                </div>
                            </div>
                            <Link 
                                :href="route('items.show', resource.id)"
                                class="p-1 text-blue-600 hover:text-blue-800"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-if="totalCost > 0" class="mt-4 pt-4 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-semibold text-gray-900">Coût total estimé :</span>
                        <span class="text-xl font-bold text-blue-600">{{ formatNumber(totalCost) }} K</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        * Basé sur les prix disponibles. Certains items peuvent ne pas avoir de prix.
                    </p>
                </div>
            </div>
        </div>

        <div v-if="copied" class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg transition-opacity">
            Liste copiée dans le presse-papier !
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    recipe: {
        type: Object,
        required: true
    },
    itemName: {
        type: String,
        required: true
    }
});

const craftCount = ref(1);
const showResources = ref(false);
const totalResources = ref({});
const copied = ref(false);

const calculateResources = () => {
    totalResources.value = {};
    
    if (!props.recipe || !props.recipe.ingredients) {
        showResources.value = true;
        return;
    }
    
    calculateResourcesRecursive(props.recipe, craftCount.value);
    showResources.value = true;
};

const calculateResourcesRecursive = (recipe, quantity) => {
    if (!recipe || !recipe.ingredients) return;
    
    recipe.ingredients.forEach(ingredient => {
        const neededQuantity = ingredient.pivot.quantity * quantity;
        
        if (ingredient.recipe && ingredient.recipe.ingredients) {
            calculateResourcesRecursive(ingredient.recipe, neededQuantity);
        } else {
            if (!totalResources.value[ingredient.id]) {
                totalResources.value[ingredient.id] = {
                    id: ingredient.id,
                    name: ingredient.name,
                    quantity: 0,
                    level: ingredient.level,
                    image_url: ingredient.image_url,
                    price: ingredient.prices && ingredient.prices.length > 0 
                        ? ingredient.prices.reduce((sum, p) => sum + p.price, 0) / ingredient.prices.length 
                        : null
                };
            }
            totalResources.value[ingredient.id].quantity += neededQuantity;
        }
    });
};

const sortedResources = computed(() => {
    return Object.values(totalResources.value).sort((a, b) => {
        if (a.level && b.level) return b.level - a.level;
        return a.name.localeCompare(b.name);
    });
});

const totalCost = computed(() => {
    return sortedResources.value.reduce((sum, resource) => {
        if (resource.price) {
            resource.totalPrice = resource.price * resource.quantity;
            return sum + resource.totalPrice;
        }
        return sum;
    }, 0);
});

const copyResourcesList = async () => {
    const text = sortedResources.value
        .map(r => `${r.quantity}x ${r.name}`)
        .join('\n');
    
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Erreur lors de la copie:', err);
    }
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('fr-FR').format(Math.round(num));
};
</script>
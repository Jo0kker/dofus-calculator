<template>
    <AppLayout title="Calculateur de Rentabilité">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Calculateur de Rentabilité Dofus
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Filters -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">Filtres</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Métier</label>
                            <select 
                                v-model="filters.profession"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                                <option value="" disabled>Sélectionnez un métier</option>
                                <option v-for="profession in professions" :key="profession" :value="profession">
                                    {{ profession }}
                                </option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Niveau min</label>
                            <input 
                                type="number" 
                                v-model="filters.min_level"
                                @input="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm"
                                min="1"
                                max="200"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Niveau max</label>
                            <input 
                                type="number" 
                                v-model="filters.max_level"
                                @input="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm"
                                min="1"
                                max="200"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Trier par</label>
                            <select 
                                v-model="filters.sort_by"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm"
                            >
                                <option value="profit">Profit</option>
                                <option value="profit_margin">Marge (%)</option>
                                <option value="revenue">Revenus</option>
                                <option value="cost">Coût</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Crafts les plus rentables</h3>
                        
                        <div v-if="!selectedServer" class="text-center py-8 text-gray-500">
                            Veuillez sélectionner un serveur pour voir les calculs de rentabilité
                        </div>
                        
                        <div v-else-if="profitableRecipes.length === 0" class="text-center py-8 text-gray-500">
                            Aucune recette rentable trouvée. Vérifiez que les prix sont saisis pour ce serveur.
                        </div>
                        
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Item
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Métier
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Coût
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Prix de vente
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Profit
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Marge
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="result in profitableRecipes" :key="result.recipe.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <img 
                                                    v-if="result.item.image_url" 
                                                    :src="result.item.image_url" 
                                                    :alt="result.item.name"
                                                    class="w-10 h-10 mr-3"
                                                />
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ result.item.name }}
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        Niveau {{ result.item.level }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ result.recipe.profession }}
                                            <span v-if="result.recipe.profession_level" class="text-gray-500">
                                                ({{ result.recipe.profession_level }})
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {{ formatNumber(result.cost) }} K
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {{ formatNumber(result.revenue) }} K
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600 text-right">
                                            +{{ formatNumber(result.profit) }} K
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                            <span :class="getMarginClass(result.profit_margin)">
                                                {{ result.profit_margin.toFixed(1) }}%
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <Link 
                                                :href="route('calculator.show', result.recipe.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Détails
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useServerSelection } from '@/Composables/useServerSelection';

const props = defineProps({
    profitableRecipes: Array,
    professions: Array,
    filters: Object,
});

const { selectedServer, selectedServerId } = useServerSelection();
const filters = ref({
    profession: props.filters?.profession || '',
    min_level: props.filters?.min_level || '',
    max_level: props.filters?.max_level || '',
    sort_by: props.filters?.sort_by || 'profit',
});


const applyFilters = () => {
    router.get(route('calculator.index'), {
        server_id: selectedServerId.value,
        ...filters.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatNumber = (num) => {
    return Math.round(num / 1000);
};

const getMarginClass = (margin) => {
    if (margin >= 100) return 'text-green-600 font-bold';
    if (margin >= 50) return 'text-green-600';
    if (margin >= 25) return 'text-yellow-600';
    return 'text-gray-600';
};
</script>
<template>
    <AppLayout title="Mes Favoris">
        <Head>
            <meta name="description" content="Gérez vos items favoris dans Dofus. Analysez rapidement les coûts de fabrication et la rentabilité de vos recettes préférées." />
        </Head>
        <template #header>
            <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Mes Favoris - Analyse des Coûts
            </h1>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div v-if="!selectedServer" class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-gray-500">Veuillez sélectionner un serveur pour analyser vos favoris</p>
                </div>

                <div v-else-if="!favorites || favorites.length === 0" class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-gray-500">Aucun favori ajouté. Ajoutez des items depuis la page des items !</p>
                </div>

                <div v-else class="space-y-6">
                    <div 
                        v-for="favorite in favorites" 
                        :key="favorite.item.id"
                        class="bg-white rounded-lg shadow overflow-hidden"
                    >
                        <div class="p-6">
                            <!-- Item Header -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center space-x-4">
                                    <Link :href="route('items.show', favorite.item.id)">
                                        <img 
                                            v-if="favorite.item.image_url" 
                                            :src="favorite.item.image_url" 
                                            :alt="favorite.item.name"
                                            class="w-16 h-16 object-contain hover:opacity-80 transition-opacity"
                                        />
                                    </Link>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">
                                            <Link :href="route('items.show', favorite.item.id)" class="hover:text-blue-600 transition-colors">
                                                {{ favorite.item.name }}
                                            </Link>
                                        </h2>
                                        <p v-if="favorite.item.level" class="text-sm text-gray-500">
                                            Niveau {{ favorite.item.level }}
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Best Option Badge -->
                                <div class="text-right">
                                    <div :class="getBestOptionClass(favorite.best_option)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mb-2">
                                        {{ getBestOptionText(favorite.best_option) }}
                                    </div>
                                    <div v-if="favorite.savings > 0" class="text-sm text-green-600 font-semibold">
                                        Économie: {{ formatNumber(favorite.savings) }} K
                                    </div>
                                </div>
                            </div>

                            <!-- Price Comparison -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-blue-900 mb-2">Prix d'achat direct</h4>
                                    <div class="text-2xl font-bold text-blue-600">
                                        {{ favorite.direct_price ? formatNumber(favorite.direct_price) + ' K' : 'Non disponible' }}
                                    </div>
                                </div>
                                
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-green-900 mb-2">Coût de craft</h4>
                                    <div class="text-2xl font-bold text-green-600">
                                        {{ favorite.craft_cost ? formatNumber(favorite.craft_cost) + ' K' : 'Non craftable' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Craft Tree -->
                            <div v-if="favorite.craft_tree && favorite.craft_tree.length > 0" class="border-t pt-4">
                                <h4 class="font-semibold text-gray-900 mb-3">Arbre de craft optimal</h4>
                                <CraftTree :tree="favorite.craft_tree" :level="0" :route="route" :Link="Link" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useServerSelection } from '@/Composables/useServerSelection';

// Composant Craft Tree
const CraftTree = {
    props: ['tree', 'level', 'route', 'Link'],
    template: `
        <div class="space-y-2">
            <div 
                v-for="node in tree" 
                :key="node.item.id"
                :class="['flex items-center p-2 rounded border-l-4', getMethodBorderClass(node.chosen_method)]"
                :style="{ marginLeft: level * 20 + 'px' }"
            >
                <component :is="Link" :href="route('items.show', node.item.id)" class="shrink-0">
                    <img 
                        v-if="node.item.image_url" 
                        :src="node.item.image_url" 
                        :alt="node.item.name"
                        class="w-8 h-8 mr-3 hover:opacity-80 transition-opacity"
                    />
                </component>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">
                            {{ node.quantity }}x 
                            <component :is="Link" :href="route('items.show', node.item.id)" class="hover:text-blue-600 transition-colors">
                                {{ node.item.name }}
                            </component>
                        </span>
                        <div class="text-sm">
                            <span v-if="node.chosen_method === 'buy'" class="text-blue-600">
                                Acheter: {{ formatNumber(node.direct_price) }} K chacun
                            </span>
                            <span v-else-if="node.chosen_method === 'craft'" class="text-green-600">
                                Crafter: {{ formatNumber(node.craft_cost) }} K chacun
                            </span>
                            <span v-else class="text-gray-500">Non disponible</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-for="node in tree" :key="'sub-' + node.item.id">
                <CraftTree v-if="node.subtree && node.subtree.length > 0" :tree="node.subtree" :level="level + 1" :route="route" :Link="Link" />
            </div>
        </div>
    `,
    methods: {
        formatNumber: (num) => new Intl.NumberFormat('fr-FR').format(num),
        getMethodBorderClass: (method) => {
            switch (method) {
                case 'buy': return 'border-blue-400';
                case 'craft': return 'border-green-400';
                default: return 'border-gray-400';
            }
        }
    }
};

const props = defineProps({
    favorites: Array,
});

const { selectedServer, selectedServerId } = useServerSelection();

const formatNumber = (num) => {
    return new Intl.NumberFormat('fr-FR').format(Math.round(num));
};

const getBestOptionClass = (option) => {
    switch (option) {
        case 'craft':
            return 'bg-green-100 text-green-800';
        case 'buy':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getBestOptionText = (option) => {
    switch (option) {
        case 'craft':
            return '🔨 Mieux de crafter';
        case 'buy':
            return '🛒 Mieux d\'acheter';
        default:
            return '❌ Non disponible';
    }
};
</script>
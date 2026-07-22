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

        <div class="py-8 sm:py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="!selectedServer && favorites.length" class="mx-4 mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 sm:mx-0">
                    Sélectionnez un serveur pour afficher les prix et comparer achat et craft. Vous pouvez tout de même gérer vos favoris.
                </div>

                <div v-if="!favorites.length" class="mx-4 bg-white rounded-lg shadow p-8 text-center sm:mx-0">
                    <p class="text-gray-500">Aucun favori ajouté. Ajoutez des items depuis la page des items !</p>
                </div>

                <div v-else>
                    <section class="mx-4 mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:mx-0 sm:p-5" aria-label="Gestion des favoris">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                            <div class="min-w-0 flex-1">
                                <label for="favorite-search" class="mb-1.5 block text-sm font-medium text-gray-700">Rechercher</label>
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                                    <input
                                        id="favorite-search"
                                        v-model="search"
                                        type="search"
                                        placeholder="Nom, type ou catégorie…"
                                        class="w-full rounded-lg border-gray-300 py-2.5 pl-9 pr-9 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    />
                                    <button
                                        v-if="search"
                                        type="button"
                                        class="absolute right-2 top-1/2 rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
                                        aria-label="Effacer la recherche"
                                        @click="search = ''"
                                    >
                                        <X class="h-4 w-4" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:w-[30rem]">
                                <div>
                                    <label for="favorite-type" class="mb-1.5 block text-sm font-medium text-gray-700">Type</label>
                                    <select id="favorite-type" v-model="selectedType" class="w-full rounded-lg border-gray-300 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">Tous les types</option>
                                        <option v-for="type in availableTypes" :key="type" :value="type">{{ type }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="favorite-sort" class="mb-1.5 block text-sm font-medium text-gray-700">Trier par</label>
                                    <select id="favorite-sort" v-model="sortBy" class="w-full rounded-lg border-gray-300 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="recent">Ajout récent</option>
                                        <option value="name">Nom</option>
                                        <option value="level">Niveau décroissant</option>
                                        <option value="savings">Économie décroissante</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap gap-2" aria-label="Filtrer par meilleure option">
                                <button
                                    v-for="option in optionFilters"
                                    :key="option.value"
                                    type="button"
                                    :class="bestOptionFilter === option.value
                                        ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                                        : 'border-gray-200 bg-white text-gray-600 hover:border-blue-300 hover:text-blue-700'"
                                    class="rounded-full border px-3 py-1.5 text-sm font-medium transition"
                                    :aria-pressed="bestOptionFilter === option.value"
                                    @click="bestOptionFilter = option.value"
                                >
                                    {{ option.label }}
                                    <span class="ml-1 opacity-75">{{ option.count }}</span>
                                </button>
                            </div>

                            <div class="flex items-center justify-between gap-3 text-sm text-gray-500 sm:justify-end">
                                <span>{{ resultLabel }}</span>
                                <button
                                    v-if="hasActiveFilters"
                                    type="button"
                                    class="font-medium text-blue-600 transition hover:text-blue-800"
                                    @click="resetFilters"
                                >
                                    Réinitialiser
                                </button>
                            </div>
                        </div>
                    </section>

                    <div v-if="filteredFavorites.length" class="space-y-6">
                    <div 
                        v-for="favorite in filteredFavorites"
                        :key="favorite.item.id"
                        class="mx-4 overflow-hidden rounded-lg bg-white shadow sm:mx-0"
                    >
                        <div class="p-6">
                            <!-- Item Header -->
                            <div class="mb-4 flex flex-col items-start gap-4 sm:flex-row sm:justify-between">
                                <div class="flex items-center space-x-4">
                                    <button type="button" @click="openItemDetails(favorite.item)">
                                        <img 
                                            v-if="favorite.item.image_url" 
                                            :src="favorite.item.image_url" 
                                            :alt="favorite.item.name"
                                            class="w-16 h-16 object-contain hover:opacity-80 transition-opacity"
                                        />
                                    </button>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">
                                            <button type="button" class="hover:text-blue-600 transition-colors" @click="openItemDetails(favorite.item)">
                                                {{ favorite.item.name }}
                                            </button>
                                        </h2>
                                        <p v-if="favorite.item.level" class="text-sm text-gray-500">
                                            Niveau {{ favorite.item.level }}
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Best Option Badge -->
                                <div class="flex w-full shrink-0 items-start justify-between gap-3 text-right sm:w-auto sm:justify-start sm:pl-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-2.5 py-2 text-sm font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 disabled:cursor-wait disabled:opacity-50"
                                        :disabled="removingIds.has(favorite.item.id)"
                                        :aria-label="`Retirer ${favorite.item.name} des favoris`"
                                        @click="removeFavorite(favorite.item)"
                                    >
                                        <StarOff class="h-4 w-4" aria-hidden="true" />
                                        <span class="hidden sm:inline">Retirer</span>
                                    </button>
                                    <div>
                                    <div :class="getBestOptionClass(favorite.best_option)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mb-2">
                                        {{ getBestOptionText(favorite.best_option) }}
                                    </div>
                                    <div v-if="favorite.savings > 0" class="text-sm text-green-600 font-semibold">
                                        Économie: {{ formatNumber(favorite.savings) }} K
                                    </div>
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

                    <div v-else class="mx-4 rounded-lg border border-dashed border-gray-300 bg-white px-6 py-10 text-center sm:mx-0">
                        <p class="font-medium text-gray-700">Aucun favori ne correspond à ces filtres.</p>
                        <button type="button" class="mt-2 text-sm font-medium text-blue-600 hover:text-blue-800" @click="resetFilters">
                            Afficher tous les favoris
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import { Search, StarOff, X } from '@lucide/vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useServerSelection } from '@/Composables/useServerSelection';
import { useDesktopBridge } from '@/Composables/useDesktopBridge';

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

const { selectedServer } = useServerSelection();
const { isDesktopFrame, openDesktopWindow } = useDesktopBridge();
const search = ref('');
const selectedType = ref('');
const bestOptionFilter = ref('all');
const sortBy = ref('recent');
const removingIds = reactive(new Set());

const availableTypes = computed(() => [...new Set(
    props.favorites
        .map((favorite) => favorite.item.type)
        .filter(Boolean),
)].sort((a, b) => a.localeCompare(b, 'fr')));

const optionFilters = computed(() => [
    { value: 'all', label: 'Tous', count: props.favorites.length },
    { value: 'buy', label: 'À acheter', count: props.favorites.filter((favorite) => favorite.best_option === 'buy').length },
    { value: 'craft', label: 'À crafter', count: props.favorites.filter((favorite) => favorite.best_option === 'craft').length },
    { value: 'unavailable', label: 'Sans estimation', count: props.favorites.filter((favorite) => favorite.best_option === 'unavailable').length },
]);

const filteredFavorites = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('fr');
    const favorites = props.favorites.filter((favorite) => {
        const item = favorite.item;
        const matchesSearch = !query || [item.name, item.type, item.category]
            .filter(Boolean)
            .some((value) => value.toLocaleLowerCase('fr').includes(query));
        const matchesType = !selectedType.value || item.type === selectedType.value;
        const matchesOption = bestOptionFilter.value === 'all' || favorite.best_option === bestOptionFilter.value;

        return matchesSearch && matchesType && matchesOption;
    });

    return favorites.sort((left, right) => {
        if (sortBy.value === 'name') {
            return left.item.name.localeCompare(right.item.name, 'fr');
        }

        if (sortBy.value === 'level') {
            return (right.item.level || 0) - (left.item.level || 0);
        }

        if (sortBy.value === 'savings') {
            return (right.savings || 0) - (left.savings || 0);
        }

        return new Date(right.item.pivot?.created_at || 0) - new Date(left.item.pivot?.created_at || 0);
    });
});

const hasActiveFilters = computed(() => search.value !== ''
    || selectedType.value !== ''
    || bestOptionFilter.value !== 'all'
    || sortBy.value !== 'recent');

const resultLabel = computed(() => {
    const count = filteredFavorites.value.length;
    return `${count} favori${count > 1 ? 's' : ''} affiché${count > 1 ? 's' : ''}`;
});

const resetFilters = () => {
    search.value = '';
    selectedType.value = '';
    bestOptionFilter.value = 'all';
    sortBy.value = 'recent';
};

const removeFavorite = (item) => {
    removingIds.add(item.id);
    router.delete(route('favorites.destroy', item.id), {
        preserveScroll: true,
        onFinish: () => removingIds.delete(item.id),
    });
};

const openItemDetails = (item) => {
    const itemUrl = route('items.show', item.id);

    if (isDesktopFrame.value && openDesktopWindow({
        id: `item-${item.id}`,
        title: item.name,
        url: itemUrl,
        width: 980,
        height: 720,
    })) {
        return;
    }

    router.visit(itemUrl);
};

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

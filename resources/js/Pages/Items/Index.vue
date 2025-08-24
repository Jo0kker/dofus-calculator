<template>
    <AppLayout title="Items">
        <Head>
            <meta name="description" content="Base de données complète des items Dofus. Consultez les prix, les recettes et les caractéristiques de tous les objets du jeu." />
        </Head>
        <template #header>
            <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Base de données des items
            </h1>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Search and Filters -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4">Recherche et filtres</h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rechercher</label>
                            <input 
                                type="text" 
                                v-model="filters.search"
                                @input="debounceSearch"
                                placeholder="Nom de l'item..."
                                class="w-full border-gray-300 rounded-md shadow-sm"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select 
                                v-model="filters.type"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm"
                            >
                                <option value="">Tous les types</option>
                                <option v-for="type in types" :key="type" :value="type">
                                    {{ type }}
                                </option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Niveau</label>
                            <div class="flex space-x-2">
                                <input 
                                    type="number" 
                                    v-model="filters.min_level"
                                    @input="applyFilters"
                                    placeholder="Min"
                                    class="w-1/2 border-gray-300 rounded-md shadow-sm"
                                    min="1"
                                    max="200"
                                />
                                <input 
                                    type="number" 
                                    v-model="filters.max_level"
                                    @input="applyFilters"
                                    placeholder="Max"
                                    class="w-1/2 border-gray-300 rounded-md shadow-sm"
                                    min="1"
                                    max="200"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items List -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div 
                                v-for="item in items.data" 
                                :key="item.id"
                                class="border rounded-lg p-4 hover:shadow-lg transition-shadow cursor-pointer"
                                @click="viewItem(item)"
                            >
                                <div class="flex items-start space-x-3">
                                    <img 
                                        v-if="item.image_url" 
                                        :src="item.image_url" 
                                        :alt="item.name"
                                        class="w-12 h-12 object-contain"
                                    />
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-900">{{ item.name }}</h4>
                                        <p class="text-sm text-gray-500">
                                            <span v-if="item.level">Niveau {{ item.level }}</span>
                                            <span v-if="item.type" class="ml-2">{{ item.type }}</span>
                                        </p>
                                        <div v-if="item.recipe" class="mt-2">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Craftable
                                            </span>
                                            <span v-if="item.recipe.profession" class="ml-1 text-xs text-gray-500">
                                                {{ item.recipe.profession }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="items.links.length > 3" class="mt-6">
                            <nav class="flex items-center justify-between">
                                <div class="flex-1 flex justify-between sm:hidden">
                                    <Link
                                        v-if="items.prev_page_url"
                                        :href="items.prev_page_url"
                                        :data="filters"
                                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        Précédent
                                    </Link>
                                    <Link
                                        v-if="items.next_page_url"
                                        :href="items.next_page_url"
                                        :data="filters"
                                        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        Suivant
                                    </Link>
                                </div>
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            Affichage de
                                            <span class="font-medium">{{ items.from }}</span>
                                            à
                                            <span class="font-medium">{{ items.to }}</span>
                                            sur
                                            <span class="font-medium">{{ items.total }}</span>
                                            résultats
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                            <template v-for="link in items.links" :key="link.label">
                                                <Link
                                                    v-if="link.url"
                                                    :href="link.url"
                                                    :data="filters"
                                                    :class="[
                                                        link.active
                                                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50',
                                                        'relative inline-flex items-center px-4 py-2 border text-sm font-medium'
                                                    ]"
                                                    v-html="link.label"
                                                />
                                                <span
                                                    v-else
                                                    :class="[
                                                        'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-300 cursor-default'
                                                    ]"
                                                    v-html="link.label"
                                                />
                                            </template>
                                        </nav>
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    items: Object,
    filters: Object,
    types: Array,
});

const filters = ref({
    search: props.filters?.search || '',
    type: props.filters?.type || '',
    min_level: props.filters?.min_level || '',
    max_level: props.filters?.max_level || '',
});

let searchTimeout = null;

const debounceSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 500);
};

const applyFilters = () => {
    router.get(route('items.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const viewItem = (item) => {
    router.visit(route('items.show', item.id));
};
</script>
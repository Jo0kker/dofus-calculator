<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const props = defineProps({ payload: { type: Object, default: () => ({}) } });
const emit = defineEmits(['open-app']);

const search = ref('');
const type = ref('');
const profession = ref('');
const bestOptionFilter = ref('all');
const sortBy = ref('recent');
const loading = ref(false);
const error = ref('');
const favorites = ref([]);
const types = ref([]);
const professions = ref([]);
const removingIds = ref(new Set());

const optionFilters = computed(() => [
    { value: 'all', label: 'Tous', count: favorites.value.length },
    { value: 'buy', label: 'À acheter', count: favorites.value.filter((item) => item.best_option === 'buy').length },
    { value: 'craft', label: 'À crafter', count: favorites.value.filter((item) => item.best_option === 'craft').length },
    { value: 'unavailable', label: 'Sans estimation', count: favorites.value.filter((item) => item.best_option === 'unavailable').length },
]);

const filteredFavorites = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('fr');
    const results = favorites.value.filter((item) => {
        const matchesSearch = !query || [item.name, item.type, item.category, item.profession]
            .filter(Boolean)
            .some((value) => value.toLocaleLowerCase('fr').includes(query));
        const matchesType = !type.value || item.type === type.value;
        const matchesProfession = !profession.value || item.profession === profession.value;
        const matchesOption = bestOptionFilter.value === 'all' || item.best_option === bestOptionFilter.value;

        return matchesSearch && matchesType && matchesProfession && matchesOption;
    });

    return results.sort((left, right) => {
        if (sortBy.value === 'name') {
            return left.name.localeCompare(right.name, 'fr');
        }

        if (sortBy.value === 'level') {
            return (right.level || 0) - (left.level || 0);
        }

        if (sortBy.value === 'savings') {
            return (right.savings || 0) - (left.savings || 0);
        }

        return new Date(right.favorited_at || 0) - new Date(left.favorited_at || 0);
    });
});

const hasActiveFilters = computed(() => search.value !== ''
    || type.value !== ''
    || profession.value !== ''
    || bestOptionFilter.value !== 'all'
    || sortBy.value !== 'recent');

const resultLabel = computed(() => {
    const count = filteredFavorites.value.length;
    return `${count} favori${count > 1 ? 's' : ''} affiché${count > 1 ? 's' : ''}`;
});

const resetFilters = () => {
    search.value = '';
    type.value = '';
    profession.value = '';
    bestOptionFilter.value = 'all';
    sortBy.value = 'recent';
};

const fetchFavorites = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/desktop/api/favorites');
        favorites.value = data.favorites || [];
        types.value = data.types || [];
        professions.value = data.professions || [];
    } catch {
        error.value = 'Impossible de charger les favoris.';
    } finally {
        loading.value = false;
    }
};

const inspect = (item) => emit('open-app', 'itemInspector', {
    windowId: `item-${item.id}`,
    title: item.name,
    itemId: item.id,
});

const inspectLink = (event, item) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    inspect(item);
};

const removeFavorite = async (item) => {
    removingIds.value = new Set([...removingIds.value, item.id]);
    error.value = '';

    try {
        await axios.delete(`/desktop/api/favorites/${item.id}`);
        favorites.value = favorites.value.filter((favorite) => favorite.id !== item.id);
        types.value = [...new Set(favorites.value.map((favorite) => favorite.type).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'fr'));
        professions.value = [...new Set(favorites.value.map((favorite) => favorite.profession).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'fr'));
    } catch {
        error.value = `Impossible de retirer ${item.name} des favoris.`;
    } finally {
        const pendingIds = new Set(removingIds.value);
        pendingIds.delete(item.id);
        removingIds.value = pendingIds;
    }
};

const bestOptionLabel = (item) => {
    if (item.best_option === 'buy') return 'À acheter';
    if (item.best_option === 'craft') return 'À crafter';
    return 'Sans estimation';
};

const bestOptionClass = (item) => {
    if (item.best_option === 'buy') return 'border-blue-300 bg-blue-50 text-blue-800';
    if (item.best_option === 'craft') return 'border-emerald-300 bg-emerald-50 text-emerald-800';
    return 'border-slate-300 bg-slate-50 text-slate-600';
};

const formatNumber = (value) => new Intl.NumberFormat('fr-FR').format(Math.round(value || 0));

watch(() => props.payload.seedItem, fetchFavorites);
onMounted(() => {
    fetchFavorites();
    window.addEventListener('dofus:favorites-changed', fetchFavorites);
});
onUnmounted(() => window.removeEventListener('dofus:favorites-changed', fetchFavorites));
</script>

<template>
    <DesktopAppShell title="Favoris" subtitle="Retrouve et gère les items que tu utilises souvent.">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 space-y-2 border-b border-[#9c9c9c] bg-[#f3f0df] p-3">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_10rem]">
                <input
                    v-model="search"
                    type="search"
                    class="min-w-0 border border-[#808080] bg-white px-3 py-2 text-sm shadow-inner focus:border-[#0f63bd] focus:ring-0"
                    placeholder="Nom, type ou catégorie…"
                />
                <select
                    v-model="type"
                    class="border border-[#808080] bg-white px-2 py-2 text-xs shadow-inner focus:border-[#0f63bd] focus:ring-0"
                >
                    <option value="">Tous les types</option>
                    <option v-for="itemType in types" :key="itemType" :value="itemType">{{ itemType }}</option>
                </select>
                <select
                    v-model="profession"
                    class="border border-[#808080] bg-white px-2 py-2 text-xs shadow-inner focus:border-[#0f63bd] focus:ring-0"
                >
                    <option value="">Tous les métiers</option>
                    <option v-for="itemProfession in professions" :key="itemProfession" :value="itemProfession">{{ itemProfession }}</option>
                </select>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <label for="desktop-favorite-sort" class="text-[11px] font-bold text-slate-700">Trier :</label>
                <select
                    id="desktop-favorite-sort"
                    v-model="sortBy"
                    class="min-w-44 flex-1 border border-[#808080] bg-white px-2 py-1.5 text-xs shadow-inner focus:border-[#0f63bd] focus:ring-0"
                >
                    <option value="recent">Ajout récent</option>
                    <option value="name">Nom</option>
                    <option value="level">Niveau décroissant</option>
                    <option value="savings">Économie décroissante</option>
                </select>
                <button v-if="hasActiveFilters" type="button" class="desk-button" @click="resetFilters">Réinitialiser</button>
            </div>

            <div class="flex flex-wrap gap-1" aria-label="Filtrer par meilleure option">
                <button
                    v-for="option in optionFilters"
                    :key="option.value"
                    type="button"
                    class="border px-2 py-1 text-[11px] font-bold shadow-[1px_1px_0_rgba(0,0,0,.12)]"
                    :class="bestOptionFilter === option.value
                        ? 'border-[#083f88] bg-[#0f63bd] text-white'
                        : 'border-[#9c9c9c] bg-white text-slate-700 hover:bg-blue-50'"
                    :aria-pressed="bestOptionFilter === option.value"
                    @click="bestOptionFilter = option.value"
                >
                    {{ option.label }} ({{ option.count }})
                </button>
            </div>

            <p class="text-[11px] text-slate-600">{{ resultLabel }}</p>
        </div>

        <p v-if="error" class="mb-2 border border-red-500 bg-red-50 p-2 text-xs font-bold text-red-700">{{ error }}</p>
        <div v-if="loading" class="p-6 text-center text-xs text-slate-500">Chargement des favoris…</div>
        <div v-else-if="!favorites.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">
            Aucun favori pour l’instant. Ajoute une étoile depuis la fiche d’un item.
        </div>
        <div v-else-if="!filteredFavorites.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">
            Aucun favori ne correspond à ces filtres.
            <button type="button" class="mt-2 block w-full font-bold text-blue-700 hover:underline" @click="resetFilters">Afficher tous les favoris</button>
        </div>

        <div v-else class="grid gap-2">
            <article
                v-for="item in filteredFavorites"
                :key="item.id"
                class="flex items-center gap-3 border border-[#b6b6b6] bg-white p-2 shadow-[2px_2px_0_rgba(0,0,0,.12)]"
            >
                <a
                    :href="route('items.show', item.id)"
                    class="flex min-w-0 flex-1 items-center gap-3"
                    @click="inspectLink($event, item)"
                >
                    <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-11 w-11 object-contain" />
                    <div v-else class="grid h-11 w-11 place-items-center border border-[#9c9c9c] bg-[#ece9d8] text-lg">⭐</div>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-sm font-black text-slate-950 hover:text-blue-700">{{ item.name }}</h3>
                        <p class="text-[11px] text-slate-600">
                            <span v-if="item.level">Niv. {{ item.level }}</span>
                            <span v-if="item.type"> · {{ item.type }}</span>
                            <span v-if="item.is_craftable" class="ml-1 font-bold text-emerald-700">Craftable<span v-if="item.profession"> · {{ item.profession }}</span></span>
                        </p>
                        <div class="mt-1 flex flex-wrap items-center gap-1 text-[10px]">
                            <span class="border px-1.5 py-0.5 font-bold" :class="bestOptionClass(item)">{{ bestOptionLabel(item) }}</span>
                            <span v-if="item.savings > 0" class="font-bold text-emerald-700">Économie {{ formatNumber(item.savings) }} K</span>
                        </div>
                    </div>
                </a>
                <div class="flex shrink-0 gap-1">
                    <a :href="route('items.show', item.id)" class="desk-button" @click="inspectLink($event, item)">Ouvrir</a>
                    <button
                        type="button"
                        class="desk-button text-red-700"
                        :disabled="removingIds.has(item.id)"
                        :aria-label="`Retirer ${item.name} des favoris`"
                        @click="removeFavorite(item)"
                    >
                        Retirer
                    </button>
                </div>
            </article>
        </div>
    </DesktopAppShell>
</template>

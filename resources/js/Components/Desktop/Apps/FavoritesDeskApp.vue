<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const props = defineProps({ payload: { type: Object, default: () => ({}) } });
const emit = defineEmits(['open-app']);

const search = ref('');
const type = ref('');
const loading = ref(false);
const error = ref('');
const favorites = ref([]);
const types = ref([]);
const removingIds = ref(new Set());

const filteredFavorites = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('fr');

    return favorites.value.filter((item) => {
        const matchesSearch = !query || [item.name, item.type, item.category]
            .filter(Boolean)
            .some((value) => value.toLocaleLowerCase('fr').includes(query));

        return matchesSearch && (!type.value || item.type === type.value);
    });
});

const fetchFavorites = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/desktop/api/favorites');
        favorites.value = data.favorites || [];
        types.value = data.types || [];
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

const removeFavorite = async (item) => {
    removingIds.value = new Set([...removingIds.value, item.id]);
    error.value = '';

    try {
        await axios.delete(`/desktop/api/favorites/${item.id}`);
        favorites.value = favorites.value.filter((favorite) => favorite.id !== item.id);
        types.value = [...new Set(favorites.value.map((favorite) => favorite.type).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'fr'));
    } catch {
        error.value = `Impossible de retirer ${item.name} des favoris.`;
    } finally {
        const pendingIds = new Set(removingIds.value);
        pendingIds.delete(item.id);
        removingIds.value = pendingIds;
    }
};

watch(() => props.payload.seedItem, fetchFavorites);
onMounted(fetchFavorites);
</script>

<template>
    <DesktopAppShell title="Favoris" subtitle="Retrouve et gère les items que tu utilises souvent.">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 border-b border-[#9c9c9c] bg-[#f3f0df] p-3">
            <div class="flex gap-2">
                <input
                    v-model="search"
                    type="search"
                    class="min-w-0 flex-1 border border-[#808080] bg-white px-3 py-2 text-sm shadow-inner focus:border-[#0f63bd] focus:ring-0"
                    placeholder="Rechercher dans les favoris…"
                />
                <select
                    v-model="type"
                    class="w-40 border border-[#808080] bg-white px-2 py-2 text-xs shadow-inner focus:border-[#0f63bd] focus:ring-0"
                >
                    <option value="">Tous les types</option>
                    <option v-for="itemType in types" :key="itemType" :value="itemType">{{ itemType }}</option>
                </select>
            </div>
            <p class="mt-2 text-[11px] text-slate-600">
                {{ filteredFavorites.length }} favori{{ filteredFavorites.length > 1 ? 's' : '' }} affiché{{ filteredFavorites.length > 1 ? 's' : '' }}
            </p>
        </div>

        <p v-if="error" class="mb-2 border border-red-500 bg-red-50 p-2 text-xs font-bold text-red-700">{{ error }}</p>
        <div v-if="loading" class="p-6 text-center text-xs text-slate-500">Chargement des favoris…</div>
        <div v-else-if="!favorites.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">
            Aucun favori pour l’instant. Ajoute une étoile depuis la fiche d’un item.
        </div>
        <div v-else-if="!filteredFavorites.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">
            Aucun favori ne correspond à cette recherche.
        </div>

        <div v-else class="grid gap-2">
            <article
                v-for="item in filteredFavorites"
                :key="item.id"
                class="flex items-center gap-3 border border-[#b6b6b6] bg-white p-2 shadow-[2px_2px_0_rgba(0,0,0,.12)]"
            >
                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-11 w-11 object-contain" />
                <div v-else class="grid h-11 w-11 place-items-center border border-[#9c9c9c] bg-[#ece9d8] text-lg">⭐</div>
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-sm font-black text-slate-950">{{ item.name }}</h3>
                    <p class="text-[11px] text-slate-600">
                        <span v-if="item.level">Niv. {{ item.level }}</span>
                        <span v-if="item.type"> · {{ item.type }}</span>
                        <span v-if="item.is_craftable" class="ml-1 font-bold text-emerald-700">Craftable</span>
                    </p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <button type="button" class="desk-button" @click="inspect(item)">Ouvrir</button>
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

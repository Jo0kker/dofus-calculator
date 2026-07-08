<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const emit = defineEmits(['open-app']);

const search = ref('');
const type = ref('');
const loading = ref(false);
const items = ref([]);
const types = ref([]);
let timeout = null;

const hasResults = computed(() => items.value.length > 0);

const fetchItems = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/desktop/api/items', {
            params: { search: search.value, type: type.value, limit: 16 },
        });
        items.value = data.items || [];
        types.value = data.types || [];
    } finally {
        loading.value = false;
    }
};

watch([search, type], () => {
    clearTimeout(timeout);
    timeout = setTimeout(fetchItems, 250);
});

const inspect = (item) => emit('open-app', 'itemInspector', {
    windowId: `item-${item.id}`,
    title: item.name,
    itemId: item.id,
});

onMounted(fetchItems);
</script>

<template>
    <DesktopAppShell title="Recherche d’items" subtitle="Cherche un item puis ouvre sa fiche.">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 border-b border-[#9c9c9c] bg-[#f3f0df] p-3">
            <div class="flex gap-2">
                <input
                    v-model="search"
                    type="search"
                    class="min-w-0 flex-1 border border-[#808080] bg-white px-3 py-2 text-sm shadow-inner focus:border-[#0f63bd] focus:ring-0"
                    placeholder="Chercher un item, ressource, équipement…"
                    autofocus
                />
                <select
                    v-model="type"
                    class="w-40 border border-[#808080] bg-white px-2 py-2 text-xs shadow-inner focus:border-[#0f63bd] focus:ring-0"
                >
                    <option value="">Tous</option>
                    <option v-for="itemType in types" :key="itemType" :value="itemType">{{ itemType }}</option>
                </select>
            </div>
        </div>

        <div v-if="loading" class="p-6 text-center text-xs text-slate-500">Recherche en cours…</div>
        <div v-else-if="!hasResults" class="p-6 text-center text-xs text-slate-500">Aucun item trouvé.</div>

        <div v-else class="grid gap-2">
            <article
                v-for="item in items"
                :key="item.id"
                class="flex items-center gap-3 border border-[#b6b6b6] bg-white p-2 shadow-[2px_2px_0_rgba(0,0,0,.12)]"
            >
                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-11 w-11 object-contain" />
                <div v-else class="grid h-11 w-11 place-items-center border border-[#9c9c9c] bg-[#ece9d8] text-lg">📦</div>
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
                </div>
            </article>
        </div>
    </DesktopAppShell>
</template>

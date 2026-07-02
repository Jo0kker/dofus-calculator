<script setup>
import { ref, watch } from 'vue';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';
import { useServerSelection } from '@/Composables/useServerSelection';

const props = defineProps({ payload: { type: Object, default: () => ({}) } });
const emit = defineEmits(['open-app']);

const STORAGE_KEY = 'dofus-calculator.desktop.price-watch.v1';
const { selectedServerId } = useServerSelection();

const readStoredWatch = () => {
    if (typeof window === 'undefined') return [];

    try {
        const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
        return Array.isArray(stored) ? stored : [];
    } catch {
        return [];
    }
};

const watched = ref(readStoredWatch());

const persistWatch = () => {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(watched.value));
};

const findPriceForServer = (item) => {
    if (!selectedServerId.value || !item?.prices) return null;
    return item.prices.find((price) => Number(price.server_id || price.server?.id) === Number(selectedServerId.value)) || null;
};

const currentPriceFor = (entry) => {
    const price = findPriceForServer(entry);
    return price ? Number(price.price) : null;
};

const isTargetReached = (entry) => {
    const current = currentPriceFor(entry);
    const target = Number(entry.target || 0);
    return current !== null && target > 0 && current <= target;
};

const addWatchedItem = (item) => {
    if (!item) return;

    const existing = watched.value.find((entry) => Number(entry.id) === Number(item.id));
    if (existing) {
        Object.assign(existing, { ...item, target: existing.target ?? null });
    } else {
        watched.value.push({ ...item, target: null });
    }
    persistWatch();
};

const removeWatchedItem = (itemId) => {
    watched.value = watched.value.filter((entry) => Number(entry.id) !== Number(itemId));
    persistWatch();
};

const copyItemName = async (name) => {
    await navigator.clipboard?.writeText(name);
};

watch(() => props.payload.seedItem, addWatchedItem, { immediate: true });
watch(watched, persistWatch, { deep: true });
</script>

<template>
    <DesktopAppShell title="Suivi des prix" subtitle="Items surveillés persistants et objectifs d’achat.">
        <div v-if="!watched.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">
            Ajoute un item depuis l’inspecteur avec le bouton “Surveiller”.
        </div>
        <div v-else class="space-y-2">
            <article v-for="entry in watched" :key="entry.id" class="border border-[#b6b6b6] bg-white p-3 shadow-[2px_2px_0_rgba(0,0,0,.12)]">
                <div class="flex items-start gap-3">
                    <img v-if="entry.image_url" :src="entry.image_url" :alt="entry.name" class="h-10 w-10 object-contain" />
                    <div v-else class="grid h-10 w-10 place-items-center border border-[#9c9c9c] bg-[#ece9d8]">📈</div>
                    <div class="min-w-0 flex-1">
                        <button type="button" class="block max-w-full truncate text-left text-sm font-black text-[#0b3f88] hover:underline" @click="emit('open-app', 'itemInspector', { windowId: `item-${entry.id}`, title: entry.name, itemId: entry.id })">
                            {{ entry.name }}
                        </button>
                        <p class="text-[11px] text-slate-600">{{ entry.type || 'Item' }} · Niv. {{ entry.level || '—' }}</p>
                    </div>
                    <button class="desk-button px-2 py-1 text-[11px]" type="button" @click="copyItemName(entry.name)">Copier</button>
                    <button class="desk-button px-2 py-1 text-[11px]" type="button" @click="removeWatchedItem(entry.id)">Retirer</button>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <div class="border border-[#d1d1d1] bg-[#f8f8f0] p-2">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Prix actuel</div>
                        <strong :class="currentPriceFor(entry) ? 'text-[#0b3f88]' : 'text-slate-500'">{{ currentPriceFor(entry) ? `${Number(currentPriceFor(entry)).toLocaleString('fr-FR')} K` : '—' }}</strong>
                    </div>
                    <label class="border border-[#d1d1d1] bg-[#f8f8f0] p-2">
                        <span class="block text-[11px] uppercase tracking-wide text-slate-500">Objectif achat</span>
                        <input v-model.number="entry.target" type="number" min="0" placeholder="kamas" class="mt-1 w-full border border-[#808080] px-2 py-1 text-xs" />
                    </label>
                    <div class="border border-[#d1d1d1] bg-[#f8f8f0] p-2">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Statut</div>
                        <strong :class="isTargetReached(entry) ? 'text-emerald-700' : 'text-slate-700'">
                            {{ isTargetReached(entry) ? 'Objectif atteint' : 'En attente' }}
                        </strong>
                    </div>
                </div>
            </article>
        </div>
    </DesktopAppShell>
</template>

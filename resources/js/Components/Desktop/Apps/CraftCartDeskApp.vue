<script setup>
import { computed, ref, watch } from 'vue';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const props = defineProps({ payload: { type: Object, default: () => ({}) } });
const emit = defineEmits(['open-app']);
const entries = ref([]);

watch(() => props.payload.seedItem, (item) => {
    if (!item) return;
    const existing = entries.value.find((entry) => entry.id === item.id);
    if (existing) existing.quantity += 1;
    else entries.value.push({ ...item, quantity: 1 });
}, { immediate: true });

const totalQuantity = computed(() => entries.value.reduce((sum, entry) => sum + entry.quantity, 0));
</script>

<template>
    <DesktopAppShell title="Panier craft" subtitle="Garde les items à crafter au même endroit, avec les quantités.">
        <div class="mb-3 flex gap-2">
            <button class="desk-button" type="button" @click="emit('open-app', 'itemSearch')">Ajouter un item</button>
            <button class="desk-button" type="button" @click="emit('open-app', 'calculator', { items: entries })">Calculer tout</button>
        </div>
        <div v-if="!entries.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">
            Aucun item dans le panier. Lance une recherche puis clique “Panier”.
        </div>
        <div v-else class="space-y-2">
            <article v-for="entry in entries" :key="entry.id" class="flex items-center gap-3 border border-[#b6b6b6] bg-white p-2 shadow-[2px_2px_0_rgba(0,0,0,.12)]">
                <div class="grid h-10 w-10 place-items-center border border-[#9c9c9c] bg-[#ece9d8]">🧺</div>
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-sm font-black">{{ entry.name }}</h3>
                    <p class="text-[11px] text-slate-600">{{ entry.type || 'Item' }} · Niv. {{ entry.level || '—' }}</p>
                </div>
                <input v-model.number="entry.quantity" type="number" min="1" class="w-16 border border-[#808080] px-2 py-1 text-xs" />
                <button class="desk-button" type="button" @click="emit('open-app', 'itemInspector', { windowId: `item-${entry.id}`, title: entry.name, itemId: entry.id })">Voir</button>
            </article>
            <p class="text-xs font-bold text-slate-700">Total lignes: {{ entries.length }} · Quantité: {{ totalQuantity }}</p>
        </div>
    </DesktopAppShell>
</template>

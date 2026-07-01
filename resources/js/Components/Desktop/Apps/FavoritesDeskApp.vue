<script setup>
import { ref, watch } from 'vue';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const props = defineProps({ payload: { type: Object, default: () => ({}) } });
const pins = ref([]);
watch(() => props.payload.seedItem, (item) => {
    if (item && !pins.value.some((entry) => entry.id === item.id)) pins.value.push(item);
}, { immediate: true });
</script>

<template>
    <DesktopAppShell title="Favoris desktop" subtitle="Pins rapides du workspace, séparés de la page favoris classique pour limiter l’impact.">
        <div v-if="!pins.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">Aucun pin desktop pour l’instant.</div>
        <div v-else class="grid gap-2 sm:grid-cols-2">
            <article v-for="item in pins" :key="item.id" class="border border-[#b6b6b6] bg-white p-2 text-xs shadow-[2px_2px_0_rgba(0,0,0,.12)]">
                <strong class="block truncate text-sm">⭐ {{ item.name }}</strong>
                <span class="text-slate-600">{{ item.type || 'Item' }} · Niv. {{ item.level || '—' }}</span>
            </article>
        </div>
    </DesktopAppShell>
</template>

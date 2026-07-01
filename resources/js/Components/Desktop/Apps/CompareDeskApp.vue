<script setup>
import { ref, watch } from 'vue';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const props = defineProps({ payload: { type: Object, default: () => ({}) } });
const compared = ref([]);

watch(() => props.payload.seedItem, (item) => {
    if (item && !compared.value.some((entry) => entry.id === item.id)) compared.value.push(item);
}, { immediate: true });
</script>

<template>
    <DesktopAppShell title="Comparateur" subtitle="Fenêtre native pour comparer plusieurs items côte à côte.">
        <div v-if="!compared.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">
            Envoie des items depuis l’inspecteur pour les comparer ici.
        </div>
        <div v-else class="grid gap-2 sm:grid-cols-2">
            <article v-for="item in compared" :key="item.id" class="border border-[#b6b6b6] bg-white p-3 text-xs shadow-[2px_2px_0_rgba(0,0,0,.12)]">
                <strong class="block text-sm">{{ item.name }}</strong>
                <span class="text-slate-600">{{ item.type || 'Item' }} · Niv. {{ item.level || '—' }}</span>
                <div class="mt-2 grid grid-cols-2 gap-1 text-[11px]">
                    <span class="border bg-[#f8f8f0] p-1">Craft: {{ item.is_craftable ? 'oui' : 'non' }}</span>
                    <span class="border bg-[#f8f8f0] p-1">Cat.: {{ item.category || '—' }}</span>
                </div>
            </article>
        </div>
    </DesktopAppShell>
</template>

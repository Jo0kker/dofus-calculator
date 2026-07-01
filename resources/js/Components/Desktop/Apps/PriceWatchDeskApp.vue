<script setup>
import { ref, watch } from 'vue';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const props = defineProps({ payload: { type: Object, default: () => ({}) } });
const watched = ref([]);

watch(() => props.payload.seedItem, (item) => {
    if (item && !watched.value.some((entry) => entry.id === item.id)) {
        watched.value.push({ ...item, target: null });
    }
}, { immediate: true });
</script>

<template>
    <DesktopAppShell title="Suivi des prix" subtitle="Ajoute les items à surveiller et définis tes objectifs d’achat.">
        <div v-if="!watched.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">
            Ajoute un item depuis l’inspecteur pour commencer à surveiller son prix.
        </div>
        <div v-else class="space-y-2">
            <article v-for="entry in watched" :key="entry.id" class="border border-[#b6b6b6] bg-white p-3 shadow-[2px_2px_0_rgba(0,0,0,.12)]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-black">{{ entry.name }}</h3>
                        <p class="text-[11px] text-slate-600">{{ entry.type || 'Item' }} · objectif d’achat</p>
                    </div>
                    <input v-model.number="entry.target" type="number" min="0" placeholder="kamas" class="w-28 border border-[#808080] px-2 py-1 text-xs" />
                </div>
                <p class="mt-2 text-[11px] text-slate-500">Prochaine étape: brancher alertes et opportunités craft &lt; vente.</p>
            </article>
        </div>
    </DesktopAppShell>
</template>

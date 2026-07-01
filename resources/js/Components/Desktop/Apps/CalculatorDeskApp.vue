<script setup>
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';
const props = defineProps({ payload: { type: Object, default: () => ({}) } });
</script>

<template>
    <DesktopAppShell title="Calculateur desktop" subtitle="Surface native de calcul contextualisée par les autres fenêtres.">
        <div class="border border-[#9c9c9c] bg-white p-3 text-xs shadow-inner">
            <p v-if="payload.seedItem"><strong>Item courant :</strong> {{ payload.seedItem.name }}</p>
            <p v-else-if="payload.items?.length"><strong>Panier reçu :</strong> {{ payload.items.length }} ligne(s)</p>
            <p v-else>Aucun contexte reçu. Ouvre un item ou un panier pour alimenter le calculateur.</p>
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <div class="border border-[#b6b6b6] bg-[#f8f8f0] p-3 text-xs">
                <strong>À brancher</strong>
                <p class="mt-1 text-slate-600">Coût récursif, achat vs craft, marges et export.</p>
            </div>
            <div class="border border-[#b6b6b6] bg-[#f8f8f0] p-3 text-xs">
                <strong>UX cible</strong>
                <p class="mt-1 text-slate-600">Calcul immédiat depuis inspecteur/panier, sans page séparée.</p>
            </div>
        </div>
    </DesktopAppShell>
</template>

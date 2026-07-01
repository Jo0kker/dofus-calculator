<script setup>
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';
const emit = defineEmits(['open-app']);
</script>

<template>
    <DesktopAppShell title="Actions rapides" subtitle="Ouvre les outils utiles en un clic.">
        <div class="grid gap-2">
            <button class="desk-button justify-start py-2 text-left" type="button" @click="emit('open-app', 'itemSearch')">🔎 Nouvelle recherche item</button>
            <button class="desk-button justify-start py-2 text-left" type="button" @click="emit('open-app', 'craftCart')">🧺 Ouvrir panier craft</button>
            <button class="desk-button justify-start py-2 text-left" type="button" @click="emit('open-app', 'compare')">⚖️ Comparateur</button>
            <button class="desk-button justify-start py-2 text-left" type="button" @click="emit('open-app', 'priceWatch')">📈 Watchlist prix</button>
            <button class="desk-button justify-start py-2 text-left" type="button" @click="emit('open-app', 'notifications')">🔔 Centre notifications</button>
            <button class="desk-button justify-start py-2 text-left" type="button" @click="emit('open-app', 'server')">🌍 Serveur de prix</button>
        </div>
    </DesktopAppShell>
</template>

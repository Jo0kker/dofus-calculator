<script setup>
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';
import ServerSelector from '@/Components/ServerSelector.vue';
</script>

<template>
    <DesktopAppShell title="Serveur et prix" subtitle="Contexte global utilisé par recherche, prix et craft.">
        <div class="border border-[#9c9c9c] bg-white p-3 shadow-inner">
            <ServerSelector compact />
        </div>
        <p class="mt-3 text-xs leading-relaxed text-slate-600">Le serveur et la source des prix s’appliquent à toutes les fenêtres. Une fiche item peut ensuite définir sa propre exception.</p>
    </DesktopAppShell>
</template>

<script setup>
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';
const events = [
    { icon: '✅', text: 'Mode bureau activé.' },
    { icon: '🪟', text: 'Tu peux ouvrir plusieurs outils en même temps.' },
    { icon: '⚡', text: 'Les raccourcis clavier et les alertes arriveront ensuite.' },
];
</script>

<template>
    <DesktopAppShell title="Notifications" subtitle="Retrouve ici les messages importants.">
        <div class="space-y-2">
            <div v-for="event in events" :key="event.text" class="flex gap-3 border border-[#b6b6b6] bg-white p-3 text-xs shadow-[2px_2px_0_rgba(0,0,0,.12)]">
                <span>{{ event.icon }}</span>
                <span>{{ event.text }}</span>
            </div>
        </div>
    </DesktopAppShell>
</template>

<script setup>
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';
const events = [
    { icon: '✅', text: 'Desktop natif activé pour ce mode uniquement.' },
    { icon: '🪟', text: 'Les fenêtres legacy restent disponibles mais ne sont plus le cœur du flow.' },
    { icon: '⚡', text: 'Prochaine étape: command palette Ctrl+K et notifications temps réel.' },
];
</script>

<template>
    <DesktopAppShell title="Notifications" subtitle="Centre d’événements du workspace.">
        <div class="space-y-2">
            <div v-for="event in events" :key="event.text" class="flex gap-3 border border-[#b6b6b6] bg-white p-3 text-xs shadow-[2px_2px_0_rgba(0,0,0,.12)]">
                <span>{{ event.icon }}</span>
                <span>{{ event.text }}</span>
            </div>
        </div>
    </DesktopAppShell>
</template>

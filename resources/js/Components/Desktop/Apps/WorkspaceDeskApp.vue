<script setup>
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const emit = defineEmits(['open-app']);

const cards = [
    { app: 'itemSearch', title: 'Chercher un item', icon: '🔎', text: 'Ouvre une fiche item avec ses prix, sa recette et ses ressources.' },
    { app: 'calculator', title: 'Calculette', icon: '🧮', text: 'Un calcul rapide de coût, marge ou quantité sans sortir du bureau.' },
    { app: 'priceWatch', title: 'Surveiller les prix', icon: '📈', text: 'Garde de côté les items dont tu veux suivre le prix.' },
    { app: 'favorites', title: 'Favoris', icon: '⭐', text: 'Retrouve les items que tu utilises souvent.' },
];
</script>

<template>
    <DesktopAppShell
        title="Accueil bureau"
        subtitle="Les raccourcis utiles, sans fausses apps qui ne servent à rien."
    >
        <div class="grid gap-3 sm:grid-cols-2">
            <button
                v-for="card in cards"
                :key="card.app"
                type="button"
                class="group border border-[#9c9c9c] bg-white/80 p-3 text-left shadow-[inset_1px_1px_0_#fff,3px_3px_0_rgba(0,0,0,.18)] transition hover:-translate-y-0.5 hover:bg-white"
                @click="emit('open-app', card.app)"
            >
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 place-items-center border border-[#808080] bg-[#ece9d8] text-xl shadow-inner">{{ card.icon }}</span>
                    <span>
                        <span class="block text-sm font-black text-slate-950">{{ card.title }}</span>
                        <span class="mt-1 block text-xs leading-relaxed text-slate-600">{{ card.text }}</span>
                    </span>
                </div>
            </button>
        </div>

        <div class="mt-4 border border-[#9c9c9c] bg-[#fff8d8] p-3 text-xs text-slate-700 shadow-inner">
            <strong>Flux conseillé :</strong> recherche un item, règle la quantité sur sa fiche, puis ouvre la liste des ressources nécessaires.
        </div>
    </DesktopAppShell>
</template>

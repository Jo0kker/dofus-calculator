<script setup>
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const emit = defineEmits(['open-app']);

const cards = [
    { app: 'itemSearch', title: 'Chercher un item', icon: '🔎', text: 'Trouve, inspecte, compare et ajoute au panier sans quitter le bureau.' },
    { app: 'craftCart', title: 'Préparer un craft', icon: '🧺', text: 'Centralise les items à crafter, quantités et prochaines actions.' },
    { app: 'compare', title: 'Comparer', icon: '⚖️', text: 'Garde plusieurs items côte à côte pour décider vite.' },
    { app: 'priceWatch', title: 'Surveiller les prix', icon: '📈', text: 'Crée une watchlist pour repérer opportunités et seuils.' },
    { app: 'quickActions', title: 'Actions rapides', icon: '⚡', text: 'Ouvre les outils que tu utilises le plus.' },
];
</script>

<template>
    <DesktopAppShell
        title="Accueil bureau"
        subtitle="Tout ce qu’il faut pour chercher, comparer et préparer tes crafts."
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
            <strong>Astuce :</strong> commence par une recherche, puis envoie les items vers le panier, le comparateur ou le suivi des prix.
        </div>
    </DesktopAppShell>
</template>

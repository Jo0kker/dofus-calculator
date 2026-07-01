<script setup>
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';
import IngredientPricing from '@/Components/IngredientPricing.vue';
import RecipeCostCalculator from '@/Components/RecipeCostCalculator.vue';
import ResourcesCalculator from '@/Components/ResourcesCalculator.vue';

const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['open-app']);
const loading = ref(false);
const item = ref(null);

const getDirectPrice = () => {
    if (!item.value?.prices?.length) return null;

    const totalPrice = item.value.prices.reduce((sum, price) => sum + Number(price.price || 0), 0);
    return totalPrice / item.value.prices.length;
};

const descriptionText = () => {
    const description = item.value?.metadata?.description;

    if (!description) return '';
    if (typeof description === 'string') return description.replace(/\\n/g, '\n').trim();

    if (typeof description === 'object') {
        const preferred = description.fr || description.en || Object.values(description).find(value => typeof value === 'string');
        return typeof preferred === 'string' ? preferred.replace(/\\n/g, '\n').trim() : '';
    }

    return '';
};

const reloadItem = () => loadItem();

const loadItem = async () => {
    if (!props.payload.itemId) return;
    loading.value = true;
    try {
        const { data } = await axios.get(`/desktop/api/items/${props.payload.itemId}`);
        item.value = data.item;
    } finally {
        loading.value = false;
    }
};

watch(() => props.payload.itemId, loadItem);
onMounted(loadItem);
</script>

<template>
    <DesktopAppShell title="Inspecteur item" subtitle="Prix, recette et calculs de craft.">
        <div v-if="loading" class="p-6 text-center text-xs text-slate-500">Chargement…</div>
        <div v-else-if="!item" class="p-6 text-center text-xs text-slate-500">Sélectionne un item depuis la recherche.</div>
        <div v-else class="space-y-3">
            <section class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)]">
                <div class="min-w-0 space-y-3">
                    <section class="flex gap-3 border border-[#9c9c9c] bg-white p-3 shadow-[3px_3px_0_rgba(0,0,0,.16)]">
                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-16 w-16 object-contain" />
                <div v-else class="grid h-16 w-16 place-items-center border border-[#9c9c9c] bg-[#ece9d8] text-2xl">📦</div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-black text-slate-950">{{ item.name }}</h3>
                    <p class="text-xs text-slate-600">Niv. {{ item.level || '—' }} · {{ item.type || 'Type inconnu' }}</p>
                    <p v-if="descriptionText()" class="mt-2 whitespace-pre-line text-xs leading-relaxed text-slate-700">{{ descriptionText() }}</p>
                </div>
                    </section>

                    <section class="grid grid-cols-2 gap-2">
                <button class="desk-button py-2" type="button" @click="emit('open-app', 'craftCart', { seedItem: item })">Ajouter au panier</button>
                <button class="desk-button py-2" type="button" @click="emit('open-app', 'compare', { seedItem: item })">Comparer</button>
                <button class="desk-button py-2" type="button" @click="emit('open-app', 'priceWatch', { seedItem: item })">Surveiller le prix</button>
                <button class="desk-button py-2" type="button" @click="emit('open-app', 'calculator', { seedItem: item })">Calculer craft</button>
                <button class="desk-button py-2" type="button" @click="emit('open-app', 'favorites', { seedItem: item })">Épingler</button>
                    </section>

                    <section class="border border-[#9c9c9c] bg-white p-3 shadow-inner">
                        <h4 class="mb-2 text-xs font-black uppercase tracking-wide text-[#0b3f88]">Recette</h4>
                        <div v-if="item.recipe" class="space-y-3">
                            <p class="text-xs text-slate-700">
                                {{ item.recipe.profession || 'Métier inconnu' }}
                                <span v-if="item.recipe.profession_level">niv. {{ item.recipe.profession_level }}</span>
                                · produit x{{ item.recipe.quantity_produced || 1 }}
                            </p>
                            <div class="grid gap-1">
                                <button
                                    v-for="ingredient in item.recipe.ingredients"
                                    :key="ingredient.id"
                                    type="button"
                                    class="flex items-center justify-between border border-[#d1d1d1] bg-[#f8f8f0] px-2 py-1 text-left text-xs hover:bg-white"
                                    @click="emit('open-app', 'itemInspector', { windowId: `item-${ingredient.id}`, title: ingredient.name, itemId: ingredient.id })"
                                >
                                    <span class="truncate">{{ ingredient.name }}</span>
                                    <span class="font-black">x{{ ingredient.pivot?.quantity || ingredient.quantity }}</span>
                                </button>
                            </div>

                            <RecipeCostCalculator
                                :recipe="item.recipe"
                                :direct-price="getDirectPrice()"
                                :item-id="item.id"
                            />

                            <ResourcesCalculator :recipe="item.recipe" :item-name="item.name" />
                        </div>
                        <p v-else class="text-xs text-slate-500">Pas de recette connue.</p>
                    </section>
                </div>

                <aside class="min-w-0 space-y-3">
                    <section class="border border-[#9c9c9c] bg-white p-3 shadow-inner">
                        <h4 class="mb-2 text-xs font-black uppercase tracking-wide text-[#0b3f88]">Prix de l'item</h4>
                        <IngredientPricing :ingredient="item" @price-updated="reloadItem" />
                    </section>

                    <section class="border border-[#9c9c9c] bg-white p-3 shadow-inner">
                        <h4 class="mb-2 text-xs font-black uppercase tracking-wide text-[#0b3f88]">Prix récents</h4>
                        <p v-if="!item.prices?.length" class="text-xs text-slate-500">Aucun prix approuvé disponible.</p>
                        <div v-else class="grid gap-1 text-xs">
                            <div v-for="price in item.prices" :key="price.id" class="flex justify-between border-b border-slate-100 py-1">
                                <span>{{ price.server?.name || 'Serveur' }}</span>
                                <strong>{{ Number(price.price).toLocaleString('fr-FR') }} k</strong>
                            </div>
                        </div>
                    </section>
                </aside>
            </section>
        </div>
    </DesktopAppShell>
</template>

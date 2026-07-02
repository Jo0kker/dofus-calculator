<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';
import { useServerSelection } from '@/Composables/useServerSelection';

const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['open-app']);

const { selectedServerId, isServerSelected } = useServerSelection();

const loading = ref(false);
const item = ref(null);
const priceInput = ref('');
const priceSaving = ref(false);
const priceMessage = ref('');
const reportComment = ref('');
const reportSaving = ref(false);
const reportMessage = ref('');
const calculationMode = ref('optimized');
const includedIngredients = ref({});
const optimizedCalculation = ref(null);
const optimizedLoading = ref(false);
const craftQuantity = ref(1);

const formatNumber = (value) => new Intl.NumberFormat('fr-FR').format(Math.round(Number(value || 0)));

const findPriceForServer = (prices = []) => {
    if (!selectedServerId.value) return null;
    return prices.find((price) => Number(price.server_id || price.server?.id) === Number(selectedServerId.value)) || null;
};

const currentPrice = computed(() => findPriceForServer(item.value?.prices || []));
const directPrice = computed(() => currentPrice.value ? Number(currentPrice.value.price) : null);

const descriptionText = computed(() => {
    const description = item.value?.metadata?.description;

    if (!description) return '';
    if (typeof description === 'string') return description.replace(/\\n/g, '\n').trim();

    if (typeof description === 'object') {
        const preferred = description.fr || description.en || Object.values(description).find(value => typeof value === 'string');
        return typeof preferred === 'string' ? preferred.replace(/\\n/g, '\n').trim() : '';
    }

    return '';
});

const recipeIngredients = computed(() => item.value?.recipe?.ingredients || []);

const manualRows = computed(() => recipeIngredients.value.map((ingredient) => {
    const quantity = Number(ingredient.pivot?.quantity || ingredient.quantity || 1);
    const price = findPriceForServer(ingredient.prices || []);
    const included = includedIngredients.value[ingredient.id] !== false;

    return {
        ingredient,
        quantity,
        included,
        unitPrice: price ? Number(price.price) : null,
        total: price && included ? Number(price.price) * quantity : 0,
        missing: !price && included,
    };
}));

const manualTotal = computed(() => manualRows.value.reduce((sum, row) => sum + row.total, 0));
const manualMissingCount = computed(() => manualRows.value.filter(row => row.missing).length);
const manualCanCraft = computed(() => manualMissingCount.value === 0);
const excludedCount = computed(() => manualRows.value.filter(row => !row.included).length);

const bestCraftCost = computed(() => {
    if (calculationMode.value === 'optimized' && optimizedCalculation.value?.craftCost) {
        return Number(optimizedCalculation.value.craftCost);
    }

    if (calculationMode.value === 'manual' && manualCanCraft.value) {
        return manualTotal.value;
    }

    return null;
});

const craftVerdict = computed(() => {
    if (!bestCraftCost.value || !directPrice.value) return null;

    const diff = directPrice.value - bestCraftCost.value;
    return {
        label: diff > 0 ? 'Craft rentable' : 'Achat direct meilleur',
        diff: Math.abs(diff),
        profitable: diff > 0,
    };
});

const resourceRows = computed(() => recipeIngredients.value.map((ingredient) => {
    const quantity = Number(ingredient.pivot?.quantity || ingredient.quantity || 1) * Number(craftQuantity.value || 1);
    const price = findPriceForServer(ingredient.prices || []);

    return {
        id: ingredient.id,
        name: ingredient.name,
        image_url: ingredient.image_url,
        quantity,
        unitPrice: price ? Number(price.price) : null,
        total: price ? Number(price.price) * quantity : null,
    };
}));

const resourcesTotal = computed(() => resourceRows.value.reduce((sum, row) => sum + (row.total || 0), 0));

const copyResources = async () => {
    const text = resourceRows.value
        .map(row => `${row.quantity}x ${row.name}${row.total ? ` — ${formatNumber(row.total)} K` : ''}`)
        .join('\n');

    await navigator.clipboard?.writeText(text);
    priceMessage.value = 'Liste copiée.';
};

const initializeIncludedIngredients = () => {
    recipeIngredients.value.forEach((ingredient) => {
        if (!(ingredient.id in includedIngredients.value)) {
            includedIngredients.value[ingredient.id] = true;
        }
    });
};

const toggleAllIngredients = () => {
    const nextValue = manualRows.value.some(row => !row.included);
    recipeIngredients.value.forEach((ingredient) => {
        includedIngredients.value[ingredient.id] = nextValue;
    });
};

const loadOptimizedCalculation = async () => {
    if (!selectedServerId.value || !item.value?.id || calculationMode.value !== 'optimized') {
        optimizedCalculation.value = null;
        return;
    }

    optimizedLoading.value = true;
    try {
        const { data } = await axios.get(`/items/${item.value.id}/calculate-recursive`, {
            params: { server_id: selectedServerId.value },
        });
        optimizedCalculation.value = data;
    } catch {
        optimizedCalculation.value = null;
    } finally {
        optimizedLoading.value = false;
    }
};

const loadItem = async () => {
    if (!props.payload.itemId) return;

    loading.value = true;
    priceMessage.value = '';
    reportMessage.value = '';

    try {
        const { data } = await axios.get(`/desktop/api/items/${props.payload.itemId}`);
        item.value = data.item;
        initializeIncludedIngredients();
        await loadOptimizedCalculation();
    } finally {
        loading.value = false;
    }
};

const savePrice = async () => {
    if (!item.value?.id || !selectedServerId.value || !priceInput.value) return;

    priceSaving.value = true;
    priceMessage.value = '';

    try {
        await axios.post('/prices', {
            item_id: item.value.id,
            server_id: selectedServerId.value,
            price: priceInput.value,
        });
        priceInput.value = '';
        priceMessage.value = 'Prix enregistré.';
        await loadItem();
    } catch {
        priceMessage.value = 'Impossible d’enregistrer ce prix.';
    } finally {
        priceSaving.value = false;
    }
};

const reportPrice = async () => {
    if (!currentPrice.value?.id || !reportComment.value.trim()) return;

    reportSaving.value = true;
    reportMessage.value = '';

    try {
        await axios.post(`/prices/${currentPrice.value.id}/report`, {
            comment: reportComment.value,
        });
        reportComment.value = '';
        reportMessage.value = 'Signalement envoyé.';
    } catch {
        reportMessage.value = 'Signalement impossible pour ce prix.';
    } finally {
        reportSaving.value = false;
    }
};

watch(() => props.payload.itemId, loadItem);
watch([selectedServerId, calculationMode], () => loadOptimizedCalculation());
watch(item, initializeIncludedIngredients);
onMounted(loadItem);
</script>

<template>
    <DesktopAppShell title="Fiche item" subtitle="Prix, recette et calculs de craft.">
        <div v-if="loading" class="p-6 text-center text-xs text-slate-500">Chargement…</div>
        <div v-else-if="!item" class="p-6 text-center text-xs text-slate-500">Sélectionne un item depuis la recherche.</div>

        <div v-else class="grid gap-3 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,.8fr)]">
            <div class="min-w-0 space-y-3">
                <section class="border border-[#8b8b8b] bg-white shadow-[3px_3px_0_rgba(0,0,0,.16)]">
                    <div class="flex items-start gap-3 border-b border-[#d1d1d1] bg-[#f8f8f0] p-3">
                        <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-16 w-16 shrink-0 object-contain" />
                        <div v-else class="grid h-16 w-16 shrink-0 place-items-center border border-[#9c9c9c] bg-[#ece9d8] text-2xl">📦</div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black text-slate-950">{{ item.name }}</h3>
                            <p class="text-xs text-slate-600">Niv. {{ item.level || '—' }} · {{ item.type || 'Type inconnu' }}</p>
                            <p v-if="descriptionText" class="mt-2 whitespace-pre-line text-xs leading-relaxed text-slate-700">{{ descriptionText }}</p>
                        </div>
                    </div>

                    <div class="grid gap-2 p-3 sm:grid-cols-3">
                        <button class="desk-button py-2" type="button" @click="emit('open-app', 'craftCart', { seedItem: item })">Ajouter au panier</button>
                        <button class="desk-button py-2" type="button" @click="emit('open-app', 'compare', { seedItem: item })">Comparer</button>
                        <button class="desk-button py-2" type="button" @click="emit('open-app', 'priceWatch', { seedItem: item })">Surveiller</button>
                    </div>
                </section>

                <section class="border border-[#8b8b8b] bg-white shadow-inner">
                    <div class="flex items-center justify-between border-b border-[#d1d1d1] bg-[#0b3f88] px-3 py-2 text-white">
                        <h4 class="text-xs font-black uppercase tracking-wide">Recette & calcul</h4>
                        <span v-if="item.recipe" class="text-xs">{{ item.recipe.profession || 'Métier inconnu' }} · x{{ item.recipe.quantity_produced || 1 }}</span>
                    </div>

                    <div v-if="item.recipe" class="space-y-3 p-3">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                class="desk-button py-2"
                                :class="calculationMode === 'optimized' ? 'bg-[#dbeafe]' : ''"
                                @click="calculationMode = 'optimized'"
                            >
                                Optimisé craft / achat
                            </button>
                            <button
                                type="button"
                                class="desk-button py-2"
                                :class="calculationMode === 'manual' ? 'bg-[#dbeafe]' : ''"
                                @click="calculationMode = 'manual'"
                            >
                                Manuel
                            </button>
                        </div>

                        <div v-if="!isServerSelected" class="border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800">
                            Sélectionne un serveur pour calculer les prix.
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <div class="border border-[#d1d1d1] bg-[#f8f8f0] p-3">
                                <div class="text-[11px] uppercase tracking-wide text-slate-500">Coût craft</div>
                                <div v-if="calculationMode === 'optimized' && optimizedLoading" class="mt-1 text-sm font-bold text-slate-500">Calcul…</div>
                                <div v-else-if="bestCraftCost" class="mt-1 text-xl font-black text-emerald-700">{{ formatNumber(bestCraftCost) }} K</div>
                                <div v-else class="mt-1 text-sm font-bold text-red-600">Prix incomplet</div>
                            </div>
                            <div class="border border-[#d1d1d1] bg-[#f8f8f0] p-3">
                                <div class="text-[11px] uppercase tracking-wide text-slate-500">Comparaison</div>
                                <div v-if="craftVerdict" :class="craftVerdict.profitable ? 'text-emerald-700' : 'text-red-700'" class="mt-1 text-sm font-black">
                                    {{ craftVerdict.label }}
                                </div>
                                <div v-if="craftVerdict" class="text-xs text-slate-600">Écart : {{ formatNumber(craftVerdict.diff) }} K</div>
                                <div v-else class="mt-1 text-xs text-slate-500">Prix direct ou craft manquant.</div>
                            </div>
                        </div>

                        <div v-if="calculationMode === 'manual'" class="border border-[#d1d1d1] bg-white p-2">
                            <div class="mb-2 flex items-center justify-between">
                                <strong class="text-xs uppercase text-[#0b3f88]">Ingrédients</strong>
                                <button type="button" class="text-xs font-bold text-[#0b3f88]" @click="toggleAllIngredients">
                                    {{ excludedCount ? 'Tout cocher' : 'Tout décocher' }}
                                </button>
                            </div>
                            <label v-for="row in manualRows" :key="row.ingredient.id" class="flex items-center gap-2 border-t border-slate-100 py-2 text-xs">
                                <input v-model="includedIngredients[row.ingredient.id]" type="checkbox" class="rounded border-gray-300" />
                                <img v-if="row.ingredient.image_url" :src="row.ingredient.image_url" :alt="row.ingredient.name" class="h-5 w-5 object-contain" />
                                <span class="min-w-0 flex-1 truncate" :class="!row.included ? 'text-slate-400 line-through' : ''">x{{ row.quantity }} {{ row.ingredient.name }}</span>
                                <strong :class="row.missing ? 'text-red-600' : 'text-slate-900'">
                                    {{ row.unitPrice ? `${formatNumber(row.total)} K` : 'Prix manquant' }}
                                </strong>
                            </label>
                        </div>

                        <div v-else-if="optimizedCalculation?.craftTree?.ingredients" class="border border-[#d1d1d1] bg-white p-2">
                            <strong class="text-xs uppercase text-[#0b3f88]">Décision automatique</strong>
                            <div v-for="node in optimizedCalculation.craftTree.ingredients" :key="node.id" class="mt-2 flex items-center justify-between border-t border-slate-100 pt-2 text-xs">
                                <span class="truncate">{{ node.quantity || node.pivot?.quantity || 1 }}x {{ node.name }}</span>
                                <strong>{{ node.cost ? `${formatNumber(node.cost)} K` : (node.price ? `${formatNumber(node.price)} K` : 'À calculer') }}</strong>
                            </div>
                        </div>
                    </div>

                    <p v-else class="p-3 text-xs text-slate-500">Pas de recette connue.</p>
                </section>
            </div>

            <aside class="min-w-0 space-y-3">
                <section class="border border-[#8b8b8b] bg-white shadow-inner">
                    <div class="border-b border-[#d1d1d1] bg-[#0b3f88] px-3 py-2 text-xs font-black uppercase tracking-wide text-white">Prix serveur</div>
                    <div class="space-y-3 p-3">
                        <div v-if="!isServerSelected" class="text-xs text-slate-500">Sélectionne un serveur pour gérer le prix.</div>
                        <template v-else>
                            <div class="border border-[#d1d1d1] bg-[#f8f8f0] p-3">
                                <div class="text-[11px] uppercase tracking-wide text-slate-500">Prix actuel</div>
                                <div v-if="currentPrice" class="text-2xl font-black text-[#0b3f88]">{{ formatNumber(currentPrice.price) }} K</div>
                                <div v-else class="text-sm font-bold text-slate-500">Aucun prix</div>
                            </div>

                            <form class="flex gap-2" @submit.prevent="savePrice">
                                <input v-model="priceInput" type="number" min="1" required class="min-w-0 flex-1 border-[#9c9c9c] text-sm" :placeholder="currentPrice ? 'Nouveau prix' : 'Prix'" />
                                <button type="submit" class="desk-button px-3" :disabled="priceSaving">{{ priceSaving ? '…' : (currentPrice ? 'Modifier' : 'Ajouter') }}</button>
                            </form>
                            <p v-if="priceMessage" class="text-xs text-slate-600">{{ priceMessage }}</p>

                            <div v-if="currentPrice" class="border-t border-[#d1d1d1] pt-3">
                                <textarea v-model="reportComment" rows="2" class="w-full border-[#9c9c9c] text-xs" placeholder="Signaler un prix incorrect…" />
                                <button type="button" class="desk-button mt-2 w-full py-2" :disabled="reportSaving || !reportComment.trim()" @click="reportPrice">
                                    {{ reportSaving ? 'Envoi…' : 'Signaler ce prix' }}
                                </button>
                                <p v-if="reportMessage" class="mt-1 text-xs text-slate-600">{{ reportMessage }}</p>
                            </div>
                        </template>
                    </div>
                </section>

                <section class="border border-[#8b8b8b] bg-white shadow-inner">
                    <div class="border-b border-[#d1d1d1] bg-[#0b3f88] px-3 py-2 text-xs font-black uppercase tracking-wide text-white">Prix récents</div>
                    <div class="max-h-48 overflow-auto p-2">
                        <p v-if="!item.prices?.length" class="p-2 text-xs text-slate-500">Aucun prix approuvé disponible.</p>
                        <div v-for="price in item.prices" :key="price.id" class="flex justify-between border-b border-slate-100 px-1 py-2 text-xs">
                            <span>{{ price.server?.name || 'Serveur' }}</span>
                            <strong>{{ formatNumber(price.price) }} K</strong>
                        </div>
                    </div>
                </section>

                <section v-if="item.recipe" class="border border-[#8b8b8b] bg-white shadow-inner">
                    <div class="flex items-center justify-between border-b border-[#d1d1d1] bg-[#0b3f88] px-3 py-2 text-white">
                        <span class="text-xs font-black uppercase tracking-wide">Ressources</span>
                        <input v-model.number="craftQuantity" type="number" min="1" class="w-20 border-white/40 bg-white text-xs text-slate-900" />
                    </div>
                    <div class="max-h-64 overflow-auto p-2">
                        <div v-for="row in resourceRows" :key="row.id" class="flex items-center gap-2 border-b border-slate-100 py-2 text-xs">
                            <img v-if="row.image_url" :src="row.image_url" :alt="row.name" class="h-5 w-5 object-contain" />
                            <span class="min-w-0 flex-1 truncate">x{{ row.quantity }} {{ row.name }}</span>
                            <strong>{{ row.total ? `${formatNumber(row.total)} K` : '—' }}</strong>
                        </div>
                    </div>
                    <div class="border-t border-[#d1d1d1] bg-[#f8f8f0] p-2 text-xs">
                        <div class="flex justify-between font-black">
                            <span>Total estimé</span>
                            <span>{{ formatNumber(resourcesTotal) }} K</span>
                        </div>
                        <button type="button" class="desk-button mt-2 w-full py-2" @click="copyResources">Copier la liste</button>
                    </div>
                </section>
            </aside>
        </div>
    </DesktopAppShell>
</template>

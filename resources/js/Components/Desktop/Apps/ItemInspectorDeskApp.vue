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
const copyMessage = ref('');
const resourcePriceInputs = ref({});
const resourcePriceSaving = ref({});
const resourcePriceMessage = ref({});
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
    const baseQuantity = Number(ingredient.pivot?.quantity || ingredient.quantity || 1);
    const quantity = baseQuantity * Number(craftQuantity.value || 1);
    const price = findPriceForServer(ingredient.prices || []);

    return {
        id: ingredient.id,
        ingredient,
        name: ingredient.name,
        image_url: ingredient.image_url,
        type: ingredient.type,
        level: ingredient.level,
        baseQuantity,
        quantity,
        unitPrice: price ? Number(price.price) : null,
        total: price ? Number(price.price) * quantity : null,
    };
}));

const resourcesTotal = computed(() => resourceRows.value.reduce((sum, row) => sum + (row.total || 0), 0));

const copyItemName = async (name) => {
    await navigator.clipboard?.writeText(name);
    copyMessage.value = `Nom copié : ${name}`;
};

const openIngredient = (ingredient) => {
    emit('open-app', 'itemInspector', {
        windowId: `item-${ingredient.id}`,
        title: ingredient.name,
        itemId: ingredient.id,
    });
};

const saveResourcePrice = async (ingredient) => {
    const value = resourcePriceInputs.value[ingredient.id];
    if (!ingredient?.id || !selectedServerId.value || !value) return;

    resourcePriceSaving.value[ingredient.id] = true;
    resourcePriceMessage.value[ingredient.id] = '';

    try {
        await axios.post('/prices', {
            item_id: ingredient.id,
            server_id: selectedServerId.value,
            price: value,
        });
        resourcePriceInputs.value[ingredient.id] = '';
        resourcePriceMessage.value[ingredient.id] = 'Prix OK';
        await loadItem();
    } catch {
        resourcePriceMessage.value[ingredient.id] = 'Erreur prix';
    } finally {
        resourcePriceSaving.value[ingredient.id] = false;
    }
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
    copyMessage.value = '';

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
        <div v-if="loading" class="p-3 text-center text-[11px] text-slate-500">Chargement…</div>
        <div v-else-if="!item" class="p-3 text-center text-[11px] text-slate-500">Sélectionne un item depuis la recherche.</div>

        <div v-else class="item-desk grid gap-2 xl:grid-cols-[minmax(0,1fr)_18.5rem]">
            <div class="min-w-0 space-y-2">
                <section class="desk-panel">
                    <div class="item-head">
                        <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-10 w-10 shrink-0 object-contain" />
                        <div v-else class="empty-icon">📦</div>

                        <div class="min-w-0 flex-1">
                            <div class="flex min-w-0 items-center gap-1">
                                <h3 class="min-w-0 flex-1 truncate text-[13px] font-black text-slate-950">{{ item.name }}</h3>
                                <button class="icon-btn" type="button" title="Copier le nom" @click="copyItemName(item.name)">⧉</button>
                                <button class="icon-btn" type="button" title="Ajouter au panier" @click="emit('open-app', 'craftCart', { seedItem: item })">🧺</button>
                                <button class="icon-btn" type="button" title="Comparer" @click="emit('open-app', 'compare', { seedItem: item })">⚖</button>
                                <button class="icon-btn" type="button" title="Surveiller" @click="emit('open-app', 'priceWatch', { seedItem: item })">⌁</button>
                            </div>
                            <p class="truncate text-[10px] text-slate-500">Niv. {{ item.level || '—' }} · {{ item.type || 'Type inconnu' }}</p>
                            <p v-if="copyMessage" class="mt-0.5 truncate text-[10px] font-bold text-[#0b3f88]">{{ copyMessage }}</p>
                            <p v-if="descriptionText" class="mt-1 line-clamp-3 whitespace-pre-line text-[11px] leading-snug text-slate-700">{{ descriptionText }}</p>
                        </div>
                    </div>
                </section>

                <section class="desk-panel">
                    <div class="panel-title">
                        <span>Recette & calcul</span>
                        <span v-if="item.recipe" class="font-normal normal-case tracking-normal text-white/80">{{ item.recipe.profession || 'Métier inconnu' }} · x{{ item.recipe.quantity_produced || 1 }}</span>
                    </div>

                    <div v-if="item.recipe" class="space-y-2 p-2">
                        <div class="segmented">
                            <button type="button" :class="calculationMode === 'optimized' ? 'active' : ''" @click="calculationMode = 'optimized'">Optimisé</button>
                            <button type="button" :class="calculationMode === 'manual' ? 'active' : ''" @click="calculationMode = 'manual'">Manuel</button>
                        </div>

                        <div v-if="!isServerSelected" class="compact-note warn">Sélectionne un serveur pour calculer les prix.</div>

                        <div class="grid grid-cols-2 gap-1.5">
                            <div class="metric-card">
                                <span>Coût craft</span>
                                <strong v-if="calculationMode === 'optimized' && optimizedLoading" class="text-slate-500">Calcul…</strong>
                                <strong v-else-if="bestCraftCost" class="text-emerald-700">{{ formatNumber(bestCraftCost) }} K</strong>
                                <strong v-else class="text-red-600">Prix incomplet</strong>
                            </div>
                            <div class="metric-card">
                                <span>Comparaison</span>
                                <strong v-if="craftVerdict" :class="craftVerdict.profitable ? 'text-emerald-700' : 'text-red-700'">{{ craftVerdict.label }}</strong>
                                <em v-if="craftVerdict">Écart {{ formatNumber(craftVerdict.diff) }} K</em>
                                <em v-else>Prix direct/craft manquant</em>
                            </div>
                        </div>

                        <div v-if="calculationMode === 'manual'" class="compact-list">
                            <div class="list-head">
                                <strong>Ingrédients</strong>
                                <button type="button" class="link-btn" @click="toggleAllIngredients">{{ excludedCount ? 'Tout cocher' : 'Tout décocher' }}</button>
                            </div>
                            <div v-for="row in manualRows" :key="row.ingredient.id" class="line-row">
                                <input v-model="includedIngredients[row.ingredient.id]" type="checkbox" class="compact-check" />
                                <img v-if="row.ingredient.image_url" :src="row.ingredient.image_url" :alt="row.ingredient.name" class="h-5 w-5 object-contain" />
                                <button type="button" class="min-w-0 flex-1 truncate text-left font-bold text-[#0b3f88] hover:underline" :class="!row.included ? 'text-slate-400 line-through' : ''" @click="openIngredient(row.ingredient)">
                                    x{{ row.quantity }} {{ row.ingredient.name }}
                                </button>
                                <strong class="shrink-0" :class="row.missing ? 'text-red-600' : 'text-slate-900'">{{ row.unitPrice ? `${formatNumber(row.total)} K` : '—' }}</strong>
                                <button type="button" class="icon-btn" title="Copier" @click="copyItemName(row.ingredient.name)">⧉</button>
                                <button type="button" class="icon-btn" title="Ouvrir" @click="openIngredient(row.ingredient)">↗</button>
                            </div>
                        </div>

                        <div v-else-if="optimizedCalculation?.craftTree?.ingredients" class="compact-list">
                            <div class="list-head"><strong>Décision automatique</strong></div>
                            <div v-for="node in optimizedCalculation.craftTree.ingredients" :key="node.id" class="line-row">
                                <button type="button" class="min-w-0 flex-1 truncate text-left font-bold text-[#0b3f88] hover:underline" @click="openIngredient(node)">{{ node.quantity || node.pivot?.quantity || 1 }}x {{ node.name }}</button>
                                <strong class="shrink-0">{{ node.cost ? `${formatNumber(node.cost)} K` : (node.price ? `${formatNumber(node.price)} K` : '—') }}</strong>
                                <button type="button" class="icon-btn" title="Copier" @click="copyItemName(node.name)">⧉</button>
                                <button type="button" class="icon-btn" title="Ouvrir" @click="openIngredient(node)">↗</button>
                            </div>
                        </div>
                    </div>

                    <p v-else class="p-2 text-[11px] text-slate-500">Pas de recette connue.</p>
                </section>
            </div>

            <aside class="min-w-0 space-y-2">
                <section class="desk-panel">
                    <div class="panel-title">Prix serveur</div>
                    <div class="space-y-1.5 p-2">
                        <div v-if="!isServerSelected" class="compact-note">Sélectionne un serveur.</div>
                        <template v-else>
                            <div class="price-strip">
                                <span>Actuel</span>
                                <strong v-if="currentPrice">{{ formatNumber(currentPrice.price) }} K</strong>
                                <strong v-else class="text-slate-500">Aucun</strong>
                            </div>

                            <form class="flex gap-1" @submit.prevent="savePrice">
                                <input v-model="priceInput" type="number" min="1" required class="compact-input flex-1" :placeholder="currentPrice ? 'Nouveau prix' : 'Prix'" />
                                <button type="submit" class="icon-btn wide" title="Enregistrer" :disabled="priceSaving">{{ priceSaving ? '…' : '✓' }}</button>
                            </form>
                            <p v-if="priceMessage" class="truncate text-[10px] text-slate-600">{{ priceMessage }}</p>

                            <div v-if="currentPrice" class="flex gap-1 border-t border-[#ddd8c2] pt-1.5">
                                <input v-model="reportComment" class="compact-input flex-1" placeholder="Signaler…" />
                                <button type="button" class="icon-btn wide" title="Signaler" :disabled="reportSaving || !reportComment.trim()" @click="reportPrice">⚠</button>
                            </div>
                            <p v-if="reportMessage" class="truncate text-[10px] text-slate-600">{{ reportMessage }}</p>
                        </template>
                    </div>
                </section>

                <section class="desk-panel">
                    <div class="panel-title">Prix récents</div>
                    <div class="max-h-32 overflow-auto p-1.5">
                        <p v-if="!item.prices?.length" class="p-1 text-[10px] text-slate-500">Aucun prix approuvé.</p>
                        <div v-for="price in item.prices" :key="price.id" class="line-row py-1">
                            <span class="min-w-0 flex-1 truncate">{{ price.server?.name || 'Serveur' }}</span>
                            <strong>{{ formatNumber(price.price) }} K</strong>
                        </div>
                    </div>
                </section>

                <section v-if="item.recipe" class="desk-panel">
                    <div class="panel-title with-input">
                        <span>Ressources</span>
                        <input v-model.number="craftQuantity" type="number" min="1" class="compact-input qty" />
                    </div>
                    <div class="max-h-72 overflow-auto p-1.5">
                        <article v-for="row in resourceRows" :key="row.id" class="resource-row">
                            <div class="flex min-w-0 items-center gap-1.5">
                                <img v-if="row.image_url" :src="row.image_url" :alt="row.name" class="h-5 w-5 object-contain" />
                                <button type="button" class="min-w-0 flex-1 truncate text-left font-bold text-[#0b3f88] hover:underline" @click="openIngredient(row.ingredient)">x{{ row.quantity }} {{ row.name }}</button>
                                <strong class="shrink-0">{{ row.total ? `${formatNumber(row.total)} K` : '—' }}</strong>
                                <button type="button" class="icon-btn" title="Copier" @click="copyItemName(row.name)">⧉</button>
                                <button type="button" class="icon-btn" title="Ouvrir" @click="openIngredient(row.ingredient)">↗</button>
                            </div>
                            <div class="mt-1 flex gap-1 pl-6">
                                <input v-model="resourcePriceInputs[row.id]" type="number" min="1" class="compact-input min-w-0 flex-1" :placeholder="row.unitPrice ? `${formatNumber(row.unitPrice)} K/u` : 'Prix unité'" />
                                <button type="button" class="icon-btn wide" title="Enregistrer prix" :disabled="resourcePriceSaving[row.id]" @click="saveResourcePrice(row.ingredient)">{{ resourcePriceSaving[row.id] ? '…' : '✓' }}</button>
                                <span v-if="resourcePriceMessage[row.id]" class="truncate py-0.5 text-[10px] font-bold text-[#0b3f88]">{{ resourcePriceMessage[row.id] }}</span>
                            </div>
                        </article>
                    </div>
                    <div class="total-strip">
                        <span>Total estimé</span>
                        <strong>{{ formatNumber(resourcesTotal) }} K</strong>
                    </div>
                </section>
            </aside>
        </div>
    </DesktopAppShell>
</template>

<style scoped>
.item-desk {
    color: #1f2933;
    font-size: 11px;
    line-height: 1.2;
}

.desk-panel {
    border: 1px solid #b8b29c;
    background: #fbfaf0;
    box-shadow: none;
}

.item-head {
    display: flex;
    gap: 8px;
    padding: 8px;
    background: #fbfaf0;
}

.empty-icon {
    display: grid;
    height: 40px;
    width: 40px;
    flex-shrink: 0;
    place-items: center;
    border: 1px solid #c8c1aa;
    background: #eee9d4;
    font-size: 18px;
}

.panel-title {
    display: flex;
    min-height: 20px;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    background: #0b4c95;
    padding: 3px 7px;
    color: white;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.panel-title.with-input {
    padding-right: 4px;
}

.icon-btn {
    display: inline-grid;
    min-width: 20px;
    height: 20px;
    place-items: center;
    border: 1px solid #8f8a78;
    background: linear-gradient(#ffffff, #dedac9);
    padding: 0 4px;
    color: #111827;
    font-size: 11px;
    font-weight: 800;
    line-height: 1;
}

.icon-btn:hover:not(:disabled) {
    background: #ffffff;
}

.icon-btn:disabled {
    opacity: .55;
}

.icon-btn.wide {
    min-width: 24px;
}

.segmented {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border: 1px solid #b8b29c;
    background: #e8e3cf;
    padding: 1px;
}

.segmented button {
    height: 20px;
    border: 0;
    background: transparent;
    font-size: 10px;
    font-weight: 800;
}

.segmented button.active {
    background: white;
    box-shadow: inset 0 0 0 1px #9f9987;
    color: #0b4c95;
}

.metric-card,
.price-strip,
.total-strip {
    border: 1px solid #ddd8c2;
    background: #fffef7;
    padding: 6px;
}

.metric-card span,
.price-strip span {
    display: block;
    color: #6b7280;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.metric-card strong {
    display: block;
    margin-top: 2px;
    font-size: 13px;
    font-weight: 900;
}

.metric-card em {
    display: block;
    margin-top: 1px;
    color: #64748b;
    font-size: 10px;
    font-style: normal;
}

.price-strip,
.total-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.price-strip strong,
.total-strip strong {
    font-size: 13px;
    font-weight: 900;
    color: #0b4c95;
}

.total-strip {
    border-width: 1px 0 0;
    background: #efead7;
    font-size: 10px;
    font-weight: 800;
}

.compact-input {
    height: 21px;
    border: 1px solid #b8b29c;
    background: white;
    padding: 1px 5px;
    font-size: 10px;
}

.compact-input.qty {
    width: 48px;
    height: 18px;
    border-color: #d5d0bb;
    color: #111827;
}

.compact-note {
    border: 1px solid #ddd8c2;
    background: #fffef7;
    padding: 5px;
    color: #64748b;
    font-size: 10px;
}

.compact-note.warn {
    border-color: #e6c35c;
    background: #fff8df;
    color: #8a5b00;
}

.compact-list {
    border: 1px solid #ddd8c2;
    background: white;
}

.list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e7e2ce;
    padding: 4px 6px;
    color: #0b4c95;
    font-size: 10px;
    text-transform: uppercase;
}

.link-btn {
    color: #0b4c95;
    font-size: 10px;
    font-weight: 800;
}

.line-row {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 5px;
    border-bottom: 1px solid #eee9d7;
    padding: 4px 5px;
    font-size: 10px;
}

.line-row:last-child,
.resource-row:last-child {
    border-bottom: 0;
}

.compact-check {
    height: 13px;
    width: 13px;
}

.resource-row {
    border-bottom: 1px solid #eee9d7;
    padding: 5px 4px;
    font-size: 10px;
}
</style>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import CommunityContributorBadge from '@/Components/CommunityContributorBadge.vue';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';
import DesktopPriceModeSelector from '@/Components/Desktop/DesktopPriceModeSelector.vue';
import PriceSourceTag from '@/Components/PriceSourceTag.vue';
import { useServerSelection } from '@/Composables/useServerSelection';

const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['open-app']);

const page = usePage();
const { selectedServerId, isServerSelected } = useServerSelection();

const loading = ref(false);
const item = ref(null);
const priceInput = ref('');
const priceSaving = ref(false);
const priceMessage = ref('');
const copiedKey = ref('');
const copyFeedbackText = ref('Copié');
let copyMessageTimer = null;
const isFavorite = ref(false);
const favoriteSaving = ref(false);
const resourcePriceInputs = ref({});
const resourcePriceSaving = ref({});
const resourcePriceMessage = ref({});
const resourcePriceOverrides = ref({});
const resourcePreferenceSaving = ref({});
const reportComment = ref('');
const reportSaving = ref(false);
const reportMessage = ref('');
const calculationMode = ref('optimized');
const includedIngredients = ref({});
const optimizedCalculation = ref(null);
const optimizedLoading = ref(false);
const craftQuantity = ref(1);
const itemPriceOverride = ref('');
const preferenceSaving = ref(false);

const formatNumber = (value) => new Intl.NumberFormat('fr-FR').format(Math.round(Number(value || 0)));
const findPriceForServer = (prices = []) => {
    if (!selectedServerId.value) return null;
    return prices.find((price) => Number(price.server_id || price.server?.id) === Number(selectedServerId.value)) || null;
};

const findPreferenceForServer = subject => findPriceForServer(subject?.price_preferences || []);
const findPersonalPriceForServer = subject => findPriceForServer(subject?.personal_prices || []);
const normalizeItemPreference = mode => mode === 'personal' ? 'personal' : '';
const getResourcePriceMode = subject => Object.prototype.hasOwnProperty.call(resourcePriceOverrides.value, subject?.id)
    ? resourcePriceOverrides.value[subject.id]
    : normalizeItemPreference(findPreferenceForServer(subject)?.mode);
const getEffectivePriceMode = subject => getResourcePriceMode(subject) === 'personal' ? 'personal' : 'community';
const resolveEffectivePriceForServer = subject => {
    const mode = getEffectivePriceMode(subject);
    const personal = findPersonalPriceForServer(subject);
    const community = findPriceForServer(subject?.prices || []);

    if (mode === 'personal' && personal) {
        return {
            price: personal,
            source: {
                type: 'personal',
                label: 'Perso',
                isFallback: false,
                contributor: null,
            },
        };
    }

    if (community) {
        return {
            price: community,
            source: {
                type: 'community',
                label: 'HDV',
                isFallback: mode === 'personal',
                contributor: community.user || null,
            },
        };
    }

    return { price: null, source: null };
};

const communityPrice = computed(() => findPriceForServer(item.value?.prices || []));
const personalPrice = computed(() => findPersonalPriceForServer(item.value));
const effectivePriceMode = computed(() => itemPriceOverride.value === 'personal' ? 'personal' : 'community');
const currentPrice = computed(() => {
    if (effectivePriceMode.value === 'personal') {
        return personalPrice.value || communityPrice.value;
    }

    return communityPrice.value;
});
const isUsingPersonalPrice = computed(() => effectivePriceMode.value === 'personal' && Boolean(personalPrice.value));
const directPrice = computed(() => currentPrice.value ? Number(currentPrice.value.price) : null);
const normalizedCraftQuantity = computed(() => Math.max(1, Number(craftQuantity.value || 1)));
const directPriceTotal = computed(() => directPrice.value ? directPrice.value * normalizedCraftQuantity.value : null);

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
    const resolution = resolveEffectivePriceForServer(ingredient);
    const price = resolution.price;
    const included = includedIngredients.value[ingredient.id] !== false;

    return {
        ingredient,
        quantity: quantity * normalizedCraftQuantity.value,
        included,
        unitPrice: price ? Number(price.price) : null,
        priceSource: resolution.source,
        total: price && included ? Number(price.price) * quantity * normalizedCraftQuantity.value : 0,
        missing: !price && included,
    };
}));

const manualTotal = computed(() => manualRows.value.reduce((sum, row) => sum + row.total, 0));
const manualMissingCount = computed(() => manualRows.value.filter(row => row.missing).length);
const manualCanCraft = computed(() => manualMissingCount.value === 0);
const excludedCount = computed(() => manualRows.value.filter(row => !row.included).length);

const bestCraftCost = computed(() => {
    if (calculationMode.value === 'optimized' && optimizedCalculation.value?.craftCost) {
        return Number(optimizedCalculation.value.craftCost) * normalizedCraftQuantity.value;
    }

    if (calculationMode.value === 'manual' && manualCanCraft.value) {
        return manualTotal.value;
    }

    return null;
});

const craftVerdict = computed(() => {
    if (!bestCraftCost.value || !directPriceTotal.value) return null;

    const diff = directPriceTotal.value - bestCraftCost.value;
    return {
        label: diff > 0 ? 'Craft rentable' : 'Achat direct meilleur',
        diff: Math.abs(diff),
        profitable: diff > 0,
    };
});

const resourceRows = computed(() => recipeIngredients.value.map((ingredient) => {
    const baseQuantity = Number(ingredient.pivot?.quantity || ingredient.quantity || 1);
    const quantity = baseQuantity * Number(craftQuantity.value || 1);
    const resolution = resolveEffectivePriceForServer(ingredient);
    const price = resolution.price;

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
        priceSource: resolution.source,
        total: price ? Number(price.price) * quantity : null,
    };
}));

const resourcesTotal = computed(() => resourceRows.value.reduce((sum, row) => sum + (row.total || 0), 0));
const missingResourcePriceCount = computed(() => resourceRows.value.filter(row => row.unitPrice === null).length);

const optimizedNodeUnitCost = (node) => Number(node.usedPrice ?? node.price ?? node.directPrice ?? node.craftCost ?? node.cost ?? 0);
const optimizedNodeQuantity = (node) => Number(node.quantity || node.pivot?.quantity || 1) * normalizedCraftQuantity.value;
const optimizedNodeTotal = (node) => {
    const unitCost = optimizedNodeUnitCost(node);
    return unitCost ? unitCost * optimizedNodeQuantity(node) : 0;
};
const optimizedNodeMethod = (node) => {
    if (node.usedMethod === 'craft') return 'craft';
    if (node.usedMethod === 'buy') return 'achat';
    return node.hasCraft ? 'craft possible' : 'achat';
};

const openResourceList = () => {
    if (!item.value?.recipe) return;

    emit('open-app', 'craftCart', {
        windowId: `resources-${item.value.id}`,
        title: `Ressources - ${item.value.name}`,
        sourceName: item.value.name,
        craftQuantity: normalizedCraftQuantity.value,
        resources: resourceRows.value,
    });
};

const copyWithSelectionFallback = (value) => {
    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    const copied = document.execCommand('copy');
    textarea.remove();

    return copied;
};

const copyItemName = async (name, key) => {
    copiedKey.value = key;
    copyFeedbackText.value = 'Copié';
    clearTimeout(copyMessageTimer);

    let copied = copyWithSelectionFallback(name);

    try {
        if (!copied && navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(name);
            copied = true;
        }

        if (!copied) copyFeedbackText.value = 'Copie impossible';
    } catch {
        copyFeedbackText.value = 'Copie impossible';
    }

    copyMessageTimer = setTimeout(() => {
        if (copiedKey.value === key) copiedKey.value = '';
    }, 4000);
};

const toggleFavorite = async () => {
    if (!item.value?.id || favoriteSaving.value) return;

    favoriteSaving.value = true;
    try {
        if (isFavorite.value) {
            await axios.delete(`/desktop/api/favorites/${item.value.id}`);
            isFavorite.value = false;
        } else {
            await axios.post(`/desktop/api/favorites/${item.value.id}`);
            isFavorite.value = true;
        }

        window.dispatchEvent(new CustomEvent('dofus:favorites-changed'));
    } finally {
        favoriteSaving.value = false;
    }
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
            price_mode: getEffectivePriceMode(ingredient),
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

const applyResourcePricePreference = (ingredient, preference) => {
    const preferences = (ingredient.price_preferences || [])
        .filter(entry => Number(entry.server_id) !== Number(selectedServerId.value));

    if (preference === 'personal') {
        preferences.push({
            server_id: Number(selectedServerId.value),
            mode: 'personal',
        });
    }

    ingredient.price_preferences = preferences;
};

const setResourcePriceMode = async (ingredient, mode) => {
    if (!ingredient?.id || !selectedServerId.value || resourcePreferenceSaving.value[ingredient.id]) return;

    const preference = normalizeItemPreference(mode);
    if (getResourcePriceMode(ingredient) === preference) return;

    resourcePriceOverrides.value = {
        ...resourcePriceOverrides.value,
        [ingredient.id]: preference,
    };
    resourcePreferenceSaving.value[ingredient.id] = true;
    resourcePriceMessage.value[ingredient.id] = '';

    try {
        await axios.put('/prices/item-preference', {
            item_id: ingredient.id,
            server_id: selectedServerId.value,
            price_mode: preference || null,
        });
        applyResourcePricePreference(ingredient, preference);
        const overrides = { ...resourcePriceOverrides.value };
        delete overrides[ingredient.id];
        resourcePriceOverrides.value = overrides;
        await loadOptimizedCalculation();
    } catch {
        const overrides = { ...resourcePriceOverrides.value };
        delete overrides[ingredient.id];
        resourcePriceOverrides.value = overrides;
        resourcePriceMessage.value[ingredient.id] = 'Mode non enregistré';
    } finally {
        resourcePreferenceSaving.value[ingredient.id] = false;
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
    copiedKey.value = '';
    resourcePriceOverrides.value = {};

    try {
        const { data } = await axios.get(`/desktop/api/items/${props.payload.itemId}`);
        item.value = data.item;
        isFavorite.value = Boolean(data.item.is_favorite);
        itemPriceOverride.value = normalizeItemPreference(findPreferenceForServer(item.value)?.mode);
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
            price_mode: effectivePriceMode.value,
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
    if (!communityPrice.value?.id || !reportComment.value.trim()) return;

    reportSaving.value = true;
    reportMessage.value = '';

    try {
        await axios.post(`/prices/${communityPrice.value.id}/report`, {
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

const saveItemPriceMode = async () => {
    if (!item.value?.id || !selectedServerId.value || preferenceSaving.value) return;

    preferenceSaving.value = true;
    try {
        await axios.put('/prices/item-preference', {
            item_id: item.value.id,
            server_id: selectedServerId.value,
            price_mode: itemPriceOverride.value || null,
        });
        await loadItem();
    } catch {
        itemPriceOverride.value = normalizeItemPreference(findPreferenceForServer(item.value)?.mode);
        priceMessage.value = 'Mode non enregistré.';
    } finally {
        preferenceSaving.value = false;
    }
};

const setItemPriceMode = mode => {
    const preference = normalizeItemPreference(mode);
    if (itemPriceOverride.value === preference || preferenceSaving.value) return;

    itemPriceOverride.value = preference;
    saveItemPriceMode();
};

watch(() => props.payload.itemId, loadItem);
watch(selectedServerId, () => {
    itemPriceOverride.value = '';
    optimizedCalculation.value = null;
});
watch(() => page.props.selected_server_id, loadItem);
watch(calculationMode, () => loadOptimizedCalculation());
watch(item, initializeIncludedIngredients);
onMounted(loadItem);
onUnmounted(() => clearTimeout(copyMessageTimer));
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
                                <button
                                    class="icon-btn favorite-btn"
                                    :class="isFavorite ? 'favorite-btn--active' : ''"
                                    type="button"
                                    :title="isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
                                    :aria-label="isFavorite ? `Retirer ${item.name} des favoris` : `Ajouter ${item.name} aux favoris`"
                                    :disabled="favoriteSaving"
                                    @click="toggleFavorite"
                                >
                                    {{ isFavorite ? '★' : '☆' }}
                                </button>
                                <span class="copy-action">
                                    <button class="icon-btn" type="button" title="Copier le nom" @click="copyItemName(item.name, `item-${item.id}`)">⧉</button>
                                    <Transition name="copy-pop">
                                        <span v-if="copiedKey === `item-${item.id}`" class="copy-pop">{{ copyFeedbackText }}</span>
                                    </Transition>
                                </span>
                                <button v-if="item.recipe" class="icon-btn" type="button" title="Liste ressources" @click="openResourceList">🧺</button>
                                <button class="icon-btn" type="button" title="Surveiller" @click="emit('open-app', 'priceWatch', { seedItem: item })">⌁</button>
                            </div>
                            <p class="truncate text-[10px] text-slate-500">Niv. {{ item.level || '—' }} · {{ item.type || 'Type inconnu' }}</p>
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

                        <div class="craft-qty-strip">
                            <label>Nombre de crafts</label>
                            <input v-model.number="craftQuantity" type="number" min="1" class="compact-input qty" />
                            <button type="button" class="icon-btn wide" title="Ouvrir la liste des ressources" @click="openResourceList">🧺</button>
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
                                <PriceSourceTag v-if="row.unitPrice" :source="row.priceSource" />
                                <strong class="shrink-0" :class="row.missing ? 'text-red-600' : 'text-slate-900'">{{ row.unitPrice ? `${formatNumber(row.total)} K` : '—' }}</strong>
                                <span class="copy-action">
                                    <button type="button" class="icon-btn" title="Copier" @click="copyItemName(row.ingredient.name, `manual-${row.ingredient.id}`)">⧉</button>
                                    <Transition name="copy-pop">
                                        <span v-if="copiedKey === `manual-${row.ingredient.id}`" class="copy-pop">{{ copyFeedbackText }}</span>
                                    </Transition>
                                </span>
                                <button type="button" class="icon-btn" title="Ouvrir" @click="openIngredient(row.ingredient)">↗</button>
                            </div>
                        </div>

                        <div v-else-if="optimizedCalculation?.craftTree?.ingredients" class="compact-list">
                            <div class="list-head"><strong>Décision automatique</strong></div>
                            <div v-for="node in optimizedCalculation.craftTree.ingredients" :key="node.id" class="line-row">
                                <button type="button" class="min-w-0 flex-1 truncate text-left font-bold text-[#0b3f88] hover:underline" @click="openIngredient(node)">x{{ optimizedNodeQuantity(node) }} {{ node.name }}</button>
                                <span class="shrink-0 text-[9px] uppercase text-slate-500">{{ optimizedNodeMethod(node) }}</span>
                                <PriceSourceTag v-if="node.usedMethod === 'buy'" :source="node.usedPriceSource" />
                                <strong class="shrink-0">{{ optimizedNodeTotal(node) ? `${formatNumber(optimizedNodeTotal(node))} K` : '—' }}</strong>
                                <span class="copy-action">
                                    <button type="button" class="icon-btn" title="Copier" @click="copyItemName(node.name, `optimized-${node.id || node.name}`)">⧉</button>
                                    <Transition name="copy-pop">
                                        <span v-if="copiedKey === `optimized-${node.id || node.name}`" class="copy-pop">{{ copyFeedbackText }}</span>
                                    </Transition>
                                </span>
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
                            <div class="block border border-[#c8c1aa] bg-[#eee9d4] p-1.5 text-[10px] text-slate-700">
                                <span class="mb-1 flex items-center justify-between gap-2">
                                    <strong>Source pour cet objet</strong>
                                    <span
                                        class="rounded-full px-1.5 py-0.5 font-bold"
                                        :class="effectivePriceMode === 'personal' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'"
                                    >
                                        {{ effectivePriceMode === 'personal' ? 'Prix perso' : 'Prix HDV' }}
                                    </span>
                                </span>
                                <DesktopPriceModeSelector
                                    :model-value="itemPriceOverride"
                                    :disabled="preferenceSaving"
                                    :show-label="false"
                                    @select="setItemPriceMode"
                                />
                            </div>

                            <div class="price-strip">
                                <span>{{ isUsingPersonalPrice ? 'Prix perso' : 'Prix HDV' }}</span>
                                <strong v-if="currentPrice">{{ formatNumber(currentPrice.price) }} K</strong>
                                <strong v-else class="text-slate-500">Aucun</strong>
                            </div>
                            <p v-if="effectivePriceMode === 'personal' && !personalPrice && communityPrice" class="text-[10px] text-slate-500">
                                Aucun prix perso : le prix HDV est utilisé en repli.
                            </p>
                            <div v-if="!isUsingPersonalPrice && communityPrice" class="flex flex-wrap items-center gap-1 border-t border-[#ddd8c2] pt-1.5 text-[9px] text-slate-600">
                                <CommunityContributorBadge
                                    v-if="communityPrice.user"
                                    :name="communityPrice.user.name"
                                    :contribution-count="communityPrice.user.price_contributions_count"
                                    compact
                                />
                            </div>

                            <form class="flex gap-1" @submit.prevent="savePrice">
                                <input v-model="priceInput" type="number" min="1" required class="compact-input flex-1" :placeholder="effectivePriceMode === 'personal' ? 'Prix perso' : 'Prix HDV'" />
                                <button type="submit" class="icon-btn wide" title="Enregistrer" :disabled="priceSaving">{{ priceSaving ? '…' : '✓' }}</button>
                            </form>
                            <p v-if="priceMessage" class="truncate text-[10px] text-slate-600">{{ priceMessage }}</p>

                            <div v-if="communityPrice && !isUsingPersonalPrice" class="flex gap-1 border-t border-[#ddd8c2] pt-1.5">
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
                        <span>Ressources pour {{ normalizedCraftQuantity }} craft(s)</span>
                        <button type="button" class="icon-btn wide" title="Ouvrir en liste" @click="openResourceList">🧺</button>
                    </div>
                    <div class="max-h-72 overflow-auto p-1.5">
                        <article v-for="row in resourceRows" :key="row.id" class="resource-row">
                            <div class="flex min-w-0 items-center gap-1.5">
                                <img v-if="row.image_url" :src="row.image_url" :alt="row.name" class="h-5 w-5 object-contain" />
                                <button type="button" class="min-w-0 flex-1 truncate text-left font-bold text-[#0b3f88] hover:underline" @click="openIngredient(row.ingredient)">x{{ row.quantity }} {{ row.name }}</button>
                                <PriceSourceTag v-if="row.unitPrice" :source="row.priceSource" />
                                <strong class="shrink-0">{{ row.total ? `${formatNumber(row.total)} K` : '—' }}</strong>
                                <span class="copy-action">
                                    <button type="button" class="icon-btn" title="Copier" @click="copyItemName(row.name, `resource-${row.id}`)">⧉</button>
                                    <Transition name="copy-pop">
                                        <span v-if="copiedKey === `resource-${row.id}`" class="copy-pop">{{ copyFeedbackText }}</span>
                                    </Transition>
                                </span>
                                <button type="button" class="icon-btn" title="Ouvrir" @click="openIngredient(row.ingredient)">↗</button>
                            </div>
                            <div class="mt-1 flex items-end gap-1 pl-6">
                                <DesktopPriceModeSelector
                                    :model-value="getResourcePriceMode(row.ingredient)"
                                    :disabled="resourcePreferenceSaving[row.id]"
                                    @select="setResourcePriceMode(row.ingredient, $event)"
                                />
                                <input v-model="resourcePriceInputs[row.id]" type="number" min="1" class="compact-input min-w-0 flex-1" :placeholder="row.unitPrice ? `${formatNumber(row.unitPrice)} K/u` : 'Prix unité'" />
                                <button type="button" class="icon-btn wide" title="Enregistrer prix" :disabled="resourcePriceSaving[row.id]" @click="saveResourcePrice(row.ingredient)">{{ resourcePriceSaving[row.id] ? '…' : '✓' }}</button>
                                <span v-if="resourcePriceMessage[row.id]" class="truncate py-0.5 text-[10px] font-bold text-[#0b3f88]">{{ resourcePriceMessage[row.id] }}</span>
                            </div>
                        </article>
                    </div>
                    <div class="total-strip">
                        <span>Total estimé</span>
                        <strong>{{ formatNumber(resourcesTotal) }} K <em v-if="missingResourcePriceCount">({{ missingResourcePriceCount }} prix manquant(s))</em></strong>
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

.favorite-btn {
    font-size: 14px;
}

.favorite-btn--active {
    border-color: #b7791f;
    background: linear-gradient(#fff8cf, #f5d56a);
    color: #8a4b00;
}

.copy-action {
    position: relative;
    display: inline-grid;
    flex-shrink: 0;
}

.copy-pop {
    position: absolute;
    top: 50%;
    right: calc(100% + 5px);
    z-index: 30;
    transform: translateY(-50%);
    border: 1px solid #07366b;
    background: #0b4c95;
    padding: 2px 5px;
    color: white;
    font-size: 9px;
    font-weight: 900;
    line-height: 1.2;
    pointer-events: none;
    white-space: nowrap;
    box-shadow: 1px 1px 0 rgb(0 0 0 / 20%);
}

.copy-pop::after {
    position: absolute;
    top: 50%;
    left: 100%;
    width: 0;
    height: 0;
    transform: translateY(-50%);
    border-top: 3px solid transparent;
    border-bottom: 3px solid transparent;
    border-left: 4px solid #0b4c95;
    content: '';
}

.copy-pop-enter-active {
    animation: copy-pop-in .18s ease-out;
}

.copy-pop-leave-active {
    transition: opacity .14s ease;
}

.copy-pop-leave-to {
    opacity: 0;
}

@keyframes copy-pop-in {
    from {
        opacity: 0;
        transform: translate(4px, -50%) scale(.85);
    }

    to {
        opacity: 1;
        transform: translate(0, -50%) scale(1);
    }
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

.craft-qty-strip {
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #ddd8c2;
    background: #fffef7;
    padding: 5px;
    font-size: 10px;
    font-weight: 800;
}

.craft-qty-strip label {
    flex: 1;
    color: #334155;
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

.total-strip em {
    color: #b91c1c;
    font-size: 10px;
    font-style: normal;
    font-weight: 700;
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

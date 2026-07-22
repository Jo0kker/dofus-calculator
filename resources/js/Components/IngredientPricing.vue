<template>
    <div v-if="!isServerSelected" class="text-xs text-gray-500 italic">
        Sélectionnez un serveur pour voir ou modifier le prix
    </div>

    <div v-else class="space-y-2">
        <div
            class="rounded-lg border border-l-[3px] bg-white shadow-sm"
            :class="effectivePriceMode === 'personal' ? 'border-l-violet-500' : 'border-l-blue-500'"
        >
            <div class="flex flex-wrap items-center gap-x-5 gap-y-3 px-4 py-3">
                <div v-if="currentPrice" class="min-w-[9rem] shrink-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <strong class="whitespace-nowrap text-xl leading-none" :class="isUsingPersonalPrice ? 'text-violet-700' : 'text-blue-600'">
                            {{ formatNumber(currentPrice.price) }} K
                        </strong>
                        <span
                            v-if="effectivePriceMode === 'personal' && !personalPrice"
                            class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[9px] text-gray-500"
                        >
                            repli HDV
                        </span>
                    </div>
                    <div class="mt-1 text-[11px] text-gray-600">
                        {{ isUsingPersonalPrice ? 'Prix personnel' : 'Prix HDV' }}
                        <span class="mx-1 text-gray-300">·</span>
                        Relevé {{ formatDate(currentPriceObservedAt) }}
                    </div>
                </div>

                <div v-else class="min-w-[12rem] flex-1 text-xs text-gray-500">
                    {{ effectivePriceMode === 'personal' ? 'Aucun prix personnel ni communautaire.' : 'Aucun prix communautaire.' }}
                </div>

                <div
                    v-if="!isUsingPersonalPrice && communityPrice"
                    class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2.5 gap-y-1.5 text-[10px] text-gray-600"
                >
                    <CommunityContributorBadge
                        v-if="communityPrice.user"
                        :name="communityPrice.user.name"
                        :contribution-count="communityPrice.user.price_contributions_count"
                    />
                </div>

                <div v-if="$page.props.auth?.user" class="ml-auto flex items-end gap-2">
                    <button
                        v-if="!isUsingPersonalPrice && communityPrice"
                        type="button"
                        class="h-9 rounded-md px-2 text-[10px] text-gray-400 transition-colors hover:bg-red-50 hover:text-red-700"
                        title="Signaler ce prix comme incorrect"
                        @click="showReportModal = true"
                    >
                        Signaler
                    </button>

                    <PriceModeSelector
                        :model-value="itemPriceOverride"
                        :disabled="preferenceSaving"
                        @select="setItemPriceMode"
                    />
                </div>
            </div>

            <form v-if="$page.props.auth?.user" class="flex items-center gap-2 border-t border-gray-100 bg-gray-50/70 px-3 py-2" @submit.prevent="submitPrice">
                <span class="sr-only">
                    <template v-if="effectivePriceMode === 'personal'">
                        Ce prix reste privé et sera utilisé dans vos calculs.
                    </template>
                    <template v-else>
                        Ce prix sera partagé avec la communauté et comptera comme une contribution.
                    </template>
                </span>
                <input
                    v-model="quickPriceForm.price"
                    type="number"
                    :placeholder="effectivePriceMode === 'personal' ? 'Votre prix…' : 'Nouveau prix HDV…'"
                    class="h-9 min-w-0 flex-1 rounded-md border-gray-300 bg-white py-0 text-xs"
                    min="1"
                    required
                >

                <button
                    type="submit"
                    class="h-9 rounded-md px-4 text-xs font-medium text-white transition"
                    :class="effectivePriceMode === 'personal' ? 'bg-violet-600 hover:bg-violet-700' : 'bg-blue-600 hover:bg-blue-700'"
                    :disabled="quickPriceForm.processing"
                >
                    {{ quickPriceForm.processing ? '…' : 'Enregistrer' }}
                </button>
            </form>
        </div>

        <PriceReportModal
            :show="showReportModal"
            :item-price="currentPriceWithItem"
            @close="showReportModal = false"
            @reported="onPriceReported"
        />

        <div v-if="!$page.props.auth?.user && !currentPrice" class="text-xs italic text-gray-500">
            <Link :href="route('login')" class="text-blue-600 hover:underline">Connectez-vous</Link> pour ajouter un prix
        </div>

        <div v-if="ingredient.recipe" class="flex items-center text-xs text-green-600">
            <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" />
            </svg>
            Peut être crafté
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useServerSelection } from '@/Composables/useServerSelection';
import CommunityContributorBadge from './CommunityContributorBadge.vue';
import PriceModeSelector from './PriceModeSelector.vue';
import PriceReportModal from './PriceReportModal.vue';

const props = defineProps({
    ingredient: Object,
});

const emit = defineEmits(['price-updated']);
const { selectedServerId, isServerSelected } = useServerSelection();
const showReportModal = ref(false);
const itemPriceOverride = ref('');
const preferenceSaving = ref(false);
const normalizeItemPreference = mode => mode === 'personal' ? 'personal' : '';

const quickPriceForm = useForm({
    item_id: props.ingredient.id,
    server_id: '',
    price: '',
    price_mode: 'community',
});

const communityPrice = computed(() => {
    if (!selectedServerId.value || !props.ingredient.prices) return null;
    return props.ingredient.prices.find(price => (price.server_id ?? price.server?.id) == selectedServerId.value) || null;
});

const personalPrice = computed(() => {
    if (!selectedServerId.value || !props.ingredient.personal_prices) return null;
    return props.ingredient.personal_prices.find(price => price.server_id == selectedServerId.value) || null;
});

const storedItemPreference = computed(() => {
    if (!selectedServerId.value || !props.ingredient.price_preferences) return null;
    return props.ingredient.price_preferences.find(preference => preference.server_id == selectedServerId.value) || null;
});

const effectivePriceMode = computed(() => itemPriceOverride.value === 'personal' ? 'personal' : 'community');

const currentPrice = computed(() => {
    if (effectivePriceMode.value === 'personal') {
        return personalPrice.value || communityPrice.value;
    }
    return communityPrice.value;
});

const isUsingPersonalPrice = computed(() => effectivePriceMode.value === 'personal' && Boolean(personalPrice.value));
const currentPriceObservedAt = computed(() => isUsingPersonalPrice.value
    ? personalPrice.value?.updated_at
    : communityPrice.value?.updated_at);

const currentPriceWithItem = computed(() => {
    if (!communityPrice.value) return null;
    return { ...communityPrice.value, item: props.ingredient };
});

watch(selectedServerId, newServerId => {
    quickPriceForm.server_id = newServerId;
    itemPriceOverride.value = normalizeItemPreference(storedItemPreference.value?.mode);
}, { immediate: true });

watch(effectivePriceMode, mode => {
    quickPriceForm.price_mode = mode;
});

watch(storedItemPreference, preference => {
    itemPriceOverride.value = normalizeItemPreference(preference?.mode);
});

const saveItemPriceMode = previousPreference => {
    preferenceSaving.value = true;

    const restorePreviousPreference = () => {
        itemPriceOverride.value = previousPreference;
    };

    router.put(route('prices.item-preference'), {
        item_id: props.ingredient.id,
        server_id: selectedServerId.value,
        price_mode: itemPriceOverride.value || null,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: restorePreviousPreference,
        onException: restorePreviousPreference,
        onFinish: () => {
            preferenceSaving.value = false;
        },
    });
};

const setItemPriceMode = mode => {
    const preference = normalizeItemPreference(mode);
    if (itemPriceOverride.value === preference || preferenceSaving.value) return;

    const previousPreference = itemPriceOverride.value;
    itemPriceOverride.value = preference;
    saveItemPriceMode(previousPreference);
};

const submitPrice = () => {
    quickPriceForm.post(route('prices.store'), {
        preserveScroll: true,
        onSuccess: () => {
            quickPriceForm.reset('price');
            emit('price-updated');
        },
    });
};

const formatNumber = num => new Intl.NumberFormat('fr-FR').format(num);

const formatDate = date => {
    if (!date) return 'récemment';

    const value = new Date(date);
    const days = Math.floor((new Date() - value) / (1000 * 60 * 60 * 24));

    if (days === 0) return "aujourd'hui";
    if (days === 1) return 'hier';
    if (days < 7) return `il y a ${days} jours`;
    if (days < 30) return `il y a ${Math.floor(days / 7)} semaines`;
    return `il y a ${Math.floor(days / 30)} mois`;
};

const onPriceReported = () => {
    router.reload({ preserveScroll: true });
};
</script>

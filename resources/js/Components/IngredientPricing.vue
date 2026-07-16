<template>
    <div v-if="!isServerSelected" class="text-xs text-gray-500 italic">
        Sélectionnez un serveur pour voir ou modifier le prix
    </div>

    <div v-else class="space-y-3">
        <div v-if="$page.props.auth?.user" class="space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-2.5">
            <div class="flex items-center justify-between gap-3 text-xs text-gray-500">
                <span>Source globale actuelle</span>
                <span
                    class="rounded-full px-2 py-1 font-medium"
                    :class="globalPriceMode === 'personal' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'"
                >
                    {{ globalPriceMode === 'personal' ? 'Prix perso' : 'Prix HDV' }}
                </span>
            </div>

            <label class="flex items-center justify-between gap-3 text-xs text-gray-600">
                <span>Pour cet objet</span>
                <select
                    v-model="itemPriceOverride"
                    class="rounded-md border-gray-300 py-1 pl-2 pr-8 text-xs focus:border-blue-500 focus:ring-blue-500"
                    @change="saveItemPriceMode"
                >
                    <option value="">Suivre le mode global</option>
                    <option value="community">Toujours utiliser le prix HDV</option>
                    <option value="personal">Toujours utiliser le prix perso</option>
                </select>
            </label>
        </div>

        <div v-if="currentPrice" class="rounded-lg border bg-white px-3 py-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-700">
                            {{ isUsingPersonalPrice ? 'Votre prix personnel' : 'Prix HDV communautaire' }}
                        </span>
                        <span
                            v-if="effectivePriceMode === 'personal' && !personalPrice"
                            class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-600"
                        >
                            valeur de repli
                        </span>
                    </div>
                    <div class="text-lg font-bold" :class="isUsingPersonalPrice ? 'text-violet-700' : 'text-blue-600'">
                        {{ formatNumber(currentPrice.price) }} K
                    </div>
                    <div class="text-xs text-gray-500">
                        Mis à jour {{ formatDate(currentPrice.updated_at) }}
                    </div>
                </div>

                <button
                    v-if="$page.props.auth?.user && !isUsingPersonalPrice && communityPrice"
                    type="button"
                    class="rounded px-2 py-1 text-xs text-red-600 transition-colors hover:bg-red-50 hover:text-red-800"
                    title="Signaler ce prix comme incorrect"
                    @click="showReportModal = true"
                >
                    🚨 Signaler
                </button>
            </div>

            <PriceConfidenceBadge
                v-if="!isUsingPersonalPrice && communityPrice"
                :price="communityPrice"
                class="mt-3"
            />

            <div
                v-if="!isUsingPersonalPrice && communityPrice?.user"
                class="mt-3 space-y-1.5 rounded-md bg-blue-50 px-2.5 py-2 text-xs text-blue-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        Dernier relevé par <strong>{{ communityPrice.user.name }}</strong>
                    </span>
                    <span class="rounded-full bg-white px-2 py-1 font-semibold text-blue-700">
                        {{ formatContributionCount(communityPrice.user.price_contributions_count) }}
                    </span>
                </div>
            </div>
        </div>

        <div v-else class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-500">
            {{ effectivePriceMode === 'personal' ? 'Aucun prix personnel ni communautaire disponible.' : 'Aucun prix communautaire disponible.' }}
        </div>

        <PriceReportModal
            :show="showReportModal"
            :item-price="currentPriceWithItem"
            @close="showReportModal = false"
            @reported="onPriceReported"
        />

        <form v-if="$page.props.auth?.user" class="space-y-2" @submit.prevent="submitPrice">
            <p class="text-xs text-gray-500">
                <template v-if="effectivePriceMode === 'personal'">
                    Ce prix reste privé et sera utilisé dans vos calculs.
                </template>
                <template v-else>
                    Ce prix sera partagé avec la communauté et comptera comme une contribution.
                </template>
            </p>
            <div class="flex items-center space-x-2">
                <input
                    v-model="quickPriceForm.price"
                    type="number"
                    :placeholder="effectivePriceMode === 'personal' ? 'Votre prix…' : 'Prix HDV…'"
                    class="flex-1 rounded border-gray-300 text-sm"
                    min="1"
                    required
                >

                <button
                    type="submit"
                    class="rounded px-4 py-2 text-sm text-white transition"
                    :class="effectivePriceMode === 'personal' ? 'bg-violet-600 hover:bg-violet-700' : 'bg-blue-600 hover:bg-blue-700'"
                    :disabled="quickPriceForm.processing"
                >
                    {{ quickPriceForm.processing ? '…' : 'Enregistrer' }}
                </button>
            </div>
        </form>

        <div v-else-if="!currentPrice" class="text-xs italic text-gray-500">
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
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useServerSelection } from '@/Composables/useServerSelection';
import PriceConfidenceBadge from './PriceConfidenceBadge.vue';
import PriceReportModal from './PriceReportModal.vue';

const props = defineProps({
    ingredient: Object,
});

const emit = defineEmits(['price-updated']);
const page = usePage();
const { selectedServerId, isServerSelected } = useServerSelection();
const showReportModal = ref(false);
const globalPriceMode = ref(page.props.auth?.user?.price_mode || 'community');
const itemPriceOverride = ref('');

const quickPriceForm = useForm({
    item_id: props.ingredient.id,
    server_id: '',
    price: '',
    price_mode: globalPriceMode.value,
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

const effectivePriceMode = computed(() => itemPriceOverride.value || globalPriceMode.value);

const currentPrice = computed(() => {
    if (effectivePriceMode.value === 'personal') {
        return personalPrice.value || communityPrice.value;
    }
    return communityPrice.value;
});

const isUsingPersonalPrice = computed(() => effectivePriceMode.value === 'personal' && Boolean(personalPrice.value));

const currentPriceWithItem = computed(() => {
    if (!communityPrice.value) return null;
    return { ...communityPrice.value, item: props.ingredient };
});

watch(selectedServerId, newServerId => {
    quickPriceForm.server_id = newServerId;
    itemPriceOverride.value = storedItemPreference.value?.mode || '';
}, { immediate: true });

watch(effectivePriceMode, mode => {
    quickPriceForm.price_mode = mode;
});

watch(() => page.props.auth?.user?.price_mode, mode => {
    if (mode) globalPriceMode.value = mode;
});

watch(storedItemPreference, preference => {
    itemPriceOverride.value = preference?.mode || '';
});

const saveItemPriceMode = () => {
    router.put(route('prices.item-preference'), {
        item_id: props.ingredient.id,
        server_id: selectedServerId.value,
        price_mode: itemPriceOverride.value || null,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
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

const formatContributionCount = count => {
    const total = Number(count || 0);
    return `${formatNumber(total)} contribution${total > 1 ? 's' : ''}`;
};

const formatDate = date => {
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

<script setup>
import { computed } from 'vue';

const props = defineProps({
    price: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const details = computed(() => props.price.confidence_details || {});
const level = computed(() => props.price.confidence_level || 'low');
const contributors = computed(() => Number(props.price.recent_contributors_count || 0));
const observations = computed(() => Number(props.price.recent_observations_count || 0));
const isPending = computed(() => !props.price.confidence_computed_at);

const levelConfig = computed(() => isPending.value ? {
    label: 'Confiance en cours de calcul',
    badge: 'border-gray-300 bg-gray-50 text-gray-700',
} : ({
    low: {
        label: 'Confiance faible',
        badge: 'border-amber-300 bg-amber-50 text-amber-800',
    },
    medium: {
        label: 'Confiance moyenne',
        badge: 'border-blue-300 bg-blue-50 text-blue-800',
    },
    high: {
        label: 'Confiance élevée',
        badge: 'border-emerald-300 bg-emerald-50 text-emerald-800',
    },
}[level.value]));

const reasonLabels = {
    single_contributor: 'Une seule source disponible.',
    few_independent_contributors: 'Encore peu de contributeurs indépendants.',
    learning_contributors: 'La fiabilité des contributeurs est encore en apprentissage.',
    high_dispersion: 'Les relevés récents sont assez dispersés.',
    stale_observations: 'Les derniers relevés commencent à dater.',
    stable_consensus: 'Les relevés récents forment un consensus stable.',
    no_valid_observation: 'Aucun relevé valide disponible.',
};

const reasons = computed(() => (details.value.reason_codes || [])
    .map(reason => reasonLabels[reason])
    .filter(Boolean));

</script>

<template>
    <details class="group" :class="compact ? 'text-[10px]' : 'text-xs'">
        <summary
            class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md border px-2 py-1.5 font-semibold marker:hidden"
            :class="levelConfig.badge"
        >
            <span>{{ levelConfig.label }}</span>
            <span v-if="!isPending" class="font-normal opacity-80">
                {{ contributors }} contributeur{{ contributors !== 1 ? 's' : '' }} récent{{ contributors !== 1 ? 's' : '' }}
            </span>
        </summary>

        <div class="mt-1.5 space-y-1 rounded-md border border-gray-200 bg-white p-2 text-gray-600 shadow-sm">
            <div v-if="!isPending" class="flex justify-between gap-3">
                <span>Relevés récents</span>
                <strong class="text-gray-800">{{ observations }} relevé{{ observations !== 1 ? 's' : '' }}</strong>
            </div>
            <ul v-if="reasons.length" class="list-disc space-y-0.5 pl-4 text-gray-500">
                <li v-for="reason in reasons" :key="reason">{{ reason }}</li>
            </ul>
            <p v-if="isPending" class="text-gray-500">
                Les relevés historiques seront analysés lors du prochain recalcul.
            </p>
            <p class="border-t border-gray-100 pt-1 text-gray-400">
                La confiance concerne ce prix communautaire, pas la valeur d’une personne.
            </p>
        </div>
    </details>
</template>

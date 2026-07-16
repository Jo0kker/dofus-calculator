<script setup>
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const samples = computed(() => Number(props.user.price_reliability_samples || 0));
const score = computed(() => Number(props.user.price_reliability_score || 60));
const isLearning = computed(() => samples.value < 3);
const evaluationLabel = computed(() => `évaluation${samples.value !== 1 ? 's' : ''}`);
const label = computed(() => isLearning.value
    ? `Fiabilité en apprentissage · ${samples.value}/3 ${evaluationLabel.value}`
    : `Fiabilité estimée ${score.value} % · ${samples.value} ${evaluationLabel.value}`);
</script>

<template>
    <span
        class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold"
        :class="isLearning
            ? 'border-gray-200 bg-gray-50 text-gray-600'
            : 'border-violet-200 bg-violet-50 text-violet-700'"
        title="Estimation de la probabilité que le prochain relevé soit cohérent. Ce n’est pas une note morale ni un statut de compte."
    >
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { Store, UserRound } from '@lucide/vue';

const props = defineProps({
    source: {
        type: Object,
        default: null,
    },
});

const isPersonal = computed(() => props.source?.type === 'personal');
const contributorName = computed(() => props.source?.contributor?.name || '');
const label = computed(() => {
    if (isPersonal.value) return 'Perso';

    const prefix = props.source?.isFallback ? 'HDV (repli)' : 'HDV';
    return contributorName.value ? `${prefix} · ${contributorName.value}` : prefix;
});
const tooltip = computed(() => {
    if (isPersonal.value) return 'Ton prix personnel privé est utilisé pour ce calcul.';

    const contributor = contributorName.value
        ? ` Dernier relevé par ${contributorName.value}.`
        : '';
    const fallback = props.source?.isFallback
        ? ' Aucun prix personnel n’est disponible : le prix HDV est utilisé en repli.'
        : '';

    return `Prix HDV communautaire utilisé.${contributor}${fallback}`;
});
</script>

<template>
    <span
        v-if="source"
        class="inline-flex max-w-40 shrink-0 items-center gap-1 rounded-full border px-1.5 py-0.5 text-[9px] font-bold leading-none"
        :class="isPersonal
            ? 'border-violet-400 bg-violet-100 text-violet-900'
            : source.isFallback
                ? 'border-amber-500 bg-amber-100 text-amber-950'
                : 'border-blue-400 bg-blue-100 text-blue-900'"
        :title="tooltip"
    >
        <UserRound v-if="isPersonal" :size="10" :stroke-width="2.5" aria-hidden="true" />
        <Store v-else :size="10" :stroke-width="2.5" aria-hidden="true" />
        <span class="truncate">{{ label }}</span>
    </span>
</template>

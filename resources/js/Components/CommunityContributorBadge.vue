<script setup>
import { Medal, Sparkles } from '@lucide/vue';

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    contributionCount: {
        type: Number,
        default: 0,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const formatNumber = value => new Intl.NumberFormat('fr-FR').format(Number(value || 0));
const contributionLabel = () => {
    const total = Number(props.contributionCount || 0);
    return `${formatNumber(total)} contribution${total > 1 ? 's' : ''}`;
};
</script>

<template>
    <span
        class="group/contributor relative inline-flex min-w-0 shrink-0 items-center rounded-xl border border-amber-200/90 bg-gradient-to-r from-amber-50 via-white to-orange-50 text-slate-700 shadow-[0_2px_7px_rgba(120,53,15,0.1)] transition duration-150 hover:-translate-y-px hover:border-amber-300 hover:shadow-[0_4px_12px_rgba(120,53,15,0.16)]"
        :class="compact ? 'gap-1.5 px-1.5 py-1' : 'gap-2 px-2 py-1.5'"
        :title="`Merci à ${name} pour ses relevés communautaires. Ce badge ne représente ni un score ni un niveau de confiance.`"
        :aria-label="`Dernier relevé par ${name}, ${contributionLabel()}`"
    >
        <span
            class="relative inline-flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-300 via-amber-400 to-orange-500 text-amber-950 ring-1 ring-amber-600/40 shadow-sm"
            :class="compact ? 'size-6' : 'size-7'"
            aria-hidden="true"
        >
            <Medal :size="compact ? 13 : 15" :stroke-width="2.35" />
            <Sparkles
                class="absolute -right-0.5 -top-0.5 rounded-full bg-white p-px text-amber-600 shadow-sm"
                :size="compact ? 8 : 9"
                :stroke-width="2.6"
            />
        </span>

        <span class="min-w-0 text-left leading-tight">
            <span v-if="!compact" class="block text-[8px] font-semibold uppercase tracking-[0.08em] text-amber-700/70">
                Relevé par
            </span>
            <strong class="block max-w-28 truncate text-[11px] font-bold text-slate-900">{{ name }}</strong>
        </span>

        <span
            class="shrink-0 border-l border-amber-200 font-semibold text-amber-900/75"
            :class="compact ? 'pl-1.5 text-[8px]' : 'pl-2 text-[9px]'"
        >
            {{ compact ? formatNumber(contributionCount) : contributionLabel() }}
        </span>
    </span>
</template>

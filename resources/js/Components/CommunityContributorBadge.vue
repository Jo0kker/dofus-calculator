<script setup>
import { computed } from 'vue';
import { Award, BadgeCheck, Crown, Gem, Medal, ShieldCheck, Sparkles, Trophy } from '@lucide/vue';

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
    return `${formatNumber(total)} contribution${total === 1 ? '' : 's'}`;
};

const contributorTier = computed(() => {
    const total = Number(props.contributionCount || 0);

    if (total >= 5000) {
        return {
            label: 'Légende de la communauté',
            icon: Crown,
            containerClass: 'border-fuchsia-300 bg-gradient-to-r from-fuchsia-50 via-white to-amber-50 text-fuchsia-950 shadow-[0_2px_10px_rgba(192,38,211,0.18)] hover:border-fuchsia-400 hover:shadow-[0_4px_16px_rgba(192,38,211,0.25)]',
            iconClass: 'bg-gradient-to-br from-fuchsia-400 via-rose-500 to-amber-400 text-white ring-fuchsia-700/45',
            accentClass: 'text-fuchsia-700',
            dividerClass: 'border-fuchsia-200 text-fuchsia-900/85',
            sparklesClass: 'text-fuchsia-600',
        };
    }

    if (total >= 2500) {
        return {
            label: 'Pilier de la communauté',
            icon: Gem,
            containerClass: 'border-violet-300 bg-gradient-to-r from-violet-50 via-white to-fuchsia-50 text-violet-950 shadow-[0_2px_9px_rgba(109,40,217,0.16)] hover:border-violet-400 hover:shadow-[0_4px_14px_rgba(109,40,217,0.22)]',
            iconClass: 'bg-gradient-to-br from-violet-300 via-violet-500 to-fuchsia-600 text-white ring-violet-700/40',
            accentClass: 'text-violet-700',
            dividerClass: 'border-violet-200 text-violet-900/80',
            sparklesClass: 'text-violet-600',
        };
    }

    if (total >= 1000) {
        return {
            label: 'Expert du marché',
            icon: Trophy,
            containerClass: 'border-indigo-300 bg-gradient-to-r from-indigo-50 via-white to-sky-50 text-indigo-950 shadow-[0_2px_9px_rgba(67,56,202,0.15)] hover:border-indigo-400 hover:shadow-[0_4px_14px_rgba(67,56,202,0.22)]',
            iconClass: 'bg-gradient-to-br from-sky-300 via-indigo-400 to-indigo-600 text-white ring-indigo-700/40',
            accentClass: 'text-indigo-700',
            dividerClass: 'border-indigo-200 text-indigo-900/80',
            sparklesClass: 'text-indigo-600',
        };
    }

    if (total >= 500) {
        return {
            label: 'Grand contributeur',
            icon: Award,
            containerClass: 'border-amber-300 bg-gradient-to-r from-amber-50 via-white to-yellow-50 text-amber-950 shadow-[0_2px_9px_rgba(180,83,9,0.16)] hover:border-amber-400 hover:shadow-[0_4px_14px_rgba(180,83,9,0.22)]',
            iconClass: 'bg-gradient-to-br from-yellow-300 via-amber-400 to-orange-500 text-amber-950 ring-amber-600/50',
            accentClass: 'text-amber-700',
            dividerClass: 'border-amber-200 text-amber-900/80',
            sparklesClass: 'text-amber-600',
        };
    }

    if (total >= 200) {
        return {
            label: 'Contributeur confirmé',
            icon: ShieldCheck,
            containerClass: 'border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-teal-50 text-emerald-950 shadow-[0_2px_8px_rgba(5,150,105,0.11)] hover:border-emerald-300 hover:shadow-[0_4px_13px_rgba(5,150,105,0.17)]',
            iconClass: 'bg-gradient-to-br from-emerald-200 via-emerald-400 to-teal-500 text-emerald-950 ring-emerald-700/35',
            accentClass: 'text-emerald-700',
            dividerClass: 'border-emerald-200 text-emerald-900/75',
            sparklesClass: 'text-emerald-600',
        };
    }

    if (total >= 50) {
        return {
            label: 'Contributeur actif',
            icon: BadgeCheck,
            containerClass: 'border-sky-200 bg-gradient-to-r from-sky-50 via-white to-slate-50 text-slate-800 shadow-[0_2px_7px_rgba(30,64,175,0.1)] hover:border-sky-300 hover:shadow-[0_4px_12px_rgba(30,64,175,0.16)]',
            iconClass: 'bg-gradient-to-br from-slate-200 via-sky-300 to-blue-400 text-slate-800 ring-sky-600/35',
            accentClass: 'text-sky-700',
            dividerClass: 'border-sky-200 text-sky-900/75',
            sparklesClass: 'text-sky-600',
        };
    }

    return {
        label: 'Relevé par',
        icon: Medal,
        containerClass: 'border-orange-200 bg-gradient-to-r from-orange-50 via-white to-amber-50 text-slate-700 shadow-[0_2px_7px_rgba(120,53,15,0.1)] hover:border-orange-300 hover:shadow-[0_4px_12px_rgba(120,53,15,0.16)]',
        iconClass: 'bg-gradient-to-br from-orange-200 via-orange-300 to-amber-400 text-orange-950 ring-orange-600/35',
        accentClass: 'text-orange-700',
        dividerClass: 'border-orange-200 text-orange-900/75',
        sparklesClass: 'text-orange-600',
    };
});

const accessibleTierLabel = computed(() => contributorTier.value.label === 'Relevé par'
    ? 'Contributeur débutant'
    : contributorTier.value.label);
</script>

<template>
    <span
        class="group/contributor relative inline-flex min-w-0 max-w-full items-center rounded-xl border transition duration-150 hover:-translate-y-px"
        :class="[compact ? 'gap-1.5 px-1.5 py-1' : 'gap-2 px-2 py-1.5', contributorTier.containerClass]"
        :aria-label="`Dernier relevé par ${name}, ${contributionLabel()}, palier ${accessibleTierLabel}`"
    >
        <span
            class="relative inline-flex shrink-0 items-center justify-center rounded-full ring-1 shadow-sm"
            :class="[compact ? 'size-6' : 'size-7', contributorTier.iconClass]"
            aria-hidden="true"
        >
            <component :is="contributorTier.icon" :size="compact ? 13 : 15" :stroke-width="2.35" />
            <Sparkles
                class="absolute -right-0.5 -top-0.5 rounded-full bg-white p-px shadow-sm"
                :class="contributorTier.sparklesClass"
                :size="compact ? 8 : 9"
                :stroke-width="2.6"
            />
        </span>

        <span class="min-w-0 text-left leading-tight">
            <span
                v-if="!compact"
                class="block whitespace-nowrap text-[8px] font-semibold uppercase tracking-[0.08em] opacity-75"
                :class="contributorTier.accentClass"
            >
                {{ contributorTier.label }}
            </span>
            <strong class="block max-w-28 truncate text-[11px] font-bold text-slate-900">{{ name }}</strong>
        </span>

        <span
            class="shrink-0 border-l font-semibold"
            :class="[compact ? 'pl-1.5 text-[8px]' : 'pl-2 text-[9px]', contributorTier.dividerClass]"
        >
            {{ compact ? formatNumber(contributionCount) : contributionLabel() }}
        </span>
    </span>
</template>

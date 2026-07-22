<script setup>
import { CircleHelp, Store, UserRound } from '@lucide/vue';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['select']);
</script>

<template>
    <fieldset class="relative min-w-0">
        <legend class="sr-only">Source de prix</legend>

        <div class="mb-1 flex items-center gap-1">
            <span class="text-[9px] font-semibold uppercase tracking-wide text-gray-400">Source</span>

            <div class="group relative">
                <button
                    type="button"
                    class="inline-flex size-4 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
                    aria-label="Afficher l’aide sur les sources de prix"
                >
                    <CircleHelp :size="11" :stroke-width="2.2" aria-hidden="true" />
                </button>

                <div
                    role="tooltip"
                    class="pointer-events-none invisible absolute bottom-full right-0 z-50 mb-1.5 w-52 translate-y-1 space-y-1 rounded-md border border-slate-700 bg-slate-900 p-2 text-[9px] leading-tight normal-case tracking-normal text-slate-100 opacity-0 shadow-lg transition duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100"
                >
                    <div class="flex items-center gap-1.5">
                        <Store class="shrink-0 text-blue-300" :size="11" aria-hidden="true" />
                        <span><strong class="text-white">HDV</strong> · prix communautaire par défaut</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <UserRound class="shrink-0 text-violet-300" :size="11" aria-hidden="true" />
                        <span><strong class="text-white">Perso</strong> · prix privé, repli HDV</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="inline-flex rounded-lg border border-gray-200 bg-gray-100 p-0.5 shadow-inner">
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
                :class="[
                    compact ? 'size-7' : 'size-8',
                    modelValue !== 'personal' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-400 hover:bg-white/70 hover:text-blue-600',
                ]"
                aria-label="Toujours utiliser le prix HDV"
                :aria-pressed="modelValue !== 'personal'"
                :disabled="disabled"
                @click="$emit('select', 'community')"
            >
                <Store :size="compact ? 13 : 15" :stroke-width="2.2" aria-hidden="true" />
            </button>

            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
                :class="[
                    compact ? 'size-7' : 'size-8',
                    modelValue === 'personal' ? 'bg-violet-600 text-white shadow-sm' : 'text-gray-400 hover:bg-white/70 hover:text-violet-600',
                ]"
                aria-label="Toujours utiliser le prix personnel"
                :aria-pressed="modelValue === 'personal'"
                :disabled="disabled"
                @click="$emit('select', 'personal')"
            >
                <UserRound :size="compact ? 13 : 15" :stroke-width="2.2" aria-hidden="true" />
            </button>
        </div>

    </fieldset>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const selectedMode = ref(page.props.auth?.user?.price_mode || 'community');
const saving = ref(false);

watch(() => page.props.auth?.user?.price_mode, mode => {
    if (mode) selectedMode.value = mode;
});

const selectMode = mode => {
    if (saving.value || selectedMode.value === mode) return;

    const previousMode = selectedMode.value;
    selectedMode.value = mode;
    saving.value = true;

    router.put(route('prices.preference'), { price_mode: mode }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            selectedMode.value = previousMode;
        },
        onFinish: () => {
            saving.value = false;
        },
    });
};
</script>

<template>
    <div
        v-if="$page.props.auth?.user"
        :class="compact
            ? 'w-full border border-[#9c9c9c] bg-[#ece9d8] p-2 text-slate-800 shadow-inner'
            : 'flex items-center gap-2'"
    >
        <span :class="compact ? 'mb-1 block text-[11px] font-bold' : 'text-sm font-medium text-gray-700 dark:text-gray-200'">
            Source des prix
        </span>
        <div
            class="grid grid-cols-2 p-0.5 text-xs font-semibold"
            :class="compact ? 'w-full border border-[#808080] bg-[#c8c8c8]' : 'rounded-lg bg-gray-200'"
        >
            <button
                type="button"
                class="px-2 py-1 transition-colors"
                :class="[
                    compact ? 'border border-transparent' : 'rounded-md',
                    selectedMode === 'community'
                        ? (compact ? 'border-[#174d8c] bg-white text-[#0b3f88] shadow-inner' : 'bg-white text-blue-700 shadow-sm')
                        : 'text-gray-600 hover:text-gray-900',
                ]"
                :disabled="saving"
                @click="selectMode('community')"
            >
                HDV
            </button>
            <button
                type="button"
                class="px-2 py-1 transition-colors"
                :class="[
                    compact ? 'border border-transparent' : 'rounded-md',
                    selectedMode === 'personal'
                        ? (compact ? 'border-[#5b3b87] bg-white text-violet-800 shadow-inner' : 'bg-white text-violet-700 shadow-sm')
                        : 'text-gray-600 hover:text-gray-900',
                ]"
                :disabled="saving"
                @click="selectMode('personal')"
            >
                Perso
            </button>
        </div>
        <span v-if="compact" class="mt-1 block text-[10px] leading-tight text-slate-600">
            Choix global, sauf exception définie sur un objet.
        </span>
    </div>
</template>

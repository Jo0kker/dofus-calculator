<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex h-full flex-col bg-[#f3f0df] text-slate-900">
        <div class="border-b border-[#9c9c9c] bg-[#ece9d8] px-3 py-2 shadow-[inset_0_1px_0_#fff]">
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#0b3f88]">Dofus Desktop</p>
            <h2 class="text-sm font-black text-slate-950">{{ title }}</h2>
            <p v-if="subtitle" class="mt-0.5 text-[11px] text-slate-600">{{ subtitle }}</p>
        </div>
        <div class="min-h-0 flex-1 overflow-auto p-3">
            <slot />
        </div>
    </div>
</template>

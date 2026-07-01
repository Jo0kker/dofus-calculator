<script setup>
import VueDraggableResizable from 'vue-draggable-resizable';
import 'vue-draggable-resizable/style.css';

const props = defineProps({
    windowState: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits([
    'close',
    'focus',
    'minimize',
    'toggle-maximize',
    'update-bounds',
]);

const updateDrag = (x, y) => {
    emit('update-bounds', props.windowState.id, { x, y });
};

const updateResize = (x, y, w, h) => {
    emit('update-bounds', props.windowState.id, { x, y, w, h });
};
</script>

<template>
    <VueDraggableResizable
        :x="windowState.x"
        :y="windowState.y"
        :w="windowState.w"
        :h="windowState.h"
        :z="windowState.z"
        :active="true"
        :parent="true"
        :draggable="!windowState.maximized"
        :resizable="!windowState.maximized"
        :min-width="420"
        :min-height="280"
        drag-handle=".desktop-window__titlebar"
        class-name="desktop-window-wrapper"
        @activated="emit('focus', windowState.id)"
        @dragging="updateDrag"
        @resizing="updateResize"
    >
        <section
            class="flex h-full flex-col overflow-hidden rounded-xl border border-slate-600/70 bg-slate-950 text-slate-100 shadow-2xl shadow-black/40"
            @mousedown="emit('focus', windowState.id)"
        >
            <header class="desktop-window__titlebar flex cursor-move select-none items-center justify-between border-b border-slate-700 bg-slate-900/95 px-3 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow shadow-emerald-400/40" />
                    <h3 class="truncate text-sm font-semibold tracking-wide">
                        {{ windowState.title }}
                    </h3>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="rounded px-2 py-1 text-xs text-slate-300 hover:bg-slate-700 hover:text-white"
                        title="Réduire"
                        @click.stop="emit('minimize', windowState.id)"
                    >
                        —
                    </button>
                    <button
                        type="button"
                        class="rounded px-2 py-1 text-xs text-slate-300 hover:bg-slate-700 hover:text-white"
                        :title="windowState.maximized ? 'Restaurer' : 'Agrandir'"
                        @click.stop="emit('toggle-maximize', windowState.id)"
                    >
                        {{ windowState.maximized ? '❐' : '□' }}
                    </button>
                    <button
                        type="button"
                        class="rounded px-2 py-1 text-xs text-slate-300 hover:bg-red-600 hover:text-white"
                        title="Fermer"
                        @click.stop="emit('close', windowState.id)"
                    >
                        ×
                    </button>
                </div>
            </header>

            <iframe
                :src="windowState.url"
                class="h-full w-full flex-1 border-0 bg-white"
                :title="windowState.title"
            />
        </section>
    </VueDraggableResizable>
</template>

<style>
.desktop-window-wrapper {
    border: 0 !important;
}

.desktop-window-wrapper > .handle {
    z-index: 20;
}
</style>

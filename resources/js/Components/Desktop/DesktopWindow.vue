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
            class="flex h-full flex-col overflow-hidden border border-[#083f88] bg-[#d4d0c8] text-slate-950 shadow-[8px_8px_0_rgba(0,0,0,0.28)]"
            @mousedown="emit('focus', windowState.id)"
        >
            <header class="desktop-window__titlebar flex h-8 cursor-move select-none items-center justify-between bg-gradient-to-r from-[#083f88] via-[#0f63bd] to-[#5aa0e6] px-1.5 text-white">
                <div class="flex min-w-0 items-center gap-2 px-1">
                    <span class="grid h-4 w-4 place-items-center rounded-sm bg-[#f5c542] text-[10px] text-[#3b2a00]">◆</span>
                    <h3 class="truncate text-xs font-bold tracking-wide drop-shadow">
                        {{ windowState.title }}
                    </h3>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="window-control"
                        title="Réduire"
                        @click.stop="emit('minimize', windowState.id)"
                    >
                        _
                    </button>
                    <button
                        type="button"
                        class="window-control"
                        :title="windowState.maximized ? 'Restaurer' : 'Agrandir'"
                        @click.stop="emit('toggle-maximize', windowState.id)"
                    >
                        {{ windowState.maximized ? '❐' : '□' }}
                    </button>
                    <button
                        type="button"
                        class="window-control bg-[#c73c3c] text-white hover:bg-[#e24b4b]"
                        title="Fermer"
                        @click.stop="emit('close', windowState.id)"
                    >
                        ×
                    </button>
                </div>
            </header>

            <div class="flex h-7 items-center gap-4 border-b border-[#9c9c9c] bg-[#ece9d8] px-3 text-[11px] text-slate-800">
                <span>Fichier</span>
                <span>Édition</span>
                <span>Affichage</span>
                <span>Aide</span>
            </div>

            <iframe
                :src="windowState.url"
                class="h-full w-full flex-1 border-0 bg-[#111827]"
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

.window-control {
    display: grid;
    height: 1.25rem;
    min-width: 1.25rem;
    place-items: center;
    border: 1px solid #4d4d4d;
    background: linear-gradient(#ffffff, #c9c4ba);
    padding: 0 0.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1;
    color: #111827;
    box-shadow: inset 1px 1px 0 #ffffff, inset -1px -1px 0 #7c7c7c;
}

.window-control:hover {
    filter: brightness(1.05);
}
</style>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success');
let hideTimer = null;

const hide = () => {
    visible.value = false;
};

const show = (nextMessage, nextType) => {
    if (!nextMessage) return;

    clearTimeout(hideTimer);
    message.value = Array.isArray(nextMessage) ? nextMessage[0] : String(nextMessage);
    type.value = nextType;
    visible.value = true;
    hideTimer = setTimeout(hide, 4500);
};

watch(
    () => [page.props.flash, page.props.errors],
    ([flash, errors]) => {
        const validationError = Object.values(errors || {})[0];

        if (flash?.error || validationError) {
            show(flash?.error || validationError, 'error');
        } else if (flash?.success) {
            show(flash.success, 'success');
        }
    },
    { immediate: true, deep: true },
);

onBeforeUnmount(() => clearTimeout(hideTimer));
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            class="fixed right-4 top-4 z-[100] flex max-w-[calc(100vw-2rem)] items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-xl sm:max-w-md"
            :class="type === 'error'
                ? 'border-red-300 bg-red-50 text-red-900'
                : 'border-emerald-300 bg-emerald-50 text-emerald-900'"
            role="status"
            aria-live="polite"
        >
            <span class="mt-0.5 font-bold" aria-hidden="true">
                {{ type === 'error' ? '×' : '✓' }}
            </span>
            <p class="min-w-0 flex-1 leading-5">{{ message }}</p>
            <button
                type="button"
                class="rounded px-1 text-current opacity-60 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current"
                aria-label="Fermer la notification"
                @click="hide"
            >
                ×
            </button>
        </div>
    </Transition>
</template>
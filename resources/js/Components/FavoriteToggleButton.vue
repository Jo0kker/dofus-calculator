<template>
    <button
        v-if="page.props.auth?.user"
        type="button"
        :disabled="saving"
        :aria-label="label"
        :title="label"
        :class="[
            'inline-flex shrink-0 items-center justify-center rounded-full p-2 transition-colors',
            isFavorite
                ? 'bg-yellow-100 text-yellow-600 hover:bg-yellow-200'
                : 'bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-600',
            saving ? 'cursor-wait opacity-60' : '',
        ]"
        @click.stop.prevent="toggleFavorite"
    >
        <svg
            class="h-5 w-5"
            :class="{ 'fill-current': isFavorite }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
            />
        </svg>
    </button>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    itemId: {
        type: [Number, String],
        required: true,
    },
    itemName: {
        type: String,
        default: 'cet item',
    },
    initialFavorite: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const isFavorite = ref(props.initialFavorite);
const saving = ref(false);

const label = computed(() => (
    isFavorite.value
        ? `Retirer ${props.itemName} des favoris`
        : `Ajouter ${props.itemName} aux favoris`
));

watch(() => props.initialFavorite, value => {
    isFavorite.value = value;
});

const syncFavorite = event => {
    if (String(event.detail?.itemId) === String(props.itemId)) {
        isFavorite.value = Boolean(event.detail.isFavorite);
    }
};

onMounted(() => window.addEventListener('dofus:favorite-updated', syncFavorite));
onBeforeUnmount(() => window.removeEventListener('dofus:favorite-updated', syncFavorite));

const toggleFavorite = () => {
    if (saving.value) return;

    const nextValue = !isFavorite.value;
    saving.value = true;

    router.post(route('favorites.toggle', props.itemId), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            window.dispatchEvent(new CustomEvent('dofus:favorite-updated', {
                detail: {
                    itemId: props.itemId,
                    isFavorite: nextValue,
                },
            }));
            window.dispatchEvent(new CustomEvent('dofus:favorites-changed'));
        },
        onFinish: () => {
            saving.value = false;
        },
    });
};
</script>

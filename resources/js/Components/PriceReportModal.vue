<template>
    <Modal :show="show" @close="$emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                🚨 Signaler un prix incorrect
            </h2>
            
            <div class="mb-4 p-3 bg-gray-50 rounded">
                <div class="flex items-center space-x-3">
                    <img v-if="itemPrice.item.image_url" 
                        :src="itemPrice.item.image_url" 
                        :alt="itemPrice.item.name"
                        class="w-8 h-8"
                    />
                    <div>
                        <p class="font-medium">{{ itemPrice.item.name }}</p>
                        <p class="text-sm text-gray-600">
                            Prix signalé: <span class="font-bold text-red-600">{{ formatNumber(itemPrice.price) }}K</span>
                            • Serveur: {{ itemPrice.server?.name }}
                        </p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Pourquoi ce prix vous semble-t-il incorrect ? <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        v-model="form.comment" 
                        rows="4" 
                        class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Ex: Prix beaucoup trop élevé par rapport au marché, prix obsolète de plusieurs mois, erreur de saisie, etc."
                        maxlength="500"
                        required
                    ></textarea>
                    <div class="text-xs text-gray-500 mt-1">{{ form.comment?.length || 0 }}/500 caractères</div>
                    <div v-if="errors.comment" class="text-red-500 text-sm mt-1">{{ errors.comment }}</div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button 
                        type="button" 
                        @click="$emit('close')"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                        :disabled="processing"
                    >
                        Annuler
                    </button>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                        :disabled="processing || !form.comment?.trim()"
                    >
                        {{ processing ? 'Envoi...' : 'Signaler le prix' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from './Modal.vue';

const props = defineProps({
    show: Boolean,
    itemPrice: Object,
});

const emit = defineEmits(['close', 'reported']);

const processing = ref(false);
const errors = ref({});

const form = reactive({
    comment: '',
});

const submit = () => {
    processing.value = true;
    errors.value = {};

    router.post(route('prices.report', props.itemPrice.id), form, {
        onSuccess: () => {
            form.comment = '';
            emit('close');
            emit('reported');
        },
        onError: (serverErrors) => {
            errors.value = serverErrors;
        },
        onFinish: () => {
            processing.value = false;
        }
    });
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('fr-FR').format(Math.round(num));
};
</script>
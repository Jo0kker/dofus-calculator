<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    importStatus: Object,
});

const status = ref(props.importStatus ?? { status: 'idle', started_at: null, progress: null, last_result: null });
const isSubmitting = ref(false);
const pollErrorCount = ref(0);
const maxPollErrors = 5;
let pollInterval = null;

const isRunning = () => status.value?.status === 'running';

const startImport = () => {
    if (isRunning() || isSubmitting.value) return;

    isSubmitting.value = true;
    pollErrorCount.value = 0;
    router.post(route('admin.commands.import-recipes'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            startPolling();
        },
    });
};

const fetchStatus = async () => {
    try {
        const response = await fetch(route('admin.commands.import-recipes.status'));
        if (response.ok) {
            status.value = await response.json();
            pollErrorCount.value = 0;

            if (status.value.status !== 'running') {
                stopPolling();
            }
        } else {
            pollErrorCount.value++;
            if (pollErrorCount.value >= maxPollErrors) {
                stopPolling();
            }
        }
    } catch (e) {
        pollErrorCount.value++;
        if (pollErrorCount.value >= maxPollErrors) {
            stopPolling();
        }
    }
};

const startPolling = () => {
    stopPolling();
    pollInterval = setInterval(fetchStatus, 3000);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('fr-FR');
};

const formatDuration = (seconds) => {
    if (!seconds) return '-';
    if (seconds < 60) return `${Math.round(seconds * 10) / 10}s`;
    const minutes = Math.floor(seconds / 60);
    const remaining = Math.round(seconds - minutes * 60);
    return `${minutes}m ${remaining}s`;
};

const statusLabel = () => {
    const labels = {
        idle: 'Inactif',
        running: 'En cours...',
        completed: 'Terminé',
        failed: 'Échoué',
    };
    return labels[status.value?.status] || 'Inconnu';
};

const statusColor = () => {
    const colors = {
        idle: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        running: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    };
    return colors[status.value?.status] || colors.idle;
};

onMounted(() => {
    if (isRunning()) {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <AppLayout title="Commandes Admin">
        <Head title="Commandes Admin" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Commandes Admin</h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Lancer les commandes d'administration en arrière-plan</p>
                </div>

                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                    <p class="text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.flash?.error" class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                    <p class="text-red-800 dark:text-red-200">{{ $page.props.flash.error }}</p>
                </div>

                <!-- Polling error -->
                <div v-if="pollErrorCount >= maxPollErrors" class="mb-4 p-4 bg-yellow-100 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                    <p class="text-yellow-800 dark:text-yellow-200">Le suivi en temps réel s'est arrêté suite à des erreurs de connexion. Rechargez la page pour actualiser le statut.</p>
                </div>

                <!-- Import Recipes Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Import des Recettes</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Importe toutes les recettes depuis l'API DofusDB. Les logs seront envoyés sur Discord à la fin de l'exécution.
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1 font-mono">
                                    dofus:import-recipes
                                </p>
                            </div>
                            <span :class="statusColor()" class="px-3 py-1 text-sm font-semibold rounded-full whitespace-nowrap">
                                {{ statusLabel() }}
                            </span>
                        </div>

                        <!-- Progress info when running -->
                        <div v-if="isRunning() && status.progress" class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                        {{ status.progress?.processed ?? 0 }} recettes traitées
                                    </p>
                                    <p class="text-xs text-blue-600 dark:text-blue-400">
                                        Mémoire : {{ status.progress?.memory ?? '?' }}MB
                                    </p>
                                </div>
                            </div>
                            <p v-if="status.started_at" class="text-xs text-blue-500 dark:text-blue-500 mt-2">
                                Démarré le {{ formatDate(status.started_at) }}
                            </p>
                        </div>

                        <!-- Running without progress yet -->
                        <div v-else-if="isRunning()" class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-sm font-medium text-blue-800 dark:text-blue-200">Import en cours de démarrage...</p>
                            </div>
                        </div>

                        <!-- Last result -->
                        <div v-if="status.last_result && !isRunning()" class="mb-4 p-4 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Dernier import</h4>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Importées</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ status.last_result?.imported ?? 0 }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Mises à jour</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ status.last_result?.updated ?? 0 }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Erreurs</p>
                                    <p class="text-lg font-semibold" :class="(status.last_result?.errors_count ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'">
                                        {{ status.last_result?.errors_count ?? 0 }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Durée</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ formatDuration(status.last_result?.duration) }}</p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                                Terminé le {{ formatDate(status.last_result?.finished_at) }}
                            </p>
                        </div>

                        <!-- Action button -->
                        <button
                            @click="startImport"
                            :disabled="isRunning() || isSubmitting"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed dark:focus:ring-offset-gray-800"
                        >
                            <svg v-if="isRunning() || isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                            </svg>
                            {{ isRunning() ? 'Import en cours...' : isSubmitting ? 'Lancement...' : 'Lancer l\'import' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

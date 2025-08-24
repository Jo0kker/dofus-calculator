import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

// État global du serveur sélectionné
const selectedServer = ref(null);

export function useServerSelection() {
    const page = usePage();

    // Récupérer les serveurs depuis les props Inertia
    const servers = computed(() => page.props.servers || []);
    const sessionServerId = computed(() => page.props.selected_server_id);

    // Initialiser automatiquement le serveur sélectionné
    const initializeSelectedServer = () => {
        // Le serveur est maintenant géré côté backend via HandleInertiaRequests
        if (sessionServerId.value) {
            const server = servers.value.find(s => s.id === sessionServerId.value);
            if (server) {
                selectedServer.value = server;
            }
        }
    };

    // Surveiller les changements de données Inertia
    watch([servers, sessionServerId], initializeSelectedServer, { immediate: true });

    const setSelectedServer = (server) => {
        selectedServer.value = server;
        
        // Persister en session via une route dédiée
        if (server) {
            router.post(route('server.select'), {
                server_id: server.id
            }, {
                preserveState: true,
                preserveScroll: true,
            });
        }
    };

    const selectedServerId = computed(() => {
        return selectedServer.value?.id || null;
    });

    const isServerSelected = computed(() => {
        return selectedServer.value !== null;
    });

    return {
        selectedServer: computed(() => selectedServer.value),
        selectedServerId,
        servers,
        isServerSelected,
        setSelectedServer,
    };
}
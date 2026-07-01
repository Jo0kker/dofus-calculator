<template>
    <div :class="compact ? 'w-full' : 'flex items-center'">
        <!-- Version mobile : sélecteur compact -->
        <div :class="compact ? 'flex w-full flex-col gap-1' : 'flex items-center sm:hidden'">
            <label :class="compact ? 'text-[11px] font-semibold uppercase tracking-wide text-slate-200' : 'text-xs text-gray-600 mr-1'">
                Serveur
            </label>
            <select
                :value="selectedServerId"
                @change="handleServerChange"
                :class="compact
                    ? 'w-full max-w-full truncate rounded-sm border border-[#6b6b6b] bg-[#f5f5f5] px-2 py-1 text-xs text-slate-900 shadow-inner focus:border-blue-700 focus:ring-1 focus:ring-blue-700'
                    : 'border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm text-sm py-1 px-2 bg-white'"
            >
                <option value="">Choisir...</option>
                <option v-for="server in servers" :key="server.id" :value="server.id">
                    {{ server.name }}
                </option>
            </select>
        </div>

        <!-- Version desktop : dropdown classique -->
        <div v-if="!compact" class="hidden sm:flex items-center space-x-3">
            <span class="text-sm font-medium text-gray-700">Serveur:</span>
            <select
                :value="selectedServerId"
                @change="handleServerChange"
                class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm text-sm"
            >
                <option value="">-- Sélectionnez un serveur --</option>
                <option v-for="server in servers" :key="server.id" :value="server.id">
                    {{ server.name }}
                </option>
            </select>
        </div>
    </div>
</template>

<script setup>
import { useServerSelection } from '@/Composables/useServerSelection';

defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});

const { selectedServerId, servers, setSelectedServer } = useServerSelection();

const handleServerChange = (event) => {
    const serverId = event.target.value;
    if (serverId) {
        const server = servers.value.find(s => s.id == serverId);
        setSelectedServer(server);
    } else {
        setSelectedServer(null);
    }
};
</script>
<template>
    <div class="flex items-center space-x-3">
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
</template>

<script setup>
import { useServerSelection } from '@/Composables/useServerSelection';

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
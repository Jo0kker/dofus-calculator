<template>
    <div class="craft-ingredient" :style="{ paddingLeft: depth * 20 + 'px' }">
        <!-- Si c'est un ACHAT -->
        <div v-if="node.usedMethod === 'buy'" class="flex justify-between items-center py-1 text-sm">
            <div class="flex items-center space-x-2">
                <img v-if="node.image_url" :src="node.image_url" :alt="node.name" class="w-4 h-4" />
                <span>{{ node.quantity }}x {{ node.name }}</span>
                <span class="bg-green-100 text-green-800 px-2 py-0.5 text-xs rounded">💰 ACHAT</span>
            </div>
            <span class="font-bold text-green-600">{{ formatNumber(node.usedPrice * node.quantity) }}K</span>
        </div>
        
        <!-- Si c'est un CRAFT -->
        <div v-else-if="node.usedMethod === 'craft'">
            <div class="flex justify-between items-center py-1 text-sm bg-blue-50 px-2 rounded">
                <div class="flex items-center space-x-2">
                    <img v-if="node.image_url" :src="node.image_url" :alt="node.name" class="w-4 h-4" />
                    <span class="font-bold">{{ node.quantity }}x {{ node.name }}</span>
                    <span class="bg-blue-100 text-blue-800 px-2 py-0.5 text-xs rounded">🔨 CRAFT</span>
                </div>
                <span class="font-bold text-blue-600">{{ formatNumber(node.usedPrice * node.quantity) }}K</span>
            </div>
            
            <!-- DÉTAIL DU CRAFT -->
            <div class="ml-4 mt-1 border-l-2 border-blue-200 pl-3">
                <div class="text-xs text-blue-700 mb-1 font-medium">Ingrédients pour crafter {{ node.name }}:</div>
                <CraftTreeNode 
                    v-for="subNode in node.craftTree.ingredients"
                    :key="subNode.id"
                    :node="subNode"
                    :depth="depth + 1"
                />
            </div>
        </div>
        
        <!-- Si pas de méthode définie -->
        <div v-else class="flex justify-between items-center py-1 text-sm text-gray-500">
            <div class="flex items-center space-x-2">
                <img v-if="node.image_url" :src="node.image_url" :alt="node.name" class="w-4 h-4" />
                <span>{{ node.quantity }}x {{ node.name }}</span>
                <span class="bg-gray-100 text-gray-600 px-2 py-0.5 text-xs rounded">❌ INDISPONIBLE</span>
            </div>
            <span class="text-red-500">N/A</span>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    node: Object,
    depth: {
        type: Number,
        default: 0
    }
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('fr-FR').format(Math.round(num));
};
</script>

<style scoped>
.craft-node {
    position: relative;
}

.depth-0 {
    border-left: 3px solid #4A90E2;
}

.depth-1 {
    border-left: 2px solid #7FB069;
}

.depth-2 {
    border-left: 2px solid #F4A261;
}

.depth-3 {
    border-left: 2px solid #E76F51;
}
</style>
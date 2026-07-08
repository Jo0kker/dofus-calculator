<script setup>
import { computed, ref, watch } from 'vue';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const props = defineProps({ payload: { type: Object, default: () => ({}) } });
const emit = defineEmits(['open-app']);
const entries = ref([]);
const sourceName = ref('');
const craftCount = ref(1);

const mergeResources = (resources = []) => {
    resources.forEach((resource) => {
        const id = resource.id || resource.ingredient?.id;
        if (!id) return;

        const quantity = Number(resource.quantity || 0);
        const unitPrice = resource.unitPrice !== null && resource.unitPrice !== undefined
            ? Number(resource.unitPrice)
            : null;
        const existing = entries.value.find((entry) => Number(entry.id) === Number(id));

        if (existing) {
            existing.quantity += quantity;
            existing.total = unitPrice !== null ? (existing.total || 0) + unitPrice * quantity : existing.total;
            return;
        }

        entries.value.push({
            id,
            name: resource.name || resource.ingredient?.name || 'Ressource',
            image_url: resource.image_url || resource.ingredient?.image_url || null,
            type: resource.type || resource.ingredient?.type || null,
            level: resource.level || resource.ingredient?.level || null,
            quantity,
            unitPrice,
            total: unitPrice !== null ? unitPrice * quantity : null,
            ingredient: resource.ingredient || resource,
        });
    });
};

watch(() => props.payload, (payload) => {
    if (!payload) return;
    sourceName.value = payload.sourceName || sourceName.value;
    craftCount.value = Number(payload.craftQuantity || craftCount.value || 1);

    if (payload.replace !== false) {
        entries.value = [];
    }

    mergeResources(payload.resources || []);
}, { immediate: true, deep: true });

const totalQuantity = computed(() => entries.value.reduce((sum, entry) => sum + Number(entry.quantity || 0), 0));
const pricedTotal = computed(() => entries.value.reduce((sum, entry) => sum + Number(entry.total || 0), 0));
const missingPriceCount = computed(() => entries.value.filter((entry) => entry.unitPrice === null).length);
const formatNumber = (value) => new Intl.NumberFormat('fr-FR').format(Math.round(Number(value || 0)));

const copyList = async () => {
    const text = entries.value
        .map((entry) => `${formatNumber(entry.quantity)}x ${entry.name}`)
        .join('\n');
    await navigator.clipboard?.writeText(text);
};
</script>

<template>
    <DesktopAppShell title="Ressources nécessaires" subtitle="La vraie liste à préparer pour le nombre de crafts choisi.">
        <div v-if="sourceName" class="mb-3 border border-[#9c9c9c] bg-[#fff8d8] p-2 text-xs text-slate-700 shadow-inner">
            <strong>{{ sourceName }}</strong> · {{ craftCount }} craft(s)
        </div>

        <div v-if="!entries.length" class="border border-dashed border-[#9c9c9c] bg-white/60 p-6 text-center text-xs text-slate-500">
            Ouvre une fiche item, règle le nombre de crafts, puis clique sur “Liste ressources”.
        </div>

        <div v-else class="space-y-2">
            <div class="flex items-center justify-between gap-2 border border-[#b6b6b6] bg-white p-2 text-xs shadow-inner">
                <span><strong>{{ entries.length }}</strong> ressource(s) · <strong>{{ formatNumber(totalQuantity) }}</strong> unités</span>
                <button type="button" class="desk-button" @click="copyList">Copier la liste</button>
            </div>

            <article v-for="entry in entries" :key="entry.id" class="flex items-center gap-3 border border-[#b6b6b6] bg-white p-2 shadow-[2px_2px_0_rgba(0,0,0,.12)]">
                <img v-if="entry.image_url" :src="entry.image_url" :alt="entry.name" class="h-10 w-10 object-contain" />
                <div v-else class="grid h-10 w-10 place-items-center border border-[#9c9c9c] bg-[#ece9d8]">📦</div>
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-sm font-black">x{{ formatNumber(entry.quantity) }} {{ entry.name }}</h3>
                    <p class="text-[11px] text-slate-600">
                        {{ entry.type || 'Ressource' }} ·
                        <span v-if="entry.unitPrice !== null">{{ formatNumber(entry.unitPrice) }} K/u</span>
                        <span v-else>prix manquant</span>
                    </p>
                </div>
                <strong class="text-xs text-[#0b3f88]">{{ entry.total !== null ? `${formatNumber(entry.total)} K` : '—' }}</strong>
                <button class="desk-button" type="button" @click="emit('open-app', 'itemInspector', { windowId: `item-${entry.id}`, title: entry.name, itemId: entry.id })">Voir</button>
            </article>

            <div class="flex items-center justify-between border border-[#9c9c9c] bg-[#ece9d8] p-2 text-xs font-black text-slate-800 shadow-inner">
                <span>Total estimé</span>
                <span>{{ formatNumber(pricedTotal) }} K <em v-if="missingPriceCount" class="font-normal text-red-700">({{ missingPriceCount }} prix manquant(s))</em></span>
            </div>
        </div>
    </DesktopAppShell>
</template>

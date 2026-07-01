<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const tokens = ref([]);
const newToken = ref('');
const loading = ref(false);
const saving = ref(false);
const form = ref({
    name: '',
    abilities: ['read'],
});

const fetchTokens = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/desktop/api/api-tokens');
        tokens.value = data.tokens || [];
    } finally {
        loading.value = false;
    }
};

const toggleAbility = (ability) => {
    if (form.value.abilities.includes(ability)) {
        form.value.abilities = form.value.abilities.filter((item) => item !== ability);
        return;
    }

    form.value.abilities.push(ability);
};

const createToken = async () => {
    if (!form.value.name.trim()) return;

    saving.value = true;
    try {
        const { data } = await axios.post('/desktop/api/api-tokens', form.value);
        tokens.value = data.tokens || [];
        newToken.value = data.token || '';
        form.value = { name: '', abilities: ['read'] };
    } finally {
        saving.value = false;
    }
};

const deleteToken = async (tokenId) => {
    if (!confirm('Supprimer ce token API ?')) return;

    const { data } = await axios.delete(`/desktop/api/api-tokens/${tokenId}`);
    tokens.value = data.tokens || [];
    newToken.value = '';
};

const copyToken = async () => {
    if (!newToken.value) return;

    await navigator.clipboard.writeText(newToken.value);
};

onMounted(fetchTokens);
</script>

<template>
    <DesktopAppShell title="API Tokens" subtitle="Crée et gère tes clés d’accès API.">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <form class="border border-[#9c9c9c] bg-white p-3 shadow-[2px_2px_0_rgba(0,0,0,.12)]" @submit.prevent="createToken">
                <h3 class="mb-3 text-sm font-black">Nouveau token</h3>

                <label class="block text-xs font-bold text-slate-700" for="desktop-token-name">Nom du token</label>
                <input
                    id="desktop-token-name"
                    v-model="form.name"
                    class="mt-1 w-full border border-[#808080] bg-white px-2 py-1.5 text-xs shadow-inner focus:border-[#0f63bd] focus:ring-0"
                    placeholder="Ex: App mobile"
                    required
                    type="text"
                >

                <div class="mt-3 space-y-2 text-xs text-slate-700">
                    <p class="font-bold">Permissions</p>
                    <label class="flex items-center gap-2">
                        <input :checked="form.abilities.includes('read')" type="checkbox" @change="toggleAbility('read')">
                        Lecture des items
                    </label>
                    <label class="flex items-center gap-2">
                        <input :checked="form.abilities.includes('write')" type="checkbox" @change="toggleAbility('write')">
                        Mise à jour des prix
                    </label>
                </div>

                <button class="desk-button mt-4 w-full py-2" :disabled="saving" type="submit">
                    {{ saving ? 'Création…' : 'Créer le token' }}
                </button>
            </form>

            <section class="min-w-0 border border-[#9c9c9c] bg-[#f8f8f0] p-3 shadow-inner">
                <h3 class="mb-3 text-sm font-black">Tokens actifs</h3>
                <div v-if="loading" class="text-xs text-slate-500">Chargement…</div>
                <div v-else-if="!tokens.length" class="border border-dashed border-[#9c9c9c] bg-white/70 p-4 text-center text-xs text-slate-500">
                    Aucun token créé pour l’instant.
                </div>
                <div v-else class="space-y-2">
                    <article v-for="token in tokens" :key="token.id" class="flex items-start justify-between gap-3 border border-[#b6b6b6] bg-white p-2 text-xs shadow-[2px_2px_0_rgba(0,0,0,.10)]">
                        <div class="min-w-0">
                            <strong class="block truncate text-sm">{{ token.name }}</strong>
                            <span class="block text-slate-600">Permissions: {{ token.abilities.join(', ') || 'aucune' }}</span>
                            <span class="block text-slate-500">Créé le {{ token.created_at ? new Date(token.created_at).toLocaleDateString('fr-FR') : '—' }}</span>
                            <span class="block text-slate-500">Dernière utilisation: {{ token.last_used_at ? new Date(token.last_used_at).toLocaleDateString('fr-FR') : 'jamais' }}</span>
                        </div>
                        <button class="desk-button shrink-0" type="button" @click="deleteToken(token.id)">Supprimer</button>
                    </article>
                </div>
            </section>
        </div>

        <section v-if="newToken" class="mt-3 border border-emerald-700 bg-emerald-50 p-3 text-xs text-emerald-950 shadow-inner">
            <strong class="block text-sm">Token créé</strong>
            <p class="mt-1">Copie-le maintenant, il ne sera plus affiché ensuite.</p>
            <div class="mt-2 flex gap-2">
                <code class="min-w-0 flex-1 break-all border border-emerald-200 bg-white p-2 text-[11px]">{{ newToken }}</code>
                <button class="desk-button shrink-0" type="button" @click="copyToken">Copier</button>
            </div>
        </section>

        <section class="mt-3 grid gap-2 text-xs text-slate-700 md:grid-cols-2">
            <div class="border border-[#b6b6b6] bg-white p-3">
                <strong>Authentification</strong>
                <input class="mt-2 w-full bg-[#f8f8f0] p-2 font-mono" readonly value="En-tete Authorization" />
            </div>
            <div class="border border-[#b6b6b6] bg-white p-3">
                <strong>Endpoints utiles</strong>
                <ul class="mt-2 space-y-1">
                    <li><code>GET /api/items</code> — rechercher des items</li>
                    <li><code>GET /api/items/{id}</code> — détail d’un item</li>
                    <li><code>POST /api/prices</code> — envoyer un prix</li>
                </ul>
            </div>
        </section>
    </DesktopAppShell>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    applications: Array,
    maxApplications: Number,
    authorizationEndpoint: String,
    tokenEndpoint: String,
    scopes: Array,
    showRevoked: Boolean,
    revokedApplicationsCount: Number,
});

const createForm = useForm({
    name: '',
    redirect_uris: '',
});

const editForm = useForm({
    name: '',
    redirect_uris: '',
});

const editingId = ref(null);
const copiedId = ref(null);

const activeApplicationsCount = computed(() => props.applications.filter((application) => !application.revoked).length);
const canCreate = computed(() => activeApplicationsCount.value < props.maxApplications);

const createApplication = () => {
    createForm.post(route('developer.oauth-applications.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const startEditing = (application) => {
    editingId.value = application.id;
    editForm.clearErrors();
    editForm.name = application.name;
    editForm.redirect_uris = application.redirect_uris.join('\n');
};

const cancelEditing = () => {
    editingId.value = null;
    editForm.reset();
};

const updateApplication = (application) => {
    editForm.put(route('developer.oauth-applications.update', application.id), {
        preserveScroll: true,
        onSuccess: cancelEditing,
    });
};

const removeApplication = (application) => {
    if (!window.confirm(`Supprimer « ${application.name} » ? Tous ses utilisateurs seront déconnectés.`)) {
        return;
    }

    useForm({}).delete(route('developer.oauth-applications.destroy', application.id), {
        preserveScroll: true,
    });
};

const copyClientId = async (application) => {
    await navigator.clipboard.writeText(application.id);
    copiedId.value = application.id;
    window.setTimeout(() => {
        copiedId.value = null;
    }, 1600);
};

const formatDate = (date) => new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
}).format(new Date(date));
</script>

<template>
    <AppLayout title="Applications">
        <Head title="Applications" />

        <template #header>
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Applications</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Créez et gérez les applications connectées à Dofus Calculator.</p>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(300px,.75fr)]">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Créer une application</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ activeApplicationsCount }} application(s) active(s) sur {{ maxApplications }}.</p>
                            </div>
                        </div>

                        <form class="mt-6 space-y-5" @submit.prevent="createApplication">
                            <div>
                                <label for="oauth-name" class="text-sm font-medium text-gray-700 dark:text-gray-200">Nom affiché aux utilisateurs</label>
                                <input id="oauth-name" v-model="createForm.name" :disabled="!canCreate" type="text" maxlength="100" placeholder="Mon application Dofus" class="mt-2 block w-full rounded-xl border-gray-300 bg-white text-gray-900 shadow-sm focus:border-amber-500 focus:ring-amber-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                <InputError class="mt-2" :message="createForm.errors.name" />
                            </div>

                            <div>
                                <label for="oauth-redirects" class="text-sm font-medium text-gray-700 dark:text-gray-200">URL de redirection</label>
                                <textarea id="oauth-redirects" v-model="createForm.redirect_uris" :disabled="!canCreate" rows="3" placeholder="https://mon-app.fr/oauth/callback" class="mt-2 block w-full rounded-xl border-gray-300 bg-white font-mono text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:ring-amber-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Une URL par ligne. HTTPS obligatoire, sauf localhost et les schémas d’application comme <code>monapp://callback</code>.</p>
                                <InputError class="mt-2" :message="createForm.errors.redirect_uris" />
                            </div>

                            <button type="submit" :disabled="createForm.processing || !canCreate" class="inline-flex items-center rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-50">
                                {{ createForm.processing ? 'Création…' : 'Créer l’application' }}
                            </button>
                        </form>
                    </section>

                    <aside class="rounded-2xl border border-gray-200 bg-gray-950 p-6 text-gray-100 shadow-sm dark:border-gray-700">
                        <h2 class="text-lg font-semibold">Paramètres d’intégration</h2>
                        <dl class="mt-5 space-y-5 text-sm">
                            <div>
                                <dt class="text-gray-400">Autorisation</dt>
                                <dd class="mt-1 break-all font-mono text-xs text-amber-300">{{ authorizationEndpoint }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Échange du code</dt>
                                <dd class="mt-1 break-all font-mono text-xs text-amber-300">{{ tokenEndpoint }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Flux</dt>
                                <dd class="mt-1">Authorization Code + PKCE (S256)</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Permission disponible</dt>
                                <dd v-for="scope in scopes" :key="scope.id" class="mt-1"><code class="text-amber-300">{{ scope.id }}</code> — {{ scope.description }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6 border-t border-white/10 pt-4">
                            <a href="/docs/api" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm font-medium text-amber-300 transition hover:text-amber-200">
                                Consulter la documentation
                                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5H19.5V10.5M19 5L10 14M19.5 13.5V18A1.5 1.5 0 0118 19.5H6A1.5 1.5 0 014.5 18V6A1.5 1.5 0 016 4.5H10.5" />
                                </svg>
                            </a>
                        </div>
                    </aside>
                </div>

                <section>
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ showRevoked ? 'Toutes les applications' : 'Vos applications' }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Le Client ID est public et peut être intégré à votre application.</p>
                        </div>
                        <Link v-if="revokedApplicationsCount" :href="showRevoked ? route('developer.oauth-applications.index') : route('developer.oauth-applications.index', { archived: 1 })" preserve-scroll class="w-fit text-sm font-medium text-gray-500 transition hover:text-amber-600 dark:text-gray-400 dark:hover:text-amber-300">
                            {{ showRevoked ? 'Masquer les applications supprimées' : `Voir les applications supprimées (${revokedApplicationsCount})` }}
                        </Link>
                    </div>

                    <div v-if="applications.length" class="space-y-4">
                        <article v-for="application in applications" :key="application.id" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="p-5 sm:p-6">
                                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ application.name }}</h3>
                                            <span :class="application.revoked ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200'" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                                                {{ application.revoked ? 'Révoquée' : 'Active' }}
                                            </span>
                                        </div>

                                        <div class="mt-4 rounded-xl bg-gray-50 p-3 dark:bg-gray-900">
                                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Client ID</div>
                                            <div class="mt-1 flex items-center gap-2">
                                                <code class="min-w-0 flex-1 break-all text-xs text-gray-800 dark:text-gray-200">{{ application.id }}</code>
                                                <button type="button" class="shrink-0 text-xs font-semibold text-amber-600 hover:text-amber-500" @click="copyClientId(application)">{{ copiedId === application.id ? 'Copié' : 'Copier' }}</button>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Redirections</div>
                                            <ul class="mt-2 space-y-1">
                                                <li v-for="uri in application.redirect_uris" :key="uri" class="break-all font-mono text-xs text-gray-700 dark:text-gray-300">{{ uri }}</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="grid min-w-[170px] grid-cols-2 gap-3 text-center sm:grid-cols-1">
                                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-700">
                                            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ application.active_tokens_count }}</div>
                                            <div class="text-xs text-gray-500">session(s) active(s)</div>
                                        </div>
                                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-700">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatDate(application.created_at) }}</div>
                                            <div class="text-xs text-gray-500">créée le</div>
                                        </div>
                                    </div>
                                </div>

                                <form v-if="editingId === application.id" class="mt-6 space-y-4 border-t border-gray-200 pt-5 dark:border-gray-700" @submit.prevent="updateApplication(application)">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Nom</label>
                                            <input v-model="editForm.name" type="text" class="mt-2 block w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                            <InputError class="mt-2" :message="editForm.errors.name" />
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">URL de redirection</label>
                                            <textarea v-model="editForm.redirect_uris" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                                            <InputError class="mt-2" :message="editForm.errors.redirect_uris" />
                                        </div>
                                    </div>
                                    <div class="flex gap-3">
                                        <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500 disabled:opacity-50">Enregistrer</button>
                                        <button type="button" class="rounded-lg px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" @click="cancelEditing">Annuler</button>
                                    </div>
                                </form>

                                <div v-else-if="!application.revoked" class="mt-5 flex flex-wrap gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700" @click="startEditing(application)">Modifier</button>
                                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950" @click="removeApplication(application)">Supprimer</button>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-3xl">🔐</div>
                        <h3 class="mt-3 font-semibold text-gray-900 dark:text-white">Aucune application pour le moment</h3>
                        <p class="mt-1 text-sm text-gray-500">Créez votre premier Client ID avec le formulaire ci-dessus.</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

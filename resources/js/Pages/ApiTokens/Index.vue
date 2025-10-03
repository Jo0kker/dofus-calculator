<template>
  <AppLayout title="Tokens API">
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
          <h2 class="text-2xl font-bold mb-6 dark:text-gray-100">Gestion des Tokens API</h2>

          <!-- Formulaire de création -->
          <div class="mb-8 p-4 border dark:border-gray-700 rounded-lg">
            <h3 class="text-lg font-semibold mb-4 dark:text-gray-100">Créer un nouveau token</h3>
            <form @submit.prevent="createToken">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Nom du token
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                  placeholder="Ex: Application mobile"
                  required
                >
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Permissions
                </label>
                <div class="space-y-2">
                  <label class="flex items-center">
                    <input
                      type="checkbox"
                      v-model="form.abilities"
                      value="read"
                      class="mr-2 rounded text-blue-600 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600"
                    >
                    <span class="dark:text-gray-300">Lecture (recherche d'items)</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="checkbox"
                      v-model="form.abilities"
                      value="write"
                      class="mr-2 rounded text-blue-600 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600"
                    >
                    <span class="dark:text-gray-300">Écriture (mise à jour des prix)</span>
                  </label>
                </div>
              </div>

              <button
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                :disabled="form.processing"
              >
                Créer le token
              </button>
            </form>
          </div>

          <!-- Affichage du token nouvellement créé -->
          <div v-if="newToken" class="mb-6 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
            <h3 class="text-lg font-semibold mb-2 text-green-800 dark:text-green-100">
              Token créé avec succès!
            </h3>
            <p class="text-sm text-green-600 dark:text-green-300 mb-2">
              Copiez ce token maintenant, il ne sera plus visible après.
            </p>
            <div class="flex items-center gap-2">
              <code class="flex-1 p-2 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded text-xs break-all text-gray-800 dark:text-gray-200">
                {{ newToken }}
              </code>
              <button
                @click="copyToken"
                class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Copier
              </button>
            </div>
          </div>

          <!-- Liste des tokens existants -->
          <div>
            <h3 class="text-lg font-semibold mb-4 dark:text-gray-100">Tokens actifs</h3>
            <div v-if="tokens.length === 0" class="text-gray-500 dark:text-gray-400">
              Aucun token API créé pour le moment.
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="token in tokens"
                :key="token.id"
                class="flex items-center justify-between p-3 border dark:border-gray-700 rounded"
              >
                <div>
                  <div class="font-medium dark:text-gray-100">{{ token.name }}</div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    Créé le {{ new Date(token.created_at).toLocaleDateString() }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    Dernière utilisation: {{ token.last_used_at ? new Date(token.last_used_at).toLocaleDateString() : 'Jamais' }}
                  </div>
                </div>
                <button
                  @click="deleteToken(token.id)"
                  class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                >
                  Supprimer
                </button>
              </div>
            </div>
          </div>

          <!-- Documentation API -->
          <div class="mt-8 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold dark:text-gray-100">Documentation API</h3>
              <a
                href="/docs/api"
                target="_blank"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
              >
                📚 Voir la documentation complète
              </a>
            </div>
            <div class="space-y-4 text-sm">
              <div>
                <h4 class="font-semibold dark:text-gray-100">Authentification</h4>
                <p class="text-gray-600 dark:text-gray-400">
                  Incluez votre token dans l'en-tête Authorization de vos requêtes:
                </p>
                <code class="block mt-1 p-2 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded text-xs text-gray-800 dark:text-gray-200">
                  Authorization: Bearer YOUR_API_TOKEN
                </code>
              </div>

              <div>
                <h4 class="font-semibold dark:text-gray-100">Available Endpoints</h4>

                <div class="mt-2 mb-4">
                  <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Public endpoints (no auth required):</div>
                  <ul class="space-y-1 text-gray-600 dark:text-gray-400">
                    <li>
                      <code class="text-xs bg-white dark:bg-gray-800 px-1 py-0.5 rounded text-gray-800 dark:text-gray-200">GET /api/servers</code>
                      - Get all servers
                    </li>
                    <li>
                      <code class="text-xs bg-white dark:bg-gray-800 px-1 py-0.5 rounded text-gray-800 dark:text-gray-200">GET /api/items</code>
                      - Search items (with advanced filters)
                    </li>
                    <li>
                      <code class="text-xs bg-white dark:bg-gray-800 px-1 py-0.5 rounded text-gray-800 dark:text-gray-200">GET /api/items/{id}</code>
                      - Get item details
                    </li>
                  </ul>
                </div>

                <div>
                  <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Protected endpoints (require API token):</div>
                  <ul class="space-y-1 text-gray-600 dark:text-gray-400">
                    <li>
                      <code class="text-xs bg-white dark:bg-gray-800 px-1 py-0.5 rounded text-gray-800 dark:text-gray-200">GET /api/user</code>
                      - Get current user info
                    </li>
                    <li>
                      <code class="text-xs bg-white dark:bg-gray-800 px-1 py-0.5 rounded text-gray-800 dark:text-gray-200">POST /api/prices</code>
                      - Update prices (requires write permission)
                    </li>
                    <li>
                      <code class="text-xs bg-white dark:bg-gray-800 px-1 py-0.5 rounded text-gray-800 dark:text-gray-200">POST /api/prices/bulk</code>
                      - Bulk update prices (requires write permission)
                    </li>
                  </ul>
                </div>
              </div>

              <div>
                <h4 class="font-semibold dark:text-gray-100">Available Query Parameters</h4>

                <div class="mb-4">
                  <p class="text-gray-600 dark:text-gray-400 mb-2">Search and filtering parameters:</p>
                  <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1 mb-2">
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">search</code> - Basic search by item name (supports wildcards)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">name</code> - Filter by item name (supports operators)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">type</code> - Filter by item type (supports operators)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">category</code> - Filter by item category (supports operators)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">level</code> - Filter by item level (supports operators)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">dofusdb_id</code> - Filter by DofusDB ID (supports operators)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">server_id</code> - Server ID for price filtering (default: user's server or 1)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">per_page</code> - Items per page (max: 100, default: 20)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">sort</code> - Sort field (name, level, type, category, created_at, dofusdb_id)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">order</code> - Sort order (asc, desc)</div>
                    <div><code class="bg-white dark:bg-gray-800 px-1 rounded">include</code> - Comma-separated relations to include</div>
                  </div>
                </div>

                <div class="mb-4">
                  <p class="text-gray-600 dark:text-gray-400 mb-2">Search Examples:</p>
                  <code class="block p-2 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded text-xs text-gray-800 dark:text-gray-200">
                    # Basic search in item names<br>
                    GET /api/items?search=bois<br><br>
                    # Search by exact name<br>
                    GET /api/items?name=eq:Bois de Frêne<br><br>
                    # Search by name containing text<br>
                    GET /api/items?name=like:épée<br><br>
                    # Search by name starting with<br>
                    GET /api/items?name=starts:Bois<br><br>
                    # Search by DofusDB ID<br>
                    GET /api/items?dofusdb_id=289<br><br>
                    # Search by multiple DofusDB IDs<br>
                    GET /api/items?dofusdb_id=in:289,290,291<br><br>
                    # Filter by item type<br>
                    GET /api/items?type=eq:Ressource<br><br>
                    # Filter by level range<br>
                    GET /api/items?level=between:50,100<br><br>
                    # Multiple filters combined<br>
                    GET /api/items?type=like:arme&level=gte:100&name=starts:Épée
                  </code>
                </div>

                <div class="mb-4">
                  <p class="text-gray-600 dark:text-gray-400 mb-2">Include Examples (add relations to response):</p>
                  <code class="block p-2 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded text-xs text-gray-800 dark:text-gray-200">
                    # Get items with prices from ALL servers<br>
                    GET /api/items?include=prices<br><br>
                    # Get items with prices for a specific server only<br>
                    GET /api/items?include=prices&server_id=24<br><br>
                    # Get items with recipe info<br>
                    GET /api/items?include=recipe<br><br>
                    # Get items with recipe and ingredients<br>
                    GET /api/items?include=recipe,recipe.ingredients<br><br>
                    # Get items with all available data<br>
                    GET /api/items?include=prices,recipe,recipe.ingredients,usedInRecipes,metadata<br><br>
                    # Search with includes and filters<br>
                    GET /api/items?name=like:bois&include=prices,recipe
                  </code>
                  <div class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                    <strong>Available includes:</strong>
                    <strong>prices</strong> (item prices - all servers or filtered by server_id),
                    <strong>recipe</strong> (crafting recipe),
                    <strong>recipe.ingredients</strong> (recipe details),
                    <strong>usedInRecipes</strong> (where item is used),
                    <strong>metadata</strong> (item stats/effects)
                    <br><br>
                    <strong>⚠️ Important:</strong> Without the <code class="bg-white dark:bg-gray-800 px-1 rounded">include</code> parameter,
                    only basic item data is returned (no prices, no recipe). You must explicitly request what you need!
                    <br><br>
                    <strong>Server filtering:</strong>
                    - Without <code class="bg-white dark:bg-gray-800 px-1 rounded">server_id</code>: returns prices from ALL servers (each price includes its server_id)
                    <br>
                    - With <code class="bg-white dark:bg-gray-800 px-1 rounded">server_id</code>: returns prices only for that specific server
                  </div>
                </div>

                <div class="mb-4">
                  <p class="text-gray-600 dark:text-gray-400 mb-2">Sorting and Pagination:</p>
                  <code class="block p-2 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded text-xs text-gray-800 dark:text-gray-200">
                    # Sort by level (ascending)<br>
                    GET /api/items?sort=level&order=asc<br><br>
                    # Sort by name (descending) with pagination<br>
                    GET /api/items?sort=name&order=desc&per_page=50<br><br>
                    # Complete example: search, filter, include, sort<br>
                    GET /api/items?search=épée&level=gte:50&include=prices,recipe&sort=level&order=desc&server_id=1&per_page=20
                  </code>
                </div>

                <div class="mb-4">
                  <p class="text-gray-600 dark:text-gray-400 mb-2">Update prices:</p>
                  <code class="block p-2 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded text-xs text-gray-800 dark:text-gray-200">
                    POST /api/prices<br>
                    {<br>
                    &nbsp;&nbsp;"server_id": 1,<br>
                    &nbsp;&nbsp;"prices": [{<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;"item_id": 123,<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;"price": 1000<br>
                    &nbsp;&nbsp;}]<br>
                    }
                  </code>
                </div>

                <div>
                  <p class="text-gray-600 dark:text-gray-400 mb-2">Available operators for filtering:</p>
                  <div class="text-xs text-gray-600 dark:text-gray-400">
                    <strong>String fields:</strong> eq:, like:, starts:, ends:, in:<br>
                    <strong>Number fields:</strong> eq:, gt:, gte:, lt:, lte:, between:, in:
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  tokens: Array,
  newToken: String
});

const newToken = ref(props.newToken || null);

const form = useForm({
  name: '',
  abilities: ['read']
});

const createToken = () => {
  form.post('/api-tokens', {
    preserveScroll: true,
    onSuccess: (page) => {
      form.reset();
      if (page.props.newToken) {
        newToken.value = page.props.newToken;
      }
    }
  });
};

const deleteToken = (tokenId) => {
  if (confirm('Êtes-vous sûr de vouloir supprimer ce token?')) {
    router.delete(`/api-tokens/${tokenId}`, {
      preserveScroll: true,
      onSuccess: () => {
        newToken.value = null;
      }
    });
  }
};

const copyToken = () => {
  navigator.clipboard.writeText(newToken.value);
  alert('Token copié dans le presse-papier!');
};
</script>
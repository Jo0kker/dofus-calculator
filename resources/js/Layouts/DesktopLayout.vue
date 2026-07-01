<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import ServerSelector from '@/Components/ServerSelector.vue';
import DesktopWindow from '@/Components/Desktop/DesktopWindow.vue';
import { useDesktopWindows } from '@/Composables/useDesktopWindows';

const props = defineProps({
    title: String,
});

const page = usePage();

const {
    visibleWindows,
    minimizedWindows,
    openRouteWindow,
    closeWindow,
    minimizeWindow,
    focusWindow,
    toggleMaximizeWindow,
    updateWindowBounds,
    resetDesktop,
} = useDesktopWindows();

const user = computed(() => page.props.auth?.user);

const openApp = (title, url, options = {}) => {
    openRouteWindow(title, url, options);
};

const duplicateCurrentPage = () => {
    if (typeof window === 'undefined') {
        return;
    }

    openApp(props.title || 'Page courante', `${window.location.pathname}${window.location.search}`, {
        width: 980,
        height: 680,
    });
};

const logout = () => {
    router.post(route('logout'));
};

const switchToClassic = () => {
    router.post(route('user-profile-information.update'), {
        _method: 'PUT',
        name: user.value.name,
        email: user.value.email,
        interface_mode: 'classic',
    }, {
        preserveScroll: true,
    });
};

const handleDesktopWindowPayload = (payload) => {
    openRouteWindow(payload.title || 'Fenêtre', payload.url || '/', {
        id: payload.id,
        width: payload.width || 980,
        height: payload.height || 680,
    });
};

const handleDesktopMessage = (event) => {
    if (event.origin !== window.location.origin || event.data?.type !== 'dofus-desktop:open-window') {
        return;
    }

    handleDesktopWindowPayload(event.data);
};

const handleDesktopCustomEvent = (event) => {
    handleDesktopWindowPayload(event.detail || {});
};

onMounted(() => {
    window.addEventListener('message', handleDesktopMessage);
    window.addEventListener('dofus-desktop:open-window', handleDesktopCustomEvent);
});

onUnmounted(() => {
    window.removeEventListener('message', handleDesktopMessage);
    window.removeEventListener('dofus-desktop:open-window', handleDesktopCustomEvent);
});
</script>

<template>
    <div class="min-h-screen overflow-hidden bg-slate-950 text-slate-100">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.22),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(168,85,247,0.22),_transparent_34%)]" />

        <aside class="absolute left-4 top-4 z-30 flex w-64 flex-col rounded-2xl border border-white/10 bg-slate-900/80 p-3 shadow-2xl backdrop-blur">
            <div class="mb-4 flex items-center gap-3 border-b border-white/10 pb-3">
                <ApplicationMark class="h-9 w-auto" />
                <div>
                    <p class="text-sm font-bold">Dofus Calculator</p>
                    <p class="text-xs text-slate-400">Bureau multi-fenêtres</p>
                </div>
            </div>

            <div class="mb-4">
                <p class="mb-2 text-xs uppercase tracking-wide text-slate-400">Serveur</p>
                <ServerSelector />
            </div>

            <nav class="space-y-2">
                <button type="button" class="desktop-launcher" @click="openApp('Recherche items', '/items', { id: 'items', width: 980, height: 680 })">
                    🧰 Items
                </button>
                <button type="button" class="desktop-launcher" @click="openApp('Calculateur', '/calculator', { id: 'calculator', width: 1120, height: 720 })">
                    🧮 Calculateur
                </button>
                <button type="button" class="desktop-launcher" @click="openApp('Favoris', '/favorites', { id: 'favorites', width: 900, height: 640 })">
                    ⭐ Favoris
                </button>
                <button type="button" class="desktop-launcher" @click="openApp('API Tokens', '/api-tokens', { id: 'api-tokens', width: 900, height: 620 })">
                    🔑 API Tokens
                </button>
                <button type="button" class="desktop-launcher" @click="openApp('Profil', '/user/profile', { id: 'profile', width: 980, height: 720 })">
                    👤 Profil
                </button>
            </nav>

            <div class="mt-4 border-t border-white/10 pt-3 text-xs text-slate-400">
                <p class="mb-2">Astuce : ouvre plusieurs items depuis les fenêtres pour comparer sans multiplier les onglets navigateur.</p>
                <button type="button" class="text-sky-300 hover:text-sky-200" @click="resetDesktop">
                    Réinitialiser le bureau
                </button>
            </div>
        </aside>

        <main class="absolute inset-0 z-10">
            <div class="absolute left-80 top-4 z-10 rounded-xl border border-white/10 bg-slate-900/70 px-4 py-2 text-sm shadow-lg backdrop-blur">
                Fenêtre active : {{ title || 'Dofus Calculator' }}
            </div>

            <div class="absolute left-80 top-16 h-[calc(100vh-7rem)] w-[calc(100vw-21rem)] overflow-hidden rounded-2xl border border-white/10 bg-slate-100 shadow-2xl">
                <div class="flex h-10 items-center justify-between border-b border-slate-300 bg-slate-200 px-3 text-slate-700">
                    <span class="text-sm font-semibold">{{ title }}</span>
                    <button
                        type="button"
                        class="rounded bg-slate-800 px-2 py-1 text-xs font-semibold text-white hover:bg-slate-700"
                        @click="duplicateCurrentPage"
                    >
                        Dupliquer
                    </button>
                </div>
                <div class="h-[calc(100%-2.5rem)] overflow-auto bg-gray-100 text-slate-900">
                    <div class="desktop-page-slot">
                        <slot name="header" />
                        <slot />
                    </div>
                </div>
            </div>

            <DesktopWindow
                v-for="windowState in visibleWindows"
                :key="windowState.id"
                :window-state="windowState"
                @close="closeWindow"
                @focus="focusWindow"
                @minimize="minimizeWindow"
                @toggle-maximize="toggleMaximizeWindow"
                @update-bounds="updateWindowBounds"
            />
        </main>

        <footer class="absolute bottom-0 left-0 right-0 z-40 flex h-14 items-center gap-2 border-t border-white/10 bg-slate-900/90 px-4 shadow-2xl backdrop-blur">
            <button type="button" class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500" @click="openApp('Recherche items', '/items', { id: 'items' })">
                Dofus
            </button>

            <button
                v-for="windowState in minimizedWindows"
                :key="windowState.id"
                type="button"
                class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-slate-200 hover:bg-slate-700"
                @click="focusWindow(windowState.id)"
            >
                {{ windowState.title }}
            </button>

            <div class="ml-auto flex items-center gap-3 text-sm text-slate-300">
                <button type="button" class="hover:text-white" @click="switchToClassic">
                    Mode classique
                </button>
                <Link :href="route('profile.show')" class="hover:text-white">
                    {{ user?.name }}
                </Link>
                <button type="button" class="rounded bg-slate-800 px-3 py-1.5 hover:bg-slate-700" @click="logout">
                    Déconnexion
                </button>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.desktop-launcher {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.75rem;
    padding: 0.625rem 0.75rem;
    text-align: left;
    font-size: 0.875rem;
    color: rgb(226 232 240);
    transition: background-color 150ms ease, color 150ms ease;
}

.desktop-launcher:hover {
    background: rgb(51 65 85 / 0.9);
    color: white;
}

.desktop-page-slot :deep(header) {
    display: none;
}
</style>

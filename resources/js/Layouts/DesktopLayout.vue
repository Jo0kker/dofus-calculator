<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import ServerSelector from '@/Components/ServerSelector.vue';
import DesktopWindow from '@/Components/Desktop/DesktopWindow.vue';
import { desktopAppRegistry, findDesktopApp, legacyDesktopApps } from '@/Components/Desktop/desktopApps';
import { getStoredDesktopScale } from '@/Composables/useDesktopBridge';
import { useDesktopWindows } from '@/Composables/useDesktopWindows';

const props = defineProps({
    title: String,
});

const page = usePage();
const isStartMenuOpen = ref(false);
const desktopScale = ref(getStoredDesktopScale());
const desktopScaleOptions = [
    { value: 0.75, label: 'Très compacte (75%)' },
    { value: 0.85, label: 'Compacte (85%)' },
    { value: 0.9, label: 'Optimisée (90%)' },
    { value: 1, label: 'Normale (100%)' },
    { value: 1.1, label: 'Large (110%)' },
];

const {
    visibleWindows,
    minimizedWindows,
    openDesktopApp,
    openRouteWindow,
    closeWindow,
    minimizeWindow,
    focusWindow,
    toggleMaximizeWindow,
    updateWindowBounds,
    updateDesktopScale,
    resetDesktop,
} = useDesktopWindows();

const user = computed(() => page.props.auth?.user);
const activeWindowId = computed(() => {
    const orderedWindows = [...visibleWindows.value].sort((left, right) => right.z - left.z);

    return orderedWindows[0]?.id;
});

const desktopApps = desktopAppRegistry;
const legacyApps = legacyDesktopApps;
const desktopIconApps = desktopApps.slice(0, 8);

const openNativeApp = (appId, payload = {}) => {
    const app = findDesktopApp(appId);
    if (!app) {
        return;
    }

    openDesktopApp(app, payload || {});
    isStartMenuOpen.value = false;
};

const openLegacyApp = (app) => {
    openRouteWindow(app.title, app.url, {
        id: app.id,
        width: app.width,
        height: app.height,
    });
    isStartMenuOpen.value = false;
};

const changeDesktopScale = (event) => {
    desktopScale.value = updateDesktopScale(event.target.value);
};

const duplicateCurrentPage = () => {
    if (typeof window === 'undefined') {
        return;
    }

    openRouteWindow(props.title || 'Page courante', `${window.location.pathname}${window.location.search}`, {
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
    if (payload.component || payload.appId) {
        openNativeApp(payload.appId || payload.component, payload);
        return;
    }

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

const closeStartMenu = (event) => {
    if (!event.target.closest?.('[data-start-menu]')) {
        isStartMenuOpen.value = false;
    }
};

onMounted(() => {
    window.addEventListener('message', handleDesktopMessage);
    window.addEventListener('dofus-desktop:open-window', handleDesktopCustomEvent);
    window.addEventListener('click', closeStartMenu);
});

onUnmounted(() => {
    window.removeEventListener('message', handleDesktopMessage);
    window.removeEventListener('dofus-desktop:open-window', handleDesktopCustomEvent);
    window.removeEventListener('click', closeStartMenu);
});
</script>

<template>
    <div class="relative h-screen overflow-hidden bg-[#0b5f64] font-sans text-slate-950">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(209,250,229,0.45),transparent_24%),radial-gradient(circle_at_78%_28%,rgba(96,165,250,0.38),transparent_24%),linear-gradient(135deg,#13686d_0%,#0f7978_45%,#0b3d73_100%)]" />
        <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(45deg,rgba(255,255,255,.16)_25%,transparent_25%,transparent_50%,rgba(255,255,255,.16)_50%,rgba(255,255,255,.16)_75%,transparent_75%,transparent)] [background-size:64px_64px]" />

        <header class="absolute left-0 right-0 top-0 z-40 flex h-8 items-center border-b border-[#0f3970] bg-[#d4d0c8] px-2 text-xs shadow-[inset_0_1px_0_#fff,inset_0_-1px_0_#808080]">
            <div class="flex items-center gap-2 border-r border-[#8f8f8f] pr-3 font-bold text-[#0b3f88]">
                <ApplicationMark class="h-5 w-auto" />
                <span>Dofus Calculator Desktop</span>
            </div>
            <nav class="flex h-full items-center">
                <button type="button" class="top-menu-item" @click.stop="isStartMenuOpen = !isStartMenuOpen">Applications</button>
                <button type="button" class="top-menu-item" @click="duplicateCurrentPage">Fenêtre</button>
                <button type="button" class="top-menu-item" @click="resetDesktop">Réinitialiser</button>
                <button type="button" class="top-menu-item" @click="switchToClassic">Mode classique</button>
            </nav>
            <div class="ml-auto flex items-center gap-2 text-[11px] text-slate-700">
                <span>{{ user?.name }}</span>
            </div>
        </header>

        <main class="absolute inset-x-0 bottom-10 top-8 z-10">
            <section class="absolute left-5 top-5 grid w-24 grid-cols-1 gap-4">
                <button
                    v-for="app in desktopIconApps"
                    :key="app.id"
                    type="button"
                    class="desktop-icon"
                    @dblclick="openNativeApp(app.id)"
                    @click="focusWindow(app.id)"
                >
                    <span class="desktop-icon__glyph">{{ app.icon }}</span>
                    <span class="desktop-icon__label">{{ app.title }}</span>
                </button>
            </section>

            <section class="absolute right-5 top-5 w-72 border border-[#404040] bg-[#d4d0c8] p-2 shadow-[4px_4px_0_rgba(0,0,0,0.25)]">
                <div class="mb-2 bg-gradient-to-r from-[#083f88] to-[#5aa0e6] px-2 py-1 text-xs font-bold text-white">
                    Panneau système
                </div>
                <div class="space-y-3 text-xs">
                    <ServerSelector compact />
                    <label class="block border border-[#9c9c9c] bg-[#ece9d8] p-2 text-slate-800 shadow-inner">
                        <span class="mb-1 block font-bold">Taille de l’interface</span>
                        <select
                            :value="desktopScale"
                            class="w-full border border-[#808080] bg-white px-2 py-1 text-xs text-slate-900 shadow-[inset_1px_1px_0_#c0c0c0] focus:border-[#0f63bd] focus:ring-0"
                            @change="changeDesktopScale"
                        >
                            <option
                                v-for="option in desktopScaleOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <span class="mt-1 block text-[11px] text-slate-600">
                            Réduit le contenu dans les fenêtres pour afficher plus d’informations sur grand écran.
                        </span>
                    </label>
                    <div class="border border-[#9c9c9c] bg-[#ece9d8] p-2 text-slate-700 shadow-inner">
                        Double-clique une icône ou passe par Démarrer pour ouvrir plusieurs fenêtres.
                    </div>
                </div>
            </section>

            <DesktopWindow
                v-for="windowState in visibleWindows"
                :key="windowState.id"
                :is-active="windowState.id === activeWindowId"
                :desktop-scale="desktopScale"
                :window-state="windowState"
                @close="closeWindow"
                @focus="focusWindow"
                @minimize="minimizeWindow"
                @toggle-maximize="toggleMaximizeWindow"
                @update-bounds="updateWindowBounds"
                @open-app="openNativeApp"
            />
        </main>

        <footer class="absolute bottom-0 left-0 right-0 z-50 flex h-10 items-center border-t border-[#ffffff] bg-[#d4d0c8] px-1 shadow-[inset_0_1px_0_#ffffff]">
            <div class="relative" data-start-menu>
                <button
                    type="button"
                    class="start-button"
                    :class="{ 'start-button--active': isStartMenuOpen }"
                    @click.stop="isStartMenuOpen = !isStartMenuOpen"
                >
                    <span class="text-base">◆</span>
                    Démarrer
                </button>

                <div v-if="isStartMenuOpen" class="start-menu">
                    <div class="start-menu__rail">DOFUS</div>
                    <div class="flex-1 py-1">
                        <button
                            v-for="app in desktopApps"
                            :key="app.id"
                            type="button"
                            class="start-menu__item"
                            @click="openNativeApp(app.id)"
                        >
                            <span class="text-lg">{{ app.icon }}</span>
                            <span>
                                <span class="block font-bold">{{ app.title }}</span>
                                <span class="text-[10px] text-slate-600">{{ app.description }}</span>
                            </span>
                        </button>
                        <div class="my-1 border-t border-[#9c9c9c]" />
                        <button
                            v-for="app in legacyApps"
                            :key="app.id"
                            type="button"
                            class="start-menu__item opacity-80"
                            @click="openLegacyApp(app)"
                        >
                            <span class="text-lg">{{ app.icon }}</span>
                            <span>
                                <span class="block font-bold">{{ app.title }}</span>
                                <span class="text-[10px] text-slate-600">Version complète</span>
                            </span>
                        </button>
                        <div class="my-1 border-t border-[#9c9c9c]" />
                        <button type="button" class="start-menu__item" @click="resetDesktop">
                            <span class="text-lg">🧹</span>
                            <span class="font-bold">Réinitialiser le bureau</span>
                        </button>
                        <button type="button" class="start-menu__item" @click="logout">
                            <span class="text-lg">⏻</span>
                            <span class="font-bold">Déconnexion</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mx-1 h-7 border-l border-[#808080] border-r border-white" />

            <button
                v-for="windowState in visibleWindows"
                :key="`task-${windowState.id}`"
                type="button"
                class="task-button"
                :style="{ zIndex: windowState.z }"
                @click="focusWindow(windowState.id)"
            >
                {{ windowState.title }}
            </button>

            <button
                v-for="windowState in minimizedWindows"
                :key="`min-${windowState.id}`"
                type="button"
                class="task-button task-button--minimized"
                @click="focusWindow(windowState.id)"
            >
                {{ windowState.title }}
            </button>

            <div class="ml-auto flex h-8 min-w-36 items-center justify-end gap-2 border border-[#8f8f8f] bg-[#ece9d8] px-2 text-[11px] shadow-inner">
                <Link :href="route('profile.show')" class="max-w-24 truncate hover:underline">
                    {{ user?.name }}
                </Link>
                <span>{{ new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.top-menu-item {
    height: 100%;
    padding: 0 0.75rem;
    color: #1f2937;
}

.top-menu-item:hover {
    background: #0f63bd;
    color: white;
}

.desktop-icon {
    display: flex;
    min-height: 5.5rem;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid transparent;
    padding: 0.35rem;
    color: white;
    text-align: center;
    text-shadow: 1px 1px 2px rgb(0 0 0 / 0.9);
}

.desktop-icon:hover,
.desktop-icon:focus {
    border-color: rgb(191 219 254 / 0.7);
    background: rgb(59 130 246 / 0.22);
    outline: none;
}

.desktop-icon__glyph {
    display: grid;
    height: 2.75rem;
    width: 2.75rem;
    place-items: center;
    border: 1px solid rgb(255 255 255 / 0.5);
    background: rgb(15 23 42 / 0.45);
    font-size: 1.65rem;
    box-shadow: 2px 2px 0 rgb(0 0 0 / 0.25);
}

.desktop-icon__label {
    width: 100%;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.05;
}

.start-button,
.task-button {
    height: 2rem;
    border: 1px solid #505050;
    background: linear-gradient(#ffffff, #c5c0b8);
    box-shadow: inset 1px 1px 0 #ffffff, inset -1px -1px 0 #808080;
    color: #111827;
    font-size: 0.75rem;
    font-weight: 700;
}

.start-button {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0 0.65rem;
}

.start-button--active,
.start-button:active,
.task-button:active {
    box-shadow: inset -1px -1px 0 #ffffff, inset 1px 1px 0 #808080;
}

.task-button {
    margin-right: 0.25rem;
    max-width: 11rem;
    min-width: 7rem;
    overflow: hidden;
    padding: 0 0.65rem;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: left;
}

.task-button--minimized {
    opacity: 0.78;
}

.start-menu {
    position: absolute;
    bottom: 2.35rem;
    left: 0;
    display: flex;
    width: 21rem;
    min-height: 24rem;
    border: 1px solid #404040;
    background: #d4d0c8;
    box-shadow: 6px 6px 0 rgb(0 0 0 / 0.32), inset 1px 1px 0 #ffffff;
}

.start-menu__rail {
    display: flex;
    width: 3.25rem;
    align-items: end;
    justify-content: center;
    background: linear-gradient(#083f88, #0f63bd);
    padding-bottom: 0.75rem;
    color: white;
    font-size: 1.2rem;
    font-weight: 900;
    letter-spacing: 0.18em;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
}

.start-menu__item {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 0.7rem;
    text-align: left;
    font-size: 0.78rem;
    color: #111827;
}

.start-menu__item:hover {
    background: #0f63bd;
    color: white;
}

.start-menu__item:hover span span {
    color: #dbeafe;
}
</style>

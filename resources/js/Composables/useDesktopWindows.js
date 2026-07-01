import { computed, reactive } from 'vue';
import { storeDesktopScale, withDesktopFrame } from '@/Composables/useDesktopBridge';

const STORAGE_KEY = 'dofus-calculator.desktop.windows.v1';
const MIN_WIDTH = 420;
const MIN_HEIGHT = 280;
const DEFAULT_WIDTH = 900;
const DEFAULT_HEIGHT = 640;

const defaultWindows = [
    {
        id: 'items',
        title: 'Items',
        url: '/items?desktop_frame=1',
        x: 40,
        y: 40,
        w: 980,
        h: 680,
        z: 1,
        minimized: false,
        maximized: false,
    },
];

const normalizeDesktopUrl = (url) => withDesktopFrame(url);

const readStoredWindows = () => {
    if (typeof window === 'undefined') {
        return defaultWindows;
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (!stored) {
            return defaultWindows;
        }

        const parsed = JSON.parse(stored);
        if (!Array.isArray(parsed)) {
            return defaultWindows;
        }

        return parsed.map((windowState, index) => ({
            id: String(windowState.id || `window-${index}`),
            title: String(windowState.title || 'Fenêtre'),
            url: normalizeDesktopUrl(String(windowState.url || '/?desktop_frame=1')),
            x: Number(windowState.x ?? 48 + index * 32),
            y: Number(windowState.y ?? 48 + index * 32),
            w: Math.max(Number(windowState.w ?? DEFAULT_WIDTH), MIN_WIDTH),
            h: Math.max(Number(windowState.h ?? DEFAULT_HEIGHT), MIN_HEIGHT),
            z: Number(windowState.z ?? index + 1),
            minimized: Boolean(windowState.minimized),
            maximized: Boolean(windowState.maximized),
            previousBounds: windowState.previousBounds || null,
        }));
    } catch (error) {
        console.warn('Impossible de charger le bureau sauvegardé', error);
        return defaultWindows;
    }
};

const state = reactive({
    windows: readStoredWindows(),
    nextZ: 10,
});

const persist = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state.windows));
};

const nextCascadePosition = () => {
    const offset = state.windows.length * 34;

    return {
        x: 48 + (offset % 260),
        y: 48 + (offset % 180),
    };
};

export function useDesktopWindows() {
    const visibleWindows = computed(() => state.windows.filter((windowState) => !windowState.minimized));
    const minimizedWindows = computed(() => state.windows.filter((windowState) => windowState.minimized));

    const focusWindow = (id) => {
        const windowState = state.windows.find((candidate) => candidate.id === id);
        if (!windowState) {
            return;
        }

        windowState.z = state.nextZ++;
        windowState.minimized = false;
        persist();
    };

    const openWindow = ({ id, title, url, width = DEFAULT_WIDTH, height = DEFAULT_HEIGHT }) => {
        const existingWindow = state.windows.find((candidate) => candidate.id === id);
        if (existingWindow) {
            existingWindow.title = title;
            existingWindow.url = normalizeDesktopUrl(url);
            existingWindow.minimized = false;
            focusWindow(existingWindow.id);
            persist();
            return existingWindow;
        }

        const position = nextCascadePosition();
        const windowState = {
            id,
            title,
            url: normalizeDesktopUrl(url),
            x: position.x,
            y: position.y,
            w: Math.max(width, MIN_WIDTH),
            h: Math.max(height, MIN_HEIGHT),
            z: state.nextZ++,
            minimized: false,
            maximized: false,
            previousBounds: null,
        };

        state.windows.push(windowState);
        persist();
        return windowState;
    };

    const openRouteWindow = (title, url, options = {}) => openWindow({
        id: options.id || `${title}-${url}`.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
        title,
        url,
        width: options.width,
        height: options.height,
    });

    const closeWindow = (id) => {
        const index = state.windows.findIndex((candidate) => candidate.id === id);
        if (index !== -1) {
            state.windows.splice(index, 1);
            persist();
        }
    };

    const minimizeWindow = (id) => {
        const windowState = state.windows.find((candidate) => candidate.id === id);
        if (windowState) {
            windowState.minimized = true;
            persist();
        }
    };

    const toggleMaximizeWindow = (id) => {
        const windowState = state.windows.find((candidate) => candidate.id === id);
        if (!windowState) {
            return;
        }

        if (windowState.maximized) {
            Object.assign(windowState, windowState.previousBounds || {});
            windowState.maximized = false;
            windowState.previousBounds = null;
        } else {
            windowState.previousBounds = {
                x: windowState.x,
                y: windowState.y,
                w: windowState.w,
                h: windowState.h,
            };
            windowState.x = 12;
            windowState.y = 12;
            windowState.w = Math.max((globalThis.innerWidth || 1280) - 24, MIN_WIDTH);
            windowState.h = Math.max((globalThis.innerHeight || 800) - 92, MIN_HEIGHT);
            windowState.maximized = true;
        }

        focusWindow(id);
        persist();
    };

    const updateWindowBounds = (id, bounds) => {
        const windowState = state.windows.find((candidate) => candidate.id === id);
        if (!windowState || windowState.maximized) {
            return;
        }

        Object.assign(windowState, bounds);
        persist();
    };

    const updateDesktopScale = (scale) => {
        const normalizedScale = storeDesktopScale(scale);

        state.windows.forEach((windowState) => {
            windowState.url = withDesktopFrame(windowState.url, { scale: normalizedScale });
        });

        persist();

        return normalizedScale;
    };

    const resetDesktop = () => {
        state.windows.splice(0, state.windows.length, ...defaultWindows.map((windowState) => ({
            ...windowState,
            url: withDesktopFrame(windowState.url),
        })));
        state.nextZ = 10;
        persist();
    };

    return {
        windows: state.windows,
        visibleWindows,
        minimizedWindows,
        openWindow,
        openRouteWindow,
        closeWindow,
        minimizeWindow,
        focusWindow,
        toggleMaximizeWindow,
        updateWindowBounds,
        updateDesktopScale,
        resetDesktop,
    };
}

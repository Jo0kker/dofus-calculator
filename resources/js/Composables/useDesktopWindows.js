import { computed, reactive } from 'vue';
import { storeDesktopScale, withDesktopFrame } from '@/Composables/useDesktopBridge';

const STORAGE_KEY = 'dofus-calculator.desktop.windows.v1';
const MIN_WIDTH = 420;
const MIN_HEIGHT = 280;
const DEFAULT_WIDTH = 900;
const DEFAULT_HEIGHT = 640;
const DESKTOP_TOP_BAR_HEIGHT = 32;
const DESKTOP_TASKBAR_HEIGHT = 40;
const DESKTOP_SAFE_MARGIN = 16;

const defaultWindows = [
    {
        id: 'workspace',
        title: 'Workspace',
        url: null,
        component: 'workspace',
        payload: {},
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

const getWorkspaceBounds = () => {
    const viewportWidth = globalThis.innerWidth || 1280;
    const viewportHeight = globalThis.innerHeight || 800;

    return {
        width: Math.max(viewportWidth - DESKTOP_SAFE_MARGIN * 2, MIN_WIDTH),
        height: Math.max(viewportHeight - DESKTOP_TOP_BAR_HEIGHT - DESKTOP_TASKBAR_HEIGHT - DESKTOP_SAFE_MARGIN * 2, MIN_HEIGHT),
    };
};

const normalizeWindowBounds = (windowState) => {
    const workspace = getWorkspaceBounds();
    const width = Math.min(Math.max(Number(windowState.w ?? DEFAULT_WIDTH), MIN_WIDTH), workspace.width);
    const height = Math.min(Math.max(Number(windowState.h ?? DEFAULT_HEIGHT), MIN_HEIGHT), workspace.height);
    const x = Math.min(Math.max(Number(windowState.x ?? DESKTOP_SAFE_MARGIN), 0), Math.max(workspace.width - width, 0));
    const y = Math.min(Math.max(Number(windowState.y ?? DESKTOP_SAFE_MARGIN), 0), Math.max(workspace.height - height, 0));

    return {
        x,
        y,
        w: width,
        h: height,
    };
};

const buildDefaultWindows = () => defaultWindows.map((windowState) => ({
    ...windowState,
    ...normalizeWindowBounds(windowState),
    url: windowState.url ? normalizeDesktopUrl(windowState.url) : null,
}));

const readStoredWindows = () => {
    if (typeof window === 'undefined') {
        return buildDefaultWindows();
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (!stored) {
            return buildDefaultWindows();
        }

        const parsed = JSON.parse(stored);
        if (!Array.isArray(parsed)) {
            return buildDefaultWindows();
        }

        return parsed.map((windowState, index) => {
            const bounds = normalizeWindowBounds({
                x: windowState.x ?? 48 + index * 32,
                y: windowState.y ?? 48 + index * 32,
                w: windowState.w ?? DEFAULT_WIDTH,
                h: windowState.h ?? DEFAULT_HEIGHT,
            });

            return {
                id: String(windowState.id || `window-${index}`),
                title: String(windowState.title || 'Fenêtre'),
                url: windowState.url ? normalizeDesktopUrl(String(windowState.url)) : null,
                component: windowState.component || null,
                payload: windowState.payload || {},
                ...bounds,
                z: Number(windowState.z ?? index + 1),
                minimized: Boolean(windowState.minimized),
                maximized: Boolean(windowState.maximized),
                previousBounds: windowState.previousBounds || null,
            };
        });
    } catch (error) {
        console.warn('Impossible de charger le bureau sauvegardé', error);
        return buildDefaultWindows();
    }
};

const initialWindows = readStoredWindows();
const initialNextZ = Math.max(10, ...initialWindows.map((windowState) => Number(windowState.z) || 0)) + 1;

const state = reactive({
    windows: initialWindows,
    nextZ: initialNextZ,
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

    const openWindow = ({ id, title, url = null, component = null, payload = {}, width = DEFAULT_WIDTH, height = DEFAULT_HEIGHT }) => {
        const existingWindow = state.windows.find((candidate) => candidate.id === id);
        if (existingWindow) {
            existingWindow.title = title;
            existingWindow.url = url ? normalizeDesktopUrl(url) : null;
            existingWindow.component = component;
            existingWindow.payload = payload;
            Object.assign(existingWindow, normalizeWindowBounds(existingWindow));
            existingWindow.minimized = false;
            focusWindow(existingWindow.id);
            persist();
            return existingWindow;
        }

        const position = nextCascadePosition();
        const bounds = normalizeWindowBounds({
            x: position.x,
            y: position.y,
            w: width,
            h: height,
        });
        const windowState = {
            id,
            title,
            url: url ? normalizeDesktopUrl(url) : null,
            component,
            payload,
            ...bounds,
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

    const openDesktopApp = (app, payload = {}) => openWindow({
        id: payload.windowId || app.windowId || app.id,
        title: payload.title || app.title,
        component: app.component,
        payload,
        width: payload.width || app.width,
        height: payload.height || app.height,
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
            const workspace = getWorkspaceBounds();
            windowState.x = DESKTOP_SAFE_MARGIN;
            windowState.y = DESKTOP_SAFE_MARGIN;
            windowState.w = Math.max(workspace.width - DESKTOP_SAFE_MARGIN * 2, MIN_WIDTH);
            windowState.h = Math.max(workspace.height - DESKTOP_SAFE_MARGIN * 2, MIN_HEIGHT);
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

        Object.assign(windowState, normalizeWindowBounds({
            ...windowState,
            ...bounds,
        }));
        persist();
    };

    const updateDesktopScale = (scale) => {
        const normalizedScale = storeDesktopScale(scale);

        state.windows.forEach((windowState) => {
            if (windowState.url) {
                windowState.url = withDesktopFrame(windowState.url, { scale: normalizedScale });
            }
        });

        persist();

        return normalizedScale;
    };

    const resetDesktop = () => {
        state.windows.splice(0, state.windows.length, ...buildDefaultWindows());
        state.nextZ = 10;
        persist();
    };

    return {
        windows: state.windows,
        visibleWindows,
        minimizedWindows,
        openWindow,
        openDesktopApp,
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

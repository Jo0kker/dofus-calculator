import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const DESKTOP_SCALE_STORAGE_KEY = 'dofus-calculator.desktop.ui-scale.v1';
const DEFAULT_DESKTOP_SCALE = 0.9;
const MIN_DESKTOP_SCALE = 0.75;
const MAX_DESKTOP_SCALE = 1.1;

export function normalizeDesktopScale(value, fallback = DEFAULT_DESKTOP_SCALE) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return fallback;
    }

    return Math.min(Math.max(numericValue, MIN_DESKTOP_SCALE), MAX_DESKTOP_SCALE);
}

export function getStoredDesktopScale() {
    if (typeof window === 'undefined') {
        return DEFAULT_DESKTOP_SCALE;
    }

    return normalizeDesktopScale(window.localStorage.getItem(DESKTOP_SCALE_STORAGE_KEY));
}

export function storeDesktopScale(scale) {
    if (typeof window === 'undefined') {
        return DEFAULT_DESKTOP_SCALE;
    }

    const normalizedScale = normalizeDesktopScale(scale);
    window.localStorage.setItem(DESKTOP_SCALE_STORAGE_KEY, String(normalizedScale));

    return normalizedScale;
}

export function withDesktopFrame(url, options = {}) {
    if (!url) {
        return withDesktopFrame('/', options);
    }

    if (url.startsWith('http')) {
        return url;
    }

    const [path, query = ''] = url.split('?');
    const params = new URLSearchParams(query);
    params.set('desktop_frame', '1');
    params.set('desktop_scale', String(normalizeDesktopScale(options.scale ?? getStoredDesktopScale())));

    return `${path}?${params.toString()}`;
}

export function useDesktopBridge() {
    const page = usePage();

    const isDesktopFrame = computed(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.self !== window.top || new URLSearchParams(window.location.search).get('desktop_frame') === '1';
    });

    const isDesktopUser = computed(() => page.props.auth?.user?.interface_mode === 'desktop');

    const openDesktopWindow = ({ title, url, id, width = 980, height = 680 }) => {
        if (typeof window === 'undefined') {
            return false;
        }

        if (!isDesktopFrame.value && !isDesktopUser.value) {
            return false;
        }

        const payload = {
            type: 'dofus-desktop:open-window',
            title,
            url: withDesktopFrame(url),
            id,
            width,
            height,
        };

        if (window.self !== window.top) {
            window.parent.postMessage(payload, window.location.origin);
            return true;
        }

        window.dispatchEvent(new CustomEvent('dofus-desktop:open-window', { detail: payload }));
        return true;
    };

    return {
        isDesktopFrame,
        isDesktopUser,
        openDesktopWindow,
    };
}

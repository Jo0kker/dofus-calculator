import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function withDesktopFrame(url) {
    if (!url) {
        return '/?desktop_frame=1';
    }

    if (url.startsWith('http')) {
        return url;
    }

    const [path, query = ''] = url.split('?');
    const params = new URLSearchParams(query);
    params.set('desktop_frame', '1');

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

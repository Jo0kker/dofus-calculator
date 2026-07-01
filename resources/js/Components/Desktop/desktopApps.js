import CalculatorDeskApp from '@/Components/Desktop/Apps/CalculatorDeskApp.vue';
import CompareDeskApp from '@/Components/Desktop/Apps/CompareDeskApp.vue';
import CraftCartDeskApp from '@/Components/Desktop/Apps/CraftCartDeskApp.vue';
import FavoritesDeskApp from '@/Components/Desktop/Apps/FavoritesDeskApp.vue';
import ItemInspectorDeskApp from '@/Components/Desktop/Apps/ItemInspectorDeskApp.vue';
import ItemSearchDeskApp from '@/Components/Desktop/Apps/ItemSearchDeskApp.vue';
import NotificationsDeskApp from '@/Components/Desktop/Apps/NotificationsDeskApp.vue';
import PriceWatchDeskApp from '@/Components/Desktop/Apps/PriceWatchDeskApp.vue';
import QuickActionsDeskApp from '@/Components/Desktop/Apps/QuickActionsDeskApp.vue';
import ServerDeskApp from '@/Components/Desktop/Apps/ServerDeskApp.vue';
import WorkspaceDeskApp from '@/Components/Desktop/Apps/WorkspaceDeskApp.vue';

export const desktopAppComponents = {
    calculator: CalculatorDeskApp,
    compare: CompareDeskApp,
    craftCart: CraftCartDeskApp,
    favorites: FavoritesDeskApp,
    itemInspector: ItemInspectorDeskApp,
    itemSearch: ItemSearchDeskApp,
    notifications: NotificationsDeskApp,
    priceWatch: PriceWatchDeskApp,
    quickActions: QuickActionsDeskApp,
    server: ServerDeskApp,
    workspace: WorkspaceDeskApp,
};

export const desktopAppRegistry = [
    { id: 'workspace', title: 'Workspace', icon: '🧭', component: 'workspace', width: 760, height: 560, group: 'Pilotage', description: 'Vue d’ensemble, raccourcis et flow de travail.' },
    { id: 'itemSearch', title: 'Recherche native', icon: '🔎', component: 'itemSearch', width: 760, height: 680, group: 'Items', description: 'Recherche rapide desktop-first.' },
    { id: 'itemInspector', title: 'Inspecteur item', icon: '📦', component: 'itemInspector', width: 540, height: 680, group: 'Items', description: 'Détail compact, craft, prix et actions.' },
    { id: 'craftCart', title: 'Panier craft', icon: '🧺', component: 'craftCart', width: 680, height: 620, group: 'Craft', description: 'Prépare une session craft avec quantités.' },
    { id: 'compare', title: 'Comparateur', icon: '⚖️', component: 'compare', width: 760, height: 560, group: 'Items', description: 'Compare plusieurs items côte à côte.' },
    { id: 'calculator', title: 'Calculateur', icon: '🧮', component: 'calculator', width: 720, height: 620, group: 'Craft', description: 'Calculateur contextualisé desktop.' },
    { id: 'priceWatch', title: 'Watchlist prix', icon: '📈', component: 'priceWatch', width: 700, height: 560, group: 'Commerce', description: 'Surveille les opportunités et objectifs de prix.' },
    { id: 'favorites', title: 'Favoris', icon: '⭐', component: 'favorites', width: 620, height: 560, group: 'Organisation', description: 'Épingles, listes et items récents.' },
    { id: 'server', title: 'Serveur', icon: '🌍', component: 'server', width: 440, height: 420, group: 'Système', description: 'Contexte serveur du workspace.' },
    { id: 'quickActions', title: 'Actions rapides', icon: '⚡', component: 'quickActions', width: 500, height: 460, group: 'Système', description: 'Launcher, raccourcis et prochaines actions.' },
    { id: 'notifications', title: 'Notifications', icon: '🔔', component: 'notifications', width: 520, height: 460, group: 'Système', description: 'Centre d’événements du workspace.' },
];

export const legacyDesktopApps = [
    { id: 'legacy-items', title: 'Items legacy', url: '/items', icon: '🧰', width: 1040, height: 700, group: 'Legacy' },
    { id: 'legacy-calculator', title: 'Calculateur legacy', url: '/calculator', icon: '🧮', width: 1120, height: 720, group: 'Legacy' },
    { id: 'legacy-favorites', title: 'Favoris legacy', url: '/favorites', icon: '⭐', width: 940, height: 660, group: 'Legacy' },
    { id: 'api-tokens', title: 'API Tokens', url: '/api-tokens', icon: '🔑', width: 920, height: 640, group: 'Legacy' },
    { id: 'profile', title: 'Profil', url: '/user/profile', icon: '👤', width: 980, height: 720, group: 'Legacy' },
];

export const findDesktopApp = (id) => desktopAppRegistry.find((app) => app.id === id);

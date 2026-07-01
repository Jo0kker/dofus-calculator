import ApiTokensDeskApp from '@/Components/Desktop/Apps/ApiTokensDeskApp.vue';
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
    apiTokens: ApiTokensDeskApp,
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
    { id: 'workspace', title: 'Accueil bureau', icon: '🧭', component: 'workspace', width: 760, height: 560, group: 'Pilotage', description: 'Raccourcis et outils principaux' },
    { id: 'itemSearch', title: 'Recherche d’items', icon: '🔎', component: 'itemSearch', width: 760, height: 680, group: 'Items', description: 'Trouver rapidement un item' },
    { id: 'itemInspector', title: 'Détail item', icon: '📦', component: 'itemInspector', width: 540, height: 680, group: 'Items', description: 'Infos, craft, prix et actions' },
    { id: 'craftCart', title: 'Panier craft', icon: '🧺', component: 'craftCart', width: 680, height: 620, group: 'Craft', description: 'Liste des crafts à préparer' },
    { id: 'compare', title: 'Comparateur', icon: '⚖️', component: 'compare', width: 760, height: 560, group: 'Items', description: 'Comparer plusieurs items' },
    { id: 'calculator', title: 'Calculateur', icon: '🧮', component: 'calculator', width: 720, height: 620, group: 'Craft', description: 'Calculer coûts et quantités' },
    { id: 'priceWatch', title: 'Suivi des prix', icon: '📈', component: 'priceWatch', width: 700, height: 560, group: 'Commerce', description: 'Objectifs d’achat et alertes' },
    { id: 'favorites', title: 'Favoris', icon: '⭐', component: 'favorites', width: 620, height: 560, group: 'Organisation', description: 'Items épinglés et récents' },
    { id: 'server', title: 'Serveur', icon: '🌍', component: 'server', width: 440, height: 420, group: 'Système', description: 'Choisir le serveur de prix' },
    { id: 'quickActions', title: 'Actions rapides', icon: '⚡', component: 'quickActions', width: 500, height: 460, group: 'Système', description: 'Ouvrir les outils utiles' },
    { id: 'notifications', title: 'Notifications', icon: '🔔', component: 'notifications', width: 520, height: 460, group: 'Système', description: 'Messages et alertes' },
    { id: 'apiTokens', title: 'API Tokens', icon: '🔑', component: 'apiTokens', width: 840, height: 640, group: 'Compte', description: 'Créer et gérer les clés API' },
];

export const legacyDesktopApps = [
    { id: 'legacy-items', title: 'Catalogue items', url: '/items', icon: '🧰', width: 1040, height: 700, group: 'Pages' },
    { id: 'legacy-calculator', title: 'Page calculateur', url: '/calculator', icon: '🧮', width: 1120, height: 720, group: 'Pages' },
    { id: 'legacy-favorites', title: 'Page favoris', url: '/favorites', icon: '⭐', width: 940, height: 660, group: 'Pages' },
    { id: 'profile', title: 'Profil', url: '/user/profile', icon: '👤', width: 980, height: 720, group: 'Compte' },
];

export const findDesktopApp = (id) => desktopAppRegistry.find((app) => app.id === id);

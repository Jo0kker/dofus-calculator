# Dofus Calculator

Application web Laravel/Vue pour estimer les coûts de craft, suivre les prix des items et optimiser la rentabilité sur les serveurs Dofus.

## Fonctionnalités

- recherche d'items et consultation des recettes
- calcul de coût de craft et estimation de rentabilité
- mise à jour et modération des prix par serveur
- favoris et espace personnel pour suivre les items utiles
- API JSON pour lire les données et soumettre des prix
- interface desktop/workspace pour les utilisateurs connectés

## Stack technique

- PHP 8.4 / Laravel 12
- Vue 3 + Inertia.js
- PostgreSQL
- Vite + Tailwind CSS
- Laravel Sanctum / Jetstream

## Démarrage rapide

### Option recommandée : Docker / Laravel Sail

1. Copiez l'environnement : `cp .env.example .env`
2. Installez les dépendances PHP : `composer install`
3. Installez les dépendances front : `yarn install --frozen-lockfile`
4. Démarrez l'environnement : `./vendor/bin/sail up -d`
5. Générez la clé d'application : `./vendor/bin/sail artisan key:generate`
6. Lancez les migrations : `./vendor/bin/sail artisan migrate`
7. Ouvrez l'application sur `http://localhost`

### Option locale

Prérequis : PHP 8.4, Composer, Node.js 22+, Yarn et PostgreSQL.

1. `cp .env.example .env`
2. `composer install`
3. `yarn install --frozen-lockfile`
4. Configurez la base PostgreSQL dans `.env`
5. `php artisan key:generate`
6. `php artisan migrate`
7. `composer run dev`

## Import des données

Le projet inclut des commandes Artisan pour peupler les données Dofus :

- `php artisan dofus:import-servers`
- `php artisan dofus:import-recipes`
- `php artisan dofus:import --with-recipes`

## Validation locale

- tests : `composer test`
- build front : `yarn build`
- formatage PHP : `./vendor/bin/pint --test`

## API

Les routes API publiques sont disponibles sous `/api`.
La documentation OpenAPI peut être générée et consultée via Scramble selon la configuration du projet.

## Déploiement

Un push sur la branche `main` déclenche automatiquement le déploiement de l'application. Aucune étape manuelle n'est nécessaire.

## Contribution

Les contributions sont les bienvenues. Merci de lire [CONTRIBUTING.md](CONTRIBUTING.md) avant d'ouvrir une issue ou une pull request.

## Sécurité

Pour signaler une vulnérabilité, consultez [SECURITY.md](SECURITY.md).

## Licence

Ce projet est distribué sous licence [MIT](LICENSE).

## Mentions

Dofus est un MMORPG édité par Ankama. Ce projet communautaire n'est ni affilié, ni approuvé par Ankama.

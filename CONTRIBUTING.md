# Contribuer

Merci de vouloir améliorer Dofus Calculator.

## Avant de commencer

- vérifiez qu'aucune issue ou pull request ne couvre déjà le sujet
- pour les changements importants, ouvrez d'abord une issue pour discuter de l'approche
- gardez les modifications ciblées et faciles à relire

## Environnement de développement

1. installez les dépendances avec `composer install` puis `yarn install --frozen-lockfile`
2. copiez `.env.example` vers `.env`
3. configurez PostgreSQL puis lancez `php artisan key:generate` et `php artisan migrate`
4. démarrez l'application avec Laravel Sail ou `composer run dev`

## Qualité attendue

Avant d'ouvrir une pull request, exécutez si possible :

- `composer test`
- `yarn build`
- `./vendor/bin/pint --test`

## Pull requests

- décrivez clairement le problème traité et la solution proposée
- ajoutez des captures d'écran si l'interface change
- signalez les migrations, imports ou étapes manuelles nécessaires
- liez l'issue concernée si elle existe

## Style de contribution

- respectez la structure Laravel/Vue déjà en place
- évitez les refontes non liées au sujet
- ne commitez ni secrets, ni données sensibles, ni fichiers générés inutiles

En participant à ce projet, vous acceptez de respecter le [code de conduite](CODE_OF_CONDUCT.md).

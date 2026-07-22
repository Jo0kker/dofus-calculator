# Confiance des prix communautaires

## Objectif

Le système doit fournir un prix utile dès le premier relevé, tout en devenant plus robuste lorsque la communauté grandit. Il ne cherche pas à classer les personnes comme « bonnes » ou « mauvaises » : la fiabilité estime uniquement la probabilité que leur prochain relevé soit cohérent.

Trois métriques restent volontairement séparées :

- la **fiabilité du contributeur**, apprise sur ses relevés évaluables ;
- la **plausibilité d’un relevé**, mesurée par rapport aux autres relevés indépendants du même objet ;
- la **confiance du prix communautaire**, calculée à partir des preuves disponibles, de leur accord, de leur fraîcheur et de la maturité des contributeurs.

## Modèle de données

`price_histories` est le journal des observations individuelles. `item_prices` est la projection du consensus communautaire courant. Les métriques calculées sont conservées avec une version d’algorithme afin de permettre un recalcul ultérieur.

Un même utilisateur peut envoyer plusieurs relevés, mais seul son relevé valide le plus récent dans la fenêtre de 30 jours influence le consensus. Tous ses envois restent comptabilisés comme contributions.

## Calcul V1

Le consensus part d’une médiane géométrique, mesure l’écart logarithmique de chaque observation, puis retient une médiane pondérée. Le poids d’un relevé combine :

- la maturité du compte et son nombre d’évaluations ;
- la fiabilité estimée du contributeur ;
- la fraîcheur du relevé ;
- sa plausibilité par rapport au groupe.

La confiance finale combine les preuves disponibles (45 %), l’accord entre relevés (30 %), la fiabilité moyenne (15 %) et la fraîcheur (10 %). Le nombre de contributeurs attendu s’adapte à l’activité récente du serveur, entre 3 et 8.

Des plafonds protègent le démarrage et les petits échantillons :

- un contributeur : confiance faible, au maximum 39 % ;
- deux contributeurs : au maximum 64 % ;
- moins de deux contributeurs expérimentés : au maximum 69 %.

Le prix reste toutefois publié immédiatement. L’interface explique pourquoi sa confiance est faible, moyenne ou élevée.

## Apprentissage de la fiabilité

Un relevé n’est évalué automatiquement que lorsqu’au moins trois contributeurs indépendants sont disponibles. Sa référence est calculée sans son propre relevé (`leave-one-out`), afin d’éviter qu’il valide lui-même sa valeur.

La fiabilité commence avec un a priori neutre de 60 %, pondéré comme cinq évaluations. Les évaluations récentes comptent davantage et une seule évaluation active est retenue par couple objet/serveur : répéter le même relevé ne permet donc pas de fabriquer de l’expérience. Cette valeur reste interne et n’est jamais exposée dans les interfaces ou leurs réponses. Un signalement validé par la modération constitue un signal négatif fort lorsque le prix affiché provient d’un seul contributeur.

Un signalement visant un consensus multi-contributeurs n’est jamais attribué arbitrairement à son dernier contributeur. Une modération plus fine des observations individuelles pourra être ajoutée ensuite.

## Contrat public

La fiabilité d’une personne, ses évaluations, la plausibilité individuelle et son poids restent internes. Les réponses web, bureau et API n’exposent que la confiance du prix, le volume de preuves récentes, la date réelle du dernier relevé et des raisons qualitatives.

Le badge « Contributeur communautaire » est identique pour tout le monde. Il indique une présence humaine et ne dépend d’aucun score, seuil ou niveau caché.

## Recalcul et exploitation

Le consensus est recalculé à chaque nouveau relevé et quotidiennement à 03:30 avec :

```bash
php artisan prices:recalculate-confidence
```

Les options `--server` et `--item` permettent un recalcul ciblé. La commande rend également le dispositif rétroactif pour l’historique existant.

## Hors périmètre V1

- déblocage automatique de l’import CSV ;
- détection avancée de comptes liés ou de comportements coordonnés ;
- seuils propres à la volatilité de chaque catégorie d’objet ;
- bannissement ou restriction automatique d’un compte.

Ces extensions devront utiliser les métriques comme signaux explicables, sans transformer le score de fiabilité en unique règle d’autorisation.

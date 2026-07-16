# Revue BMAD Party Mode — prix communautaires

Date : 16 juillet 2026

## Question examinée

Comment rendre la communauté visible sans exposer ni gamifier la fiabilité interne, tout en gardant un consensus utile au démarrage et robuste face aux imports, au spam et aux valeurs atypiques ?

La revue a confronté trois voix indépendantes : produit/UX, adversarial/statistique et architecture/exploitation.

## Décisions communes

- Le badge d’une personne est fixe et purement participatif : « Contributeur communautaire ».
- Le niveau de confiance appartient au prix, jamais à une personne.
- La confiance publique est qualitative ; son pourcentage exact reste interne.
- Aucune réponse publique ne contient de fiabilité, évaluation, plausibilité individuelle, poids ou agrégat permettant de les reconstruire.
- Plusieurs objets d’un import sont des observations légitimes et indépendantes.
- Plusieurs mises à jour du même couple objet/serveur ne donnent qu’une voix courante et une preuve de fiabilité.
- Une référence indépendante dispersée ne doit pas évaluer la fiabilité d’une personne.
- La date présentée à l’utilisateur est celle du dernier relevé, pas celle du recalcul de l’algorithme.

## P0 traités dans cette PR

1. **Fuite de métriques internes** : champs masqués sur les historiques et détails publics réduits à la date, la fenêtre et des raisons qualitatives.
2. **Éviction par spam** : sélection SQL du dernier relevé de chaque contributeur avant agrégation, avec index adapté.
3. **Lots ambigus ou excessifs** : maximum de 500 lignes, déduplication par objet avec « le dernier gagne » et bornes de prix cohérentes entre web et API.
4. **Apprentissage ambigu** : aucune évaluation lorsque les autres sources sont trop dispersées.
5. **Modération non durable** : un consensus rejeté reste verrouillé pendant les recalculs et un report déjà traité ne peut pas appliquer deux fois sa pénalité.
6. **Fraîcheur trompeuse** : zéro preuve récente au-delà de la fenêtre et conservation de la date réelle du dernier relevé.

## P1 — prochaines features

- Définir un état « prix disputé » pour deux valeurs fortement divergentes ; la médiane pondérée départage encore une égalité en faveur de la valeur basse.
- Propager la confiance des ingrédients dans les résultats des calculateurs et remplacer un verdict ferme par « estimation fragile » lorsqu’une donnée déterminante est faible.
- Ajouter un verrou transactionnel par couple objet/serveur pour les soumissions concurrentes.
- Ajouter une clé d’idempotence aux imports et déplacer les gros lots vers un traitement asynchrone avec résultat par ligne.
- Remplacer le verrou conservateur actuel par un modèle explicite de modération (`locked_at`, auteur, motif, version du consensus et règle de levée).
- Renforcer la résistance aux cohortes de comptes complices avec ancienneté, diversité temporelle et signaux de corrélation ; la cohérence entre plusieurs comptes ne prouve pas la vérité du marché.
- Décider si le compteur brut de contributions reste public. Il mesure l’activité mais peut être gonflé et ne doit jamais être présenté comme une preuve de qualité.

## P2 — calibration et exploitation

- Adapter les fenêtres à la liquidité ou à la catégorie des objets.
- Séparer l’historique des relevés bruts de l’évolution du consensus.
- Ajouter métriques et alertes sur les durées de recalcul, marchés en échec, divergences et changements de version d’algorithme.
- Améliorer l’accessibilité des détails de confiance et des contrôles compacts du bureau.

## Questions produit encore ouvertes

1. Conserver publiquement le nombre total de relevés envoyés par une personne ?
2. À partir de quel niveau de preuve un relevé extrême doit-il être mis en quarantaine plutôt que simplement sous-pondéré ?
3. Un import API doit-il demander explicitement « personnel » ou « communautaire » avant toute écriture ?

## Critères de non-régression

- 501 relevés d’un même compte ne font jamais disparaître les autres contributeurs.
- Un lot contenant deux fois le même objet produit une seule observation, avec la dernière valeur.
- Un prix vieux de plus de 30 jours affiche zéro observation récente et sa vraie date.
- Une double validation du même report ne produit qu’une pénalité.
- Un consensus rejeté ne réapparaît pas au recalcul nocturne.
- Aucun payload classique, bureau ou API ne contient de métrique de fiabilité individuelle.

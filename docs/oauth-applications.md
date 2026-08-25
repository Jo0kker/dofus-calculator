# Applications connectées

Les comptes vérifiés peuvent enregistrer jusqu’à cinq applications actives depuis `/developer/applications`.

Chaque application est un **client OAuth2 public**. Après sa création, le développeur reçoit un `Client ID`, qui est un identifiant public et non un mot de passe. Aucun `client_secret` n’est délivré : une application mobile, desktop ou exécutée dans un navigateur ne pourrait pas le conserver de façon confidentielle.

La connexion utilise **Authorization Code avec PKCE/S256**. Le `code_verifier`, généré à chaque tentative et conservé temporairement par l’application, protège l’échange du code d’autorisation.

## Créer une application

Depuis `/developer/applications` :

1. choisir le nom présenté aux utilisateurs sur l’écran de consentement ;
2. enregistrer entre une et cinq URL de redirection exactes ;
3. copier le `Client ID` public dans la configuration de l’application.

Règles appliquées aux URL de redirection :

- HTTPS est obligatoire pour les domaines distants ;
- HTTP est accepté uniquement pour `localhost`, `127.0.0.1` et `::1` ;
- les schémas natifs comme `monapp://oauth/callback` sont acceptés ;
- les fragments `#` et les identifiants intégrés dans l’URL sont refusés.

La configuration publique du serveur est disponible avec `GET /.well-known/oauth-authorization-server`.

## Flux d’authentification

### 1. Générer PKCE

Pour chaque connexion, l’application génère :

- un `code_verifier` aléatoire de 43 à 128 caractères ;
- `code_challenge = BASE64URL(SHA256(code_verifier))` ;
- un `state` aléatoire, à vérifier strictement au retour.

### 2. Demander l’autorisation

Ouvrir le navigateur système sur :

```text
GET /oauth/authorize
  ?client_id=CLIENT_ID
  &redirect_uri=CALLBACK_EXACT
  &response_type=code
  &scope=profile:read
  &state=VALEUR_ALEATOIRE
  &code_challenge=CHALLENGE
  &code_challenge_method=S256
```

L’utilisateur se connecte, choisit éventuellement un autre compte, puis autorise ou refuse l’application.

### 3. Échanger le code

Après avoir vérifié le `state`, envoyer :

```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&client_id=CLIENT_ID&redirect_uri=CALLBACK_EXACT&code=CODE&code_verifier=VERIFIER
```

Il ne faut pas envoyer de `client_secret`. La réponse fournit `access_token`, `refresh_token` et `expires_in`.

### 4. Appeler l’API

```http
GET /api/v1/me
Authorization: Bearer ACCESS_TOKEN
Accept: application/json
```

La permission disponible est `profile:read`, qui autorise la lecture du profil et du serveur sélectionné.

### 5. Rafraîchir la session

```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token&client_id=CLIENT_ID&refresh_token=REFRESH_TOKEN
```

Là encore, aucun `client_secret` n’est requis. Le client remplace l’access token **et** le refresh token par les nouvelles valeurs reçues.

### 6. Se déconnecter

```http
DELETE /api/v1/session
Authorization: Bearer ACCESS_TOKEN
Accept: application/json
```

L’application supprime ensuite ses copies locales des deux jetons.

## Gestion et révocation

Un développeur peut modifier le nom et les URL de redirection de ses applications. Il peut aussi révoquer une application ; cette action coupe tous ses access tokens et refresh tokens encore actifs.

Les administrateurs disposent de `/admin/oauth-applications` pour :

- voir le propriétaire, les utilisateurs et les sessions actives ;
- suivre les requêtes des dernières 24 heures et ouvrir les logs détaillés ;
- couper uniquement les sessions ;
- bloquer ou réactiver une application.

Une application réactivée ne récupère jamais ses anciennes sessions : ses utilisateurs doivent recommencer le flux OAuth.

La configuration spécifique de l’application officielle et les commandes de production sont détaillées dans [mobile-oauth.md](mobile-oauth.md).

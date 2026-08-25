# Authentification OAuth2 de l’application mobile

L’application mobile utilise le flux **Authorization Code avec PKCE/S256**. Elle est un client public : aucun `client_secret` ne doit être intégré au binaire.

Le `Client ID` est le seul identifiant OAuth à configurer dans l’application. Il est public ; la sécurité du flux repose sur le callback exact, le `state` et PKCE, pas sur un secret embarqué.

## Préparation d’un environnement

```bash
php artisan migrate
php artisan passport:keys
php artisan passport:client --public \
  --name="Dofus Calculator Mobile" \
  --redirect_uri="dofuscalculator://auth/callback"
```

Le client local peut conserver plusieurs callbacks exacts, par exemple `http://localhost/auth/callback` pour les tests web et `dofuscalculator://auth/callback` pour l’application NativePHP. Pour la publication, un Universal Link iOS / App Link Android vérifié par le domaine pourra remplacer le schéma personnalisé. Le `Client ID` est une configuration publique de l’application mobile.

Les développeurs tiers peuvent créer eux-mêmes leur application publique depuis `/developer/applications` au lieu d’exécuter la commande Artisan. Les mêmes règles de callback et l’obligation PKCE s’appliquent.

Les clés `storage/oauth-private.key` et `storage/oauth-public.key` ne doivent pas être versionnées. En production, elles peuvent aussi être injectées avec `PASSPORT_PRIVATE_KEY` et `PASSPORT_PUBLIC_KEY`.

## Mise en production

Le déploiement de `main` exécute déjà `php artisan migrate --force`. Pour l’environnement de production :

1. générer une seule fois la paire de clés avec `php artisan passport:keys` ;
2. conserver les clés durablement dans `PASSPORT_PRIVATE_KEY` et `PASSPORT_PUBLIC_KEY` lorsque le conteneur n’a pas de volume persistant ;
3. ne jamais régénérer ces clés lors d’un déploiement courant, car cela invaliderait tous les access tokens existants ;
4. créer le client public de production :

```bash
php artisan passport:client --public \
  --name="Dofus Calculator Mobile" \
  --redirect_uri="dofuscalculator://auth/callback"
```

Reporter le `Client ID` affiché dans la configuration de l’application mobile avec :

```dotenv
DOFUS_API_URL=https://dofus-calculator.fr
DOFUS_OAUTH_CLIENT_ID=CLIENT_ID_PRODUCTION
DOFUS_OAUTH_REDIRECT_URI=dofuscalculator://auth/callback
```

Contrôler enfin `GET https://dofus-calculator.fr/.well-known/oauth-authorization-server`, puis réaliser un parcours réel connexion, autorisation, rafraîchissement et déconnexion.

## Découverte

La configuration publique du serveur est exposée sur :

```text
GET /.well-known/oauth-authorization-server
```

## 1. Ouvrir l’autorisation dans le navigateur système

L’application génère pour chaque tentative :

- un `code_verifier` aléatoire de 43 à 128 caractères ;
- `code_challenge = BASE64URL(SHA256(code_verifier))` ;
- un `state` aléatoire à vérifier au retour.

Elle ouvre ensuite :

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

Utiliser `ASWebAuthenticationSession` sur iOS et Custom Tabs sur Android. Ne pas utiliser une WebView embarquée.

## 2. Échanger le code

Après avoir vérifié que le `state` reçu correspond à celui de départ :

```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&client_id=CLIENT_ID&redirect_uri=CALLBACK_EXACT&code=CODE&code_verifier=VERIFIER
```

Aucun `client_secret` ne doit être ajouté à cette requête.

La réponse contient `access_token`, `refresh_token` et `expires_in`. L’application NativePHP chiffre les deux jetons au repos avec la clé propre à l’installation. NativePHP conserve cette `APP_KEY` dans le Keychain iOS ou l’Android Keystore ; le plugin Secure Storage reste optionnel si une conservation directe de petites valeurs dans ces coffres est souhaitée.

## 3. Appeler l’API

```http
GET /api/v1/me
Authorization: Bearer ACCESS_TOKEN
Accept: application/json
```

L’access token expire par défaut après 15 minutes.

## 4. Rafraîchir la session

```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token&client_id=CLIENT_ID&refresh_token=REFRESH_TOKEN
```

Aucun `client_secret` n’est requis pour le rafraîchissement d’un client public.

Le serveur effectue une rotation : le client doit remplacer **à la fois** l’access token et le refresh token par les nouvelles valeurs. Un refresh token inutilisé expire après 30 jours avec la configuration par défaut.

## 5. Déconnexion

```http
DELETE /api/v1/session
Authorization: Bearer ACCESS_TOKEN
Accept: application/json
```

Le serveur révoque l’access token courant et son refresh token. L’application supprime ensuite ses copies locales.

## Scopes

| Scope | Usage |
| --- | --- |
| `profile:read` | Consulter le profil et le serveur sélectionné |

Les routes métier et les tokens Sanctum existants restent inchangés. Passport ajoute uniquement l’authentification OAuth de l’application mobile ; le contrat fonctionnel reste celui de la documentation API existante.

La création et la gestion des applications tierces sont détaillées dans [oauth-applications.md](oauth-applications.md).

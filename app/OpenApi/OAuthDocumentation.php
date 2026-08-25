<?php

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

class OAuthDocumentation
{
    public function __invoke(OpenApi $openApi): void
    {
        $this->addSecurityScheme($openApi);
        $this->secureMobileEndpoints($openApi);
        $this->addMetadataEndpoint($openApi);
        $this->addAuthorizationEndpoint($openApi);
        $this->addTokenEndpoint($openApi);
    }

    private function addSecurityScheme(OpenApi $openApi): void
    {
        $scheme = SecurityScheme::oauth2()
            ->as('OAuth2')
            ->setDescription('Connexion d’une application avec un Client ID public. Aucun Client secret n’est nécessaire.')
            ->flow('authorizationCode', fn ($flow) => $flow
                ->authorizationUrl(url('/oauth/authorize'))
                ->tokenUrl(url('/oauth/token'))
                ->refreshUrl(url('/oauth/token'))
                ->addScope('profile:read', 'Consulter le profil et le serveur sélectionné'));

        $openApi->components->addSecurityScheme('OAuth2', $scheme);
    }

    private function secureMobileEndpoints(OpenApi $openApi): void
    {
        foreach ($openApi->paths as $path) {
            if (! in_array($path->path, ['v1/me', 'v1/session'], true)) {
                continue;
            }

            foreach ($path->operations as $operation) {
                $operation->security = [
                    new SecurityRequirement(['OAuth2' => ['profile:read']]),
                ];
            }
        }
    }

    private function addMetadataEndpoint(OpenApi $openApi): void
    {
        $response = new ObjectType;
        $response
            ->addProperty('issuer', $this->string('URL du serveur', url('/')))
            ->addProperty('authorization_endpoint', $this->string('URL d’autorisation', url('/oauth/authorize')))
            ->addProperty('token_endpoint', $this->string('URL d’échange et de rafraîchissement', url('/oauth/token')))
            ->addProperty('grant_types_supported', $this->stringArray(['authorization_code', 'refresh_token']))
            ->addProperty('response_types_supported', $this->stringArray(['code']))
            ->addProperty('token_endpoint_auth_methods_supported', $this->stringArray(['none']))
            ->addProperty('code_challenge_methods_supported', $this->stringArray(['S256']))
            ->addProperty('scopes_supported', $this->stringArray(['profile:read']));

        $operation = Operation::make('get')
            ->setOperationId('getOAuthServerMetadata')
            ->summary('Découvrir la configuration OAuth')
            ->description('Retourne les URLs et capacités publiques nécessaires pour configurer une application.')
            ->setTags(['Authentification OAuth'])
            ->servers([$this->rootServer()])
            ->addSecurity([])
            ->addResponse(Response::make(200)
                ->setDescription('Configuration OAuth publique')
                ->setContent('application/json', Schema::fromType($response)));

        $openApi->addPath(Path::make('.well-known/oauth-authorization-server')->addOperation($operation));
    }

    private function addAuthorizationEndpoint(OpenApi $openApi): void
    {
        $operation = Operation::make('get')
            ->setOperationId('authorizeOAuthApplication')
            ->summary('Demander l’autorisation de l’utilisateur')
            ->description('Ouvrir cette URL dans le navigateur système. Après la connexion et le consentement, le serveur redirige vers le callback exact de l’application avec `code` et `state`. Le Client ID se crée depuis `/developer/applications`.')
            ->setTags(['Authentification OAuth'])
            ->servers([$this->rootServer()])
            ->addSecurity([])
            ->addParameters([
                $this->query('client_id', 'Client ID public de l’application', true, '01a034b2-f813-7052-ab61-683ee146f854'),
                $this->query('redirect_uri', 'Callback exact enregistré pour l’application', true, 'dofuscalculator://auth/callback'),
                $this->query('response_type', 'Toujours `code`', true, 'code', ['code']),
                $this->query('scope', 'Permissions séparées par des espaces', true, 'profile:read'),
                $this->query('state', 'Valeur aléatoire à vérifier au retour', true, 'random-state'),
                $this->query('code_challenge', 'Empreinte SHA-256 encodée en Base64 URL-safe du code_verifier', true, 'PKCE_CHALLENGE'),
                $this->query('code_challenge_method', 'Méthode utilisée pour calculer le challenge', true, 'S256', ['S256']),
            ])
            ->addResponse(Response::make(302)->setDescription('Redirection vers le callback avec un code, ou avec une erreur si l’utilisateur refuse'));

        $openApi->addPath(Path::make('oauth/authorize')->addOperation($operation));
    }

    private function addTokenEndpoint(OpenApi $openApi): void
    {
        $request = new ObjectType;
        $request
            ->addProperty('grant_type', (new StringType)
                ->enum(['authorization_code', 'refresh_token'])
                ->setDescription('`authorization_code` pour la connexion, `refresh_token` pour renouveler la session'))
            ->addProperty('client_id', $this->string('Client ID public de l’application'))
            ->addProperty('redirect_uri', $this->string('Callback exact utilisé pendant l’autorisation'))
            ->addProperty('code', $this->string('Code reçu sur le callback'))
            ->addProperty('code_verifier', $this->string('Valeur PKCE originale générée par l’application'))
            ->addProperty('refresh_token', $this->string('Refresh token reçu lors du précédent échange'))
            ->setRequired(['grant_type', 'client_id']);

        $response = new ObjectType;
        $response
            ->addProperty('token_type', (new StringType)->example('Bearer'))
            ->addProperty('expires_in', (new IntegerType)->example(900))
            ->addProperty('access_token', (new StringType)->example('ACCESS_TOKEN'))
            ->addProperty('refresh_token', (new StringType)->example('REFRESH_TOKEN'))
            ->setRequired(['token_type', 'expires_in', 'access_token', 'refresh_token']);

        $error = new ObjectType;
        $error
            ->addProperty('error', (new StringType)->example('invalid_grant'))
            ->addProperty('error_description', (new StringType)->example('The provided authorization grant is invalid.'));

        $operation = Operation::make('post')
            ->setOperationId('exchangeOrRefreshOAuthToken')
            ->summary('Obtenir ou rafraîchir les jetons')
            ->description('Échange un code d’autorisation avec `code_verifier`, ou renouvelle la session avec `refresh_token`. N’envoyez pas de Client secret : l’application est un client public.')
            ->setTags(['Authentification OAuth'])
            ->servers([$this->rootServer()])
            ->addSecurity([])
            ->addRequestBodyObject(RequestBodyObject::make()
                ->required()
                ->setContent('application/x-www-form-urlencoded', Schema::fromType($request)))
            ->addResponse(Response::make(200)
                ->setDescription('Jetons générés. Lors d’un rafraîchissement, remplacez les deux jetons locaux.')
                ->setContent('application/json', Schema::fromType($response)))
            ->addResponse(Response::make(400)
                ->setDescription('Requête ou code invalide')
                ->setContent('application/json', Schema::fromType($error)));

        $openApi->addPath(Path::make('oauth/token')->addOperation($operation));
    }

    private function rootServer(): Server
    {
        return Server::make(rtrim(url('/'), '/'))->setDescription('Serveur OAuth');
    }

    /** @param list<string> $enum */
    private function query(
        string $name,
        string $description,
        bool $required,
        string $example,
        array $enum = [],
    ): Parameter {
        $type = (new StringType)->example($example);

        if ($enum !== []) {
            $type->enum($enum);
        }

        return Parameter::make($name, 'query')
            ->description($description)
            ->required($required)
            ->setSchema(Schema::fromType($type));
    }

    private function string(string $description, ?string $example = null): StringType
    {
        $type = (new StringType)->setDescription($description);

        return $example === null ? $type : $type->example($example);
    }

    /** @param list<string> $example */
    private function stringArray(array $example): ArrayType
    {
        return (new ArrayType)->setItems(new StringType)->example($example);
    }
}

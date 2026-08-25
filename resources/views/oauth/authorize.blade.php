<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autoriser {{ $client->name }} — {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #111827; color: #f9fafb; }
        main { width: 100%; max-width: 520px; padding: 32px; border: 1px solid #374151; border-radius: 18px; background: #1f2937; box-shadow: 0 24px 60px rgba(0, 0, 0, .35); }
        h1 { margin: 0 0 12px; font-size: 1.55rem; }
        p { color: #d1d5db; line-height: 1.55; }
        .account { padding: 12px 14px; border-radius: 10px; background: #111827; color: #f3f4f6; }
        .account strong { overflow-wrap: anywhere; }
        .account a { display: inline-block; margin-top: 8px; color: #fbbf24; font-size: .9rem; font-weight: 700; text-decoration: none; }
        .account a:hover { text-decoration: underline; }
        .third-party { margin: -2px 0 18px; color: #fbbf24; font-size: .85rem; }
        ul { margin: 18px 0 24px; padding-left: 22px; color: #e5e7eb; }
        li + li { margin-top: 8px; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        button { width: 100%; padding: 12px 16px; border: 0; border-radius: 10px; font: inherit; font-weight: 700; cursor: pointer; }
        button:disabled { cursor: wait; opacity: .65; }
        .deny { background: #374151; color: #f9fafb; }
        .approve { background: #d97706; color: #fff; }
        .approve[aria-busy="true"] { background: #b45309; opacity: 1; }
        .security { margin: 22px 0 0; font-size: .82rem; color: #9ca3af; }
        @media (max-width: 640px) {
            body { display: block; padding: 0; background: #1f2937; }
            main {
                max-width: none;
                min-height: 100vh;
                min-height: 100dvh;
                padding: 24px max(16px, env(safe-area-inset-right)) max(32px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }
            h1 { font-size: clamp(1.45rem, 7vw, 1.75rem); line-height: 1.2; }
            .actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main>
    <h1>Autoriser {{ $client->name }} ?</h1>
    <p>L’application souhaite accéder à votre compte {{ config('app.name') }}.</p>

    <div class="account">
        Connecté avec <strong>{{ $user->email }}</strong><br>
        <a href="{{ $request->fullUrlWithQuery(['prompt' => 'login']) }}">Ce n’est pas vous ? Changer de compte</a>
    </div>

    @if ($client->owner_id)
        <p class="third-party">Application tierce proposée par {{ $client->owner?->name ?? 'un développeur externe' }}.</p>
    @endif

    @if (count($scopes) > 0)
        <p>Permissions demandées :</p>
        <ul>
            @foreach ($scopes as $scope)
                <li>{{ $scope->description }}</li>
            @endforeach
        </ul>
    @endif

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <input type="hidden" name="state" value="{{ $request->state }}">
            <button class="deny" type="submit">Refuser</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <input type="hidden" name="state" value="{{ $request->state }}">
            <button class="approve" type="submit">Autoriser</button>
        </form>
    </div>

    <p class="security">Vous pourrez révoquer cet accès à tout moment. Votre mot de passe n’est jamais transmis à l’application.</p>
</main>
<script>
    const resetAuthorizationButtons = () => {
        document.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = false;
            button.removeAttribute('aria-busy');

            if (button.dataset.idleLabel) {
                button.textContent = button.dataset.idleLabel;
            }
        });
    };

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            document.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.dataset.idleLabel = button.textContent;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });

            const submittedButton = form.querySelector('button[type="submit"]');

            if (submittedButton) {
                submittedButton.textContent = submittedButton.classList.contains('approve')
                    ? 'Autorisation en cours…'
                    : 'Refus en cours…';
            }
        }, { once: true });
    });

    // Safari may restore this page from its back/forward cache after a failed
    // navigation. In that case the consent controls must become usable again.
    window.addEventListener('pageshow', resetAuthorizationButtons);
</script>
</body>
</html>

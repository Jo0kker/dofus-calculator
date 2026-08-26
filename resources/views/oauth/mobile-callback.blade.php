<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Retour vers Dofus Calculator</title>
    <style nonce="{{ $nonce }}">
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; display: grid; place-items: center; padding: 24px; background: #111827; color: #f9fafb; }
        main { width: 100%; max-width: 480px; padding: 30px; border: 1px solid #374151; border-radius: 18px; background: #1f2937; text-align: center; }
        h1 { margin: 0 0 12px; font-size: 1.55rem; }
        p { margin: 0 0 24px; color: #d1d5db; line-height: 1.55; }
        a { display: block; width: 100%; padding: 14px 18px; border-radius: 10px; background: #d97706; color: #fff; font-weight: 700; text-decoration: none; }
        .hint { margin: 16px 0 0; color: #9ca3af; font-size: .82rem; }
        @media (max-width: 640px) {
            body { padding: 0; background: #1f2937; }
            main { max-width: none; min-height: 100vh; min-height: 100dvh; display: grid; align-content: center; border: 0; border-radius: 0; }
        }
    </style>
</head>
<body>
<main>
    <h1>Retour vers Dofus Calculator</h1>
    <p>La connexion est terminée. L’application va se rouvrir automatiquement.</p>
    <a id="open-app" href="{{ $callbackUrl }}">Ouvrir Dofus Calculator</a>
    <p class="hint">Si rien ne se passe, touchez le bouton ci-dessus.</p>
</main>
<script nonce="{{ $nonce }}">
    const callbackUrl = @json($callbackUrl);

    window.location.replace(callbackUrl);

    // Some iOS browser builds require a user gesture before opening a custom
    // scheme. The visible link remains available as a deterministic fallback.
    document.getElementById('open-app').addEventListener('click', () => {
        window.location.href = callbackUrl;
    });
</script>
</body>
</html>

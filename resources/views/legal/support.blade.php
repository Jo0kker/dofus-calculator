<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assistance — Dofus Calculator</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #111827; color: #f8fafc; }
        main { width: min(760px, 100%); margin: 0 auto; padding: 40px 20px 64px; }
        nav { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 36px; }
        nav a, a { color: #f59e0b; }
        h1 { margin: 0 0 12px; font-size: clamp(2rem, 7vw, 3rem); }
        h2 { margin-top: 0; font-size: 1.2rem; }
        p, li { color: #cbd5e1; line-height: 1.65; }
        .lead { font-size: 1.08rem; }
        .card { margin-top: 18px; padding: 22px; border: 1px solid #3b475a; border-radius: 16px; background: #1f2937; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 20px; }
        .button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px; background: #d97706; color: white; font-weight: 700; text-decoration: none; }
        .button.secondary { background: #374151; }
        footer { margin-top: 34px; color: #94a3b8; font-size: .9rem; }
    </style>
</head>
<body>
<main>
    <nav>
        <strong>Dofus Calculator</strong>
        <a href="{{ route('home') }}">Retour au site</a>
    </nav>

    <h1>Assistance</h1>
    <p class="lead">Besoin d’aide avec le site, l’application mobile, une connexion OAuth ou tes données ? Les ressources ci-dessous restent accessibles sans installer l’application.</p>

    <section class="card">
        <h2>Compte et connexion</h2>
        <p>Tu peux modifier ton profil, changer ton mot de passe, révoquer tes sessions et supprimer ton compte depuis la gestion du compte.</p>
        <div class="actions">
            <a class="button" href="{{ route('profile.show') }}">Gérer mon compte</a>
            <a class="button secondary" href="{{ route('account-deletion') }}">Supprimer mon compte</a>
        </div>
    </section>

    <section class="card">
        <h2>Signaler un problème</h2>
        <p>Décris le modèle du téléphone, la version du système, l’écran concerné et les étapes qui reproduisent le problème. Ne publie jamais de mot de passe, de jeton OAuth ou d’information privée.</p>
        <div class="actions">
            <a class="button" href="https://github.com/Jo0kker/dofus-calculator/issues" rel="noopener noreferrer">Ouvrir une demande d’assistance</a>
        </div>
    </section>

    <section class="card">
        <h2>Confidentialité</h2>
        <p>Pour une demande d’accès, de rectification ou de suppression de données, ouvre une demande en précisant qu’elle concerne la confidentialité. Une vérification d’identité pourra être demandée avant toute communication de données.</p>
        <div class="actions">
            <a class="button secondary" href="{{ route('policy.show') }}">Politique de confidentialité</a>
        </div>
    </section>

    <footer>Dofus Calculator est un service communautaire indépendant, non affilié à Ankama.</footer>
</main>
</body>
</html>

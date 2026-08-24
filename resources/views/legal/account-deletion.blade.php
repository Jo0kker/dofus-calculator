<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suppression de compte — Dofus Calculator</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #111827; color: #f8fafc; }
        main { width: min(720px, 100%); margin: 0 auto; padding: 40px 20px 64px; }
        nav { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 36px; }
        nav a, a { color: #f59e0b; }
        h1 { margin: 0 0 12px; font-size: clamp(2rem, 7vw, 3rem); }
        p, li { color: #cbd5e1; line-height: 1.65; }
        .card { margin-top: 22px; padding: 22px; border: 1px solid #3b475a; border-radius: 16px; background: #1f2937; }
        .button { display: inline-flex; min-height: 46px; align-items: center; justify-content: center; margin-top: 12px; padding: 11px 18px; border-radius: 10px; background: #be123c; color: white; font-weight: 700; text-decoration: none; }
        .note { color: #fbbf24; }
        footer { margin-top: 34px; color: #94a3b8; font-size: .9rem; }
    </style>
</head>
<body>
<main>
    <nav>
        <strong>Dofus Calculator</strong>
        <a href="{{ route('support') }}">Assistance</a>
    </nav>

    <h1>Supprimer mon compte</h1>
    <p>La suppression est disponible depuis le site, même si l’application mobile a déjà été désinstallée.</p>

    <section class="card">
        <h2>Procédure</h2>
        <ol>
            <li>Connecte-toi avec le compte à supprimer.</li>
            <li>Ouvre la section de suppression en bas de la page de profil.</li>
            <li>Confirme avec ton mot de passe.</li>
        </ol>
        <a class="button" href="{{ route('account-deletion.start') }}">Continuer vers la suppression</a>
    </section>

    <section class="card">
        <h2>Données supprimées</h2>
        <p>Le compte, le profil, les favoris associés, les données personnelles, les sessions, les jetons API et les jetons OAuth sont supprimés des systèmes actifs. Les données locales de l’application doivent être effacées en supprimant l’application ou ses données.</p>
        <p class="note">Cette opération est définitive. Les copies résiduelles présentes dans les sauvegardes disparaissent lors de leur rotation.</p>
    </section>

    <footer>Dofus Calculator est un service communautaire indépendant, non affilié à Ankama.</footer>
</main>
</body>
</html>

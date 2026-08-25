<link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/auth-premium.css')) ?>">
<main class="premium-auth-shell">
    <section class="premium-auth-story">
        <a class="premium-auth-back" href="<?= htmlspecialchars(route_url('/')) ?>">← Retour à l'accueil</a>
        <div class="premium-auth-story-copy"><span class="premium-auth-eyebrow">Pilotage éditorial unifié</span><h1>Votre production et vos résultats au même endroit.</h1><p>Retrouvez les calendriers clients, les validations et la publication multiréseau dans un espace sécurisé.</p><div class="premium-auth-proof"><span>✓ Données isolées</span><span>✓ Accès révocables</span><span>✓ Publication validée</span></div></div>
        <figure><img src="<?= htmlspecialchars(app_url('/public/assets/site/secure-sync.webp')) ?>" alt="Équipe marketing collaborant dans Strax"><figcaption>Une continuité claire, de l'idée à la performance.</figcaption></figure>
    </section>
    <section class="premium-auth-panel"><form method="post" class="premium-auth-form">
        <div class="premium-auth-heading"><span class="premium-auth-mark">S</span><div><small>Bienvenue sur Strax</small><h2>Connectez-vous à votre espace</h2></div></div>
        <p class="premium-auth-lead">Accédez à votre organisation et reprenez votre travail là où vous l'avez laissé.</p>
        <label><span>Adresse e-mail</span><input type="email" name="email" required autocomplete="email" placeholder="vous@entreprise.com"></label>
        <label><span>Mot de passe</span><span class="password-control"><input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="Votre mot de passe"><button type="button" data-password-toggle>Afficher</button></span></label>
        <div class="premium-auth-options"><label><input type="checkbox" name="remember" value="1"><span>Rester connecté</span></label><a href="<?= htmlspecialchars(route_url('/password-reset/request')) ?>">Compte ou mot de passe oublié ?</a></div>
        <button class="premium-auth-submit" type="submit">Se connecter <span>→</span></button>
        <p class="premium-auth-switch">Nouveau sur Strax ? <a href="<?= htmlspecialchars(route_url('/public/register')) ?>">Créer votre espace</a></p><p class="premium-auth-security">Connexion protégée et tentatives surveillées.</p>
    </form></section>
</main>
<script>document.querySelector('[data-password-toggle]')?.addEventListener('click',function(){var i=document.getElementById('login-password'),v=i.type==='text';i.type=v?'password':'text';this.textContent=v?'Afficher':'Masquer';});</script>

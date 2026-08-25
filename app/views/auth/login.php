<section class="auth-card">
    <form method="post" class="auth-form">
        <p>Connecte-toi pour acceder a l'espace d'administration.</p>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" required>
        </label>
        <label class="field">
            <span>Mot de passe</span>
            <input type="password" name="password" required>
        </label>
        <button class="button" type="submit">Se connecter</button>
        <p class="auth-switch">Nouveau sur Strax ? <a href="<?= htmlspecialchars(route_url('/public/register')) ?>">Créer un compte</a></p>
    </form>
</section>

<section class="page-intro-card compact-intro"><div><span class="page-eyebrow">Portefeuille clients</span><h2>Nouveau client</h2><p>Ajoutez les informations essentielles. Les projets et accès pourront être configurés ensuite.</p></div><a class="button secondary" href="<?= htmlspecialchars(route_url('/client')) ?>">← Retour aux clients</a></section>
<section class="panel form-panel-narrow"><div class="panel-head"><div><h2>Informations du client</h2><p class="panel-subtitle">Les champs marqués d’un astérisque sont obligatoires.</p></div></div>
<form method="post" class="form-grid two-columns entity-form">
 <label class="field"><span>Nom du contact *</span><input type="text" name="nom" required autocomplete="name" placeholder="Nom et prénom"></label>
 <label class="field"><span>Entreprise</span><input type="text" name="entreprise" autocomplete="organization" placeholder="Raison sociale"></label>
 <label class="field"><span>Secteur d’activité</span><input type="text" name="secteur" placeholder="Ex. Immobilier, santé, conseil"></label>
 <label class="field"><span>Téléphone</span><input type="tel" name="telephone" autocomplete="tel" placeholder="+228 ..."></label>
 <label class="field"><span>Adresse email</span><input type="email" name="email" autocomplete="email" placeholder="contact@entreprise.com"></label>
 <label class="field"><span>Statut</span><select name="statut"><option value="Actif">Actif</option><option value="Inactif">Inactif</option></select></label>
 <div class="form-actions form-actions-wide"><a class="button secondary" href="<?= htmlspecialchars(route_url('/client')) ?>">Annuler</a><button class="button primary" type="submit">Créer le client</button></div>
</form></section>
<?php
// register.php
session_start();

// Récupérer et consommer les messages flash s'ils existent
$errors = $_SESSION['reg_errors'] ?? [];
$success_message = $_SESSION['reg_success'] ?? '';

unset($_SESSION['reg_errors'], $_SESSION['reg_success']);

// Pré-remplissage si retour en erreur
$old = $_SESSION['reg_old'] ?? [];
unset($_SESSION['reg_old']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>MyKelasi | Créer compte promoteur</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
  <style>
    body{background:#f4f6f9;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0}
    .container-card{background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.06);padding:28px;max-width:860px;width:100%}
    .form-label{font-weight:600}
    .toggle-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;opacity:.6}
    .toggle-eye:hover{opacity:1}
  </style>
</head>
<body>
  <div class="container-card">
    <h2 class="mb-2 text-center">Créer compte promoteur</h2>
    <p class="text-center text-muted mb-4">Créez votre accès pour gérer vos établissements scolaires.</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <strong>Veuillez corriger les erreurs suivantes :</strong>
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
      <div class="alert alert-success text-center">
        <?= htmlspecialchars($success_message) ?>
      </div>
    <?php endif; ?>

    <form action="register_process.php" method="post" autocomplete="off" novalidate>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="first_name">Prénom</label>
          <input class="form-control" type="text" id="first_name" name="first_name"
                 value="<?= htmlspecialchars($old['first_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="last_name">Nom de famille</label>
          <input class="form-control" type="text" id="last_name" name="last_name"
                 value="<?= htmlspecialchars($old['last_name'] ?? '') ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="phone">Téléphone</label>
          <input class="form-control" type="text" id="phone" name="phone"
                 value="<?= htmlspecialchars($old['phone'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="username">Nom d'utilisateur</label>
          <input class="form-control" type="text" id="username" name="username"
                 value="<?= htmlspecialchars($old['username'] ?? '') ?>" required>
        </div>

        <div class="col-md-12">
          <label class="form-label" for="email">Email</label>
          <input class="form-control" type="email" id="email" name="email"
                 value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
        </div>

        <div class="col-md-6 position-relative">
          <label class="form-label" for="password">Mot de passe</label>
          <input class="form-control" type="password" id="password" name="password" required>
          <span class="toggle-eye" onclick="togglePwd('password', this)" title="Afficher/Masquer">👁️</span>
        </div>
        <div class="col-md-6 position-relative">
          <label class="form-label" for="confirm_password">Confirmez le mot de passe</label>
          <input class="form-control" type="password" id="confirm_password" name="confirm_password" required>
          <span class="toggle-eye" onclick="togglePwd('confirm_password', this)" title="Afficher/Masquer">👁️</span>
        </div>

        <div class="col-md-12">
          <label class="form-label" for="numero_bancaire">Compte bancaire (optionnel)</label>
          <input class="form-control" type="text" id="numero_bancaire" name="numero_bancaire"
                 placeholder="XXXXX-XXXXX-XXXXX-XXXXX"
                 value="<?= htmlspecialchars($old['numero_bancaire'] ?? '') ?>">
        </div>

        <div class="col-12">
          <button class="btn btn-primary w-100" type="submit">Créer maintenant</button>
        </div>
      </div>
    </form>
  </div>

  <script>
    function togglePwd(id, el){
      const input = document.getElementById(id);
      if(!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      el.textContent = input.type === 'password' ? '👁️' : '🙈';
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
          integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
</body>
</html>

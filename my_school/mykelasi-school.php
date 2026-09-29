<?php
// /mykelasi/my_school/ecoles_create.php
declare(strict_types=1);

 // DEBUG handled in db_connect.php


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Helpers session
function cu(): ?array {
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return $_SESSION['user'];
    if (!empty($_SESSION['user_id'])) {
        return [
            'id'         => (int) $_SESSION['user_id'],
            'role'       => $_SESSION['role']       ?? null,
            'email'      => $_SESSION['email']      ?? null,
            'first_name' => $_SESSION['first_name'] ?? null,
            'last_name'  => $_SESSION['last_name']  ?? null,
            'username'   => $_SESSION['username']   ?? null,
        ];
    }
    return null;
}
function uid(): int { $u = cu(); return isset($u['id']) ? (int)$u['id'] : 0; }
function norm_role(?string $r): string { return strtolower(trim((string)$r)); }
function has_role($roles): bool {
    $u = cu(); if (!$u || empty($u['role'])) return false;
    $cur = norm_role($u['role']);
    $arr = is_array($roles) ? $roles : [$roles];
    foreach ($arr as $r) if ($cur === norm_role($r)) return true;
    return false;
}

// Auth simple
if (uid() <= 0) {
    $login = '../login/';
    $redir = (str_ends_with($login, '/')
        ? $login.'?redirect='.urlencode($_SERVER['REQUEST_URI'] ?? '')
        : $login.'&redirect='.urlencode($_SERVER['REQUEST_URI'] ?? '')
    );
    header("Location: $redir"); exit;
}

// DB connect (facultatif pour debug visuel)
$pdo = null; $loaded_db_path = null;
$db_candidates = [
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/database/db_connect.php',
];
foreach ($db_candidates as $cand) { if (file_exists($cand)) { $loaded_db_path = $cand; require_once $cand; break; } }

// Rôle promoteur exigé
if (!has_role('promoteur')) { http_response_code(403); exit('Accès refusé (rôle requis : promoteur).'); }

// Flash
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | My School</title>
    <meta name="description" content="Création des écoles">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../css/datepicker.min.css">
    <link rel="stylesheet" href="../style.css">
    
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
        .card-ecole { border:1px solid #e7e7e7; border-radius:12px; padding:16px; margin-bottom:16px; position:relative; }
        .remove-btn { position:absolute; right:10px; top:10px; }
        .small-muted { font-size:.9rem; color:#666; }
        .pwd-wrap{position:relative}
        .pwd-toggle{position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; opacity:.75}
        .pwd-toggle:hover{opacity:1}
        .locked { opacity: .85; }
        .form-section-title { font-weight: 600; font-size: 1rem; margin: 12px 0 6px; }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php $nav=__DIR__.'/layouts/navbar.php'; if(file_exists($nav)) require $nav; ?>

        <div class="dashboard-page-one">
            <?php $side=__DIR__.'/layouts/sidebar.php'; if(file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">
                <div class="card height-auto mt-3">
                    <div class="card-body">
                        <?php if ($flash_success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
                        <?php endif; ?>
                        <?php if ($flash_error): ?>
                            <div class="alert alert-danger" style="white-space:pre-line"><?= htmlspecialchars($flash_error) ?></div>
                        <?php endif; ?>

                        <form id="formEcoles" action="service/enregistrer.php" method="post" enctype="multipart/form-data" autocomplete="off">
                            <div id="ecolesContainer" class="form mb-3"></div>

                            <div class="row form-group">
                                <button type="button"
                                    class="col-lg-6 col-sm-12 mb-3 m-2 btn-fill-lg btn-gradient-yellow btn-hover-bluedark"
                                    onclick="ajouterEcole()">+ Ajouter une école</button>
                                <button type="submit"
                                    class="col-lg-6 col-sm-12 mb-3 btn-fill-lg bg-blue-dark btn-hover-yellow">Créer maintenant</button>
                            </div>
                        </form>

                        <p class="small-muted">
                            Le <b>Type</b> est préfixé dans le <b>Nom de l’école</b> et
                            <b>pris en compte</b> pour générer <b>Code</b> et <b>URL</b>.
                            Le calcul ignore aussi tout texte <b>entre parenthèses</b>.
                            L’unicité finale est sécurisée côté serveur.
                        </p>
                    </div>
                </div>

                <?php $foot=__DIR__.'/layouts/footer.php'; if(file_exists($foot)) require $foot; ?>
            </div>
        </div>
    </div>

    <script>
    // ===== Utilitaires front (génération) =====
    const TYPE_PREFIX_RE = /^\s*(CS|GS|EP|L\.|COL)\s*[-–—]?\s*/i;

    function stripTypePrefix(s) {
        return (s || '').replace(TYPE_PREFIX_RE, '');
    }
    function stripParentheses(s) {
        return (s || '').replace(/\([^)]*\)/g, ' ').replace(/\s+/g, ' ').trim();
    }
    function onlyAlnumUpper(s) {
        return (s || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    }
    function initials(s) {
        return (s || '')
            .split(/[\s\-_]+/).filter(Boolean)
            .map(w => w[0].toUpperCase())
            .join('');
    }
    function slugAscii(s) {
        s = (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        s = s.replace(/[^A-Za-z0-9\- ]+/g, ' ');
        s = s.trim().replace(/\s+/g, '-').replace(/\-+/g, '-').replace(/^\-+|\-+$/g, '');
        return s.toLowerCase();
    }

    // *** NOUVEAU: tien compte du type ***
    function computeCode(typeVal, nameVal) {
        const type = onlyAlnumUpper(typeVal); // CS | GS | EP | L | COL (le point saute)
        const baseName = stripParentheses(stripTypePrefix(nameVal));
        const ini = initials(baseName); // initiales du nom
        // code = TYPE + INI, tronqué à 11
        return (type + ini).slice(0, 11);
    }
    function computeUrl(typeVal, nameVal) {
        // url = type- + slug(name), max 20
        const typeSlug = slugAscii(typeVal).replace(/[^a-z0-9]+/g, ''); // cs, gs, ep, l, col
        const baseName = stripParentheses(stripTypePrefix(nameVal));
        const nameSlug = slugAscii(baseName);
        let full = (typeSlug ? (typeSlug + '-') : '') + nameSlug;
        if (!full) full = 'ecole';
        return full.slice(0, 20);
    }

    // ===== Bloc école dynamique =====
    let ecoleIndex = 0;

    function ajouterEcole() {
        const idx = ecoleIndex++;
        const wrap = document.createElement('div');
        wrap.className = 'card-ecole';
        wrap.id = `ecole_${idx}`;

        wrap.innerHTML = `
            <button type="button" class="btn btn-sm btn-outline-danger remove-btn" onclick="this.closest('.card-ecole').remove()">Supprimer</button>

            <div class="form-section-title">Informations de l’école</div>
            <div class="row">
                <!-- TYPE AVANT NOM -->
                <div class="col-md-3 mb-3">
                    <label class="form-label">Type d’école</label>
                    <select name="ecoles[${idx}][type_ecole]" class="form-control" id="type_${idx}">
                        <option value="" selected disabled>Sélectionner le type de l'école</option>
                        <option value="CS">Complexe Scolaire (CS)</option>
                        <option value="GS">Groupe Scolaire (GS)</option>
                        <option value="EP">Ecole Public (EP)</option>
                        <option value="L.">Lycée (L.)</option>
                        <option value="COL">Collége (COL)</option>
                    </select>
                </div>
                <div class="col-md-9 mb-3">
                    <label class="form-label">Nom de l’école *</label>
                    <input type="text" name="ecoles[${idx}][nom_ecole]" class="text-uppercase form-control" required
                           placeholder="Ex: CS Kelasi" id="nom_${idx}">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Code école *</label>
                    <input type="text" name="ecoles[${idx}][code_ecole]" class="form-control locked" id="code_${idx}" readonly>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">URL école *</label>
                    <input type="text" name="ecoles[${idx}][url_ecole]" class="form-control locked" id="url_${idx}" readonly>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Ville</label>
                    <input type="text" name="ecoles[${idx}][ville]" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Pays</label>
                    <input type="text" name="ecoles[${idx}][pays]" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Province / État</label>
                    <input type="text" name="ecoles[${idx}][province_etat]" class="form-control">
                </div>

                <div class="col-md-12 mb-3">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="ecoles[${idx}][adress]" class="form-control">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Téléphone 1</label>
                    <input type="text" name="ecoles[${idx}][telephone1]" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Téléphone 2</label>
                    <input type="text" name="ecoles[${idx}][telephone2]" class="form-control">
                </div>
            </div>

            <div class="form-section-title">Responsable & pièces</div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nom du responsable</label>
                    <input type="text" name="ecoles[${idx}][nom_responsable]" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Postnom du responsable</label>
                    <input type="text" name="ecoles[${idx}][postnom_responsable]" class="form-control">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Type de pièce</label>
                    <input type="text" name="ecoles[${idx}][type_piece]" class="form-control" placeholder="Carte d’identité, etc.">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Pièce jointe</label>
                    <input type="file" name="piece_jointe_${idx}" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Logo</label>
                    <input type="file" name="logo_${idx}" class="form-control">
                </div>

                <div class="col-md-12 mb-3">
                    <label class="form-label">Documents</label>
                    <input type="file" name="docs_${idx}" class="form-control">
                </div>
            </div>

            <div class="form-section-title">Compte administrateur de l’école</div>
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label class="form-label">Email (login) *</label>
                    <input type="email" name="ecoles[${idx}][admin_email]" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nom d’utilisateur (facultatif)</label>
                    <input type="text" name="ecoles[${idx}][admin_username]" class="form-control" placeholder="par défaut = email">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Téléphone contact</label>
                    <input type="text" name="ecoles[${idx}][admin_phone]" class="form-control">
                </div>

                <div class="col-md-6 mb-3 pwd-wrap">
                    <label class="form-label">Mot de passe admin *</label>
                    <input type="password" name="ecoles[${idx}][admin_password]" class="form-control" required id="pwd_${idx}" minlength="8">
                    <span class="pwd-toggle" onclick="togglePwd(this,'pwd_${idx}')" title="Afficher">👁️</span>
                </div>
                <div class="col-md-6 mb-3 pwd-wrap">
                    <label class="form-label">Confirmer *</label>
                    <input type="password" name="ecoles[${idx}][admin_password_confirm]" class="form-control" required id="pwdc_${idx}" minlength="8">
                    <span class="pwd-toggle" onclick="togglePwd(this,'pwdc_${idx}')" title="Afficher">👁️</span>
                </div>
            </div>
        `;

        document.getElementById('ecolesContainer').appendChild(wrap);

        // WIRING : références
        const typeEl = document.getElementById(`type_${idx}`);
        const nomEl  = document.getElementById(`nom_${idx}`);
        const codeEl = document.getElementById(`code_${idx}`);
        const urlEl  = document.getElementById(`url_${idx}`);

        // Préfixer visuellement le type dans le nom (pour cohérence UX)
        const applyTypePrefix = () => {
            const typeVal = (typeEl.value || '').trim(); // CS|GS|EP|L.|COL
            const rest = stripTypePrefix(nomEl.value || '');
            nomEl.value = rest ? (typeVal + ' ' + rest) : (typeVal + ' ');
            updateFromInputs();
        };

        // *** met à jour Code & URL en tenant compte du type ***
        const updateFromInputs = () => {
            const t = (typeEl.value || '').trim();
            const n = nomEl.value || '';
            codeEl.value = computeCode(t, n); // 11 max
            urlEl.value  = computeUrl(t, n);  // 20 max
        };

        // Listeners
        typeEl.addEventListener('change', applyTypePrefix);
        nomEl.addEventListener('input', updateFromInputs);

        // Init
        applyTypePrefix();
    }

    function togglePwd(btn, inputId) {
        const el = document.getElementById(inputId);
        if (!el) return;
        if (el.type === 'password') {
            el.type = 'text'; btn.innerText = '🙈'; btn.title = 'Masquer';
        } else {
            el.type = 'password'; btn.innerText = '👁️'; btn.title = 'Afficher';
        }
    }

    // 1er bloc au chargement
    window.onload = function() {
        ajouterEcole();
        // Auto-compléter confirmation si vide au submit
        document.getElementById('formEcoles').addEventListener('submit', function() {
            const blocks = document.querySelectorAll('[id^="ecole_"]');
            blocks.forEach(function(b) {
                const pass = b.querySelector('input[name$="[admin_password]"]');
                const conf = b.querySelector('input[name$="[admin_password_confirm]"]');
                if (pass && conf && conf.value.trim() === '') conf.value = pass.value;
            });
        });
    };
    </script>

    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="../js/jquery.counterup.min.js"></script>
    <script src="js/select2.min.js"></script>
    <script src="js/datepicker.min.js"></script>
    <script src="js/jquery.smoothscroll.min.js"></script>
    <script src="js/jquery.scrollUp.min.js"></script>
    <script src="js/main.js"></script>

    <?php if (DEBUG): ?>
    <div style="position:fixed;z-index:9999;bottom:10px;right:10px;background:#111;color:#fff;padding:12px;border-radius:8px;font:12px monospace;max-width:50vw">
        <div><b>DEBUG ecoles_create.php</b></div>
        <div>user_id: <?= htmlspecialchars((string)uid()) ?></div>
        <div>session role: <?= htmlspecialchars((string)($_SESSION['role'] ?? 'null')) ?></div>
        <div>db_connect: <?= htmlspecialchars((string)$loaded_db_path) ?></div>
    </div>
    <?php endif; ?>
</body>
</html>

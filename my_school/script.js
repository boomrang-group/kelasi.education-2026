let ECOLE_INDEX = 0;

// === Injecte l'email du promoteur depuis PHP dans la page :
//   <script>window.PROMOTEUR_EMAIL = "promoteur@domaine.tld";</script>
// Si non défini, le champ restera éditable.
function getPromoteurEmail() {
  if (typeof window !== "undefined" && window.PROMOTEUR_EMAIL) {
    return String(window.PROMOTEUR_EMAIL || "").trim();
  }
  return "";
}

function slugify(str) {
  return (str || "")
    .toString()
    .normalize("NFD").replace(/[\u0300-\u036f]/g, "")   // accents
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")                       // non alphanum -> -
    .replace(/^-+|-+$/g, "")                           // - en tête/queue
    .substring(0, 60);
}

function genCodeFromName(nom) {
  // Génére un code Ecole (max 11) en MAJ, sans espaces/accents
  let s = (nom || "")
    .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, "");
  if (s.length < 3) s = (s + "ECOLE").substring(0, 5);
  return s.substring(0, 11);
}

function wireGenerators(idx) {
  const nameInput = document.getElementById(`nom_ecole_${idx}`);
  const codeInput = document.getElementById(`code_ecole_${idx}`);
  const urlInput  = document.getElementById(`url_ecole_${idx}`);
  const genBtn    = document.getElementById(`btn_gen_${idx}`);
  const genUrlBtn = document.getElementById(`btn_gen_url_${idx}`);

  if (genBtn) {
    genBtn.addEventListener('click', () => {
      codeInput.value = genCodeFromName(nameInput.value);
    });
  }
  if (genUrlBtn) {
    genUrlBtn.addEventListener('click', () => {
      const base = slugify(nameInput.value);
      urlInput.value = base || `ecole-${Date.now().toString(36)}`;
    });
  }

  // Préremplir Email (contact) = Promoteur
  const contactEmail = document.getElementById(`email_contact_${idx}`);
  const promo = getPromoteurEmail();
  if (contactEmail && promo) {
    contactEmail.value = promo;
    contactEmail.readOnly = true;               // verrouiller si on a l'email promoteur
    contactEmail.title = "Renseigné automatiquement avec l'email du promoteur";
  }
}

function ajouterEcole() {
  const idx = ECOLE_INDEX++;
  const container = document.getElementById('ecolesContainer');
  const wrap = document.createElement('div');
  wrap.className = 'card-ecole position-relative';
  wrap.id = `ecole_${idx}`;

  wrap.innerHTML = `
    <button type="button" class="btn btn-sm btn-danger remove-btn"
            onclick="document.getElementById('ecole_${idx}').remove()">Supprimer</button>
    <h5 class="mb-3">École #${idx + 1}</h5>

    <!-- ===================== INFOS ÉCOLE ===================== -->
    <div class="row">
      <div class="col-md-6 mb-3">
        <label>Nom de l'école *</label>
        <input id="nom_ecole_${idx}" type="text" class="form-control"
               name="ecoles[${idx}][nom_ecole]" placeholder="Ex: CS Kelasi" required>
      </div>

      <div class="col-md-3 mb-3">
        <label>Code école (max 11) *</label>
        <div class="input-group">
          <input id="code_ecole_${idx}" type="text" class="form-control"
                 name="ecoles[${idx}][code_ecole]" maxlength="100" required
                 placeholder="Ex: AMANI01"> 
        </div> 
      </div>

      <div class="col-md-3 mb-3">
        <label>URL école *</label>
        <div class="input-group">
          <input id="url_ecole_${idx}" type="text" class="form-control"
                 name="ecoles[${idx}][url_ecole]" required placeholder="ex: amanischool"> 
        </div> 
      </div>
    </div>

    <!-- ===================== COORDONNÉES ===================== -->
    <div class="row">
      <div class="col-md-4 mb-3">
        <label>Ville</label>
        <input class="form-control" name="ecoles[${idx}][ville]">
      </div>
      <div class="col-md-4 mb-3">
        <label>Province / État</label>
        <input class="form-control" name="ecoles[${idx}][province_etat]">
      </div>
      <div class="col-md-4 mb-3">
        <label>Pays</label>
        <input class="form-control" name="ecoles[${idx}][pays]">
      </div>
      <div class="col-12 mb-3">
        <label>Adresse</label>
        <textarea class="form-control" rows="2" name="ecoles[${idx}][adress]"></textarea>
      </div>
      <div class="col-md-6 mb-3">
        <label>Téléphone 1</label>
        <input class="form-control" name="ecoles[${idx}][telephone1]">
      </div>
      <div class="col-md-6 mb-3">
        <label>Téléphone 2</label>
        <input class="form-control" name="ecoles[${idx}][telephone2]">
      </div>

      <!-- Email (contact) = Promoteur -->
      <div class="col-md-6 mb-3">
        <label>Email (contact) — promoteur</label>
        <input id="email_contact_${idx}" type="email" class="form-control" name="ecoles[${idx}][email]"
               placeholder="Pré-rempli automatiquement si possible">
        <small class="text-muted">Sera automatiquement remplacé par l'email du promoteur côté serveur.</small>
      </div>
    </div>

    <!-- ===================== RESPONSABLE ===================== -->
    <div class="row">
      <div class="col-md-6 mb-3">
        <label>Nom du responsable</label>
        <input class="form-control" name="ecoles[${idx}][nom_responsable]">
      </div>
      <div class="col-md-6 mb-3">
        <label>Postnom du responsable</label>
        <input class="form-control" name="ecoles[${idx}][postnom_responsable]">
      </div>

      <div class="col-md-4 mb-3">
        <label>Type de pièce</label>
        <select class="form-control" name="ecoles[${idx}][type_piece]">
          <option value="">—</option>
          <option>CNI</option>
          <option>Passeport</option>
          <option>Permis</option>
          <option>Autre</option>
        </select>
      </div>
    </div>

    <!-- ===================== FICHIERS ===================== -->
    <div class="row">
      <div class="col-md-4 mb-3">
        <label>Logo (png/jpg/jpeg)</label>
        <input type="file" class="form-control" name="logo_${idx}" accept="image/png,image/jpg,image/jpeg">
      </div>
      <div class="col-md-4 mb-3">
        <label>Documents (pdf/zip)</label>
        <input type="file" class="form-control" name="docs_${idx}" accept="application/pdf,application/zip">
      </div>
      <div class="col-md-4 mb-3">
        <label>Pièce jointe (pdf/jpg/png)</label>
        <input type="file" class="form-control" name="piece_jointe_${idx}" accept="application/pdf,image/png,image/jpg,image/jpeg">
      </div>
    </div>

    <hr>

    <!-- ===================== COMPTE ADMIN (login) ===================== -->
    <h6>Compte Admin (de l'école)</h6>
    <div class="row">
      <div class="col-md-4 mb-3">
        <label>Email (login) *</label>
        <input type="email" class="form-control" name="ecoles[${idx}][admin_email]" required>
      </div>
      <div class="col-md-4 mb-3">
        <label>Username (optionnel)</label>
        <input class="form-control" name="ecoles[${idx}][admin_username]" placeholder="si vide, l'email sera utilisé">
      </div>
      <div class="col-md-4 mb-3">
        <label>Mot de passe *</label>
        <div class="pwd-wrap">
          <input id="admin_password_${idx}" type="password" class="form-control"
                 name="ecoles[${idx}][admin_password]" autocomplete="new-password" required>
          <button type="button" class="pwd-toggle btn btn-link p-0"
                  onclick="togglePwd(this, 'admin_password_${idx}')">👁️</button>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label>Confirmer mot de passe *</label>
      <div class="pwd-wrap">
        <input id="admin_password_confirm_${idx}" type="password" class="form-control"
               name="ecoles[${idx}][admin_password_confirm]" autocomplete="new-password" required>
        <button type="button" class="pwd-toggle btn btn-link p-0"
                onclick="togglePwd(this, 'admin_password_confirm_${idx}')">👁️</button>
      </div>
    </div>

    <p class="small-muted">
      Deux e-mails seront envoyés : <b>au promoteur (Email contact)</b> et <b>à l’admin (Email login)</b>.
    </p>
  `;

  container.appendChild(wrap);
  wireGenerators(idx);
}

// ======== Helpers UI existants ========
function togglePwd(btn, inputId) {
  const el = document.getElementById(inputId);
  if (!el) return;
  if (el.type === 'password') {
    el.type = 'text';
    btn.innerText = '🙈';
    btn.title = 'Masquer';
  } else {
    el.type = 'password';
    btn.innerText = '👁️';
    btn.title = 'Afficher';
  }
}

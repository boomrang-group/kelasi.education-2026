<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Inclure la DB avec vos chemins relatifs existants
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "<!doctype html><html><body><p style='color:#b00'>Erreur serveur : DB indisponible.</p></body></html>";
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$codeEcole = $_SESSION['code_ecole'] ?? '';
$flashSuccess = '';
$flashError = '';

if (empty($codeEcole)) {
    header("Location: index.php");
    exit;
}

// Chemin relatif depuis la racine du projet pour l'affichage HTML
$uploadWebDir = '../uploads/logo/'; 
// Chemin absolu sur le serveur pour la vérification et l'upload
$uploadServerDir = __DIR__ . '/../uploads/logo/';

// Logo par défaut en chemin relatif projet (évite les erreurs CORS sur localhost)
$defaultLogoUrl = '../img/logo.png';

// TRAITEMENT DE LA MISE À JOUR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomEcole   = trim($_POST['nom_ecole'] ?? '');
    $adresse    = trim($_POST['adresse'] ?? '');
    $telephone  = trim($_POST['telephone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $devise     = trim($_POST['devise'] ?? '');
    $ecoleSlug  = trim($_POST['url_ecole'] ?? '');

    if (!empty($nomEcole)) {
        try {
            // Traitement de l'upload du Logo
            $logoFileName = null;
            if (isset($_FILES['logo_ecole']) && $_FILES['logo_ecole']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath   = $_FILES['logo_ecole']['tmp_name'];
                $fileName      = $_FILES['logo_ecole']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    if (!is_dir($uploadServerDir)) {
                        mkdir($uploadServerDir, 0755, true);
                    }

                    $cleanCode = preg_replace('/[^a-zA-Z0-9]/', '', $codeEcole);
                    $logoFileName = 'logo_' . $cleanCode . '_' . date('Ymd_His') . '_image_' . rand(10000000, 99999999) . '.' . $fileExtension;
                    $destPath = $uploadServerDir . $logoFileName;

                    if (!move_uploaded_file($fileTmpPath, $destPath)) {
                        $flashError = "Erreur lors du déplacement du fichier téléchargé.";
                        $logoFileName = null;
                    }
                } else {
                    $flashError = "Format de fichier non autorisé (Seuls JPG, PNG, WEBP, SVG sont acceptés).";
                }
            }

            if (empty($flashError)) {
                if ($logoFileName !== null) {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE ecoles 
                        SET nom_ecole = ?, adress = ?, telephone1 = ?, email = ?, url_ecole = ?, logo = ?
                        WHERE code_ecole = ?
                    ");
                    $stmtUpdate->execute([$nomEcole, $adresse, $telephone, $email, $ecoleSlug, $logoFileName, $codeEcole]);
                } else {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE ecoles 
                        SET nom_ecole = ?, adress = ?, telephone1 = ?, email = ?, url_ecole = ?
                        WHERE code_ecole = ?
                    ");
                    $stmtUpdate->execute([$nomEcole, $adresse, $telephone, $email, $ecoleSlug, $codeEcole]);
                }
                $flashSuccess = "Les informations de l'école ont été mises à jour avec succès.";
            }
        } catch (PDOException $e) {
            $flashError = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    } else {
        $flashError = "Le nom de l'école ne peut pas être vide.";
    }
}

// RÉCUPÉRATION DES INFOS DE L'ÉCOLE
$stmt = $pdo->prepare("SELECT * FROM ecoles WHERE code_ecole = ? LIMIT 1");
$stmt->execute([$codeEcole]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    $school = [
        'nom_ecole'  => '',
        'code_ecole' => $codeEcole,
        'adress'    => '',
        'telephone1'  => '',
        'email'      => '',
        'devise'     => 'USD',
        'url_ecole' => '',
        'logo'       => ''
    ];
}

$nomEcole   = $school['nom_ecole'] ?? '';
$ecoleSlug  = trim($school['url_ecole'] ?? '');
$logoName   = $school['logo'] ?? '';

// Détermination de l'URL du logo à utiliser
$logoPathSrc = '';
$qrLogoUrl = $defaultLogoUrl;

if (!empty($logoName) && file_exists($uploadServerDir . $logoName)) {
    $logoPathSrc = $uploadWebDir . $logoName;
    $qrLogoUrl = $logoPathSrc;
}

// Génération de l'URL publique
$publicLink = $ecoleSlug ? "https://kelasi.education/@" . ltrim($ecoleSlug, '@') : '#';
$rawQrApiUrl = $ecoleSlug ? "https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=H&data=" . rawurlencode($publicLink) : '';
?>

<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | Paramètres de l'école</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Assets -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">

        <?php $nav = __DIR__ . '/layout/navbar.php'; if (file_exists($nav)) require $nav; ?>

        <div class="dashboard-page-one">
            <?php $side = __DIR__ . '/layout/sidebar.php'; if (file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">

                <div class="breadcrumbs-area d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="mb-2 mb-md-0">
                        <h3>Paramètres de l'établissement</h3>
                        <ul>
                            <li><a href="index.php">Accueil</a></li>
                            <li>Profil École</li>
                        </ul>
                    </div>

                    <!-- <div class="text-right text-md-right mw-100" style="max-width: 350px;">
                        <strong class="d-block text-truncate" title="<?= h($nomEcole) ?>">
                            <?= $nomEcole ? h($nomEcole) : 'Guest'; ?>
                        </strong>
                        <?php if ($ecoleSlug): ?>
                        <a href="<?= h($publicLink); ?>" target="_blank"
                            class="text-primary font-weight-bold d-block text-truncate" style="word-break: break-all;"
                            title="<?= h($publicLink); ?>">
                            kelasi.education/@<?= h($ecoleSlug); ?>
                        </a>
                        <?php endif; ?>
                    </div> -->
                </div>

                <div class="row">
                    <!-- Formulaire principal -->
                    <div class="col-xl-8 col-lg-7 col-12">
                        <div class="card height-auto">
                            <div class="card-body">
                                <div class="heading-layout1">
                                    <div class="item-title">
                                        <h3>Informations Générales</h3>
                                    </div>
                                </div>

                                <?php if ($flashSuccess): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= h($flashSuccess) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>

                                <?php if ($flashError): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= h($flashError) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>

                                <!-- Aperçu du Logo -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <!-- <label class="font-weight-bold d-block">Logo Actuel de l'Établissement</label> -->
                                        <div class="p-3 border rounded bg-light d-flex align-items-center gap-3">
                                            <?php if (!empty($logoPathSrc)): ?>
                                            <img src="<?= h($logoPathSrc) ?>" alt="Logo Actuel" class="img-thumbnail"
                                                style="max-height: 100px; width: auto; object-fit: contain;">
                                            <div class="me-2" style="margin-left:2rem">
                                                <span class="badge badge-success mb-1"><?= h($nomEcole) ?></span>
                                                <!-- <small class="d-block text-muted">Fichier : <?= h($logoName) ?></small> -->
                                                <?php if ($ecoleSlug): ?>
                                                <a href="<?= h($publicLink); ?>" target="_blank"
                                                    class="text-primary font-weight-bold d-block text-truncate"
                                                    style="word-break: break-all;" title="<?= h($publicLink); ?>">
                                                    kelasi.education/@<?= h($ecoleSlug); ?>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                            <?php else: ?>
                                            <img src="<?= h($defaultLogoUrl) ?>" alt="Logo Par Défaut"
                                                class="img-thumbnail"
                                                style="max-height: 100px; width: auto; object-fit: contain;"
                                                onerror="this.onerror=null; this.src='../../img/favicon.png';">
                                            <!-- <div class="d-none">
                                                <span class="badge badge-secondary mb-1">Logo par défaut</span>
                                                <small class="d-block text-muted">Affiche le logo du système Kelasi
                                                    (Aucun fichier trouvé dans <code>uploads/logo/</code>).</small>
                                            </div> -->
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <form method="POST" action="" enctype="multipart/form-data" class="new-added-form">
                                    <div class="row">
                                        <div class="col-xl-6 col-12 form-group">
                                            <label>Code École</label>
                                            <input type="text" value="<?= h($school['code_ecole'] ?? $codeEcole) ?>"
                                                class="form-control" readonly style="background-color: #e9ecef;">
                                        </div>

                                        <div class="col-xl-6 col-12 form-group">
                                            <label>Nom de l'école *</label>
                                            <input type="text" name="nom_ecole" value="<?= h($nomEcole) ?>"
                                                class="form-control" required>
                                        </div>

                                        <div class="col-xl-6 col-12 form-group">
                                            <label>Logo école (JPG, PNG, WEBP, SVG)</label>
                                            <input type="file" name="logo_ecole" class="form-control-file"
                                                accept="image/*">
                                            <!-- <small class="form-text text-muted">Sera enregistré dans la racine <code>/uploads/logo/</code></small> -->
                                        </div>

                                        <div class="col-xl-6 col-12 form-group">
                                            <label>Identifiant URL (Slug)</label>
                                            <input type="text" name="url_ecole" value="<?= h($ecoleSlug) ?>"
                                                class="form-control" placeholder="ex: st-joseph" readonly>
                                            <!-- <small class="form-text text-muted">Lien personnalisé :
                                                kelasi.education/@votre-slug</small> -->
                                        </div>

                                        <div class="col-xl-6 col-12 form-group">
                                            <label>Devise Principale</label>
                                            <input type="text" name="devise"
                                                value="<?= h($school['devise'] ?? 'USD') ?>" class="form-control"
                                                placeholder="USD, CDF, EUR..." readonly>
                                        </div>

                                        <div class="col-xl-6 col-12 form-group">
                                            <label>Téléphone de contact</label>
                                            <input type="text" name="telephone"
                                                value="<?= h($school['telephone1'] ?? '') ?>" class="form-control"
                                                placeholder="+243 ...">
                                        </div>

                                        <div class="col-xl-6 col-12 form-group">
                                            <label>Adresse E-mail</label>
                                            <input type="email" name="email" value="<?= h($school['email'] ?? '') ?>"
                                                class="form-control" placeholder="contact@ecole.com">
                                        </div>

                                        <div class="col-12 form-group">
                                            <label>Adresse physique</label>
                                            <input type="text" name="adresse" value="<?= h($school['adress'] ?? '') ?>"
                                                class="form-control" placeholder="Avenue, Quartier, Commune...">
                                        </div>

                                        <div class="col-12 mg-t-15">
                                            <button type="submit"
                                                class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">
                                                Enregistrer les modifications
                                            </button>
                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>

                    <!-- BLOC GENERATION QR CODE -->
                    <div class="col-xl-4 col-lg-5 col-12">
                        <div class="card height-auto">
                            <div class="card-body text-center">
                                <div class="heading-layout1">
                                    <div class="item-title">
                                        <h3>Lien Public & QR Code</h3>
                                    </div>
                                </div>

                                <?php if (!empty($ecoleSlug)): ?>
                                <div class="p-3 border rounded bg-light mb-3">
                                    <div class="mb-3 d-flex justify-content-center align-items-center"
                                        style="min-height: 220px;">
                                        <canvas id="qrCanvas" width="260" height="260"
                                            class="border p-2 bg-white rounded shadow-sm"
                                            style="max-width: 100%; height: auto;"></canvas>
                                    </div>

                                    <p class="mb-2"><strong>Lien public de l'école :</strong></p>
                                    <a href="<?= h($publicLink) ?>" target="_blank"
                                        class="d-block text-break font-weight-bold text-primary mb-2"
                                        style="word-break: break-all;">
                                        <?= h($publicLink) ?>
                                    </a>
                                </div>

                                <button type="button" onclick="downloadQRCode('<?= h($ecoleSlug) ?>')"
                                    class="btn-fill-lg bg-blue-dark btn-hover-yellow text-white w-100">
                                    <i class="fas fa-download mr-2"></i> Télécharger le QR Code
                                </button>
                                <?php else: ?>
                                <div class="alert alert-warning text-left mb-0">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Veuillez définir un <strong>Identifiant URL (Slug)</strong> dans le formulaire
                                    ci-contre pour générer votre QR Code et lien public.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <?php $foot = __DIR__ . '/layout/footer.php'; if (file_exists($foot)) require $foot; ?>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/main.js"></script>

    <!-- SCRIPT QR CODE & LOGO INCRUSTÉ -->
    <?php if (!empty($ecoleSlug)): ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        generateQrCodeWithLogo();
    });

    function generateQrCodeWithLogo() {
        const canvas = document.getElementById('qrCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        const qrUrl = "<?= $rawQrApiUrl ?>";
        const logoUrl = "<?= $qrLogoUrl ?>";

        const qrImg = new Image();
        qrImg.crossOrigin = "anonymous";
        qrImg.src = qrUrl;

        qrImg.onload = function() {
            // Dessine le QR Code principal sur le Canvas
            ctx.drawImage(qrImg, 0, 0, canvas.width, canvas.height);

            // Tentative d'incrustation du logo au centre
            const logoImg = new Image();
            logoImg.src = logoUrl;

            logoImg.onload = function() {
                drawLogoOnCanvas(ctx, canvas, logoImg);
            };

            logoImg.onerror = function() {
                // Tente de basculer vers le logo par défaut en cas d'échec
                const fallbackLogo = new Image();
                fallbackLogo.src = "<?= $defaultLogoUrl ?>";
                fallbackLogo.onload = function() {
                    drawLogoOnCanvas(ctx, canvas, fallbackLogo);
                };
                // Si même le fallback échoue, le QR code brut reste affiché correctement
            };
        };
    }

    function drawLogoOnCanvas(ctx, canvas, logoImg) {
        const logoSize = canvas.width * 0.22; // Taille du logo (22%)
        const x = (canvas.width - logoSize) / 2;
        const y = (canvas.height - logoSize) / 2;
        const padding = 6;

        // Carré blanc de fond
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(x - padding / 2, y - padding / 2, logoSize + padding, logoSize + padding);

        // Bordure légère
        ctx.strokeStyle = '#CCCCCC';
        ctx.lineWidth = 1;
        ctx.strokeRect(x - padding / 2, y - padding / 2, logoSize + padding, logoSize + padding);

        // Dessin du logo au centre
        ctx.drawImage(logoImg, x, y, logoSize, logoSize);
    }

    function downloadQRCode(slug) {
        const canvas = document.getElementById('qrCanvas');
        if (!canvas) return;

        const a = document.createElement('a');
        a.download = `qrcode-${slug}.png`;
        a.href = canvas.toDataURL('image/png');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
    </script>
    <?php endif; ?>
</body>

</html>
<?php
// repetitorats/teachers/services/cours-lessons-services.php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));

if (empty($_SESSION['user_id']) || $role !== 'repetiteur') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$dbPaths = [
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
];

foreach ($dbPaths as $path) {
    if (is_file($path)) {
        require_once $path;
        break;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur : base de données indisponible.');
}

$userId = (int) $_SESSION['user_id'];
$username = (string) ($_SESSION['username'] ?? 'Répétiteur');
$firstName = (string) ($_SESSION['first_name'] ?? '');
$lastName = (string) ($_SESSION['last_name'] ?? '');
$codeEcole = trim((string) ($_SESSION['code_ecole'] ?? ''));

$nomComplet = trim($firstName . ' ' . $lastName);
if ($nomComplet === '') {
    $nomComplet = $username;
}

$nomEcole = (string) ($_SESSION['nom_ecole'] ?? 'Raynée International School');
$urlEcole = (string) ($_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international');

$successMsg = '';
$errorMsg = '';
$mesCours = [];
$existingCourses = [];

// =====================================================
// TRAITEMENT DES ACTIONS (AJAX / POST CLASSIQUE)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    // 1. Mise à jour du statut (AJAX)
    if ($action === 'update_status') {
        $leconId = (int) ($_POST['lecon_id'] ?? 0);
        $newStatus = (int) ($_POST['is_published'] ?? 0);

        if ($leconId > 0) {
            try {
                $stVerify = $pdo->prepare(
                    'SELECT l.id FROM lecons_repetitorat l
                     JOIN cours_repetitorat c ON l.cours_id = c.id
                     WHERE l.id = :lid AND c.teacher_user_id = :uid AND c.code_ecole = :ce LIMIT 1'
                );
                $stVerify->execute([':lid' => $leconId, ':uid' => $userId, ':ce' => $codeEcole]);

                if ($stVerify->fetchColumn()) {
                    $stUpd = $pdo->prepare('UPDATE lecons_repetitorat SET is_published = :pub WHERE id = :lid');
                    $stUpd->execute([':pub' => $newStatus, ':lid' => $leconId]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
                }
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }
    }

    // 2. Suppression d'une leçon (AJAX)
    if ($action === 'delete_lecon') {
        $leconId = (int) ($_POST['lecon_id'] ?? 0);
        if ($leconId > 0) {
            try {
                $stVerify = $pdo->prepare(
                    'SELECT l.id FROM lecons_repetitorat l
                     JOIN cours_repetitorat c ON l.cours_id = c.id
                     WHERE l.id = :lid AND c.teacher_user_id = :uid AND c.code_ecole = :ce LIMIT 1'
                );
                $stVerify->execute([':lid' => $leconId, ':uid' => $userId, ':ce' => $codeEcole]);
                if ($stVerify->fetchColumn()) {
                    $stDel = $pdo->prepare('DELETE FROM lecons_repetitorat WHERE id = :lid');
                    $stDel->execute([':lid' => $leconId]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Non autorisé.']);
                }
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }
    }

    // 3. Modification d'une leçon (POST classique via Modal Edit)
    if ($action === 'edit_lecon') {
        $leconId = (int) ($_POST['lecon_id'] ?? 0);
        $titreLecon = trim((string) ($_POST['titre_lecon'] ?? ''));
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        if ($leconId > 0 && $titreLecon !== '') {
            try {
                $stVerify = $pdo->prepare(
                    'SELECT l.id FROM lecons_repetitorat l
                     JOIN cours_repetitorat c ON l.cours_id = c.id
                     WHERE l.id = :lid AND c.teacher_user_id = :uid AND c.code_ecole = :ce LIMIT 1'
                );
                $stVerify->execute([':lid' => $leconId, ':uid' => $userId, ':ce' => $codeEcole]);
                if ($stVerify->fetchColumn()) {
                    $stUpd = $pdo->prepare('UPDATE lecons_repetitorat SET titre = :titre, is_published = :pub WHERE id = :lid');
                    $stUpd->execute([':titre' => $titreLecon, ':pub' => $isPublished, ':lid' => $leconId]);
                    $successMsg = 'Leçon mise à jour avec succès.';
                } else {
                    $errorMsg = 'Modification non autorisée.';
                }
            } catch (Throwable $e) {
                $errorMsg = 'Erreur lors de la modification : ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'Veuillez remplir les champs obligatoires pour la modification.';
        }
    }

    // 4. Création d'un nouveau cours / leçon
    if ($action === 'create_cours') {
        $nomCours = trim((string) ($_POST['nom_cours'] ?? ''));
        $titreLecon = trim((string) ($_POST['titre_lecon'] ?? ''));
        $descriptionContenu = trim((string) ($_POST['description_contenu'] ?? ''));
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        $typeContenu = strtolower(trim((string) ($_POST['type_contenu'] ?? '')));
        if ($typeContenu === '' && isset($_FILES['fichier_media']) && (int)($_FILES['fichier_media']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string)$_FILES['fichier_media']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $typeContenu = 'image';
            } elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a'], true)) {
                $typeContenu = 'audio';
            } elseif (in_array($ext, ['mp4', 'webm', 'ogv', 'mov'], true)) {
                $typeContenu = 'video';
            } else {
                $typeContenu = 'pdf';
            }
        }
        if ($typeContenu === '') { $typeContenu = 'pdf'; }

        if ($nomCours === '' || $titreLecon === '') {
            $errorMsg = 'Veuillez remplir le nom du cours et le titre de la leçon.';
        } elseif ($codeEcole === '') {
            $errorMsg = 'Le code de votre école est introuvable dans la session.';
        } else {
            $fichierDeplace = null;
            try {
                $pdo->beginTransaction();

                $stCheck = $pdo->prepare(
                    'SELECT id FROM cours_repetitorat WHERE nom = :nom AND code_ecole = :ce AND teacher_user_id = :uid LIMIT 1'
                );
                $stCheck->execute([':nom' => $nomCours, ':ce' => $codeEcole, ':uid' => $userId]);
                $coursId = $stCheck->fetchColumn();

                if ($coursId === false) {
                    $stInsCours = $pdo->prepare('INSERT INTO cours_repetitorat (nom, teacher_user_id, code_ecole) VALUES (:nom, :uid, :ce)');
                    $stInsCours->execute([':nom' => $nomCours, ':uid' => $userId, ':ce' => $codeEcole]);
                    $coursId = (int) $pdo->lastInsertId();
                } else {
                    $coursId = (int) $coursId;
                }

                $stOrdre = $pdo->prepare('SELECT COALESCE(MAX(ordre), 0) FROM lecons_repetitorat WHERE cours_id = :cid');
                $stOrdre->execute([':cid' => $coursId]);
                $ordre = (int) $stOrdre->fetchColumn() + 1;

                $stInsLecon = $pdo->prepare('INSERT INTO lecons_repetitorat (cours_id, titre, ordre, is_published) VALUES (:cid, :titre, :ordre, :pub)');
                $stInsLecon->execute([':cid' => $coursId, ':titre' => $titreLecon, ':ordre' => $ordre, ':pub' => $isPublished]);
                $leconId = (int) $pdo->lastInsertId();

                $uploadError = (int) ($_FILES['fichier_media']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadError !== UPLOAD_ERR_NO_FILE) {
                    if ($uploadError !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Le téléversement du fichier a échoué.');
                    }
                    $fileTmp = (string) $_FILES['fichier_media']['tmp_name'];
                    $originalName = basename((string) $_FILES['fichier_media']['name']);
                    if (!is_uploaded_file($fileTmp)) {
                        throw new RuntimeException('Fichier invalide.');
                    }

                    $extensionsAutorisees = [
                        'pdf'   => ['pdf'],
                        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                        'audio' => ['mp3', 'wav', 'ogg', 'm4a'],
                        'video' => ['mp4', 'webm', 'ogv', 'mov'],
                    ];
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    if (!in_array($extension, $extensionsAutorisees[$typeContenu], true)) {
                        throw new RuntimeException('Extension non autorisée.');
                    }

                    $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalName);
                    $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . ($safeName ?: 'fichier.' . $extension);
                    $uploadDir = __DIR__ . '/../../../uploads/cours_repetitorats/';

                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException('Impossible de créer le dossier uploads.');
                    }
                    $destination = $uploadDir . $fileName;
                    if (!move_uploaded_file($fileTmp, $destination)) {
                        throw new RuntimeException('Impossible de déplacer le fichier.');
                    }
                    $fichierDeplace = $destination;

                    $stClDefault = $pdo->prepare('SELECT id FROM classes WHERE code_ecole = :ce LIMIT 1');
                    $stClDefault->execute([':ce' => $codeEcole]);
                    $defaultClassId = (int)$stClDefault->fetchColumn();
                    if ($defaultClassId <= 0) { $defaultClassId = 1; }

                    if ($typeContenu === 'pdf') {
                        $stMed = $pdo->prepare('INSERT INTO pdfs (title, description, filename, class, code_ecole) VALUES (:t, :d, :f, :cls, :ce)');
                    } elseif ($typeContenu === 'image') {
                        $stMed = $pdo->prepare('INSERT INTO images (title, description, filename, class, code_ecole) VALUES (:t, :d, :f, :cls, :ce)');
                    } elseif ($typeContenu === 'audio') {
                        $stMed = $pdo->prepare('INSERT INTO audios (titre, description, fichier, class, code_ecole) VALUES (:t, :d, :f, :cls, :ce)');
                    } else {
                        $stMed = $pdo->prepare('INSERT INTO videos (title, description, filename, class, code_ecole) VALUES (:t, :d, :f, :cls, :ce)');
                    }
                    $stMed->execute([':t' => $titreLecon, ':d' => $descriptionContenu, ':f' => $fileName, ':cls' => $defaultClassId, ':ce' => $codeEcole]);
                    $contenuTableId = (int) $pdo->lastInsertId();

                    if ($contenuTableId > 0) {
                        $stLink = $pdo->prepare('INSERT INTO lecon_contenus_repetitorat (lecon_id, type_contenu, contenu_id, ordre) VALUES (:lid, :type, :cid, 1)');
                        $stLink->execute([':lid' => $leconId, ':type' => $typeContenu, ':cid' => $contenuTableId]);
                    }
                }

                $pdo->commit();
                $successMsg = 'Le cours et sa leçon ont été enregistrés avec succès.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                if ($fichierDeplace !== null && is_file($fichierDeplace)) { @unlink($fichierDeplace); }
                $errorMsg = 'Erreur lors de l’enregistrement : ' . $e->getMessage();
            }
        }
    }
}

// Récupération des cours existants pour le datalist
try {
    $stCourses = $pdo->prepare('SELECT DISTINCT nom FROM cours_repetitorat WHERE teacher_user_id = :uid AND code_ecole = :ce ORDER BY nom ASC');
    $stCourses->execute([':uid' => $userId, ':ce' => $codeEcole]);
    $existingCourses = $stCourses->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

// Récupérer la liste des cours et leçons
try {
    $stCoursList = $pdo->prepare(
        'SELECT
            c.id AS cours_id,
            c.nom AS cours_nom,
            l.id AS lecon_id,
            l.titre AS lecon_titre,
            l.is_published,
            l.created_at AS lecon_created_at,
            lc.type_contenu,
            lc.contenu_id
         FROM cours_repetitorat c
         LEFT JOIN lecons_repetitorat l ON l.cours_id = c.id
         LEFT JOIN lecon_contenus_repetitorat lc ON lc.lecon_id = l.id
         WHERE c.teacher_user_id = :uid
           AND c.code_ecole = :ce
         ORDER BY c.id DESC, l.ordre ASC'
    );
    $stCoursList->execute([':uid' => $userId, ':ce' => $codeEcole]);
    $mesCours = $stCoursList->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errorMsg = $errorMsg !== '' ? $errorMsg : 'Impossible de récupérer la liste des cours.';
}
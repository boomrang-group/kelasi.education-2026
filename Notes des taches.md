Du 22 au 24 AVRIL :
1. Amelioration de sidebar icons, supprimer Paiement cash et fixation et quelques effets stylés
2. Ajout deux btn Paiement cash et fixation dans Paiement
3. Dashboard admin ajout une salutation à l'user connecter
4. Ameliorer la page add classe qui enregistrer seulement la classe mais elle commence aussi ca prend aussi en charge la modification
5. Gestion du mises à jour de la page classe(Update et delete d'une classe ciblée)
6. Ajoute deux btn dans la liste des écoles coté visiteurs ils peuvents s'inscrire ou voir les détailles de l'écoles 
7. Add base des données : ALTER TABLE classes ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1;

DU 23 au 
A executer dans la DATA : 
CREATE TABLE depenses(
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_ecole VARCHAR(50) NOT NULL,
    beneficiaire VARCHAR(20) NOT NULL,
    description TEXT NULL,
    montant DECIMAL(10, 2) NOT NULL,
    date_depense DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE class_subject_teacher
DROP INDEX uq_cst_unique_class; 

ALTER TABLE class_subject_teacher
ADD UNIQUE KEY uq_class_teacher (class_id, teacher_user_id);

ALTER TABLE class_subject_teacher
DROP COLUMN username,
DROP COLUMN password;

ALTER TABLE class_subject_teacher
DROP COLUMN username,
DROP COLUMN password;

ALTER TABLE `class_subject_teacher` ADD `username` VARCHAR(50) NOT NULL DEFAULT 'MyKelasi' AFTER `teacher_user_id`;

ALTER TABLE `class_subject_teacher` ADD CONSTRAINT `fk_cst_user` FOREIGN KEY (`teacher_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE cours ADD COLUMN teacher_user_id INT NOT NULL AFTER class;

ALTER TABLE `cours` ADD CONSTRAINT `fk_cours_teacher` FOREIGN KEY (`teacher_user_id`) REFERENCES `class_subject_teacher`(`teacher_user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

1. Gestion des dépenses
2. Ajouter de la page setting avec les btn pour les navigations,
3. Page controle pour les suivis de frais scolaire
4. Page Report pour la gestion des rapport d'établissement
5. Gestion d'une classe qui doit être occupée par un ou plusieurs profs.
6. Gestion des cours, lecons et ses contenus(image, pdf, video et audio)

#18.MAI.2026

CREATE TABLE horaires (

    id INT(11) NOT NULL AUTO_INCREMENT,

    class_id INT(11) NOT NULL,

    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,

    lundi VARCHAR(255) DEFAULT NULL,
    mardi VARCHAR(255) DEFAULT NULL,
    mercredi VARCHAR(255) DEFAULT NULL,
    jeudi VARCHAR(255) DEFAULT NULL,
    vendredi VARCHAR(255) DEFAULT NULL,
    samedi VARCHAR(255) DEFAULT NULL,

    code_ecole VARCHAR(11) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_horaire_class (class_id),
    KEY idx_horaire_ecole (code_ecole),

    CONSTRAINT fk_horaire_class
        FOREIGN KEY (class_id)
        REFERENCES classes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_horaire_ecole
        FOREIGN KEY (code_ecole)
        REFERENCES ecoles(code_ecole)
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

CREATE TABLE presence_eleve (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    class_id INT NOT NULL,
    date_presence DATE NOT NULL,
    statut ENUM('present','absent','retard') DEFAULT 'present',
    commentaire TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX (eleve_id),
    INDEX (class_id),
    INDEX (date_presence)
);

1. Ajout de la page horaire pour permettre à l'admin de créer l'horaire des différentes classes.
2. Hidden preloader dans forgot.php
3. Add btn imprimer recu et la page d'impression 
4. dans settin show du btn horaire 
5. N'importe quelle la classe  peut etre modifier sans bloquer la suite
6. Ajout page pour historique de paiement et le paiement du jour 
7. Gestion des précense chez admin et élève pour la consultation, et le prof pour l'enregistrement

#Mises à jour pour le 20.05
1. Ajoute la page horaire de cours chez le prof, eleve et l'afficher dans sidebar;
2. Corretion de la page controle pour qu'il soit afficher
3. Admin gère les cours (créer, modifier, supprimer, affecter les cours chez un autre prof, ou dans une classe et l'ouvert le cours)
4. Masquer preloader dans autres pages 
5. Ajouter dans le menu sidebar le lien pour les pages 

#Mises à jour 01.06
executer dans data : 
    ALTER TABLE class_subject_teacher
    ADD titulaire TINYINT(1) NOT NULL DEFAULT 0
    AFTER teacher_user_id;

1. Effectuer le paiement en présentiel
2. Possibilité d'assigner un enseignant comme titulaire d'une classe

#MAJ 23.06
1.Modification élève
2. Changement du nom colonne titulaire => status
3. Mises en place de la page voir detail pour l'eleve chez l'admin qu'il puisse voir les détails de l'élève(Informations de l'élève, Responsables, Situation financière)
4. Correction pour l'affichage de differnent frais en apercu
5. Correction pour le mouvement de paiement avec une precision de connaitre montant à payer, payé et reste
6. Eleve peut lire les contenus d'une lecons 
7. L'élève peut consulter sa précense

 Get-ChildItem -File | Rename-Item -NewName {$_.name -replace ' ', '_'}
 ALTER TABLE `students` ADD `id_user` INT NOT NULL AFTER `statut`;

10.10.2026
ALTER TABLE users MODIFY COLUMN role ENUM('promoteur','admin','prof','eleve','manager','eleve_encadre') NOT NULL;
ALTER TABLE `users` CHANGE `role` `role` ENUM('promoteur','admin','prof','eleve','manager','eleve_encadre','repetiteur') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

corriger moi le service ici en fonction des ces tables : 
corrgiger ces codes ; 
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
if (empty($_SESSION['user_id']) ||$role !== 'repetiteur') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$pdo = null;
$dbPaths = [
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
];

foreach ($dbPaths as$path) {
    if (is_file($path)) {
        require_once $path;
        break;
    }
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('Erreur serveur : base de données indisponible.');
}

$userId   = (int)$_SESSION['user_id'];
$username = (string) ($_SESSION['username'] ?? 'Répétiteur');
$firstName = (string) ($_SESSION['first_name'] ?? '');
$lastName  = (string) ($_SESSION['last_name'] ?? '');
$codeEcole = (string) ($_SESSION['code_ecole'] ?? '');

$nomComplet = trim($firstName . ' ' .$lastName);
if ($nomComplet === '') {
    $nomComplet =$username;
}

$nomEcole = (string) ($_SESSION['nom_ecole'] ?? 'Raynée International School');
$urlEcole = (string) ($_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international');

$successMsg = '';$errorMsg = '';
$classesList = [];$mesCours = [];

// Récupérer les classes disponibles pour l'école (obligatoire pour la table `cours`)
try {
    $stCl =$pdo->prepare(
        'SELECT id, classe, description
         FROM classes
         WHERE code_ecole = :ce
         ORDER BY classe ASC'
    );
    $stCl->execute([':ce' =>$codeEcole]);
    $classesList =$stCl->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Erreur récupération classes : ' . $e->getMessage());
}

// Traitement du formulaire de création de cours/leçon
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomCours = trim((string) ($_POST['nom_cours'] ?? ''));
    $titreLecon = trim((string) ($_POST['titre_lecon'] ?? ''));
    $descriptionContenu = trim((string) ($_POST['description_contenu'] ?? ''));
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    
    // $classId = (int) ($_POST['class_id'] ?? 0);

    $typeContenu = strtolower(trim((string) ($_POST['type_contenu'] ?? 'pdf')));$typesAutorises = ['pdf', 'image', 'audio', 'video'];
    if (!in_array($typeContenu, $typesAutorises, true)) {$typeContenu = 'pdf';
    }

    if ($nomCours === '' || $titreLecon === '') {$errorMsg = 'Veuillez remplir le nom du cours, le titre de la leçon et sélectionner une classe.';
    } else {
        $fichierDeplace = null;

        try {
            $pdo->beginTransaction();

            // 1. Vérifier si le cours existe déjà pour cette classe, ce nom et cette école
            $stCheck = $pdo->prepare( 'SELECT id FROM cours WHERE nom = :nom AND code_ecole = :ce AND teacher_user_id = :uid LIMIT 1' );$stCheck->execute([
                ':nom' => $nomCours, 
                ':ce'  => $codeEcole,
                ':uid' => $userId,
            ]);
            $coursId =$stCheck->fetchColumn();

            if (!$coursId) {$stInsCours = $pdo->prepare( 'INSERT INTO cours (nom, teacher_user_id, code_ecole) VALUES (:nom, :uid, :ce)' );$stInsCours->execute([
                    ':nom' => $nomCours, 
                    ':uid' => $userId,
                    ':ce'  => $codeEcole,
                ]);
                $coursId = (int)$pdo->lastInsertId();
            } else {
                $coursId = (int)$coursId;
            }

            // 2. Calculer l'ordre de la leçon
            $stOrdre =$pdo->prepare('SELECT COUNT(*) FROM lecons WHERE cours_id = :cid');
            $stOrdre->execute([':cid' =>$coursId]);
            $ordre = (int)$stOrdre->fetchColumn() + 1;

            // 3. Enregistrer la leçon
            $stInsLecon = $pdo->prepare( 'INSERT INTO lecons (cours_id, titre, ordre, is_published) VALUES (:cid, :titre, :ordre, :pub)' );$stInsLecon->execute([
                ':cid'   => $coursId,
                ':titre' => $titreLecon,
                ':ordre' => $ordre,
                ':pub'   => $isPublished,
            ]);
            $leconId = (int)$pdo->lastInsertId();

            // 4. Téléversement du fichier média (si présent)
            $contenuTableId = 0;
            if (isset($_FILES['fichier_media'])
                && (int) ($_FILES['fichier_media']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {

                $fileTmp = (string)$_FILES['fichier_media']['tmp_name'];
                $originalName = basename((string)$_FILES['fichier_media']['name']);
                $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_',$originalName);
                $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
                $uploadDir = __DIR__ . '/../../../uploads/';

                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    throw new RuntimeException('Impossible de créer le dossier de téléversement.');
                }

                $destination = $uploadDir .$fileName;
                if (!move_uploaded_file($fileTmp,$destination)) {
                    throw new RuntimeException('Le fichier multimédia n’a pas pu être téléversé.');
                }
                $fichierDeplace =$destination;

                // Insertion dans la table spécifique au type de média
                if ($typeContenu === 'pdf') {
                    $stMed =$pdo->prepare('INSERT INTO pdfs (title, description, filename, class, code_ecole) VALUES (:t, :d, :f, :cls, :ce)');
                } elseif ($typeContenu === 'image') {
                    $stMed =$pdo->prepare('INSERT INTO images (title, description, filename, code_ecole) VALUES (:t, :d, :f, :ce)');
                } elseif ($typeContenu === 'audio') {
                    $stMed =$pdo->prepare('INSERT INTO audios (titre, description, fichier, code_ecole) VALUES (:t, :d, :f, :ce)');
                } else {
                    $stMed =$pdo->prepare('INSERT INTO videos (title, description, filename, code_ecole) VALUES (:t, :d, :f, :ce)');
                }

                $stMed->execute([
                    ':t'   => $titreLecon,
                    ':d'   => $descriptionContenu,
                    ':f'   => $fileName, 
                    ':ce'  => $codeEcole,
                ]);
                $contenuTableId = (int)$pdo->lastInsertId();
            }

            // 5. Lier le média à la leçon si enregistré
            if ($contenuTableId > 0) {$stLink = $pdo->prepare( 'INSERT INTO lecon_contenus (lecon_id, type_contenu, contenu_id, ordre) VALUES (:lid, :type, :cid, 1)' );$stLink->execute([
                    ':lid'  => $leconId,
                    ':type' => $typeContenu,
                    ':cid'  => $contenuTableId,
                ]);
            }

            $pdo->commit();$successMsg = 'Le cours et sa leçon ont été enregistrés avec succès.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {$pdo->rollBack();
            }
            if ($fichierDeplace !== null && is_file($fichierDeplace)) {
                @unlink($fichierDeplace);
            }
            error_log('Erreur création cours/leçon : ' . $e->getMessage());$errorMsg = 'Erreur lors de l’enregistrement : ' . $e->getMessage();
        }
    }
}

// Récupérer la liste des cours du répétiteur
try {
    $stCoursList =$pdo->prepare(
        'SELECT c.id AS cours_id,
                c.nom AS cours_nom, 
                l.id AS lecon_id,
                l.titre AS lecon_titre,
                l.is_published,
                l.created_at
         FROM cours c 
         LEFT JOIN lecons l ON l.cours_id = c.id
         WHERE c.teacher_user_id = :uid
         ORDER BY c.id DESC, l.ordre ASC'
    );
    $stCoursList->execute([':uid' =>$userId]);
    $mesCours =$stCoursList->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Erreur récupération cours : ' . $e->getMessage());
}
CREATE TABLE cours_repetitorat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    teacher_user_id INT NOT NULL,
    code_ecole VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_rep_teacher (teacher_user_id),
    INDEX idx_rep_ecole (code_ecole)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE lecons_repetitorat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cours_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    ordre INT NOT NULL DEFAULT 1,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_rep_lecons_cours (cours_id),

    CONSTRAINT fk_lecons_repetitorat_cours
        FOREIGN KEY (cours_id)
        REFERENCES cours_repetitorat(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE lecon_contenus_repetitorat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecon_id INT NOT NULL,
    type_contenu ENUM('pdf', 'image', 'audio', 'video') NOT NULL,
    contenu_id INT NOT NULL,
    ordre INT NOT NULL DEFAULT 1,

    INDEX idx_rep_contenus_lecon (lecon_id),

    CONSTRAINT fk_contenus_repetitorat_lecon
        FOREIGN KEY (lecon_id)
        REFERENCES lecons_repetitorat(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
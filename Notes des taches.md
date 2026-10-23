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

#20.10.2026
CREATE TABLE IF NOT EXISTS quiz_repetitorat(
    id INT AUTO_INCREMENT PRIMARY KEY,
    cours_id INT NOT NULL,
    teacher_user_id INT NOT NULL,
    code_ecole VARCHAR(50) NOT NULL,
    titre VARCHAR(255) NOT NULL,
    type_quiz ENUM('qcm', 'qr', 'pj') DEFAULT 'qcm',
    description TEXT NULL,
    fichier_pj VARCHAR(255) NULL,
    is_published TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Si elles n'existent pas du tout, voici leur création directe :
CREATE TABLE IF NOT EXISTS quiz_questions_repetitorat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_texte TEXT NOT NULL,
    type_question ENUM('qcm', 'qr') DEFAULT 'qcm',
    points INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_options_repetitorat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_texte TEXT NOT NULL,
    est_correcte TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `quiz_resultats_repetitorat` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `quiz_id` int(11) NOT NULL,
 `student_id` int(11) NOT NULL,
 `note_obtenue` decimal(5,2) NOT NULL DEFAULT 0.00,
 `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
 PRIMARY KEY (`id`),
 KEY `idx_qrr_quiz` (`quiz_id`),
 KEY `idx_qrr_student` (`student_id`),
 CONSTRAINT `fk_qrr_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quiz_repetitorat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `fk_qrr_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
<?php
// repetitorats/teachers/services/accounts-services.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || $role !== 'repetiteur') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$dbPaths = array(
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
);

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
$codeEcole = trim((string) ($_SESSION['code_ecole'] ?? ''));

$successMsg = '';
$errorMsg = '';

// Traitement de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'update_profile') {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            $errorMsg = 'Le prénom et le nom sont obligatoires.';
        } else {
            try {
                $stUpd = $pdo->prepare('UPDATE users SET first_name = :fn, last_name = :ln, phone = :ph WHERE id = :uid');
                $stUpd->execute(array(
                    ':fn' => $firstName,
                    ':ln' => $lastName,
                    ':ph' => $phone,
                    ':uid' => $userId
                ));

                // Mettre à jour les variables de session
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;

                $successMsg = 'Votre profil a été mis à jour avec succès.';
            } catch (Throwable $e) {
                $errorMsg = 'Erreur lors de la mise à jour : ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errorMsg = 'Tous les champs du mot de passe sont requis.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMsg = 'Les nouveaux mots de passe ne correspondent pas.';
        } else {
            try {
                $stUser = $pdo->prepare('SELECT password FROM users WHERE id = :uid LIMIT 1');
                $stUser->execute(array(':uid' => $userId));
                $dbPass = $stUser->fetchColumn();

                if ($dbPass && password_verify($currentPassword, $dbPass)) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stPass = $pdo->prepare('UPDATE users SET password = :pass WHERE id = :uid');
                    $stPass->execute(array(':pass' => $hashedPassword, ':uid' => $userId));

                    $successMsg = 'Votre mot de passe a été modifié avec succès.';
                } else {
                    $errorMsg = 'Le mot de passe actuel est incorrect.';
                }
            } catch (Throwable $e) {
                $errorMsg = 'Erreur lors du changement de mot de passe : ' . $e->getMessage();
            }
        }
    }
}

// Récupération des données fraîches de l'utilisateur
try {
    $stData = $pdo->prepare('SELECT * FROM users WHERE id = :uid LIMIT 1');
    $stData->execute(array(':uid' => $userId));
    $user = $stData->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $user = array();
}

$nomComplet = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($nomComplet === '') {
    $nomComplet = (string) ($user['username'] ?? 'Répétiteur');
}

$nomEcole = (string) ($_SESSION['nom_ecole'] ?? 'Raynée International School');
$urlEcole = (string) ($_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international');
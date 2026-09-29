<?php
require '../../database/db_connect.php';
require 'user_connecter.php';
require '../../service/security_helpers.php';
require 'email_send_student.php';

$code_ecole = $_SESSION['code_ecole'] ?? null;

csrf_protect();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    try {

        if ($_SERVER['CONTENT_LENGTH'] > 50 * 1024 * 1024) {
            die("Fichier trop volumineux !");
        }

        $pdo->beginTransaction();

        // =========================
        // DONNÉES
        // =========================
        $edit_id           = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
        $isEdit            = $edit_id > 0;

        $first_name        = $_POST['first_name'];
        $last_name         = $_POST['last_name'];
        $username          = $_POST['username'];
        $gender            = $_POST['gender'];

        $dob_input         = $_POST['date_of_birth'];
        $dob               = date('Y-m-d', strtotime(str_replace('/', '-', $dob_input)));

        $email             = $_POST['email'];
        $phone             = $_POST['phone'];
        $classe            = $_POST['classe'];

        $father            = $_POST['father'];
        $mother            = $_POST['mother'];
        $phone_responsable = $_POST['phone_responsable'];
        $email_responsable = $_POST['email_responsable'];

        $password_plain    = $_POST['password'] ?? '';
        $password          = $password_plain ? password_hash($password_plain, PASSWORD_DEFAULT) : null;

        $ecole_provenance  = $_POST['ecole_provenance'];
        $statut            = 'valide';

        // =========================
        // CHECK CLASSE
        // =========================
        $chk = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE id = ? AND code_ecole = ?");
        $chk->execute([$classe, $code_ecole]);

        if ((int)$chk->fetchColumn() === 0) {
            throw new Exception("Classe invalide.");
        }

        // =========================
        // DUPLICAT CHECK PROPRE
        // =========================
        $old = null;
        if ($isEdit) {

            // récupérer ancien élève avec id_user
            $stOld = $pdo->prepare("SELECT username, email, id_user FROM students WHERE id=? LIMIT 1");
            $stOld->execute([$edit_id]);
            $old = $stOld->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                throw new Exception("Élève introuvable.");
            }

            $checkUser = $pdo->prepare("
                SELECT COUNT(*)
                FROM users
                WHERE (username = ? OR email = ?)
                AND NOT (username = ? OR email = ?)
            ");

            $checkUser->execute([
                $username,
                $email,
                $old['username'],
                $old['email']
            ]);

        } else {

            $checkUser = $pdo->prepare("
                SELECT COUNT(*)
                FROM users
                WHERE username = ? OR email = ?
            ");

            $checkUser->execute([$username, $email]);
        }

        if ($checkUser->fetchColumn() > 0) {
            throw new Exception("Username ou email déjà utilisé.");
        }

        // =========================
        // INSERT MODE
        // =========================
        if (!$isEdit) {

            // 1. Créer l'utilisateur dans 'users'
            $stmtUser = $pdo->prepare("
                INSERT INTO users (
                    username, password, email, role,
                    first_name, last_name, phone, code_ecole
                ) VALUES (?,?,?,?,?,?,?,?)
            ");

            $stmtUser->execute([
                $username,
                $password,
                $email,
                'eleve',
                $first_name,
                $last_name,
                $phone,
                $code_ecole
            ]);

            // 2. Récupérer l'ID de l'utilisateur généré
            $id_user = $pdo->lastInsertId();

            // 3. Insérer dans 'students' avec l me id_user
            $stmtStudent = $pdo->prepare("
                INSERT INTO students (
                    first_name, last_name, username, gender, date_of_birth,
                    email, phone, class_id, password, father, mother,
                    phone_responsable, email_responsable,
                    code_ecole, ecole_provenance, statut, id_user
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");

            $stmtStudent->execute([
                $first_name,
                $last_name,
                $username,
                $gender,
                $dob,
                $email,
                $phone,
                $classe,
                $password,
                $father,
                $mother,
                $phone_responsable,
                $email_responsable,
                $code_ecole,
                $ecole_provenance,
                $statut,
                $id_user
            ]);
        }

        // =========================
        // UPDATE MODE
        // =========================
        else {

            $sqlStudent = "
                UPDATE students SET
                    first_name=?,
                    last_name=?, 
                    gender=?,
                    date_of_birth=?,
                    email=?,
                    phone=?,
                    class_id=?,
                    father=?,
                    mother=?,
                    phone_responsable=?,
                    email_responsable=?,
                    ecole_provenance=?
            ";

            $paramsStudent = [
                $first_name,
                $last_name, 
                $gender,
                $dob,
                $email,
                $phone,
                $classe,
                $father,
                $mother,
                $phone_responsable,
                $email_responsable,
                $ecole_provenance
            ];

            if ($password) {
                $sqlStudent .= ", password=?";
                $paramsStudent[] = $password;
            }

            $sqlStudent .= " WHERE id=? AND code_ecole=?";
            $paramsStudent[] = $edit_id;
            $paramsStudent[] = $code_ecole;

            $stmt = $pdo->prepare($sqlStudent);
            $stmt->execute($paramsStudent);

            // Mettre à jour 'users' directement via id_user (plus sûr que l'email)
            if (!empty($old['id_user'])) {
                $sqlUser = "
                    UPDATE users SET 
                        email=?,
                        first_name=?,
                        last_name=?,
                        phone=?
                ";

                $paramsUser = [ 
                    $email,
                    $first_name,
                    $last_name,
                    $phone
                ];

                if ($password) {
                    $sqlUser .= ", password=?";
                    $paramsUser[] = $password;
                }

                $sqlUser .= " WHERE id=?";
                $paramsUser[] = $old['id_user'];

                $stmt = $pdo->prepare($sqlUser);
                $stmt->execute($paramsUser);
            }
        }

        // =========================
        // COMMIT
        // =========================
        $pdo->commit();

        // =========================
        // EMAIL (UNIQUEMENT INSERT)
        // =========================
        if (!$isEdit) {

            $loginUrl = kelasi_login_url();

            $emails = array_unique(array_filter([
                $email,
                $email_responsable
            ]));

            foreach ($emails as $mail) {

                send_student_credentials($mail, [
                    'first'      => $first_name,
                    'last'       => $last_name,
                    'username'   => $username,
                    'password'   => $password_plain,
                    'code_ecole' => $code_ecole,
                    'ecole_name' => $_SESSION['nom_ecole'] ?? 'Kelasi',
                    'login_url'  => $loginUrl
                ]);
            }
        }

        header('Location: ../all-students.php?msg=success');
        exit;

    } catch (Exception $e) {

        $pdo->rollBack();
        echo "Erreur : " . $e->getMessage();
    }
}
?>
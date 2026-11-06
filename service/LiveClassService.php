<?php
// service/LiveClassService.php
declare(strict_types=1);

class LiveClassService
{
    private PDO $pdo;
    private string $secret;

    private static bool $tableChecked = false;

    public function __construct(PDO $pdo, ?string $secret = null)
    {
        $this->pdo = $pdo;
        $this->secret = $secret ?? (defined('JITSI_APP_SECRET') ? JITSI_APP_SECRET : (getenv('JITSI_APP_SECRET') ?: 'mykelasi_jitsi_default_secret'));
        if (!self::$tableChecked) {
            $this->ensureTableExists();
            self::$tableChecked = true;
        }
    }

    /**
     * Crée la table cours_live si elle n'existe pas encore
     */
    public function ensureTableExists(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS cours_live (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cours_id INT NOT NULL,
                class_id INT NOT NULL,
                teacher_user_id INT NOT NULL,
                titre VARCHAR(255) NOT NULL,
                description TEXT NULL,
                date_debut DATETIME NOT NULL,
                duree_minutes INT NOT NULL DEFAULT 60,
                status ENUM('scheduled', 'live', 'ended') NOT NULL DEFAULT 'scheduled',
                room_name VARCHAR(255) NOT NULL,
                code_ecole VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cours (cours_id),
                INDEX idx_class (class_id),
                INDEX idx_teacher (teacher_user_id),
                INDEX idx_school (code_ecole),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        try {
            $this->pdo->exec($sql);
        } catch (Throwable $e) {
            // Dans le cas de bases de données spécifiques (ex: SQLite en test)
            if (strpos(strtolower($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)), 'sqlite') !== false) {
                $sqliteSql = "
                    CREATE TABLE IF NOT EXISTS cours_live (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        cours_id INTEGER NOT NULL,
                        class_id INTEGER NOT NULL,
                        teacher_user_id INTEGER NOT NULL,
                        titre TEXT NOT NULL,
                        description TEXT,
                        date_debut TEXT NOT NULL,
                        duree_minutes INTEGER NOT NULL DEFAULT 60,
                        status TEXT NOT NULL DEFAULT 'scheduled',
                        room_name TEXT NOT NULL,
                        code_ecole TEXT NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                ";
                $this->pdo->exec($sqliteSql);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Génère un nom de salle sécurisé unique préfixé par MyKelasi_
     */
    public function generateRoomName(int $coursId, int $classId, string $codeEcole, ?int $sessionId = null): string
    {
        $seed = "course_{$coursId}_class_{$classId}_school_{$codeEcole}" . ($sessionId ? "_session_{$sessionId}" : '_' . bin2hex(random_bytes(6)));
        $hash = hash_hmac('sha256', $seed, $this->secret);
        // Format lisible et sécurisé pour Jitsi Meet
        return 'MyKelasi_' . substr($hash, 0, 24);
    }

    /**
     * Crée une nouvelle session de cours en direct
     */
    public function createLiveSession(
        int $coursId,
        int $classId,
        int $teacherUserId,
        string $titre,
        ?string $description,
        string $dateDebut,
        int $dureeMinutes,
        string $codeEcole
    ): int {
        $roomName = $this->generateRoomName($coursId, $classId, $codeEcole);

        $sql = "INSERT INTO cours_live (
                    cours_id, class_id, teacher_user_id, titre, description,
                    date_debut, duree_minutes, status, room_name, code_ecole
                ) VALUES (
                    :cours_id, :class_id, :teacher_user_id, :titre, :description,
                    :date_debut, :duree_minutes, 'scheduled', :room_name, :code_ecole
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cours_id'         => $coursId,
            ':class_id'         => $classId,
            ':teacher_user_id'  => $teacherUserId,
            ':titre'            => trim($titre),
            ':description'      => $description ? trim($description) : null,
            ':date_debut'       => $dateDebut,
            ':duree_minutes'    => $dureeMinutes > 0 ? $dureeMinutes : 60,
            ':room_name'        => $roomName,
            ':code_ecole'       => $codeEcole,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Démarre une session en direct (statut 'live')
     */
    public function startLiveSession(int $sessionId, int $teacherUserId, string $codeEcole): bool
    {
        $sql = "UPDATE cours_live
                SET status = 'live'
                WHERE id = :id
                  AND teacher_user_id = :teacher_user_id
                  AND code_ecole = :code_ecole";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'               => $sessionId,
            ':teacher_user_id'  => $teacherUserId,
            ':code_ecole'       => $codeEcole
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Termine une session en direct (statut 'ended')
     */
    public function endLiveSession(int $sessionId, int $teacherUserId, string $codeEcole): bool
    {
        $sql = "UPDATE cours_live
                SET status = 'ended'
                WHERE id = :id
                  AND teacher_user_id = :teacher_user_id
                  AND code_ecole = :code_ecole";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'               => $sessionId,
            ':teacher_user_id'  => $teacherUserId,
            ':code_ecole'       => $codeEcole
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Récupère une session par son ID
     */
    public function getLiveSession(int $sessionId, string $codeEcole): ?array
    {
        $sql = "SELECT cl.*, c.nom AS cours_nom
                FROM cours_live cl
                LEFT JOIN cours c ON cl.cours_id = c.id
                WHERE cl.id = :id AND cl.code_ecole = :code_ecole
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $sessionId, ':code_ecole' => $codeEcole]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Récupère les sessions actives ou programmées pour un cours donné
     */
    public function getLivesByCours(int $coursId, string $codeEcole): array
    {
        $sql = "SELECT * FROM cours_live
                WHERE cours_id = :cours_id AND code_ecole = :code_ecole
                ORDER BY CASE status WHEN 'live' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END, date_debut DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cours_id' => $coursId, ':code_ecole' => $codeEcole]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les sessions pour un professeur
     */
    public function getLivesForTeacher(int $teacherUserId, string $codeEcole, ?int $classId = null): array
    {
        $sql = "SELECT cl.*, c.nom AS cours_nom
                FROM cours_live cl
                LEFT JOIN cours c ON cl.cours_id = c.id
                WHERE cl.teacher_user_id = :teacher_user_id AND cl.code_ecole = :code_ecole";
        $params = [
            ':teacher_user_id' => $teacherUserId,
            ':code_ecole'      => $codeEcole
        ];

        if ($classId) {
            $sql .= " AND cl.class_id = :class_id";
            $params[':class_id'] = $classId;
        }

        $sql .= " ORDER BY CASE cl.status WHEN 'live' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END, cl.date_debut ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les sessions pour une classe d'étudiant
     */
    public function getLivesForStudent(int $classId, string $codeEcole): array
    {
        $sql = "SELECT cl.*, c.nom AS cours_nom
                FROM cours_live cl
                LEFT JOIN cours c ON cl.cours_id = c.id
                WHERE cl.class_id = :class_id AND cl.code_ecole = :code_ecole
                ORDER BY CASE cl.status WHEN 'live' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END, cl.date_debut ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':class_id' => $classId, ':code_ecole' => $codeEcole]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un étudiant a le droit d'accéder à la session (doit appartenir à la classe)
     */
    public function canStudentJoin(array $session, int $studentClassId): bool
    {
        if ((int)$session['class_id'] !== $studentClassId) {
            return false;
        }
        return ($session['status'] === 'live');
    }
}

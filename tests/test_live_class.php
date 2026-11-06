<?php
// tests/test_live_class.php
declare(strict_types=1);

require_once __DIR__ . '/../service/LiveClassService.php';

echo "=== Démarrage des tests LiveClassService ===\n";

// Utiliser une base SQLite en mémoire pour tester sans dépendance de serveur MySQL externe
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Créer les tables mock cours et ecoles
$pdo->exec("
    CREATE TABLE cours (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        class INTEGER NOT NULL,
        teacher_user_id INTEGER NOT NULL,
        code_ecole TEXT NOT NULL
    );
");

$pdo->exec("
    INSERT INTO cours (nom, class, teacher_user_id, code_ecole) VALUES
    ('Mathématiques', 10, 42, 'SCH01'),
    ('Physique', 10, 42, 'SCH01');
");

$service = new LiveClassService($pdo, 'test_secret_key_123');

// 1. Test génération nom de salle Jitsi
$room1 = $service->generateRoomName(1, 10, 'SCH01', 101);
$room2 = $service->generateRoomName(1, 10, 'SCH01', 101);
assert($room1 === $room2, "Le nom de salle doit être déterministe pour une même seed");
assert(strpos($room1, 'MyKelasi_') === 0, "Le nom de salle doit être préfixé par MyKelasi_");
echo "[PASS] Test génération room_name Jitsi réussi ($room1)\n";

// 2. Test création d'une session live
$sessionId = $service->createLiveSession(
    1,
    10,
    42,
    'Session 1 : Algèbre linéaire',
    'Introduction aux vecteurs',
    date('Y-m-d H:i:s'),
    45,
    'SCH01'
);
assert($sessionId > 0, "L'ID de session doit être supérieur à 0");

$session = $service->getLiveSession($sessionId, 'SCH01');
assert($session !== null, "La session doit être récupérable");
assert($session['status'] === 'scheduled', "Le statut initial doit être 'scheduled'");
assert($session['titre'] === 'Session 1 : Algèbre linéaire');
assert($session['cours_nom'] === 'Mathématiques');
echo "[PASS] Test création session live réussi\n";

// 3. Test accès étudiant avant démarrage
assert($service->canStudentJoin($session, 10) === false, "L'étudiant ne doit pas pouvoir rejoindre tant que le statut n'est pas 'live'");
assert($service->canStudentJoin($session, 99) === false, "L'étudiant d'une autre classe ne doit jamais pouvoir rejoindre");
echo "[PASS] Test accès étudiant avant démarrage réussi\n";

// 4. Test démarrage du live par le prof
$started = $service->startLiveSession($sessionId, 42, 'SCH01');
assert($started === true, "La session doit être marquée comme démarrée");

$sessionLive = $service->getLiveSession($sessionId, 'SCH01');
assert($sessionLive['status'] === 'live', "Le statut doit être 'live'");

// Test accès étudiant après démarrage
assert($service->canStudentJoin($sessionLive, 10) === true, "L'étudiant de la classe 10 doit pouvoir rejoindre");
assert($service->canStudentJoin($sessionLive, 99) === false, "L'étudiant de la classe 99 ne doit pas pouvoir rejoindre");
echo "[PASS] Test démarrage et accès étudiant réussi\n";

// 5. Test clôture de session
$ended = $service->endLiveSession($sessionId, 42, 'SCH01');
assert($ended === true, "La session doit être terminée");

$sessionEnded = $service->getLiveSession($sessionId, 'SCH01');
assert($sessionEnded['status'] === 'ended', "Le statut doit être 'ended'");
assert($service->canStudentJoin($sessionEnded, 10) === false, "L'étudiant ne doit plus pouvoir rejoindre après clôture");
echo "[PASS] Test clôture de session réussi\n";

// 6. Test récupération pour la classe
$studentLives = $service->getLivesForStudent(10, 'SCH01');
assert(count($studentLives) === 1, "La classe 10 doit avoir 1 session enregistrée");
echo "[PASS] Test récupération des sessions pour la classe réussi\n";

echo "=== Tous les tests LiveClassService ont réussi avec succès ! ===\n";

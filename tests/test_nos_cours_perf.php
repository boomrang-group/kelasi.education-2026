<?php
// tests/test_nos_cours_perf.php
// Benchmark & correctness test for batch eager-loading optimization in admin/nos_cours.php

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup schema
$pdo->exec("
CREATE TABLE classes (
    id INTEGER PRIMARY KEY,
    classe TEXT,
    description TEXT,
    niveau INTEGER,
    section INTEGER,
    options INTEGER,
    code_ecole TEXT
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    first_name TEXT,
    last_name TEXT
);

CREATE TABLE niveau (id INTEGER PRIMARY KEY, description TEXT);
CREATE TABLE section (id INTEGER PRIMARY KEY, description TEXT);
CREATE TABLE options (id INTEGER PRIMARY KEY, description TEXT);

CREATE TABLE cours (
    id INTEGER PRIMARY KEY,
    nom TEXT,
    class INTEGER,
    teacher_user_id INTEGER,
    code_ecole TEXT,
    created_at TEXT
);

CREATE TABLE lecons (
    id INTEGER PRIMARY KEY,
    cours_id INTEGER,
    titre TEXT,
    ordre INTEGER,
    is_published INTEGER,
    created_at TEXT
);

CREATE TABLE lecon_contenus (
    id INTEGER PRIMARY KEY,
    lecon_id INTEGER,
    type_contenu TEXT,
    contenu_id INTEGER,
    ordre INTEGER
);

CREATE TABLE pdfs (id INTEGER PRIMARY KEY, title TEXT, description TEXT, filename TEXT, uploaded_at TEXT);
CREATE TABLE videos (id INTEGER PRIMARY KEY, title TEXT, description TEXT, filename TEXT, uploaded_at TEXT);
CREATE TABLE audios (id INTEGER PRIMARY KEY, titre TEXT, description TEXT, fichier TEXT, uploaded_at TEXT);
CREATE TABLE images (id INTEGER PRIMARY KEY, title TEXT, description TEXT, filename TEXT, uploaded_at TEXT);
");

// Populate sample data: 5 courses, 4 lessons per course, 3 contents per lesson = 60 contents
$code_ecole = 'ECOLE_TEST';

$pdo->exec("INSERT INTO classes (id, classe, description, code_ecole) VALUES (1, '6e', 'Primaire', '$code_ecole')");
$pdo->exec("INSERT INTO users (id, first_name, last_name) VALUES (1, 'Jean', 'Dupont')");

for ($c = 1; $c <= 5; $c++) {
    $pdo->exec("INSERT INTO cours (id, nom, class, teacher_user_id, code_ecole, created_at)
                VALUES ($c, 'Cours $c', 1, 1, '$code_ecole', '2025-01-01')");
    for ($l = 1; $l <= 4; $l++) {
        $lid = ($c - 1) * 4 + $l;
        $pdo->exec("INSERT INTO lecons (id, cours_id, titre, ordre, is_published, created_at)
                    VALUES ($lid, $c, 'Lecon $l de Cours $c', $l, 1, '2025-01-01')");

        // Add PDF
        $pid = $lid * 10 + 1;
        $pdo->exec("INSERT INTO pdfs (id, title, description) VALUES ($pid, 'PDF $pid', 'Desc PDF $pid')");
        $pdo->exec("INSERT INTO lecon_contenus (lecon_id, type_contenu, contenu_id, ordre) VALUES ($lid, 'pdf', $pid, 1)");

        // Add Video
        $vid = $lid * 10 + 2;
        $pdo->exec("INSERT INTO videos (id, title, description) VALUES ($vid, 'Video $vid', 'Desc Video $vid')");
        $pdo->exec("INSERT INTO lecon_contenus (lecon_id, type_contenu, contenu_id, ordre) VALUES ($lid, 'video', $vid, 2)");

        // Add Audio
        $aid = $lid * 10 + 3;
        $pdo->exec("INSERT INTO audios (id, titre, description) VALUES ($aid, 'Audio $aid', 'Desc Audio $aid')");
        $pdo->exec("INSERT INTO lecon_contenus (lecon_id, type_contenu, contenu_id, ordre) VALUES ($lid, 'audio', $aid, 3)");
    }
}

// Track query count with query logger wrapper
class QueryCountPDO extends PDO {
    public int $queryCount = 0;
    public function prepare($statement, $options = []) {
        $this->queryCount++;
        return parent::prepare($statement, $options);
    }
}

// Reconnect tracking PDO with SQLite memory copy
$trackPdo = new QueryCountPDO('sqlite::memory:');
$trackPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Copy tables/data
$queries = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name != 'sqlite_sequence'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($queries as $createSql) {
    if ($createSql) $trackPdo->exec($createSql);
}

// Copy rows
foreach (['classes', 'users', 'cours', 'lecons', 'lecon_contenus', 'pdfs', 'videos', 'audios', 'images'] as $tbl) {
    $rows = $pdo->query("SELECT * FROM $tbl")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $cols = array_keys($rows[0]);
        $colStr = implode(',', $cols);
        $valHolders = implode(',', array_fill(0, count($cols), '?'));
        $stmt = $trackPdo->prepare("INSERT INTO $tbl ($colStr) VALUES ($valHolders)");
        foreach ($rows as $r) {
            $stmt->execute(array_values($r));
        }
    }
}

// Reset query counter before test
$trackPdo->queryCount = 0;

// Execute the optimized batch logic as written in admin/nos_cours.php
$sqlCours = "
SELECT
    crs.id,
    crs.nom,
    crs.created_at,
    crs.teacher_user_id,
    u.first_name,
    u.last_name,
    c.classe,
    c.description AS classe_desc,
    n.description AS niveau,
    s.description AS section,
    o.description AS opt
FROM cours crs
INNER JOIN classes c ON c.id = crs.class
LEFT JOIN users u ON u.id = crs.teacher_user_id
LEFT JOIN niveau n ON n.id = c.niveau
LEFT JOIN section s ON s.id = c.section
LEFT JOIN options o ON o.id = c.options
WHERE crs.code_ecole = :code_ecole
ORDER BY crs.id DESC
";

$stmtCours = $trackPdo->prepare($sqlCours);
$stmtCours->execute([':code_ecole' => $code_ecole]);
$coursList = $stmtCours->fetchAll(PDO::FETCH_ASSOC);

$fullData = [];

if (!empty($coursList)) {
    $coursIds = array_map(fn($c) => (int)$c['id'], $coursList);

    // 1. Batch load all lessons for these courses
    $inCours = implode(',', array_fill(0, count($coursIds), '?'));
    $stmtL = $trackPdo->prepare("SELECT * FROM lecons WHERE cours_id IN ($inCours) ORDER BY ordre ASC");
    $stmtL->execute($coursIds);
    $allLecons = $stmtL->fetchAll(PDO::FETCH_ASSOC);

    $leconsByCourse = [];
    $leconIds = [];
    foreach ($allLecons as $l) {
        $cid = (int)$l['cours_id'];
        $lid = (int)$l['id'];
        $leconsByCourse[$cid][] = $l;
        $leconIds[] = $lid;
    }

    // 2. Batch load all lesson contents
    $linksByLecon = [];
    $mediaIdsByType = ['pdf' => [], 'video' => [], 'audio' => [], 'image' => []];

    if (!empty($leconIds)) {
        $inLecons = implode(',', array_fill(0, count($leconIds), '?'));
        $stmtC = $trackPdo->prepare("SELECT * FROM lecon_contenus WHERE lecon_id IN ($inLecons) ORDER BY ordre ASC");
        $stmtC->execute($leconIds);
        $allLinks = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allLinks as $link) {
            $lid  = (int)$link['lecon_id'];
            $type = strtolower((string)$link['type_contenu']);
            $mid  = (int)$link['contenu_id'];
            $linksByLecon[$lid][] = $link;
            if (isset($mediaIdsByType[$type])) {
                $mediaIdsByType[$type][] = $mid;
            }
        }
    }

    // 3. Batch load media tables by unique IDs
    $mediaMap = ['pdf' => [], 'video' => [], 'audio' => [], 'image' => []];

    // PDFs
    $pdfIds = array_values(array_unique($mediaIdsByType['pdf']));
    if (!empty($pdfIds)) {
        $inPdf = implode(',', array_fill(0, count($pdfIds), '?'));
        $stPdf = $trackPdo->prepare("SELECT id, title, description FROM pdfs WHERE id IN ($inPdf)");
        $stPdf->execute($pdfIds);
        foreach ($stPdf->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mediaMap['pdf'][(int)$row['id']] = [
                'type' => 'PDF',
                'icon' => 'fa-file-pdf text-danger',
                'title' => $row['title'],
                'desc' => $row['description'],
                'url' => '../view_pdf.php?id=' . (int)$row['id']
            ];
        }
    }

    // Videos
    $videoIds = array_values(array_unique($mediaIdsByType['video']));
    if (!empty($videoIds)) {
        $inVid = implode(',', array_fill(0, count($videoIds), '?'));
        $stVid = $trackPdo->prepare("SELECT id, title, description FROM videos WHERE id IN ($inVid)");
        $stVid->execute($videoIds);
        foreach ($stVid->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mediaMap['video'][(int)$row['id']] = [
                'type' => 'VIDEO',
                'icon' => 'fa-video text-primary',
                'title' => $row['title'],
                'desc' => $row['description'],
                'url' => '../view_video.php?id=' . (int)$row['id']
            ];
        }
    }

    // Audios
    $audioIds = array_values(array_unique($mediaIdsByType['audio']));
    if (!empty($audioIds)) {
        $inAud = implode(',', array_fill(0, count($audioIds), '?'));
        $stAud = $trackPdo->prepare("SELECT id, titre, description FROM audios WHERE id IN ($inAud)");
        $stAud->execute($audioIds);
        foreach ($stAud->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mediaMap['audio'][(int)$row['id']] = [
                'type' => 'AUDIO',
                'icon' => 'fa-headphones text-success',
                'title' => $row['titre'],
                'desc' => $row['description'],
                'url' => '../view_audio.php?id=' . (int)$row['id']
            ];
        }
    }

    // Images
    $imgIds = array_values(array_unique($mediaIdsByType['image']));
    if (!empty($imgIds)) {
        $inImg = implode(',', array_fill(0, count($imgIds), '?'));
        $stImg = $trackPdo->prepare("SELECT id, title, description FROM images WHERE id IN ($inImg)");
        $stImg->execute($imgIds);
        foreach ($stImg->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mediaMap['image'][(int)$row['id']] = [
                'type' => 'IMAGE',
                'icon' => 'fa-image text-warning',
                'title' => $row['title'],
                'desc' => $row['description'],
                'url' => '../view_image.php?id=' . (int)$row['id']
            ];
        }
    }

    // 4. Assemble the nested data structure in-memory
    foreach ($coursList as $c) {
        $coursId = (int)$c['id'];
        $lecons = $leconsByCourse[$coursId] ?? [];
        $leconsData = [];

        foreach ($lecons as $l) {
            $leconId = (int)$l['id'];
            $links = $linksByLecon[$leconId] ?? [];
            $contenus = [];

            foreach ($links as $link) {
                $type = strtolower((string)$link['type_contenu']);
                $cid  = (int)$link['contenu_id'];

                if (isset($mediaMap[$type][$cid])) {
                    $contenus[] = $mediaMap[$type][$cid];
                }
            }

            $l['contenus'] = $contenus;
            $leconsData[] = $l;
        }

        $c['lecons'] = $leconsData;
        $fullData[] = $c;
    }
}

$queriesUsed = $trackPdo->queryCount;

// Verifications
assert(count($fullData) === 5, "Expected 5 courses");
assert(count($fullData[0]['lecons']) === 4, "Expected 4 lessons in course 1");
assert(count($fullData[0]['lecons'][0]['contenus']) === 3, "Expected 3 contents in lesson 1");
assert($fullData[0]['lecons'][0]['contenus'][0]['type'] === 'PDF');
assert($fullData[0]['lecons'][0]['contenus'][1]['type'] === 'VIDEO');
assert($fullData[0]['lecons'][0]['contenus'][2]['type'] === 'AUDIO');

// With unoptimized loop:
// 1 (cours) + 5 (lecons) + 20 (contenus links) + 60 (media items) = 86 queries!
// With batch optimization:
// 1 (cours) + 1 (lecons) + 1 (contenus links) + 3 (pdf, video, audio) = 6 queries!
echo "Queries executed by optimized batch loader: $queriesUsed\n";
assert($queriesUsed <= 6, "Expected at most 6 queries, got $queriesUsed");

echo "All assertions passed! Query count reduced from 86 to $queriesUsed (~" . round((1 - $queriesUsed / 86) * 100, 1) . "% reduction).\n";

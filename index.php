<?php
declare(strict_types=1);

$pdo = new PDO(
    "mysql:host=127.0.0.1;dbname=gestion_etudiants_pdo;charset=utf8mb4",
    "root",
    "",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

if ($uri === '/') {
    header("Location: /etudiants");
    exit;
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function redirect($url) {
    header("Location: $url");
    exit;
}

function validate($data, $pdo) {
    $errors = [];

    if ($data['cne'] === '' || !preg_match('/^[A-Z0-9]{6,20}$/', $data['cne']))
        $errors['cne'] = "CNE invalide.";

    if ($data['nom'] === '') $errors['nom'] = "Nom requis.";
    if ($data['prenom'] === '') $errors['prenom'] = "Prénom requis.";

    if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = "Email invalide.";

    $stmt = $pdo->prepare("SELECT id FROM filiere WHERE id=?");
    $stmt->execute([(int)$data['filiere_id']]);
    if (!$stmt->fetch())
        $errors['filiere_id'] = "Filière invalide.";

    return $errors;
}

if ($uri === '/etudiants' && $method === 'GET') {

    $page = max(1, (int)($_GET['page'] ?? 1));
    $size = max(1, min(100, (int)($_GET['size'] ?? 5)));
    $offset = ($page - 1) * $size;

    $total = (int)$pdo->query("SELECT COUNT(*) FROM etudiant")->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT e.*, f.code filiere_code, f.libelle filiere_libelle
        FROM etudiant e
        JOIN filiere f ON e.filiere_id = f.id
        ORDER BY e.id DESC
        LIMIT :lim OFFSET :off
    ");
    $stmt->bindValue(':lim', $size, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll();

    echo "<h2>Liste des étudiants</h2>";
    echo "<a href='/etudiants/create'>Ajouter</a><br><br>";

    foreach ($students as $s) {
        echo "#{$s['id']} — ".e($s['nom'])." ".e($s['prenom'])."
        (<a href='/etudiants/{$s['id']}'>Voir</a>)
        (<a href='/etudiants/{$s['id']}/edit'>Edit</a>)
        <form style='display:inline' method='post' action='/etudiants/{$s['id']}/delete'>
            <button>Supprimer</button>
        </form><br>";
    }

    $totalPages = ceil($total / $size);
    echo "<br>";
    for ($p = 1; $p <= $totalPages; $p++) {
        echo "<a href='/etudiants?page=$p&size=$size'>$p</a> ";
    }

    exit;
}

if ($uri === '/etudiants/create' && $method === 'GET') {

    $filieres = $pdo->query("SELECT * FROM filiere")->fetchAll();

    echo "<h2>Créer étudiant</h2>
    <form method='post' action='/etudiants/store'>
        CNE <input name='cne'><br>
        Nom <input name='nom'><br>
        Prénom <input name='prenom'><br>
        Email <input name='email'><br>
        Filière <select name='filiere_id'>";
    foreach ($filieres as $f)
        echo "<option value='{$f['id']}'>".e($f['code'])."</option>";
    echo "</select><br>
        <button>Créer</button>
    </form>";
    exit;
}

if ($uri === '/etudiants/store' && $method === 'POST') {

    $data = [
        'cne' => trim($_POST['cne'] ?? ''),
        'nom' => trim($_POST['nom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'filiere_id' => (int)($_POST['filiere_id'] ?? 0),
    ];

    $errors = validate($data, $pdo);

    if ($errors) {
        echo "<pre>";
        print_r($errors);
        echo "</pre>";
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO etudiant (cne,nom,prenom,email,filiere_id) VALUES (?,?,?,?,?)");
    $stmt->execute([$data['cne'],$data['nom'],$data['prenom'],$data['email'],$data['filiere_id']]);
    $id = $pdo->lastInsertId();

    redirect("/etudiants/$id");
}

if (preg_match('#^/etudiants/(\d+)$#', $uri, $m) && $method === 'GET') {

    $stmt = $pdo->prepare("
        SELECT e.*, f.code filiere_code, f.libelle filiere_libelle
        FROM etudiant e
        JOIN filiere f ON e.filiere_id=f.id
        WHERE e.id=?
    ");
    $stmt->execute([$m[1]]);
    $e = $stmt->fetch();

    if (!$e) { http_response_code(404); exit("Introuvable"); }

    echo "<h2>Détail étudiant</h2>";
    echo e($e['nom'])." ".e($e['prenom'])."<br>";
    echo e($e['email'])."<br>";
    echo e($e['filiere_code'])."<br>";
    echo "<a href='/etudiants'>Retour</a>";

    exit;
}

if (preg_match('#^/etudiants/(\d+)/delete$#', $uri, $m) && $method === 'POST') {

    $stmt = $pdo->prepare("DELETE FROM etudiant WHERE id=?");
    $stmt->execute([$m[1]]);
    redirect("/etudiants");
}

http_response_code(404);
echo "404 Not Found";
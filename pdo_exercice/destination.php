<?php
/**
 * ETAPE 7 (suite) : destination.php — réception des paramètres
 */

session_start();

$methode = $_GET['methode'] ?? 'inconnue';
$valeur  = null;

switch ($methode) {
    case 'GET':
        // $_GET contient les paramètres de l'URL (?param=...)
        $valeur = $_GET['param'] ?? null;
        break;

    case 'POST':
        // $_POST contient les données envoyées par un formulaire POST
        $valeur = $_POST['param'] ?? null;
        break;

    case 'SESSION':
        // $_SESSION persiste côté serveur entre les requêtes
        $valeur = $_SESSION['param'] ?? null;
        break;

    case 'COOKIE':
        // $_COOKIE stocke les valeurs côté navigateur
        $valeur = $_COOKIE['param'] ?? null;
        break;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Destination - Réception de paramètre</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 40px auto; }
        .result { padding: 20px; border-radius: 5px; font-size: 1.2em; }
        .ok  { background: #d4edda; color: #155724; }
        .err { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>Page destination</h1>
    <h2>Méthode utilisée : <code><?= htmlspecialchars($methode) ?></code></h2>

    <?php if ($valeur !== null): ?>
        <div class="result ok">
            ✅ Valeur reçue : <strong><?= htmlspecialchars($valeur) ?></strong>
        </div>
    <?php else: ?>
        <div class="result err">
            ❌ Aucune valeur reçue pour la méthode <?= htmlspecialchars($methode) ?>
        </div>
    <?php endif; ?>

    <hr>
    <h3>Contenu des superglobales :</h3>
    <pre>
$_GET     = <?= htmlspecialchars(print_r($_GET, true)) ?>

$_POST    = <?= htmlspecialchars(print_r($_POST, true)) ?>

$_SESSION = <?= htmlspecialchars(print_r($_SESSION, true)) ?>

$_COOKIE  = <?= htmlspecialchars(print_r($_COOKIE, true)) ?>
    </pre>

    <a href="source.php">← Retour à source.php</a>
</body>
</html>

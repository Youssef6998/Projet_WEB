<?php
/**
 * ETAPE 7 : Les 4 méthodes de passage de paramètres entre pages
 *
 * GET     : paramètre dans l'URL → visible, limité en taille, bookmark possible
 * POST    : corps de la requête  → non visible dans l'URL, pour formulaires
 * SESSION : stocké côté SERVEUR  → persiste entre pages, sécurisé
 * COOKIE  : stocké côté CLIENT   → persiste entre sessions, modifiable par l'user
 */

session_start(); // Nécessaire pour utiliser $_SESSION

$param = 'SECRET';

// Stocker dans la SESSION pour que destination.php puisse le lire
$_SESSION['param'] = $param;

// Créer un COOKIE (durée 1 heure)
setcookie('param', $param, time() + 3600, '/');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Source - Passage de paramètres</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 40px auto; }
        .method { border: 1px solid #ccc; padding: 15px; margin: 15px 0; border-radius: 5px; }
        h3 { color: #333; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Page source — transmission de <code><?= htmlspecialchars($param) ?></code></h1>

    <div class="method">
        <h3>1. GET — paramètre dans l'URL</h3>
        <p>Le paramètre est visible dans l'URL. Adapté pour les filtres, la pagination, les recherches.</p>
        <a href="destination.php?param=<?= urlencode($param) ?>&methode=GET">
            Envoyer par GET → destination.php?param=<?= urlencode($param) ?>
        </a>
    </div>

    <div class="method">
        <h3>2. POST — formulaire</h3>
        <p>Le paramètre est dans le corps HTTP, non visible dans l'URL. Adapté pour les données sensibles ou les formulaires.</p>
        <form action="destination.php" method="POST">
            <input type="hidden" name="param" value="<?= htmlspecialchars($param) ?>">
            <input type="hidden" name="methode" value="POST">
            <button type="submit">Envoyer par POST</button>
        </form>
    </div>

    <div class="method">
        <h3>3. SESSION — côté serveur</h3>
        <p>Valeur stockée sur le serveur, identifiée par un cookie de session. Persiste entre les pages.</p>
        <a href="destination.php?methode=SESSION">
            Lire depuis la SESSION → destination.php
        </a>
    </div>

    <div class="method">
        <h3>4. COOKIE — côté client</h3>
        <p>Valeur stockée dans le navigateur. Persiste entre les sessions mais peut être modifié par l'utilisateur.</p>
        <a href="destination.php?methode=COOKIE">
            Lire depuis le COOKIE → destination.php
        </a>
        <p><small>⚠️ Le cookie est créé au chargement de cette page. Rechargez si c'est la première fois.</small></p>
    </div>
</body>
</html>

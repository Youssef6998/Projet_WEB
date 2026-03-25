<?php
/**
 * ETAPE 5 : fetchAll() — récupérer toutes les lignes
 *
 * fetch()    → UNE ligne à la fois (avance le curseur)
 * fetchAll() → TOUTES les lignes d'un coup dans un tableau PHP
 *
 * Pour une boucle foreach, les deux fonctionnent :
 *   - fetchAll() charge tout en mémoire (attention si beaucoup de données)
 *   - On peut aussi itérer directement sur $stmt avec foreach
 */

require_once 'config.php';

$sql = "SELECT pseudo FROM utilisateurs";
$stmt = $pdo->query($sql);

if ($stmt === false) {
    echo "❌ Requête échouée.";
    exit;
}

// fetchAll() retourne un tableau de toutes les lignes
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Liste de tous les utilisateurs :</h2><ul>";

foreach ($utilisateurs as $user) {
    echo "<li>" . htmlspecialchars($user['pseudo']) . "</li>";
    // htmlspecialchars() protège contre les injections XSS
    // (toujours échapper les données avant de les afficher en HTML)
}

echo "</ul>";

// --- Alternative : itérer directement sur le PDOStatement ---
echo "<h2>Alternative avec foreach direct sur \$stmt :</h2><ul>";

$stmt2 = $pdo->query($sql);
foreach ($stmt2 as $row) {
    echo "<li>" . htmlspecialchars($row['pseudo']) . "</li>";
}
echo "</ul>";

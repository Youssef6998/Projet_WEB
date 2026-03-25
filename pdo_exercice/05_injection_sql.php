<?php
/**
 * ETAPE 6 : Démonstration de l'injection SQL (DANGEREUX - Éducatif uniquement)
 *
 * Une injection SQL consiste à insérer du code SQL dans une variable
 * pour manipuler la requête et contourner la logique applicative.
 *
 * JAMAIS faire ça en production ! Ce fichier est uniquement pédagogique.
 */

require_once 'config.php';

// Simulation de données venant d'un formulaire
// Dans la réalité : $pseudo = $_POST['pseudo']; $mdp = $_POST['mdp'];

// --- CAS NORMAL ---
$pseudo = 'Gandalf';
$mdp    = 'Maia';

// Requête VULNÉRABLE (concaténation directe de variables)
$sql = "SELECT * FROM utilisateurs WHERE pseudo = '$pseudo' AND motDePasse = '$mdp'";
echo "<h2>Cas normal</h2>";
echo "<code>$sql</code><br>";
$stmt = $pdo->query($sql);
$user = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
echo $user ? "✅ Connexion OK pour " . htmlspecialchars($user['pseudo']) : "❌ Utilisateur non trouvé";

// --- CAS D'INJECTION SQL ---
// L'attaquant entre comme pseudo : ' OR '1'='1
// Cela "casse" la logique de la requête !
$pseudo_inject = "' OR '1'='1";
$mdp_inject    = "n'importe quoi";

$sql_inject = "SELECT * FROM utilisateurs WHERE pseudo = '$pseudo_inject' AND motDePasse = '$mdp_inject'";

echo "<h2>Injection SQL</h2>";
echo "<p>pseudo saisi : <code>" . htmlspecialchars($pseudo_inject) . "</code></p>";
echo "<p>Requête générée :<br><code>" . htmlspecialchars($sql_inject) . "</code></p>";

/*
 * La requête devient :
 * SELECT * FROM utilisateurs WHERE pseudo = '' OR '1'='1' AND motDePasse = 'n'importe quoi'
 *
 * OR '1'='1' est TOUJOURS VRAI → retourne tous les utilisateurs !
 * L'attaquant se connecte sans connaître de mot de passe.
 *
 * Autres injections possibles :
 *   - '; DROP TABLE utilisateurs; --   → supprime la table !
 *   - ' UNION SELECT * FROM users --   → vole d'autres données
 */

try {
    $stmt_inject = $pdo->query($sql_inject);
    $users = $stmt_inject ? $stmt_inject->fetchAll(PDO::FETCH_ASSOC) : [];
    echo "<p>⚠️ Résultat avec injection : " . count($users) . " utilisateur(s) retourné(s) !</p>";
    foreach ($users as $u) {
        echo "<p>→ " . htmlspecialchars($u['pseudo']) . "</p>";
    }
} catch (PDOException $e) {
    echo "<p>Erreur SQL : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><p><strong>Solution : utiliser des requêtes préparées (voir fichier suivant)</strong></p>";

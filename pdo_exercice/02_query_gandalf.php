<?php
/**
 * ETAPE 3 : PDO::query() — vérifier si un utilisateur existe
 *
 * PDO::query($sql) exécute une requête SQL directement.
 * Elle retourne un objet PDOStatement si OK, ou FALSE si erreur.
 *
 * ATTENTION : query() ne protège PAS contre les injections SQL.
 * On l'utilise ici uniquement avec une valeur codée en dur (pas de variable).
 */

require_once 'config.php'; // Fichier de connexion PDO

// --- La requête SQL ---
// COUNT(*) retourne le nombre de lignes trouvées
$sql = "SELECT COUNT(*) FROM utilisateurs WHERE pseudo = 'Gandalf'";

// Exécution avec query()
$stmt = $pdo->query($sql);

// Vérification que la requête a fonctionné
if ($stmt === false) {
    echo "❌ La requête a échoué.";
    exit;
}

// fetchColumn() récupère la valeur de la première colonne de la première ligne
// C'est parfait pour un COUNT(*)
$count = $stmt->fetchColumn();

if ($count > 0) {
    echo "✅ L'utilisateur Gandalf EST présent dans la base ($count occurrence(s)).";
} else {
    echo "❌ L'utilisateur Gandalf N'EST PAS dans la base.";
}

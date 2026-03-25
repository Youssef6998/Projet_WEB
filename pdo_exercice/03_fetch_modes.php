<?php
/**
 * ETAPE 4 : Les 3 modes de fetch PDO
 *
 * fetch() récupère UNE ligne du résultat.
 * Le mode de fetch détermine le FORMAT de cette ligne :
 *
 *   FETCH_NUM   → tableau indexé par numéro  : $row[0], $row[1]...
 *   FETCH_ASSOC → tableau associatif         : $row['pseudo'], $row['id']...
 *   FETCH_OBJ   → objet anonyme stdClass     : $row->pseudo, $row->id...
 *
 * IMPORTANT : chaque appel à fetch() avance d'une ligne dans les résultats.
 * Si vous appelez fetch() deux fois, vous récupérez deux lignes DIFFÉRENTES.
 * C'est pourquoi on commente les sections non voulues.
 */

require_once 'config.php';

$sql = "SELECT * FROM utilisateurs WHERE pseudo = 'Gandalf'";
$stmt = $pdo->query($sql);

if ($stmt === false) {
    echo "❌ Requête échouée.";
    exit;
}

// ================================================================
// MODE 1 : Tableau indexé (FETCH_NUM)
// ================================================================
echo "<h2>Mode 1 : Tableau indexé (FETCH_NUM)</h2>";

$row = $stmt->fetch(PDO::FETCH_NUM);
// $row = [0 => id, 1 => pseudo, 2 => motDePasse, 3 => statutAdmin]

echo "statutAdmin (index 3) = " . $row[3];
echo "<br>Toute la ligne : <pre>" . print_r($row, true) . "</pre>";

// ================================================================
// Pour tester les autres modes, ré-exécutez la requête
// (le curseur est "consommé" après le premier fetch)
// ================================================================
$stmt = $pdo->query($sql); // Nouvelle exécution

// MODE 2 : Tableau associatif (FETCH_ASSOC)
echo "<h2>Mode 2 : Tableau associatif (FETCH_ASSOC)</h2>";

$row = $stmt->fetch(PDO::FETCH_ASSOC);
// $row = ['id' => 1, 'pseudo' => 'Gandalf', 'motDePasse' => 'Maia', 'statutAdmin' => 1]

echo "statutAdmin (clé 'statutAdmin') = " . $row['statutAdmin'];
echo "<br>Toute la ligne : <pre>" . print_r($row, true) . "</pre>";

// ================================================================
$stmt = $pdo->query($sql); // Nouvelle exécution

// MODE 3 : Objet anonyme (FETCH_OBJ)
echo "<h2>Mode 3 : Objet anonyme (FETCH_OBJ)</h2>";

$row = $stmt->fetch(PDO::FETCH_OBJ);
// $row est un stdClass : $row->id, $row->pseudo, $row->motDePasse, $row->statutAdmin

echo "statutAdmin (propriété) = " . $row->statutAdmin;
echo "<br>Toute la ligne : <pre>" . print_r($row, true) . "</pre>";

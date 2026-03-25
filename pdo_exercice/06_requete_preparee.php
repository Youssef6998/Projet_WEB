<?php
/**
 * ETAPE 8 : Requêtes préparées — PDOStatement::prepare()
 *
 * UNE REQUÊTE PRÉPARÉE = 2 étapes :
 *   1. prepare($sql)  → PHP envoie le SQUELETTE SQL au serveur MySQL
 *   2. execute($data) → PHP envoie uniquement les DONNÉES (séparément)
 *
 * MySQL traite la structure et les données INDÉPENDAMMENT.
 * Une donnée comme "' OR '1'='1" sera traitée comme une VALEUR TEXTE,
 * jamais comme du SQL. L'injection est impossible.
 *
 * BONUS : si on exécute la même requête plusieurs fois avec des valeurs
 * différentes, MySQL n'a besoin de "compiler" la requête qu'une seule fois
 * → gain de performance.
 *
 * PLACEHOLDER :
 *   ?           → paramètre positionnel (ordre important)
 *   :nom_param  → paramètre nommé (ordre indifférent, plus lisible)
 */

require_once 'config.php';

// ================================================================
// MÉTHODE 1 : execute() avec tableau (le plus simple)
// ================================================================
echo "<h2>Méthode 1 : execute() avec tableau associatif</h2>";

$pseudo = 'Gandalf';
$mdp    = 'Maia';

// Le squelette SQL avec des placeholders nommés (:pseudo, :mdp)
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE pseudo = :pseudo AND motDePasse = :mdp");

// execute() remplace les placeholders par les vraies valeurs
// Toujours sécurisé, même si $pseudo contient du SQL malveillant
$stmt->execute([':pseudo' => $pseudo, ':mdp' => $mdp]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✅ Connexion réussie pour : " . htmlspecialchars($user['pseudo']);
    echo " (admin : " . ($user['statutAdmin'] ? 'oui' : 'non') . ")";
} else {
    echo "❌ Identifiants incorrects.";
}

// Test avec une tentative d'injection (parfaitement neutralisée)
echo "<h3>Test d'injection neutralisée :</h3>";
$pseudo_hack = "' OR '1'='1";
$stmt->execute([':pseudo' => $pseudo_hack, ':mdp' => 'nimportequoi']);
$user_hack = $stmt->fetch(PDO::FETCH_ASSOC);
echo $user_hack
    ? "⚠️ Injection réussie ! (ne devrait pas arriver)"
    : "✅ Injection bloquée ! Aucun résultat pour : " . htmlspecialchars($pseudo_hack);

// ================================================================
// MÉTHODE 2 : bindParam() — lie une RÉFÉRENCE à la variable
// ================================================================
echo "<h2>Méthode 2 : bindParam()</h2>";

/*
 * bindParam() lie le placeholder à la RÉFÉRENCE de la variable.
 * La valeur est lue au moment de execute(), PAS au moment de bindParam().
 * → Si la variable change entre bindParam() et execute(), c'est la NOUVELLE valeur qui est utilisée.
 */

$stmt2 = $pdo->prepare("SELECT * FROM utilisateurs WHERE pseudo = :pseudo");

$nom = 'Aragorn';
$stmt2->bindParam(':pseudo', $nom); // Lie la référence de $nom

// On peut changer $nom AVANT execute(), ça changera le résultat
$nom = 'Legolas'; // ← bindParam utilisera 'Legolas', pas 'Aragorn' !

$stmt2->execute();
$user2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "bindParam avec \$nom changé à 'Legolas' après le bind : ";
echo $user2 ? "✅ Trouvé : " . htmlspecialchars($user2['pseudo']) : "❌ Non trouvé";

// ================================================================
// MÉTHODE 3 : bindValue() — lie la VALEUR au moment du bind
// ================================================================
echo "<h2>Méthode 3 : bindValue()</h2>";

/*
 * bindValue() lie la VALEUR de la variable au moment de l'appel.
 * Si la variable change après, ça n'a AUCUN effet.
 * → Comportement plus prévisible, préférable dans la plupart des cas.
 */

$stmt3 = $pdo->prepare("SELECT * FROM utilisateurs WHERE pseudo = :pseudo");

$nom2 = 'Frodo';
$stmt3->bindValue(':pseudo', $nom2); // Copie la VALEUR 'Frodo' maintenant

$nom2 = 'Gimli'; // ← Trop tard ! bindValue a déjà copié 'Frodo'

$stmt3->execute();
$user3 = $stmt3->fetch(PDO::FETCH_ASSOC);
echo "bindValue avec \$nom2 changé à 'Gimli' après le bind : ";
echo $user3 ? "✅ Trouvé : " . htmlspecialchars($user3['pseudo']) . " (pas Gimli !)" : "❌ Non trouvé";

// ================================================================
// RÉSUMÉ de la différence
// ================================================================
echo <<<HTML
<hr>
<h2>Résumé : bindParam vs bindValue</h2>
<table border="1" cellpadding="8" style="border-collapse: collapse;">
  <tr>
    <th></th>
    <th>bindParam()</th>
    <th>bindValue()</th>
  </tr>
  <tr>
    <td>Ce qui est lié</td>
    <td>Référence à la variable</td>
    <td>Valeur copiée immédiatement</td>
  </tr>
  <tr>
    <td>Changement après bind ?</td>
    <td>Oui, pris en compte</td>
    <td>Non, ignoré</td>
  </tr>
  <tr>
    <td>Utile pour</td>
    <td>Boucles réutilisant la même variable</td>
    <td>Valeurs fixes, expressions littérales</td>
  </tr>
  <tr>
    <td>Accepte les littéraux ?</td>
    <td>Non : bindParam(':p', 'Gandalf') → erreur</td>
    <td>Oui : bindValue(':p', 'Gandalf') → OK</td>
  </tr>
</table>
HTML;

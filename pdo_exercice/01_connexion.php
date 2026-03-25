<?php
/**
 * ETAPE 2 : Connexion PDO avec gestion d'erreur
 *
 * PDO (PHP Data Objects) est une couche d'abstraction pour les BDD.
 * La connexion se fait via le constructeur new PDO(dsn, user, pass).
 *
 * Le DSN (Data Source Name) est une chaîne qui identifie la BDD :
 *   "mysql:host=localhost;dbname=workshop_pdo;charset=utf8mb4"
 *    ↑ driver  ↑ serveur        ↑ nom BDD        ↑ encodage
 *
 * Si la connexion échoue, PDO lance une exception PDOException.
 * Sans try/catch, PHP affiche une erreur fatale + EXPOSE vos identifiants !
 * Il faut donc toujours capturer cette exception.
 */

$host   = 'localhost';
$dbname = 'workshop_pdo';   // ← Modifie selon ton nom de BDD
$user   = 'root';
$pass   = 'StageFinder2026!'; // ← Modifie selon ton mot de passe

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass);

    // ATTR_ERRMODE : définit comment PDO signale les erreurs
    //   ERRMODE_SILENT    → pas d'erreur (défaut, déconseillé)
    //   ERRMODE_WARNING   → PHP warning
    //   ERRMODE_EXCEPTION → lance une PDOException (recommandé)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Connexion réussie à la base '$dbname'";

} catch (PDOException $e) {
    // On n'affiche PAS $e->getMessage() en production (expose les credentials !)
    // Ici on le fait pour l'exercice/développement uniquement
    echo "❌ Connexion échouée : " . $e->getMessage();
    exit; // Arrêt du programme
}

<?php
/**
 * config/database.php — Configuration et connexion à la base de données MySQL
 *
 * Ce fichier crée un objet PDO ($pdo) utilisé dans toute l'application pour
 * exécuter des requêtes SQL de façon sécurisée (requêtes préparées).
 *
 * Il est inclus dans les fichiers qui ont besoin d'accéder à la BDD,
 * typiquement via Database.php ou directement dans les modèles.
 *
 * Remarque : en production, ces identifiants devraient être stockés dans
 * des variables d'environnement (.env) et non dans ce fichier.
 */

// Hôte de la base de données (serveur MySQL local)
$host = 'localhost';

// Nom de la base de données à utiliser
$dbname = 'stagefinder';

// Identifiants de connexion MySQL
$username = 'root';
$password = 'Youssef.2006';

try {
    /**
     * Création de la connexion PDO (PHP Data Objects).
     * PDO est une abstraction qui permet de se connecter à différents SGBD.
     *
     * Paramètres du DSN (Data Source Name) :
     *   - mysql:host    : adresse du serveur MySQL
     *   - dbname        : base de données cible
     *   - charset=utf8mb4 : encodage UTF-8 complet (supporte les emojis et caractères spéciaux)
     *
     * ATTR_ERRMODE => ERRMODE_EXCEPTION :
     *   Force PDO à lancer une exception (PDOException) en cas d'erreur SQL,
     *   plutôt que de retourner silencieusement false. Cela facilite le débogage.
     */
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {
    /**
     * En cas d'échec de connexion (mauvais identifiants, serveur indisponible...),
     * l'application s'arrête immédiatement avec un message d'erreur.
     * En production, ce message ne devrait PAS être affiché à l'utilisateur final.
     */
    die("Connexion BDD échouée : " . $e->getMessage());
}

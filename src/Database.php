<?php

require 'vendor/autoload.php';

/**
 * Classe Database — Connexion PDO à MySQL (pattern Singleton).
 *
 * Une seule instance PDO est créée pour toute la durée de la requête.
 * Tous les modèles récupèrent cette instance via Database::getConnection().
 *
 * Configuration à adapter selon l'environnement :
 *   - $host     : hôte MySQL (généralement "localhost")
 *   - $dbname   : nom de la base de données
 *   - $user     : utilisateur MySQL
 *   - $password : mot de passe MySQL
 */
class Database {

    /** @var PDO|null Instance unique de la connexion PDO */
    private static ?PDO $instance = null;

    private static string $host     = 'localhost';
    private static string $dbname   = 'stageconnect';
    private static string $user     = 'Ryad';
    private static string $password = 'Rkraiss1976.';

    /**
     * Retourne l'instance PDO partagée.
     * Crée la connexion lors du premier appel, puis la réutilise.
     *
     * @return PDO Instance de connexion à la base de données.
     * @throws PDOException Si la connexion échoue.
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . self::$host . ';dbname=' . self::$dbname . ';charset=utf8mb4';
            self::$instance = new PDO($dsn, self::$user, self::$password, [
                // Lance une exception sur erreur SQL (plutôt que retourner false silencieusement)
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Retourne les résultats sous forme de tableaux associatifs par défaut
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$instance;
    }
}

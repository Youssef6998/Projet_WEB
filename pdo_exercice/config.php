<?php
/**
 * Connexion PDO partagée pour tous les fichiers de l'exercice
 * Modifie les valeurs ci-dessous selon ta configuration.
 */

$host   = 'localhost';
$dbname = 'workshop_pdo';   // ← nom de ta BDD créée dans phpMyAdmin
$user   = 'root';
$pass   = 'StageFinder2026!'; // ← ton mot de passe MySQL

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // En développement on affiche l'erreur,
    // en production on logguerait et afficherait un message générique
    die("❌ Connexion BDD échouée : " . $e->getMessage());
}

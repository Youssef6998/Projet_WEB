<?php
$host = 'localhost';
$dbname = 'stagefinder';
$username = 'root';
$password = 'Youssef.2006';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connexion BDD échouée : " . $e->getMessage());
}

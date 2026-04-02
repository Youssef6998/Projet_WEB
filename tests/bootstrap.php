<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Démarrer la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Stub minimal de Database pour éviter toute connexion réelle lors des tests
if (!class_exists('Database')) {
    class Database {
        public static function getConnection(): PDO {
            throw new \LogicException('Database::getConnection() ne doit pas être appelé dans les tests unitaires.');
        }
    }
}

// Chargement des classes sources
require_once __DIR__ . '/../src/Controllers/BaseController.php';
require_once __DIR__ . '/../src/Controllers/StatsController.php';
require_once __DIR__ . '/../src/Models/StatsModel.php';

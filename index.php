<?php

/**
 * Point d'entrée unique de l'application Stage Finder.
 *
 * Toutes les requêtes HTTP passent par ce fichier.
 * Le routage est délégué à la classe Router via le paramètre GET "uri".
 *
 * Exemple d'URL : http://projet-web.local/?uri=stages&page=2
 */

// Configuration du cookie de session : secure (HTTPS), httponly (inaccessible au JS), samesite strict
session_set_cookie_params([
    'lifetime' => 0,           // Cookie de session (expire à la fermeture du navigateur)
    'path'     => '/',
    'secure'   => true,        // Transmis uniquement sur HTTPS
    'httponly' => true,        // Inaccessible via JavaScript (protection XSS)
    'samesite' => 'Strict',    // Bloque les requêtes cross-site (protection CSRF)
]);

// Démarrage de la session PHP (gestion des utilisateurs connectés)
session_start();

// Chargement de l'autoloader Composer (Twig et autres dépendances)
require 'vendor/autoload.php';

// Chargement de la connexion PDO (singleton)
require 'src/Database.php';

// Chargement du routeur principal
require 'src/Router.php';

// Affichage des erreurs activé (environnement de développement — désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialisation du moteur de templates Twig
// - FilesystemLoader : cherche les templates dans le dossier "templates/"
// - debug => true    : active les fonctions de debug Twig (désactiver en production)
$twig = new \Twig\Environment(
    new \Twig\Loader\FilesystemLoader('templates'),
    ['debug' => true]
);

// Instanciation du routeur et dispatch de la requête courante
(new Router($twig))->dispatch();

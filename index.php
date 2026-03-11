<?php
require '../vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader('../templates');
$twig = new \Twig\Environment($loader, ['debug' => true]);

// Routeur simple
$uri = $_GET['uri'] ?? 'home';
$page = match($uri) {
    'home' => 'index.twig.html',
    'test' => 'test.twig.html',
    default => '404.twig.html'
};

echo $twig->render($page, [
    'title' => 'Projet_WEB',
    'tasks' => ['Tâche 1', 'Tâche 2'] // Données dynamiques
]);

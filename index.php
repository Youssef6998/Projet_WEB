<?php
require 'vendor/autoload.php';  // ← PAS ../vendor

$loader = new \Twig\Loader\FilesystemLoader('templates');  // ← PAS ../templates
$twig = new \Twig\Environment($loader, ['debug' => true]);

$uri = $_GET['uri'] ?? 'home';
$page = match($uri) {
    'home' => 'index.twig.html',
    'test' => 'test.twig.html',
    default => '404.twig.html'
};

echo $twig->render($page, [
    'title' => 'Projet_WEB Dynamique',
    'tasks' => ['Tâche 1', 'Tâche 2']
]);


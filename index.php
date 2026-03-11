<?php
require 'vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader, ['debug' => true]);

// Données DYNAMIQUES stages
$stages = [
    ['company' => 'Figma France', 'title' => 'Stage UX Designer', 'description' => '...', 'tags' => ['UX', 'Figma'], 'location' => 'Paris', 'duration' => '6 mois', 'date' => '2 jours'],
    ['company' => 'Doctolib', 'title' => 'Développeur Front-End React', 'description' => '...', 'tags' => ['React'], 'location' => 'Paris', 'duration' => '4-6 mois', 'date' => '3 jours'],
    // ... autres stages
];

$uri = $_GET['uri'] ?? 'cherche-stage'; 
$page = match($uri) {
    'cherche-stage' => 'cherche_stage.twig.html', 
    'home' => 'index.twig.html',
    default => '404.twig.html'
};


echo $twig->render($page, [
    'uri' => $uri,
    'stages' => $stages,
    'domaine' => $_GET['domaine'] ?? ''
]);

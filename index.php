<?php
require 'vendor/autoload.php';
require 'src/Models/StageModel.php';

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader, ['debug' => true]);

$model = new StageModel();
$page = max(1, (int)($_GET['page'] ?? 1));

$uri = $_GET['uri'] ?? 'stages';
$pageTemplate = match($uri) {
    'cherche-stage' => 'cherche_stage.twig.html',
    'stages' => 'stages.twig.html',
    'home' => 'cherche_stage.twig.html',
    'login' => 'connexion.twig.html',
    'register' => 'inscription.twig.html',
    default => '404.twig.html'
};

if ($uri === 'stages' || $uri === 'cherche-stage') {
    $data = $model->getPaginatedStages($page);
    $data['uri'] = $uri;
    $data['domaine'] = $_GET['domaine'] ?? '';
} else {
    $data = ['uri' => $uri];
}

echo $twig->render($pageTemplate, $data);

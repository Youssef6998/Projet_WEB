<?php
require 'vendor/autoload.php';  // ✅ Twig + TES modèles (App\Models\*)
require_once __DIR__ . '/config/database.php';  // ✅ $pdo global

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader, ['debug' => true]);

// ✅ Modèles (autoload + 1 require supprimé)
$stageModel = new App\Models\Stage();
$userModel = new App\Models\User();  // À utiliser plus tard

// Pagination
$page = (int)($_GET['page'] ?? 1);
$uri = $_GET['uri'] ?? 'cherche-stage';

// Routeur amélioré
$pageTemplate = match($uri) {
    'cherche-stage', 'home' => 'cherche_stage.twig.html',
    'stages' => 'stages.twig.html',
    'login' => 'connexion.twig.html',
    'register' => 'inscription.twig.html',
    'mentions' => 'mentions.twig.html',
    'entreprises' => 'cherche_entreprises.twig.html',
    'nous' => 'nous.twig.html',
    default => '404.twig.html',
};

$data = ['uri' => $uri];

// Pagination UNIQUEMENT pour stages
if (in_array($uri, ['stages', 'cherche-stage'])) {
    $paginationInfo = $stageModel->getPaginatedStages(1);
    $totalPages = $paginationInfo['totalPages'];
    $page = max(1, min($page, $totalPages));
    
    $data = array_merge($data, $stageModel->getPaginatedStages($page));
    $data['domaine'] = $_GET['domaine'] ?? '';
    $data['page'] = $page;
    $data['totalPages'] = $totalPages;
}

echo $twig->render($pageTemplate, $data);
?>

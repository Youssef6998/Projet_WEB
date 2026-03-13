<?php
require 'vendor/autoload.php';
require 'src/Database.php';
require 'src/Models/StageModel.php';

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader, ['debug' => true]);

$model = new StageModel();


$page = max(1, (int)($_GET['page'] ?? 1));

$uri = $_GET['uri'] ?? 'cherche-stage';
$pageTemplate = match($uri) {
    'cherche-stage' => 'cherche_stage.twig.html',
    'stages' => 'stages.twig.html',
    'home' => 'cherche_stage.twig.html',
    'login' => 'connexion.twig.html',
    'register' => 'inscription.twig.html',
    'mentions' => 'mentions.twig.html',
    'entreprises' => 'cherche_entreprises.twig.html',
    'nous' => 'nous.twig.html',
    default => '404.twig.html',
};

if ($uri === 'stages' || $uri === 'cherche-stage') {
    $domaine = $_GET['domaine'] ?? '';
    $data = $model->getPaginatedStages($page, 6, $domaine);
    $data['uri'] = $uri;
    $data['domaine'] = $domaine;
} elseif ($uri === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom          = trim($_POST['nom'] ?? '');
    $prenom       = trim($_POST['prenom'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $telephone    = trim($_POST['telephone'] ?? '');
    $motdepasse   = $_POST['motdepasse'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    $erreur = null;

    if ($motdepasse !== $confirmation) {
        $erreur = "Les mots de passe ne correspondent pas.";
    } elseif ($model->emailExiste($email)) {
        $erreur = "Un compte existe déjà avec cet email.";
    } else {
        $model->inscrireUtilisateur($nom, $prenom, $email, $motdepasse, $telephone);
        header('Location: /?uri=login');
        exit;
    }

    $data = ['uri' => $uri, 'erreur' => $erreur];
} else {
    $data = ['uri' => $uri];
}

echo $twig->render($pageTemplate, $data);

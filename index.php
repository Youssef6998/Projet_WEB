<?php
session_start();

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
$uri  = $_GET['uri'] ?? 'cherche-stage';

// --- Toggle Wishlist ---
if ($uri === 'wishlist-toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['user']) || empty($_SESSION['user']['id_etudiant'])) {
        header('Location: /?uri=login');
        exit;
    }
    $idOffre    = (int)($_POST['id_offre'] ?? 0);
    $idEtudiant = (int)$_SESSION['user']['id_etudiant'];
    if ($idOffre > 0) {
        $model->toggleWishlist($idEtudiant, $idOffre);
    }
    $redirect = $_POST['redirect'] ?? "/?uri=offre&id=$idOffre";
    header("Location: $redirect");
    exit;
}

// --- Candidater ---
if ($uri === 'candidater' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['user']) || empty($_SESSION['user']['id_etudiant'])) {
        header('Location: /?uri=login');
        exit;
    }
    $idOffre    = (int)($_POST['id_offre'] ?? 0);
    $idEtudiant = (int)$_SESSION['user']['id_etudiant'];

    $cvPath = '';
    if (!empty($_FILES['cv']['tmp_name']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/cv/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext      = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        $allowed  = ['pdf', 'doc', 'docx'];
        if (in_array($ext, $allowed, true)) {
            $filename = 'cv_' . $idEtudiant . '_' . $idOffre . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['cv']['tmp_name'], $uploadDir . $filename);
            $cvPath = '/uploads/cv/' . $filename;
        }
    }

    $lettreMot = trim($_POST['lettre_motivation'] ?? '');
    $model->candidater($idEtudiant, $idOffre, $lettreMot, $cvPath);
    header("Location: /?uri=offre&id=$idOffre&candidature=ok");
    exit;
}

// --- Créer entreprise ---
if ($uri === 'entreprise_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom'] ?? '');
    $pays      = trim($_POST['pays'] ?? '');
    $ville     = trim($_POST['ville'] ?? '');
    $adresse   = trim($_POST['adresse'] ?? '');
    $cp        = trim($_POST['cp'] ?? '');
    $site      = trim($_POST['site'] ?? '');
    $annee     = (int)($_POST['annee'] ?? 0);
    $telephone = trim($_POST['telephone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    // $model->creerEntreprise($nom, $pays, $ville, $adresse, $cp, $site, $annee, $telephone, $email);
    header('Location: /?uri=entreprises');
    exit;
}

// --- Déconnexion ---
if ($uri === 'logout') {
    session_destroy();
    header('Location: /?uri=cherche-stage');
    exit;
}

// --- Connexion POST ---
if ($uri === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = trim($_POST['email'] ?? '');
    $motdepasse = $_POST['motdepasse'] ?? '';
    $user       = $model->connecterUtilisateur($email, $motdepasse);

    if ($user) {
        $_SESSION['user'] = $user;
        header('Location: /?uri=profil');
        exit;
    }
    $data = ['uri' => $uri, 'erreur' => 'Email ou mot de passe incorrect.'];
    echo $twig->render('connexion.twig.html', $data);
    exit;
}

$pageTemplate = match($uri) {
    'cherche-stage'     => 'cherche_stage.twig.html',
    'stages'            => 'stages.twig.html',
    'home'              => 'cherche_stage.twig.html',
    'login'             => 'connexion.twig.html',
    'register'          => 'inscription.twig.html',
    'mentions'          => 'mentions.twig.html',
    'entreprises'       => 'cherche_entreprises.twig.html',
    'nous'              => 'nous.twig.html',
    'profil'            => 'profil.twig.html',
    'offre'             => 'offre.twig.html',
    'entreprise'        => 'entrprise.twig.html',
    'entreprise_create' => 'creer_entreprise.twig.html',
    default             => '404.twig.html',
};

if ($uri === 'stages' || $uri === 'cherche-stage') {
    $domaine = $_GET['domaine'] ?? '';
    $data = $model->getPaginatedStages($page, 6, $domaine);
    $data['uri']     = $uri;
    $data['domaine'] = $domaine;
} elseif ($uri === 'entreprises') {
    $nom = $_GET['nom'] ?? '';
    $data = $model->getPaginatedEntreprises($page, 6, $nom);
    $data['uri'] = $uri;
    $data['nom'] = $nom;
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
} elseif ($uri === 'profil') {
    if (empty($_SESSION['user'])) {
        header('Location: /?uri=login');
        exit;
    }
    $user = $_SESSION['user'];
    $candidatures = [];
    $wishlist     = [];
    if ($user['role'] === 'etudiant' && !empty($user['id_etudiant'])) {
        $candidatures = $model->getCandidaturesEtudiant($user['id_etudiant']);
        $wishlist     = $model->getWishlistEtudiant($user['id_etudiant']);
    }
    $data = [
        'uri'          => $uri,
        'user'         => $user,
        'candidatures' => $candidatures,
        'wishlist'     => $wishlist,
    ];
} elseif ($uri === 'offre') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        $pageTemplate = '404.twig.html';
        $data = ['uri' => $uri];
    } else {
        $offre = $model->getOffreById($id);
        if (!$offre) {
            $pageTemplate = '404.twig.html';
            $data = ['uri' => $uri];
        } else {
            $enFavori      = false;
            $dejaCandidate = false;
            if (!empty($_SESSION['user']['id_etudiant'])) {
                $idEt          = (int)$_SESSION['user']['id_etudiant'];
                $enFavori      = $model->isInWishlist($idEt, $id);
                $dejaCandidate = $model->dejaCandidate($idEt, $id);
            }
            $data = [
                'uri'            => $uri,
                'offre'          => $offre,
                'en_favori'      => $enFavori,
                'deja_candidate' => $dejaCandidate,
                'candidature_ok' => isset($_GET['candidature']) && $_GET['candidature'] === 'ok',
            ];
        }
    }
} else {
    $data = ['uri' => $uri];
}

// Passer les infos de session à tous les templates
$data['session_user'] = $_SESSION['user'] ?? null;

echo $twig->render($pageTemplate, $data);



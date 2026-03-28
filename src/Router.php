<?php

require_once __DIR__ . '/Models/StageModel.php';
require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/StageController.php';
require_once __DIR__ . '/Controllers/EntrepriseController.php';
require_once __DIR__ . '/Controllers/ProfilController.php';
require_once __DIR__ . '/Controllers/AdminController.php';

class Router {

    private \Twig\Environment $twig;
    private StageModel $model;

    private AuthController       $auth;
    private StageController      $stage;
    private EntrepriseController $entreprise;
    private ProfilController     $profil;
    private AdminController      $admin;

    public function __construct(\Twig\Environment $twig) {
        $this->twig       = $twig;
        $this->model      = new StageModel();
        $this->auth       = new AuthController($twig, $this->model);
        $this->stage      = new StageController($twig, $this->model);
        $this->entreprise = new EntrepriseController($twig, $this->model);
        $this->profil     = new ProfilController($twig, $this->model);
        $this->admin      = new AdminController($twig, $this->model);
    }

    public function dispatch(): void {
        $uri    = $_GET['uri'] ?? 'cherche-stage';
        $method = $_SERVER['REQUEST_METHOD'];
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $id     = (int)($_GET['id'] ?? 0);

        $output = match(true) {

            // Auth
            $uri === 'logout'
                => $this->auth->logout(),
            $uri === 'login' && $method === 'POST'
                => $this->auth->login(),
            $uri === 'login'
                => $this->auth->showLogin(),
            $uri === 'register' && $method === 'POST'
                => $this->auth->register(),
            $uri === 'register'
                => $this->auth->showRegister(),

            // Stages
            in_array($uri, ['cherche-stage', 'stages', 'home'])
                => $this->stage->index($page),
            $uri === 'offre'
                => $this->stage->show($id),
            $uri === 'candidater' && $method === 'POST'
                => $this->stage->candidater(),
            $uri === 'wishlist-toggle' && $method === 'POST'
                => $this->stage->wishlistToggle(),

            // Entreprises
            $uri === 'entreprises'
                => $this->entreprise->index($page),
            $uri === 'entreprise'
                => $this->entreprise->show($id),
            $uri === 'entreprise_list'
                => $this->entreprise->showEntrepriseList($page),
            $uri === 'entreprise_create' && $method === 'POST'
                => $this->entreprise->store(),
            $uri === 'entreprise_create'
                => $this->entreprise->showCreate(),
            $uri === 'entreprise_update' && $method === 'POST'
                => $this->entreprise->update(),
            $uri === 'entreprise_update'
                => $this->entreprise->showUpdate($id),
            $uri === 'entreprise_delete' && $method === 'POST'
                => $this->entreprise->destroy(),

            // Profil
            $uri === 'profil'
                => $this->profil->index(),

            // Admin / Pilote
            $uri === 'pilote_list'
                => $this->admin->showPiloteList(),
            $uri === 'pilote_create' && $method === 'POST'
                => $this->admin->storePilote(),
            $uri === 'pilote_create'
                => $this->admin->showPiloteCreate(),
            $uri === 'pilote_update' && $method === 'POST'
                => $this->admin->updatePilote(),
            $uri === 'pilote_update'
                => $this->admin->showPiloteEdit($id),
            $uri === 'pilote_delete' && $method === 'POST'
                => $this->admin->destroyPilote(),
            $uri === 'etudiant_list'
                => $this->admin->showEtudiantList(),
            $uri === 'etudiant_delete' && $method === 'POST'
                => $this->admin->destroyEtudiant(),
            $uri === 'avis_create' && $method === 'POST'
                => $this->admin->storeAvis(),
            $uri === 'avis_create'
                => $this->admin->showAvisCreate(),
            $uri === 'evaluation_list'
                => $this->admin->showEvaluationList(),
            $uri === 'evaluation_delete' && $method === 'POST'
                => $this->admin->destroyEvaluation(),

            // Pages statiques
            $uri === 'mentions'
                => $this->twig->render('mentions.twig.html', ['uri' => $uri, 'session_user' => $_SESSION['user'] ?? null]),
            $uri === 'nous'
                => $this->twig->render('nous.twig.html',     ['uri' => $uri, 'session_user' => $_SESSION['user'] ?? null]),

            // 404
            default
                => $this->twig->render('404.twig.html', ['uri' => $uri, 'session_user' => $_SESSION['user'] ?? null]),
        };

        if ($output !== null) {
            echo $output;
        }
    }
}

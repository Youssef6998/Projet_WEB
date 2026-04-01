<?php

require_once __DIR__ . '/Models/StageModel.php';
require_once __DIR__ . '/Models/EntrepriseModel.php';
require_once __DIR__ . '/Models/UserModel.php';
require_once __DIR__ . '/Models/EvaluationModel.php';
require_once __DIR__ . '/Models/StatsModel.php';
require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/StageController.php';
require_once __DIR__ . '/Controllers/EntrepriseController.php';
require_once __DIR__ . '/Controllers/ProfilController.php';
require_once __DIR__ . '/Controllers/PiloteController.php';
require_once __DIR__ . '/Controllers/EvaluationController.php';
require_once __DIR__ . '/Controllers/StatsController.php';

class Router {

    private \Twig\Environment $twig;

    private AuthController       $auth;
    private StageController      $stage;
    private EntrepriseController $entreprise;
    private ProfilController     $profil;
    private PiloteController     $pilote;
    private EvaluationController $evaluation;
    private StatsController      $stats;

    public function __construct(\Twig\Environment $twig) {
        $this->twig = $twig;

        $stageModel      = new StageModel();
        $entrepriseModel = new EntrepriseModel();
        $userModel       = new UserModel();
        $evalModel       = new EvaluationModel();

        $this->auth       = new AuthController($twig, $userModel);
        $this->stage      = new StageController($twig, $stageModel);
        $this->entreprise = new EntrepriseController($twig, $entrepriseModel, $evalModel);
        $this->profil     = new ProfilController($twig, $stageModel, $userModel);
        $this->pilote     = new PiloteController($twig, $userModel, $stageModel);
        $this->evaluation = new EvaluationController($twig, $evalModel, $userModel, $entrepriseModel);
        $this->stats      = new StatsController($twig, new StatsModel());
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
            $uri === 'offre_create' && $method === 'POST'
                => $this->stage->store(),
            $uri === 'offre_create'
                => $this->stage->showCreate(),
            $uri === 'offre_update' && $method === 'POST'
                => $this->stage->update(),
            $uri === 'offre_update'
                => $this->stage->showUpdate($id),
            $uri === 'offre_delete' && $method === 'POST'
                => $this->stage->destroy(),
            $uri === 'stats_offres'
                => $this->stage->showStats(),

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
            $uri === 'profil_update' && $method === 'POST'
                => $this->profil->update(),
            $uri === 'profil_delete' && $method === 'POST'
                => $this->profil->delete(),

            // Pilotes & Étudiants
            $uri === 'pilote_list'
                => $this->pilote->showList(),
            $uri === 'pilote_create'
                => $this->pilote->showCreate(),
            $uri === 'pilote_update' && $method === 'POST'
                => $this->pilote->update(),
            $uri === 'pilote_update'
                => $this->pilote->showEdit($id),
            $uri === 'pilote_delete' && $method === 'POST'
                => $this->pilote->destroy(),
            $uri === 'etudiant_list'
                => $this->pilote->showEtudiantList(),
            $uri === 'etudiant_update' && $method === 'POST'
                => $this->pilote->updateEtudiant(),
            $uri === 'etudiant_update'
                => $this->pilote->showEtudiantUpdate($id),
            $uri === 'etudiant_retirer' && $method === 'POST'
                => $this->pilote->retirerEtudiant(),
            $uri === 'etudiant_affecter' && $method === 'POST'
                => $this->pilote->affecterEtudiant(),
            $uri === 'etudiant_offres'
                => $this->pilote->showEtudiantOffres($id),
            $uri === 'etudiant_delete' && $method === 'POST'
                => $this->pilote->destroyEtudiant(),

            // Évaluations
            $uri === 'avis_create' && $method === 'POST'
                => $this->evaluation->storeAvis(),
            $uri === 'avis_create'
                => $this->evaluation->showAvisCreate(),
            $uri === 'evaluation_list'
                => $this->evaluation->showList(),
            $uri === 'evaluation_detail'
                => $this->evaluation->showDetail($id),
            $uri === 'evaluation_delete' && $method === 'POST'
                => $this->evaluation->destroy(),

            // Statistiques
            $uri === 'stats'
                => $this->stats->show(),

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

<?php

require_once __DIR__ . '/BaseController.php';

class AuthController extends BaseController {

    private StageModel $model;

    public function __construct(\Twig\Environment $twig, StageModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    // GET /?uri=login
    public function showLogin(): string {
        return $this->render('connexion.twig.html', ['uri' => 'login']);
    }

    // POST /?uri=login
    public function login(): void {
        $email      = trim($_POST['email'] ?? '');
        $motdepasse = $_POST['motdepasse'] ?? '';
        $user       = $this->model->connecterUtilisateur($email, $motdepasse);
        if ($user) {
            $_SESSION['user'] = $user;
            $this->redirect('/?uri=profil');
        }
        echo $this->render('connexion.twig.html', [
            'uri'    => 'login',
            'erreur' => 'Email ou mot de passe incorrect.',
        ]);
    }

    // GET /?uri=register
    public function showRegister(): string {
        return $this->render('inscription.twig.html', ['uri' => 'register']);
    }

    // POST /?uri=register
    public function register(): void {
        $nom          = trim($_POST['nom'] ?? '');
        $prenom       = trim($_POST['prenom'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $telephone    = trim($_POST['telephone'] ?? '');
        $motdepasse   = $_POST['motdepasse'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';

        if ($motdepasse !== $confirmation) {
            echo $this->render('inscription.twig.html', [
                'uri'    => 'register',
                'erreur' => 'Les mots de passe ne correspondent pas.',
            ]);
            return;
        }
        if ($this->model->emailExiste($email)) {
            echo $this->render('inscription.twig.html', [
                'uri'    => 'register',
                'erreur' => 'Un compte existe déjà avec cet email.',
            ]);
            return;
        }
        $this->model->inscrireUtilisateur($nom, $prenom, $email, $motdepasse, $telephone);
        $this->redirect('/?uri=login');
    }

    // GET /?uri=logout
    public function logout(): void {
        session_destroy();
        $this->redirect('/?uri=cherche-stage');
    }
}

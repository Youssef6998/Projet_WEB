<?php

require_once __DIR__ . '/BaseController.php';

class AuthController extends BaseController {

    private UserModel $userModel;

    public function __construct(\Twig\Environment $twig, UserModel $userModel) {
        parent::__construct($twig);
        $this->userModel = $userModel;
    }

    // GET /?uri=login
    public function showLogin(): string {
        return $this->render('auth/connexion.twig.html', ['uri' => 'login']);
    }

    // POST /?uri=login
    public function login(): void {
        $email      = trim($_POST['email'] ?? '');
        $motdepasse = $_POST['motdepasse'] ?? '';
        $user       = $this->userModel->connecterUtilisateur($email, $motdepasse);
        if ($user) {
            $_SESSION['user'] = $user;
            $this->redirect('/?uri=profil');
        }
        echo $this->render('auth/connexion.twig.html', [
            'uri'    => 'login',
            'erreur' => 'Email ou mot de passe incorrect.',
        ]);
    }

    // GET /?uri=register
    public function showRegister(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $type = $_GET['type'] ?? 'etudiant';
        return $this->render('auth/inscription.twig.html', [
            'uri'  => 'register',
            'type' => $type,
        ]);
    }

    // POST /?uri=register
    public function register(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        $type         = $_POST['type']        ?? 'etudiant';
        $nom          = trim($_POST['nom']       ?? '');
        $prenom       = trim($_POST['prenom']    ?? '');
        $email        = trim($_POST['email']     ?? '');
        $telephone    = trim($_POST['telephone'] ?? '');
        $motdepasse   = $_POST['motdepasse']     ?? '';
        $confirmation = $_POST['confirmation']   ?? '';
        $promotion    = trim($_POST['promotion'] ?? '');

        // Un pilote ne peut créer que des étudiants
        if ($this->isPilote() && $type !== 'etudiant') {
            $type = 'etudiant';
        }

        if ($motdepasse !== $confirmation) {
            echo $this->render('auth/inscription.twig.html', [
                'uri'    => 'register',
                'type'   => $type,
                'erreur' => 'Les mots de passe ne correspondent pas.',
                'old'    => $_POST,
            ]);
            return;
        }
        if ($this->userModel->emailExiste($email)) {
            echo $this->render('auth/inscription.twig.html', [
                'uri'    => 'register',
                'type'   => $type,
                'erreur' => 'Un compte existe déjà avec cet email.',
                'old'    => $_POST,
            ]);
            return;
        }

        match($type) {
            'pilote' => $this->userModel->creerPilote($nom, $prenom, $email, $motdepasse, $telephone, $promotion),
            'admin'  => $this->userModel->creerAdmin($nom, $prenom, $email, $motdepasse, $telephone),
            default  => $this->userModel->inscrireUtilisateur($nom, $prenom, $email, $motdepasse, $telephone),
        };

        $redirect = match($type) {
            'pilote' => '/?uri=pilote_list&success=cree',
            'admin'  => '/?uri=pilote_list&success=cree',
            default  => '/?uri=etudiant_list&success=cree',
        };
        $this->redirect($redirect);
    }

    // GET /?uri=logout
    public function logout(): void {
        session_destroy();
        $this->redirect('/?uri=cherche-stage');
    }
}

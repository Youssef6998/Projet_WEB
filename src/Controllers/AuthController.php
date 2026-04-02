<?php

require_once __DIR__ . '/BaseController.php';

/**
 * Contrôleur d'authentification et de création de comptes.
 *
 * Point d'entrée unique pour tout ce qui concerne l'identité de l'utilisateur :
 *  - Connexion (showLogin / login) : vérifie les identifiants et ouvre la session.
 *  - Inscription (showRegister / register) : crée un compte selon le type demandé.
 *  - Déconnexion (logout) : détruit la session et renvoie vers la page publique.
 *
 * Toutes les actions POST passent par verifyCsrf() pour bloquer les attaques CSRF.
 *
 * Gère le cycle de vie de la session utilisateur :
 * - Affichage et traitement du formulaire de connexion.
 * - Création de nouveaux comptes (étudiant, pilote, admin) réservée aux admins/pilotes.
 * - Déconnexion et destruction de la session.
 *
 * Les règles d'autorisation sont les suivantes :
 * - Un pilote ne peut créer que des comptes étudiant.
 * - Un admin peut créer des comptes étudiant, pilote et admin.
 */
class AuthController extends BaseController {

    /** Modèle responsable des opérations de persistance des utilisateurs. */
    private UserModel $userModel;

    /**
     * Injecte le moteur Twig et le modèle utilisateur.
     *
     * @param \Twig\Environment $twig      Moteur de rendu Twig.
     * @param UserModel         $userModel Modèle d'accès aux données utilisateurs.
     */
    public function __construct(\Twig\Environment $twig, UserModel $userModel) {
        parent::__construct($twig);
        $this->userModel = $userModel;
    }

    /**
     * Affiche le formulaire de connexion.
     *
     * GET /?uri=login
     *
     * @return string HTML de la page de connexion.
     */
    public function showLogin(): string {
        return $this->render('auth/connexion.twig.html', ['uri' => 'login']);
    }

    /**
     * Traite la soumission du formulaire de connexion.
     *
     * POST /?uri=login
     *
     * Vérifie le CSRF, tente d'authentifier l'utilisateur via le modèle.
     * En cas de succès, hydrate la session et redirige vers le profil.
     * En cas d'échec, réaffiche le formulaire avec un message d'erreur.
     *
     * @return void
     */
    public function login(): void {
        $this->verifyCsrf();
        $email      = trim($_POST['email'] ?? '');
        $motdepasse = $_POST['motdepasse'] ?? '';
        $user       = $this->userModel->connecterUtilisateur($email, $motdepasse);
        if ($user) {
            // Hydrate la session avec les données de l'utilisateur authentifié.
            $_SESSION['user'] = $user;
            $this->redirect('/?uri=profil');
        }
        // Authentification échouée : réaffiche le formulaire avec le message d'erreur.
        echo $this->render('auth/connexion.twig.html', [
            'uri'    => 'login',
            'erreur' => 'Email ou mot de passe incorrect.',
        ]);
    }

    /**
     * Affiche le formulaire de création de compte.
     *
     * GET /?uri=register
     *
     * Réservé aux admins et pilotes. Le paramètre GET 'type' détermine
     * le type de compte à créer (étudiant par défaut).
     *
     * @return string HTML du formulaire d'inscription.
     */
    public function showRegister(): string {
        // Seuls les admins et pilotes peuvent accéder à la création de comptes.
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $type = $_GET['type'] ?? 'etudiant';
        return $this->render('auth/inscription.twig.html', [
            'uri'  => 'register',
            'type' => $type,
        ]);
    }

    /**
     * Traite la soumission du formulaire de création de compte.
     *
     * POST /?uri=register
     *
     * Valide le CSRF, applique les restrictions de rôle, contrôle la cohérence
     * du mot de passe et l'unicité de l'email, puis délègue la création au modèle.
     * Redirige vers la liste appropriée après succès.
     *
     * @return void
     */
    public function register(): void {
        // Seuls les admins et pilotes peuvent créer des comptes.
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();

        $type         = $_POST['type']        ?? 'etudiant';
        $nom          = trim($_POST['nom']       ?? '');
        $prenom       = trim($_POST['prenom']    ?? '');
        $email        = trim($_POST['email']     ?? '');
        $telephone    = trim($_POST['telephone'] ?? '');
        $motdepasse   = $_POST['motdepasse']     ?? '';
        $confirmation = $_POST['confirmation']   ?? '';
        $promotion    = trim($_POST['promotion'] ?? '');

        // Un pilote ne peut créer que des étudiants : on force le type en conséquence.
        if ($this->isPilote() && $type !== 'etudiant') {
            $type = 'etudiant';
        }

        // Contrôle de cohérence : les deux saisies du mot de passe doivent correspondre.
        if ($motdepasse !== $confirmation) {
            echo $this->render('auth/inscription.twig.html', [
                'uri'    => 'register',
                'type'   => $type,
                'erreur' => 'Les mots de passe ne correspondent pas.',
                'old'    => $_POST,  // Repopule le formulaire avec les valeurs saisies.
            ]);
            return;
        }
        // Contrôle d'unicité : on refuse les doublons d'adresse email.
        if ($this->userModel->emailExiste($email)) {
            echo $this->render('auth/inscription.twig.html', [
                'uri'    => 'register',
                'type'   => $type,
                'erreur' => 'Un compte existe déjà avec cet email.',
                'old'    => $_POST,
            ]);
            return;
        }

        // Délègue la création au modèle selon le type de compte demandé.
        match($type) {
            'pilote' => $this->userModel->creerPilote($nom, $prenom, $email, $motdepasse, $telephone, $promotion),
            'admin'  => $this->userModel->creerAdmin($nom, $prenom, $email, $motdepasse, $telephone),
            default  => $this->userModel->inscrireUtilisateur($nom, $prenom, $email, $motdepasse, $telephone),
        };

        // Redirige vers la liste des pilotes pour admin/pilote, sinon vers la liste des étudiants.
        $redirect = match($type) {
            'pilote' => '/?uri=pilote_list&success=cree',
            'admin'  => '/?uri=pilote_list&success=cree',
            default  => '/?uri=etudiant_list&success=cree',
        };
        $this->redirect($redirect);
    }

    /**
     * Déconnecte l'utilisateur en détruisant sa session.
     *
     * GET /?uri=logout
     *
     * @return void
     */
    public function logout(): void {
        session_destroy();
        $this->redirect('/?uri=cherche-stage');
    }
}

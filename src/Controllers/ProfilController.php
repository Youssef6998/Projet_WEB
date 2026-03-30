<?php
require_once __DIR__ . '/BaseController.php';

class ProfilController extends BaseController {
    private StageModel $model;

    public function __construct(\Twig\Environment $twig, StageModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    // GET /?uri=profil
    public function index(): string {
    $this->requireRole(fn() => $this->isConnecte());
    $user = $this->model->getUtilisateurComplet((int)$_SESSION['user']['id_utilisateur']);
    $candidatures = [];
    $wishlist     = [];
<<<<<<< HEAD
=======
    $etudiants_supervises = [];
>>>>>>> d52e869404fe3f398a3dec13a9055b8a7fdf6a2c

    if ($user['role'] === 'etudiant' && !empty($user['id_etudiant'])) {
        $candidatures = $this->model->getCandidaturesEtudiant($user['id_etudiant']);
        $wishlist     = $this->model->getWishlistEtudiant($user['id_etudiant']);
    }

<<<<<<< HEAD
    $etudiants_supervises = [];
    if ($user['role'] === 'pilote') {
        $etudiants_supervises = $this->model->getEtudiantsSupervises((int)$_SESSION['user']['id_utilisateur']);
    }
    
    $success = $_GET['success'] ?? null;
    $erreur  = $_GET['erreur']  ?? null;

    // 🔥 ÇA MANquait ! Retourne la vue Twig
    return $this->render('profil.twig.html', [
        'uri'              => 'profil',
        'user'             => $user,
        'candidatures'     => $candidatures,
        'wishlist'         => $wishlist,
        'etudiants_supervises' => $etudiants_supervises,
        'success'          => $success,
        'erreur'           => $erreur,
    ]);
}
=======
    if ($user['role'] === 'pilote' && !empty($user['id_pilote'])) {
        $etudiants_supervises = $this->model->getEtudiantsParPiloteUtilisateur($user['id_utilisateur']);
    }

    return $this->render('profil.twig.html', [
        'uri'                  => 'profil',
        'user'                 => $user,
        'candidatures'         => $candidatures,
        'wishlist'             => $wishlist,
        'etudiants_supervises' => $etudiants_supervises,
        'success'              => $_GET['success'] ?? null,
        'erreur'               => $_GET['erreur']  ?? null,
    ]);
}

>>>>>>> d52e869404fe3f398a3dec13a9055b8a7fdf6a2c
    // POST /?uri=profil_update
    public function update(): void {
        $this->requireRole(fn() => $this->isConnecte());
        $id    = (int)$_SESSION['user']['id_utilisateur'];
        $champ = trim($_POST['champ'] ?? '');
        $val   = trim($_POST['valeur'] ?? '');

        if ($champ === 'mot_de_passe') {
            $ancien  = $_POST['ancien_mdp']  ?? '';
            $nouveau = $_POST['nouveau_mdp'] ?? '';
            $confirm = $_POST['confirm_mdp'] ?? '';
            if ($nouveau !== $confirm) {
                $this->redirect('/?uri=profil&erreur=Les+mots+de+passe+ne+correspondent+pas');
                return;
            }
            $ok = $this->model->updateMotDePasse($id, $ancien, $nouveau);
            if (!$ok) {
                $this->redirect('/?uri=profil&erreur=Ancien+mot+de+passe+incorrect');
                return;
            }
        } elseif ($champ === 'promotion') {
            $this->model->updatePromotion($id, $val);
        } else {
            $ok = $this->model->updateUtilisateur($id, $champ, $val);
            if (!$ok) {
                $this->redirect('/?uri=profil&erreur=Modification+impossible+(email+déjà+utilisé+?)');
                return;
            }
            // Mettre à jour la session
            $_SESSION['user'][$champ] = $val;
        }

        $this->redirect('/?uri=profil&success=Modification+enregistrée');
    }

    // POST /?uri=profil_delete
    public function delete(): void {
        $this->requireRole(fn() => $this->isConnecte());
        $id = (int)$_SESSION['user']['id_utilisateur'];
        $this->model->supprimerUtilisateur($id);
        session_destroy();
        $this->redirect('/?uri=cherche-stage');
    }


 
}

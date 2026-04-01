<?php

require_once __DIR__ . '/BaseController.php';

class ProfilController extends BaseController {

    private StageModel $stageModel;
    private UserModel  $userModel;

    public function __construct(\Twig\Environment $twig, StageModel $stageModel, UserModel $userModel) {
        parent::__construct($twig);
        $this->stageModel = $stageModel;
        $this->userModel  = $userModel;
    }

    // GET /?uri=profil
    public function index(): string {
        $this->requireRole(fn() => $this->isConnecte());

        $user                 = $_SESSION['user'];
        $candidatures         = [];
        $wishlist             = [];
        $etudiants_supervises = [];

        if ($user['role'] === 'etudiant' && !empty($user['id_etudiant'])) {
            $candidatures = $this->stageModel->getCandidaturesEtudiant($user['id_etudiant']);
            $wishlist     = $this->stageModel->getWishlistEtudiant($user['id_etudiant']);
        }

        if ($user['role'] === 'pilote') {
            $etudiants_supervises = $this->userModel->getEtudiantsSupervisesParPilote($user['id_utilisateur']);
        }

        $userComplet = $this->userModel->getUtilisateurComplet($user['id_utilisateur']) ?? $user;

        return $this->render('profil.twig.html', [
            'uri'                  => 'profil',
            'user'                 => $userComplet,
            'candidatures'         => $candidatures,
            'wishlist'             => $wishlist,
            'etudiants_supervises' => $etudiants_supervises,
            'success'              => $_GET['success'] ?? null,
            'erreur'               => $_GET['erreur']  ?? null,
        ]);
    }

    // POST /?uri=profil_update
    public function update(): void {
        $this->requireRole(fn() => $this->isConnecte());
        $id    = (int) $_SESSION['user']['id_utilisateur'];
        $champ = trim($_POST['champ']  ?? '');
        $val   = trim($_POST['valeur'] ?? '');

        if ($champ === 'mot_de_passe') {
            $ancien  = $_POST['ancien_mdp']  ?? '';
            $nouveau = $_POST['nouveau_mdp'] ?? '';
            $confirm = $_POST['confirm_mdp'] ?? '';
            if ($nouveau !== $confirm) {
                $this->redirect('/?uri=profil&erreur=Les+mots+de+passe+ne+correspondent+pas');
                return;
            }
            if (!$this->userModel->updateMotDePasse($id, $ancien, $nouveau)) {
                $this->redirect('/?uri=profil&erreur=Ancien+mot+de+passe+incorrect');
                return;
            }
        } elseif ($champ === 'promotion') {
            $this->userModel->updatePromotion($id, $val);
        } else {
            if (!$this->userModel->updateUtilisateur($id, $champ, $val)) {
                $this->redirect('/?uri=profil&erreur=Modification+impossible');
                return;
            }
            $_SESSION['user'][$champ] = $val;
        }

        $this->redirect('/?uri=profil&success=1');
    }

    // POST /?uri=profil_delete
    public function delete(): void {
        $this->requireRole(fn() => $this->isConnecte());
        $id = (int) $_SESSION['user']['id_utilisateur'];
        $this->userModel->supprimerUtilisateur($id);
        session_destroy();
        $this->redirect('/?uri=cherche-stage');
    }
}

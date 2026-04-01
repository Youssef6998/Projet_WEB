<?php

require_once __DIR__ . '/BaseController.php';

class PiloteController extends BaseController {

    private UserModel  $userModel;
    private StageModel $stageModel;

    public function __construct(\Twig\Environment $twig, UserModel $userModel, StageModel $stageModel) {
        parent::__construct($twig);
        $this->userModel  = $userModel;
        $this->stageModel = $stageModel;
    }

    // GET /?uri=pilote_create — redirige vers la page de création unifiée
    public function showCreate(): string {
        $this->requireRole(fn() => $this->isAdmin());
        $this->redirect('/?uri=register&type=pilote');
    }

    // GET /?uri=pilote_list
    public function showList(): string {
        $this->requireRole(fn() => $this->isAdmin());
        $nom    = trim($_GET['nom']    ?? '');
        $prenom = trim($_GET['prenom'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $data   = $this->userModel->getPaginatedPilotes($page, 6, $nom, $prenom);
        return $this->render('admin/pilote_list.twig.html', [
            'uri'          => 'pilote_list',
            'pilotes'      => $data['pilotes'],
            'currentPage'  => $data['currentPage'],
            'totalPages'   => $data['totalPages'],
            'totalPilotes' => $data['totalPilotes'],
            'nom'          => $nom,
            'prenom'       => $prenom,
            'success'      => $_GET['success'] ?? null,
        ]);
    }

    // GET /?uri=pilote_update&id=X
    public function showEdit(int $id): string {
        $this->requireRole(fn() => $this->isAdmin());
        $pilote = $this->userModel->getPiloteById($id);
        if (!$pilote) {
            $this->redirect('/?uri=pilote_list');
        }
        return $this->render('admin/modifier_pilote.twig.html', [
            'uri'    => 'pilote_update',
            'pilote' => $pilote,
        ]);
    }

    // POST /?uri=pilote_update
    public function update(): void {
        $this->requireRole(fn() => $this->isAdmin());

        $id           = (int)($_POST['id']         ?? 0);
        $nom          = trim($_POST['nom']          ?? '');
        $prenom       = trim($_POST['prenom']       ?? '');
        $email        = trim($_POST['email']        ?? '');
        $telephone    = trim($_POST['telephone']    ?? '');
        $promotion    = trim($_POST['promotion']    ?? '');
        $motdepasse   = $_POST['motdepasse']        ?? '';
        $confirmation = $_POST['confirmation']      ?? '';

        if ($motdepasse !== '' && $motdepasse !== $confirmation) {
            $pilote = $this->userModel->getPiloteById($id);
            echo $this->render('admin/modifier_pilote.twig.html', [
                'uri'    => 'pilote_update',
                'pilote' => $pilote,
                'erreur' => 'Les mots de passe ne correspondent pas.',
            ]);
            return;
        }

        $this->userModel->updatePilote($id, $nom, $prenom, $email, $telephone, $promotion, $motdepasse !== '' ? $motdepasse : null);
        $this->redirect('/?uri=pilote_list&success=modifie');
    }

    // POST /?uri=pilote_delete
    public function destroy(): void {
        $this->requireRole(fn() => $this->isAdmin());
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->userModel->supprimerPilote($id);
        }
        $this->redirect('/?uri=pilote_list&success=supprime');
    }

    // GET /?uri=etudiant_list
    public function showEtudiantList(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $prenom = trim($_GET['prenom'] ?? '');
        $nom    = trim($_GET['nom']    ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $data   = $this->userModel->getPaginatedEtudiants($page, 6, $prenom, $nom);
        return $this->render('admin/etudiant_list.twig.html', [
            'uri'            => 'etudiant_list',
            'etudiants'      => $data['etudiants'],
            'currentPage'    => $data['currentPage'],
            'totalPages'     => $data['totalPages'],
            'totalEtudiants' => $data['totalEtudiants'],
            'prenom'         => $prenom,
            'nom'            => $nom,
            'success'        => $_GET['success'] ?? null,
        ]);
    }

    // GET /?uri=etudiant_update&id=X
    public function showEtudiantUpdate(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $etudiant = $this->userModel->getEtudiantById($id);
        if (!$etudiant) {
            $this->redirect('/?uri=etudiant_list');
        }
        return $this->render('admin/modifier_etudiant.twig.html', [
            'uri'      => 'etudiant_update',
            'etudiant' => $etudiant,
        ]);
    }

    // POST /?uri=etudiant_update
    public function updateEtudiant(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->userModel->modifierEtudiant(
                $id,
                trim($_POST['nom']          ?? ''),
                trim($_POST['prenom']       ?? ''),
                trim($_POST['email']        ?? ''),
                trim($_POST['telephone']    ?? ''),
                trim($_POST['formation']    ?? ''),
                trim($_POST['niveau_etude'] ?? '')
            );
        }
        $this->redirect('/?uri=etudiant_list&success=modifie');
    }

    // POST /?uri=etudiant_affecter
    public function affecterEtudiant(): void {
        $this->requireRole(fn() => $this->isPilote());
        $idEtudiant = (int)($_POST['id_etudiant'] ?? 0);
        $idPilote   = (int)$_SESSION['user']['id_utilisateur'];
        if ($idEtudiant) {
            $this->userModel->affecterEtudiantAuPilote($idPilote, $idEtudiant);
        }
        $this->redirect('/?uri=etudiant_list&success=affecte');
    }

    // GET /?uri=etudiant_offres&id=X
    public function showEtudiantOffres(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $etudiant = $this->userModel->getEtudiantById($id);
        if (!$etudiant) {
            return $this->render('404.twig.html', ['uri' => 'etudiant_offres']);
        }
        $candidatures = $this->stageModel->getCandidaturesEtudiant($etudiant['id_etudiant']);
        return $this->render('admin/etudiant_offres.twig.html', [
            'uri'          => 'etudiant_offres',
            'etudiant'     => $etudiant,
            'candidatures' => $candidatures,
        ]);
    }

    // POST /?uri=etudiant_retirer
    public function retirerEtudiant(): void {
        $this->requireRole(fn() => $this->isPilote());
        $id = (int)($_POST['id_etudiant'] ?? 0);
        if ($id) {
            $this->userModel->retirerEtudiantPilote($id);
        }
        $this->redirect('/?uri=etudiant_list&success=retire');
    }

    // POST /?uri=etudiant_delete
    public function destroyEtudiant(): void {
        $this->requireRole(fn() => $this->isAdmin());
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->userModel->supprimerEtudiant($id);
        }
        $this->redirect('/?uri=etudiant_list&success=supprime');
    }
}

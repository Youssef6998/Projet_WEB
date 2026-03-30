<?php

require_once __DIR__ . '/BaseController.php';

class AdminController extends BaseController {

    private StageModel $model;

    public function __construct(\Twig\Environment $twig, StageModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    // GET /?uri=pilote_create
    public function showPiloteCreate(): string {
        $this->requireRole(fn() => $this->isAdmin());
        return $this->render('creer_compte_pilote.twig.html', ['uri' => 'pilote_create']);
    }

    // POST /?uri=pilote_create
    public function storePilote(): void {
        $this->requireRole(fn() => $this->isAdmin());

        $nom          = trim($_POST['nom']          ?? '');
        $prenom       = trim($_POST['prenom']       ?? '');
        $email        = trim($_POST['email']        ?? '');
        $telephone    = trim($_POST['telephone']    ?? '');
        $promotion    = trim($_POST['promotion']    ?? '');
        $motdepasse   = $_POST['motdepasse']        ?? '';
        $confirmation = $_POST['confirmation']      ?? '';

        if ($motdepasse !== $confirmation) {
            echo $this->render('creer_compte_pilote.twig.html', [
                'uri'    => 'pilote_create',
                'erreur' => 'Les mots de passe ne correspondent pas.',
            ]);
            return;
        }
        $this->model->creerPilote($nom, $prenom, $email, $motdepasse, $telephone, $promotion);
        $this->redirect('/?uri=pilote_list');
    }

    // GET /?uri=pilote_list
    public function showPiloteList(): string {
    $this->requireRole(fn() => $this->isAdmin());
    $nom    = trim($_GET['nom']    ?? '');
    $prenom = trim($_GET['prenom'] ?? '');
    $pilotes = $this->model->getAllPilotes($nom, $prenom);
    return $this->render('pilote_list.twig.html', [
        'uri'     => 'pilote_list',
        'pilotes' => $pilotes,
        'nom'     => $nom,
        'prenom'  => $prenom,
        'success' => $_GET['success'] ?? null,
    ]);
}

    // GET /?uri=pilote_update&id=X
    public function showPiloteEdit(int $id): string {
        $this->requireRole(fn() => $this->isAdmin());
        $pilote = $this->model->getPiloteById($id);
        if (!$pilote) {
            $this->redirect('/?uri=pilote_list');
        }
        return $this->render('modifier_pilote.twig.html', [
            'uri'    => 'pilote_update',
            'pilote' => $pilote,
        ]);
    }

    // POST /?uri=pilote_update
    public function updatePilote(): void {
        $this->requireRole(fn() => $this->isAdmin());

        $id           = (int)($_POST['id']            ?? 0);
        $nom          = trim($_POST['nom']             ?? '');
        $prenom       = trim($_POST['prenom']          ?? '');
        $email        = trim($_POST['email']           ?? '');
        $telephone    = trim($_POST['telephone']       ?? '');
        $promotion    = trim($_POST['promotion']       ?? '');
        $motdepasse   = $_POST['motdepasse']           ?? '';
        $confirmation = $_POST['confirmation']         ?? '';

        if ($motdepasse !== '' && $motdepasse !== $confirmation) {
            $pilote = $this->model->getPiloteById($id);
            echo $this->render('modifier_pilote.twig.html', [
                'uri'    => 'pilote_update',
                'pilote' => $pilote,
                'erreur' => 'Les mots de passe ne correspondent pas.',
            ]);
            return;
        }

        $this->model->updatePilote($id, $nom, $prenom, $email, $telephone, $promotion, $motdepasse !== '' ? $motdepasse : null);
        $this->redirect('/?uri=pilote_list&success=modifie');
    }

    // POST /?uri=pilote_delete
    public function destroyPilote(): void {
        $this->requireRole(fn() => $this->isAdmin());
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->model->supprimerPilote($id);
        }
        $this->redirect('/?uri=pilote_list&success=supprime');
    }

    // GET /?uri=etudiant_list
public function showEtudiantList(int $page = 1): string {
    // 🔥 REMPLACE isPiloteOuAdmin() par ÇA :
    $this->requireRole(fn() => $this->isAdmin() || $this->isPilote());
    
    $prenom = trim($_GET['prenom'] ?? '');
    $nom = trim($_GET['nom'] ?? '');
    $etudiants = $this->model->getEtudiantsFiltrees($prenom, $nom);
    
    return $this->render('etudiant_list.twig.html', [
        'uri' => 'etudiant_list',
        'etudiants' => $etudiants,
        'prenom' => $prenom,
        'nom' => $nom,
        'success' => $_GET['success'] ?? null
    ]);
}
    // POST /?uri=etudiant_delete
    public function destroyEtudiant(): void {
    $this->requireRole(fn() => $this->isAdminOrPilote());
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $this->model->supprimerEtudiant($id);
    }
    $this->redirect('/?uri=etudiant_list&success=supprime');
}
    // GET /?uri=avis_create
    public function showAvisCreate(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $entreprises = $this->model->getToutesEntreprises();
        $etudiants   = $this->model->getAllEtudiants();
        return $this->render('Avis.twig.html', [
            'uri'         => 'avis_create',
            'entreprises' => $entreprises,
            'etudiants'   => $etudiants,
            'message'     => isset($_GET['success']) ? 'Évaluation envoyée avec succès !' : null,
        ]);
    }

    // POST /?uri=avis_create
    public function storeAvis(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        $idEtudiant   = (int)($_POST['id_etudiant']   ?? 0);
        $idEntreprise = (int)($_POST['id_entreprise'] ?? 0);
        $note         = (int)($_POST['note']          ?? 0);
        $commentaire  = trim($_POST['commentaire']    ?? '');

        if (!$idEtudiant || !$idEntreprise || $note < 1 || $note > 5) {
            $entreprises = $this->model->getToutesEntreprises();
            $etudiants   = $this->model->getAllEtudiants();
            echo $this->render('Avis.twig.html', [
                'uri'         => 'avis_create',
                'entreprises' => $entreprises,
                'etudiants'   => $etudiants,
                'erreur'      => 'Veuillez remplir tous les champs obligatoires.',
            ]);
            return;
        }

        $this->model->creerEvaluation($idEtudiant, $idEntreprise, $note, $commentaire);
        $this->redirect('/?uri=avis_create&success=1');
    }

    public function showPiloteUpdate(int $id): string {
        $this->requireRole(fn() => $this->isAdmin());
        $pilote = $this->model->getPiloteById($id);
        if (!$pilote) return $this->render('404.twig.html', ['uri' => 'pilote_update']);
        return $this->render('modifier_pilote.twig.html', [
            'uri'    => 'pilote_update',
            'pilote' => $pilote,
        ]);
    }
    public function showEtudiantUpdate(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $etudiant = $this->model->getEtudiantById($id);
        if (!$etudiant) return $this->render('404.twig.html', ['uri' => 'etudiant_update']);
        return $this->render('modifier_etudiant.twig.html', [
            'uri'      => 'etudiant_update',
            'etudiant' => $etudiant,
        ]);
    }

    // POST /?uri=etudiant_update
    public function updateEtudiant(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->model->modifierEtudiant(
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
    // POST /?uri=etudiant_affecter
    public function affecterEtudiant(): void {
    $this->requireRole(fn() => $this->isPilote());
    $idEtudiantUtilisateur = (int)($_POST['id_etudiant'] ?? 0);
    $idPiloteUtilisateur   = (int)$_SESSION['user']['id_utilisateur'];
    if ($idEtudiantUtilisateur) {
        $this->model->affecterEtudiantAuPilote($idPiloteUtilisateur, $idEtudiantUtilisateur);
    }
    $this->redirect('/?uri=etudiant_list&success=affecte');
    }
    // GET /?uri=evaluation_list
    public function showEvaluationList(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $evaluations = $this->model->getAllEvaluations();
        return $this->render('evaluation_list.twig.html', [
            'uri'         => 'evaluation_list',
            'evaluations' => $evaluations,
            'success'     => $_GET['success'] ?? null,
        ]);
    }

    // POST /?uri=evaluation_delete
    public function destroyEvaluation(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->model->supprimerEvaluation($id);
        }
        $this->redirect('/?uri=evaluation_list&success=supprime');
    }
    

}





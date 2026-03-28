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
        $pilotes = $this->model->getAllPilotes();
        return $this->render('pilote_list.twig.html', [
            'uri'     => 'pilote_list',
            'pilotes' => $pilotes,
            'success' => $_GET['success'] ?? null,
        ]);
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
    public function showEtudiantList(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $nom    = trim($_GET['nom']    ?? '');
        $prenom = trim($_GET['prenom'] ?? '');
        $etudiants = $this->model->getAllEtudiants($nom, $prenom);
        return $this->render('etudiant_list.twig.html', [
            'uri'       => 'etudiant_list',
            'etudiants' => $etudiants,
            'nom'       => $nom,
            'prenom'    => $prenom,
            'success'   => $_GET['success'] ?? null,
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
        return $this->render('Avis.twig.html', [
            'uri'         => 'avis_create',
            'entreprises' => $entreprises,
            'message'     => isset($_GET['success']) ? 'Évaluation envoyée avec succès !' : null,
        ]);
    }

    // POST /?uri=avis_create
    public function storeAvis(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        $this->model->creerEvaluation(
            (int)($_POST['id_etudiant']   ?? 0),
            (int)($_POST['id_entreprise'] ?? 0),
            (int)($_POST['note']          ?? 0),
            trim($_POST['commentaire']    ?? '')
        );
        $this->redirect('/?uri=avis_create&success=1');
    }
}

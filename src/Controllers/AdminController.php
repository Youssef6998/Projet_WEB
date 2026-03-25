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

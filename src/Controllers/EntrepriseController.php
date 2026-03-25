<?php

require_once __DIR__ . '/BaseController.php';

class EntrepriseController extends BaseController {

    private StageModel $model;

    public function __construct(\Twig\Environment $twig, StageModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    // GET /?uri=entreprises
    public function index(int $page = 1): string {
        $nom  = $_GET['nom'] ?? '';
        $data = $this->model->getPaginatedEntreprises($page, 6, $nom);
        $data['uri'] = 'entreprises';
        $data['nom'] = $nom;
        return $this->render('cherche_entreprises.twig.html', $data);
    }

    // GET /?uri=entreprise&id=X
    public function show(int $id): string {
        if (!$id) {
            return $this->render('404.twig.html', ['uri' => 'entreprise']);
        }
        $entreprise = $this->model->getEntrepriseById($id);
        if (!$entreprise) {
            return $this->render('404.twig.html', ['uri' => 'entreprise']);
        }
        return $this->render('entreprise.twig.html', [
            'uri'        => 'entreprise',
            'entreprise' => $entreprise,
        ]);
    }

    // GET /?uri=entreprise_create
    public function showCreate(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('creer_entreprise.twig.html', ['uri' => 'entreprise_create']);
    }

    // POST /?uri=entreprise_create
    public function store(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        $this->model->creerEntreprise(
            trim($_POST['nom']         ?? ''),
            trim($_POST['ville']       ?? ''),
            trim($_POST['adresse']     ?? ''),
            trim($_POST['email']       ?? ''),
            trim($_POST['telephone']   ?? ''),
            trim($_POST['description'] ?? '')
        );
        $this->redirect('/?uri=entreprises');
    }

    // GET /?uri=entreprise_update&id=X
    public function showUpdate(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $entreprise = $this->model->getEntrepriseById($id);
        if (!$entreprise) {
            return $this->render('404.twig.html', ['uri' => 'entreprise_update']);
        }
        return $this->render('modifier_entreprise.twig.html', [
            'uri'        => 'entreprise_update',
            'entreprise' => $entreprise,
        ]);
    }

    // POST /?uri=entreprise_update
    public function update(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        // TODO: appeler $this->model->modifierEntreprise(...) quand la méthode sera implémentée
        $this->redirect('/?uri=entreprises');
    }
}

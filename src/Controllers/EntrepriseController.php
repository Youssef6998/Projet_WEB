<?php

require_once __DIR__ . '/BaseController.php';

class EntrepriseController extends BaseController {

    private EntrepriseModel  $entrepriseModel;
    private EvaluationModel  $evalModel;

    public function __construct(\Twig\Environment $twig, EntrepriseModel $entrepriseModel, EvaluationModel $evalModel) {
        parent::__construct($twig);
        $this->entrepriseModel = $entrepriseModel;
        $this->evalModel       = $evalModel;
    }

    // GET /?uri=entreprise_list  (vue de gestion admin/pilote)
    public function showEntrepriseList(int $page = 1): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $nom  = $_GET['nom'] ?? '';
        $data = $this->entrepriseModel->getPaginatedEntreprises($page, 10, $nom);
        $data['uri']     = 'entreprise_list';
        $data['nom']     = $nom;
        $data['success'] = $_GET['success'] ?? null;
        return $this->render('entreprises/entreprise_list.twig.html', $data);
    }

    // POST /?uri=entreprise_delete
    public function destroy(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->entrepriseModel->supprimerEntreprise($id);
        }
        $this->redirect('/?uri=entreprise_list&success=supprime');
    }

    // GET /?uri=entreprises
    public function index(int $page = 1): string {
        $nom  = $_GET['nom'] ?? '';
        $data = $this->entrepriseModel->getPaginatedEntreprises($page, 6, $nom);
        $data['uri'] = 'entreprises';
        $data['nom'] = $nom;
        return $this->render('entreprises/cherche_entreprises.twig.html', $data);
    }

    // GET /?uri=entreprise&id=X
    public function show(int $id): string {
        if (!$id) {
            return $this->render('404.twig.html', ['uri' => 'entreprise']);
        }
        $entreprise = $this->entrepriseModel->getEntrepriseById($id);
        if (!$entreprise) {
            return $this->render('404.twig.html', ['uri' => 'entreprise']);
        }
        $evaluations = $this->evalModel->getEvaluationsParEntreprise($id);
        return $this->render('entreprises/entreprise.twig.html', [
            'uri'         => 'entreprise',
            'entreprise'  => $entreprise,
            'evaluations' => $evaluations,
        ]);
    }

    // GET /?uri=entreprise_create
    public function showCreate(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('entreprises/creer_entreprise.twig.html', ['uri' => 'entreprise_create']);
    }

    // POST /?uri=entreprise_create
    public function store(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->entrepriseModel->creerEntreprise(
            trim($_POST['nom']         ?? ''),
            trim($_POST['ville']       ?? ''),
            trim($_POST['adresse']     ?? ''),
            trim($_POST['email']       ?? ''),
            trim($_POST['telephone']   ?? ''),
            trim($_POST['description'] ?? '')
        );
        $this->redirect('/?uri=entreprise_list&success=cree');
    }

    // GET /?uri=entreprise_update&id=X
    public function showUpdate(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $entreprise = $this->entrepriseModel->getEntrepriseById($id);
        if (!$entreprise) {
            return $this->render('404.twig.html', ['uri' => 'entreprise_update']);
        }
        return $this->render('entreprises/modifier_entreprise.twig.html', [
            'uri'        => 'entreprise_update',
            'entreprise' => $entreprise,
        ]);
    }

    // POST /?uri=entreprise_update
    public function update(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->entrepriseModel->modifierEntreprise(
                $id,
                trim($_POST['nom']         ?? ''),
                trim($_POST['ville']       ?? ''),
                trim($_POST['adresse']     ?? ''),
                trim($_POST['email']       ?? ''),
                trim($_POST['telephone']   ?? ''),
                trim($_POST['description'] ?? '')
            );
        }
        $this->redirect('/?uri=entreprise_list');
    }
}

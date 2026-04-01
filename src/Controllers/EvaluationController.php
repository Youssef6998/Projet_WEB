<?php

require_once __DIR__ . '/BaseController.php';

class EvaluationController extends BaseController {

    private EvaluationModel  $evalModel;
    private EntrepriseModel  $entrepriseModel;

    public function __construct(\Twig\Environment $twig, EvaluationModel $evalModel, UserModel $userModel, EntrepriseModel $entrepriseModel) {
        parent::__construct($twig);
        $this->evalModel       = $evalModel;
        $this->entrepriseModel = $entrepriseModel;
    }

    // GET /?uri=avis_create
    public function showAvisCreate(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('admin/Avis.twig.html', [
            'uri'         => 'avis_create',
            'entreprises' => $this->entrepriseModel->getToutesEntreprises(),
            'success'     => isset($_GET['success']),
        ]);
    }

    // POST /?uri=avis_create
    public function storeAvis(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        $idEntreprise = (int)($_POST['id_entreprise'] ?? 0);
        $note         = (int)($_POST['note']          ?? 0);
        $attendus     = trim($_POST['attendus']        ?? '');
        $idAuteur     = (int)$_SESSION['user']['id_utilisateur'];

        if (!$idEntreprise || $note < 1 || $note > 5 || $attendus === '') {
            echo $this->render('admin/Avis.twig.html', [
                'uri'         => 'avis_create',
                'entreprises' => $this->entrepriseModel->getToutesEntreprises(),
                'erreur'      => 'Veuillez remplir tous les champs obligatoires.',
                'old'         => $_POST,
            ]);
            return;
        }

        $this->evalModel->creerEvaluation($idEntreprise, $note, $attendus, $idAuteur);
        $this->redirect('/?uri=avis_create&success=1');
    }

    // GET /?uri=evaluation_list
    public function showList(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('admin/evaluation_list.twig.html', [
            'uri'     => 'evaluation_list',
            'resumes' => $this->evalModel->getResumesParEntreprise(),
            'success' => $_GET['success'] ?? null,
        ]);
    }

    // GET /?uri=evaluation_detail&id=X
    public function showDetail(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $detail = $this->evalModel->getDetailEntreprise($id);
        if (!$detail) {
            return $this->render('404.twig.html', ['uri' => 'evaluation_detail']);
        }
        return $this->render('admin/evaluation_detail.twig.html', [
            'uri'    => 'evaluation_detail',
            'detail' => $detail,
        ]);
    }

    // POST /?uri=evaluation_delete
    public function destroy(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $id       = (int)($_POST['id']            ?? 0);
        $redirect = $_POST['redirect'] ?? '/?uri=evaluation_list';
        if ($id) {
            $this->evalModel->supprimerEvaluation($id);
        }
        $this->redirect($redirect . '&success=supprime');
    }
}

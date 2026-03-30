<?php

require_once __DIR__ . '/BaseController.php';

class StageController extends BaseController {

    private StageModel $model;

    public function __construct(\Twig\Environment $twig, StageModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    // GET /?uri=cherche-stage ou /?uri=stages
    public function index(int $page = 1): string {
        $domaine    = $_GET['domaine']    ?? '';
        $ville      = $_GET['ville']      ?? '';
        $duree      = $_GET['duree']      ?? '';
        $competence = $_GET['competence'] ?? '';
        $tri        = $_GET['tri']        ?? 'date_desc';

        $data = $this->model->getPaginatedStages($page, 6, $domaine, $ville, $duree, $competence, $tri);
        $data['uri']        = $_GET['uri'] ?? 'cherche-stage';
        $data['domaine']    = $domaine;
        $data['ville']      = $ville;
        $data['duree']      = $duree;
        $data['competence'] = $competence;
        $data['tri']        = $tri;

        $template = ($data['uri'] === 'stages') ? 'stages.twig.html' : 'cherche_stage.twig.html';
        return $this->render($template, $data);
    }

    // GET /?uri=offre&id=X
    public function show(int $id): string {
        $offre = $this->model->getOffreById($id);
        if (!$offre) {
            return $this->render('404.twig.html', ['uri' => 'offre']);
        }

        $enFavori      = false;
        $dejaCandidate = false;
        if (!empty($_SESSION['user']['id_etudiant'])) {
            $idEt          = (int)$_SESSION['user']['id_etudiant'];
            $enFavori      = $this->model->isInWishlist($idEt, $id);
            $dejaCandidate = $this->model->dejaCandidate($idEt, $id);
        }

        return $this->render('offre.twig.html', [
            'uri'            => 'offre',
            'offre'          => $offre,
            'en_favori'      => $enFavori,
            'deja_candidate' => $dejaCandidate,
            'candidature_ok' => isset($_GET['candidature']) && $_GET['candidature'] === 'ok',
        ]);
    }

    // POST /?uri=candidater
    public function candidater(): void {
        $this->requireRole(fn() => $this->isEtudiant());

        $idOffre    = (int)($_POST['id_offre'] ?? 0);
        $idEtudiant = (int)$_SESSION['user']['id_etudiant'];
        $cvPath     = '';

        if (!empty($_FILES['cv']['tmp_name']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/cv/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx'];
            if (in_array($ext, $allowed, true)) {
                $filename = 'cv_' . $idEtudiant . '_' . $idOffre . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['cv']['tmp_name'], $uploadDir . $filename);
                $cvPath = '/uploads/cv/' . $filename;
            }
        }

        $lettreMot = trim($_POST['lettre_motivation'] ?? '');
        $this->model->candidater($idEtudiant, $idOffre, $lettreMot, $cvPath);
        $this->redirect("/?uri=offre&id=$idOffre&candidature=ok");
    }

    // POST /?uri=wishlist-toggle
    public function wishlistToggle(): void {
        $this->requireRole(fn() => $this->isEtudiant());

        $idOffre    = (int)($_POST['id_offre'] ?? 0);
        $idEtudiant = (int)$_SESSION['user']['id_etudiant'];
        if ($idOffre > 0) {
            $this->model->toggleWishlist($idEtudiant, $idOffre);
        }
        $redirect = $_POST['redirect'] ?? "/?uri=offre&id=$idOffre";
        $this->redirect($redirect);
    }
}

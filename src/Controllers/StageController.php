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

        $template = ($data['uri'] === 'stages') ? 'stages/stages.twig.html' : 'stages/cherche_stage.twig.html';
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

        return $this->render('stages/offre.twig.html', [
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

    // GET /?uri=offre_create
    public function showCreate(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('stages/creer_offre.twig.html', [
            'uri'         => 'offre_create',
            'entreprises' => $this->model->getToutesEntreprises(),
        ]);
    }

    // POST /?uri=offre_create
    public function store(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        $idEntreprise     = (int)($_POST['id_entreprise']     ?? 0);
        $titre            = trim($_POST['titre']              ?? '');
        $domaine          = trim($_POST['domaine']            ?? '') ?: null;
        $description      = trim($_POST['description']        ?? '') ?: null;
        $baseRemuneration = !empty($_POST['base_remuneration']) ? (float) $_POST['base_remuneration'] : null;
        $dateOffre        = trim($_POST['date_offre']         ?? '');
        $duree            = trim($_POST['duree']              ?? '') ?: null;

        if (!$idEntreprise || !$titre || !$dateOffre) {
            echo $this->render('stages/creer_offre.twig.html', [
                'uri'         => 'offre_create',
                'entreprises' => $this->model->getToutesEntreprises(),
                'erreur'      => 'Les champs Entreprise, Titre et Date de début sont obligatoires.',
                'old'         => $_POST,
            ]);
            return;
        }

        $this->model->creerOffre($idEntreprise, $titre, $domaine, $description, $baseRemuneration, $dateOffre, $duree);
        $this->redirect('/?uri=stages&success=creee');
    }

    // GET /?uri=offre_update&id=X
    public function showUpdate(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $offre = $this->model->getOffreById($id);
        if (!$offre) {
            return $this->render('404.twig.html', ['uri' => 'offre_update']);
        }
        return $this->render('stages/modifier_offre.twig.html', [
            'uri'         => 'offre_update',
            'offre'       => $offre,
            'entreprises' => $this->model->getToutesEntreprises(),
        ]);
    }

    // POST /?uri=offre_update
    public function update(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $idOffre          = (int)($_POST['id']             ?? 0);
        $idEntreprise     = (int)($_POST['id_entreprise']  ?? 0);
        $titre            = trim($_POST['titre']           ?? '');
        $description      = trim($_POST['description']     ?? '');
        $duree            = trim($_POST['duree']           ?? '');
        $dateOffre        = trim($_POST['date_offre']      ?? '');
        $baseRemuneration = !empty($_POST['base_remuneration']) ? (float) $_POST['base_remuneration'] : null;

        if ($idOffre) {
            $this->model->modifierOffre($idOffre, $idEntreprise, $titre, $description, $duree, $dateOffre, $baseRemuneration);
        }
        $this->redirect('/?uri=stages&success=modifiee');
    }

    // GET /?uri=stats_offres
    public function showStats(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('stages/stats_offres.twig.html', [
            'uri'         => 'stats_offres',
            'stats'       => $this->model->getStatsOffres(),
            'statsOffres' => $this->model->getStatsParOffre(),
        ]);
    }

    // POST /?uri=offre_delete
    public function destroy(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $idOffre = (int)($_POST['id'] ?? 0);
        if ($idOffre) {
            $this->model->supprimerCandidaturesOffre($idOffre);
            $this->model->supprimerOffre($idOffre);
        }
        $this->redirect('/?uri=stages&success=supprimee');
    }
}

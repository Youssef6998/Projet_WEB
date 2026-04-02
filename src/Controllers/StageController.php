<?php

require_once __DIR__ . '/BaseController.php';

/**
 * Contrôleur de gestion des offres de stage.
 *
 * Regroupe toutes les actions liées aux offres de stage :
 * - Consultation publique et recherche filtrée avec pagination.
 * - Détail d'une offre, gestion de la wishlist et candidature (étudiants).
 * - Création, modification et suppression d'offres (admins et pilotes).
 * - Tableau de bord statistique des candidatures par offre (admins et pilotes).
 */
class StageController extends BaseController {

    /** Modèle responsable de toutes les opérations de persistance liées aux stages. */
    private StageModel $model;

    /**
     * Injecte le moteur Twig et le modèle de stage.
     *
     * @param \Twig\Environment $twig  Moteur de rendu Twig.
     * @param StageModel        $model Modèle d'accès aux données des offres de stage.
     */
    public function __construct(\Twig\Environment $twig, StageModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    /**
     * Liste paginée et filtrable des offres de stage.
     *
     * GET /?uri=cherche-stage  — vue publique de recherche.
     * GET /?uri=stages         — vue de gestion (admin/pilote), même logique, template différent.
     *
     * Les filtres (domaine, ville, durée, compétence, tri) sont lus depuis $_GET.
     * La wishlist de l'étudiant connecté est injectée pour marquer les favoris en vue.
     *
     * @param int $page Numéro de page courant (défaut : 1).
     *
     * @return string HTML de la vue correspondant à l'URI demandée.
     */
    public function index(int $page = 1): string {
        $domaine    = $_GET['domaine']    ?? '';
        $ville      = $_GET['ville']      ?? '';
        $duree      = $_GET['duree']      ?? '';
        $competence = $_GET['competence'] ?? '';
        $tri        = $_GET['tri']        ?? 'date_desc';

        $data = $this->model->getPaginatedStages($page, 6, $domaine, $ville, $duree, $competence, $tri);
        // Restitue l'URI et les valeurs des filtres au template pour maintenir l'état du formulaire.
        $data['uri']        = $_GET['uri'] ?? 'cherche-stage';
        $data['domaine']    = $domaine;
        $data['ville']      = $ville;
        $data['duree']      = $duree;
        $data['competence'] = $competence;
        $data['tri']        = $tri;

        // Charge les IDs de la wishlist uniquement si l'utilisateur est un étudiant connecté.
        $data['wishlist_ids'] = [];
        if (!empty($_SESSION['user']['id_etudiant'])) {
            $data['wishlist_ids'] = $this->model->getWishlistIds((int)$_SESSION['user']['id_etudiant']);
        }

        // Choisit le template selon le contexte d'affichage (gestion vs recherche publique).
        $template = ($data['uri'] === 'stages') ? 'stages/stages.twig.html' : 'stages/cherche_stage.twig.html';
        return $this->render($template, $data);
    }

    /**
     * Affiche le détail d'une offre de stage.
     *
     * GET /?uri=offre&id=X
     *
     * Si l'utilisateur est un étudiant connecté, détermine en outre si l'offre
     * est dans sa wishlist et s'il a déjà soumis une candidature.
     *
     * @param int $id Identifiant de l'offre à afficher.
     *
     * @return string HTML de la page de détail ou de la page 404.
     */
    public function show(int $id): string {
        $offre = $this->model->getOffreById($id);
        if (!$offre) {
            return $this->render('404.twig.html', ['uri' => 'offre']);
        }

        // Ces indicateurs ne sont pertinents que pour les étudiants connectés.
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
            // Indicateur de succès passé en GET après une candidature réussie.
            'candidature_ok' => isset($_GET['candidature']) && $_GET['candidature'] === 'ok',
        ]);
    }

    /**
     * Traite la soumission d'une candidature à une offre de stage.
     *
     * POST /?uri=candidater
     *
     * Réservé aux étudiants. Gère l'upload optionnel d'un CV (PDF, DOC, DOCX),
     * enregistre la candidature et redirige vers la page de l'offre avec confirmation.
     *
     * @return void
     */
    public function candidater(): void {
        // Seuls les étudiants peuvent candidater.
        $this->requireRole(fn() => $this->isEtudiant());
        $this->verifyCsrf();

        $idOffre    = (int)($_POST['id_offre'] ?? 0);
        $idEtudiant = (int)$_SESSION['user']['id_etudiant'];
        $cvPath     = '';

        // Traitement de l'upload du CV si un fichier a bien été transmis sans erreur.
        if (!empty($_FILES['cv']['tmp_name']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/cv/';
            // Crée le répertoire de destination s'il n'existe pas encore.
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx'];
            // Rejette silencieusement les fichiers dont l'extension n'est pas autorisée.
            if (in_array($ext, $allowed, true)) {
                // Nom unique : id_étudiant + id_offre + timestamp pour éviter les collisions.
                $filename = 'cv_' . $idEtudiant . '_' . $idOffre . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['cv']['tmp_name'], $uploadDir . $filename);
                $cvPath = '/uploads/cv/' . $filename;
            }
        }

        $lettreMot = trim($_POST['lettre_motivation'] ?? '');
        $this->model->candidater($idEtudiant, $idOffre, $lettreMot, $cvPath);
        $this->redirect("/?uri=offre&id=$idOffre&candidature=ok");
    }

    /**
     * Bascule l'état de la wishlist (ajout / retrait) pour une offre.
     *
     * POST /?uri=wishlist-toggle
     *
     * Réservé aux étudiants. Supporte deux modes de réponse :
     * - Requête AJAX : retourne un JSON {"en_favori": bool}.
     * - Requête standard : redirige vers l'URL fournie ou vers la page de l'offre.
     *
     * @return void
     */
    public function wishlistToggle(): void {
        // Seuls les étudiants peuvent gérer leur wishlist.
        $this->requireRole(fn() => $this->isEtudiant());
        $this->verifyCsrf();

        $idOffre    = (int)($_POST['id_offre'] ?? 0);
        $idEtudiant = (int)$_SESSION['user']['id_etudiant'];
        $isNowInWishlist = false;
        if ($idOffre > 0) {
            // toggleWishlist insère ou supprime le favori et retourne le nouvel état.
            $isNowInWishlist = $this->model->toggleWishlist($idEtudiant, $idOffre);
        }

        // Détecte si la requête provient d'un appel AJAX pour adapter le format de réponse.
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
               && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['en_favori' => $isNowInWishlist]);
            exit;
        }

        // Requête classique : retour à la page d'origine ou à la page de l'offre.
        $redirect = $_POST['redirect'] ?? "/?uri=offre&id=$idOffre";
        $this->redirect($redirect);
    }

    /**
     * Affiche le formulaire de création d'une offre de stage.
     *
     * GET /?uri=offre_create
     *
     * Réservé aux admins et pilotes. Charge la liste des entreprises pour
     * alimenter le sélecteur du formulaire.
     *
     * @return string HTML du formulaire de création.
     */
    public function showCreate(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('stages/creer_offre.twig.html', [
            'uri'         => 'offre_create',
            'entreprises' => $this->model->getToutesEntreprises(),
        ]);
    }

    /**
     * Traite la soumission du formulaire de création d'une offre de stage.
     *
     * POST /?uri=offre_create
     *
     * Valide les champs obligatoires et s'assure que la date de début n'est pas
     * dans le passé avant de déléguer la persistance au modèle.
     *
     * @return void
     */
    public function store(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();

        $idEntreprise     = (int)($_POST['id_entreprise']     ?? 0);
        $titre            = trim($_POST['titre']              ?? '');
        // Les champs facultatifs sont normalisés à null si vides pour la BDD.
        $domaine          = trim($_POST['domaine']            ?? '') ?: null;
        $description      = trim($_POST['description']        ?? '') ?: null;
        $baseRemuneration = !empty($_POST['base_remuneration']) ? (float) $_POST['base_remuneration'] : null;
        $dateOffre        = trim($_POST['date_offre']         ?? '');
        $duree            = trim($_POST['duree']              ?? '') ?: null;

        // Validation des champs obligatoires : entreprise, titre et date de début.
        if (!$idEntreprise || !$titre || !$dateOffre) {
            echo $this->render('stages/creer_offre.twig.html', [
                'uri'         => 'offre_create',
                'entreprises' => $this->model->getToutesEntreprises(),
                'erreur'      => 'Les champs Entreprise, Titre et Date de début sont obligatoires.',
                'old'         => $_POST,
            ]);
            return;
        }

        // Rejette les offres dont la date de début serait antérieure à aujourd'hui.
        if ($dateOffre < date('Y-m-d')) {
            echo $this->render('stages/creer_offre.twig.html', [
                'uri'         => 'offre_create',
                'entreprises' => $this->model->getToutesEntreprises(),
                'erreur'      => 'La date de début ne peut pas être dans le passé.',
                'old'         => $_POST,
            ]);
            return;
        }

        $this->model->creerOffre($idEntreprise, $titre, $domaine, $description, $baseRemuneration, $dateOffre, $duree);
        $this->redirect('/?uri=stages&success=creee');
    }

    /**
     * Affiche le formulaire de modification d'une offre de stage existante.
     *
     * GET /?uri=offre_update&id=X
     *
     * Réservé aux admins et pilotes. Retourne une 404 si l'offre est introuvable.
     *
     * @param int $id Identifiant de l'offre à modifier.
     *
     * @return string HTML du formulaire pré-rempli ou de la page 404.
     */
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

    /**
     * Traite la soumission du formulaire de modification d'une offre.
     *
     * POST /?uri=offre_update
     *
     * La mise à jour n'est effectuée que si un identifiant valide est fourni.
     * Réservé aux admins et pilotes.
     *
     * @return void
     */
    public function update(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();
        $idOffre          = (int)($_POST['id']             ?? 0);
        $idEntreprise     = (int)($_POST['id_entreprise']  ?? 0);
        $titre            = trim($_POST['titre']           ?? '');
        $description      = trim($_POST['description']     ?? '');
        $duree            = trim($_POST['duree']           ?? '');
        $dateOffre        = trim($_POST['date_offre']      ?? '');
        $baseRemuneration = !empty($_POST['base_remuneration']) ? (float) $_POST['base_remuneration'] : null;

        // Garde-fou : n'effectue la mise à jour que si l'ID est valide (> 0).
        if ($idOffre) {
            $this->model->modifierOffre($idOffre, $idEntreprise, $titre, $description, $duree, $dateOffre, $baseRemuneration);
        }
        $this->redirect('/?uri=stages&success=modifiee');
    }

    /**
     * Affiche le tableau de bord statistique des offres de stage.
     *
     * GET /?uri=stats_offres
     *
     * Réservé aux admins et pilotes. Présente les statistiques globales ainsi
     * qu'une liste paginée de statistiques détaillées par offre.
     *
     * @return string HTML de la page de statistiques.
     */
    public function showStats(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        // Assure que le numéro de page est au minimum 1 pour éviter des requêtes invalides.
        $page           = max(1, (int)($_GET['page'] ?? 1));
        $paginatedStats = $this->model->getPaginatedStatsParOffre($page, 6);
        return $this->render('stages/stats_offres.twig.html', [
            'uri'         => 'stats_offres',
            'stats'       => $this->model->getStatsOffres(),       // Statistiques globales (totaux).
            'statsOffres' => $paginatedStats['statsOffres'],        // Détail par offre pour la page courante.
            'currentPage' => $paginatedStats['currentPage'],
            'totalPages'  => $paginatedStats['totalPages'],
        ]);
    }

    /**
     * Supprime une offre de stage et toutes ses candidatures associées.
     *
     * POST /?uri=offre_delete
     *
     * Réservé aux admins et pilotes. Les candidatures sont supprimées en premier
     * pour respecter les contraintes d'intégrité référentielle de la base de données.
     *
     * @return void
     */
    public function destroy(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();
        $idOffre = (int)($_POST['id'] ?? 0);
        if ($idOffre) {
            // Supprime d'abord les candidatures pour éviter une violation de clé étrangère.
            $this->model->supprimerCandidaturesOffre($idOffre);
            $this->model->supprimerOffre($idOffre);
        }
        $this->redirect('/?uri=stages&success=supprimee');
    }
}

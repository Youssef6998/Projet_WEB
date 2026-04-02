<?php

require_once __DIR__ . '/BaseController.php';

/**
 * Contrôleur de gestion des entreprises partenaires.
 *
 * Expose deux contextes d'utilisation :
 * - Vue publique de recherche (index / show) : accessible à tous les visiteurs.
 * - Vue de gestion CRUD (showEntrepriseList, store, showUpdate, update, destroy) :
 *   réservée aux admins et pilotes.
 *
 * Associe également les évaluations d'entreprise lors de l'affichage du détail,
 * via le modèle EvaluationModel injecté.
 */
class EntrepriseController extends BaseController {

    /** Modèle responsable des opérations CRUD sur les entreprises. */
    private EntrepriseModel  $entrepriseModel;

    /** Modèle responsable de la lecture des évaluations associées aux entreprises. */
    private EvaluationModel  $evalModel;

    /**
     * Injecte le moteur Twig et les deux modèles nécessaires.
     *
     * @param \Twig\Environment $twig             Moteur de rendu Twig.
     * @param EntrepriseModel   $entrepriseModel  Modèle d'accès aux données des entreprises.
     * @param EvaluationModel   $evalModel        Modèle d'accès aux évaluations des entreprises.
     */
    public function __construct(\Twig\Environment $twig, EntrepriseModel $entrepriseModel, EvaluationModel $evalModel) {
        parent::__construct($twig);
        $this->entrepriseModel = $entrepriseModel;
        $this->evalModel       = $evalModel;
    }

    /**
     * Affiche la liste paginée des entreprises dans la vue de gestion admin/pilote.
     *
     * GET /?uri=entreprise_list
     *
     * Supporte un filtre par nom via le paramètre GET 'nom'. Affiche 10 entreprises
     * par page. Expose également le message de succès éventuel transmis par GET.
     *
     * @param int $page Numéro de page courant (défaut : 1).
     *
     * @return string HTML de la vue de gestion des entreprises.
     */
    public function showEntrepriseList(int $page = 1): string {
        // Accès restreint aux admins et pilotes.
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $nom  = $_GET['nom'] ?? '';
        $data = $this->entrepriseModel->getPaginatedEntreprises($page, 10, $nom);
        $data['uri']     = 'entreprise_list';
        $data['nom']     = $nom;
        // Le message de succès (ex. 'cree', 'supprime') est passé en GET après une action.
        $data['success'] = $_GET['success'] ?? null;
        return $this->render('entreprises/entreprise_list.twig.html', $data);
    }

    /**
     * Supprime une entreprise à partir de son identifiant POST.
     *
     * POST /?uri=entreprise_delete
     *
     * Réservé aux admins et pilotes. La suppression n'est effectuée que si
     * un identifiant valide est fourni.
     *
     * @return void
     */
    public function destroy(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        // Garde-fou : n'effectue la suppression que si l'ID est valide (> 0).
        if ($id) {
            $this->entrepriseModel->supprimerEntreprise($id);
        }
        $this->redirect('/?uri=entreprise_list&success=supprime');
    }

    /**
     * Affiche la liste paginée des entreprises dans la vue publique de recherche.
     *
     * GET /?uri=entreprises
     *
     * Accessible à tous les visiteurs. Affiche 6 entreprises par page avec
     * un filtre optionnel par nom.
     *
     * @param int $page Numéro de page courant (défaut : 1).
     *
     * @return string HTML de la vue publique de recherche des entreprises.
     */
    public function index(int $page = 1): string {
        $nom  = $_GET['nom'] ?? '';
        $data = $this->entrepriseModel->getPaginatedEntreprises($page, 6, $nom);
        $data['uri'] = 'entreprises';
        $data['nom'] = $nom;
        return $this->render('entreprises/cherche_entreprises.twig.html', $data);
    }

    /**
     * Affiche la fiche détaillée d'une entreprise et ses évaluations.
     *
     * GET /?uri=entreprise&id=X
     *
     * Accessible à tous les visiteurs. Retourne une page 404 si l'identifiant
     * est absent ou si l'entreprise n'existe pas en base.
     *
     * @param int $id Identifiant de l'entreprise à afficher.
     *
     * @return string HTML de la fiche entreprise ou de la page 404.
     */
    public function show(int $id): string {
        // Rejette immédiatement les requêtes sans identifiant valide.
        if (!$id) {
            return $this->render('404.twig.html', ['uri' => 'entreprise']);
        }
        $entreprise = $this->entrepriseModel->getEntrepriseById($id);
        if (!$entreprise) {
            return $this->render('404.twig.html', ['uri' => 'entreprise']);
        }
        // Charge les évaluations liées à cette entreprise pour les afficher sur la fiche.
        $evaluations = $this->evalModel->getEvaluationsParEntreprise($id);
        return $this->render('entreprises/entreprise.twig.html', [
            'uri'         => 'entreprise',
            'entreprise'  => $entreprise,
            'evaluations' => $evaluations,
        ]);
    }

    /**
     * Affiche le formulaire de création d'une nouvelle entreprise.
     *
     * GET /?uri=entreprise_create
     *
     * Réservé aux admins et pilotes.
     *
     * @return string HTML du formulaire de création.
     */
    public function showCreate(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('entreprises/creer_entreprise.twig.html', ['uri' => 'entreprise_create']);
    }

    /**
     * Traite la soumission du formulaire de création d'une entreprise.
     *
     * POST /?uri=entreprise_create
     *
     * Réservé aux admins et pilotes. Toutes les valeurs POST sont assainies
     * (trim) avant d'être transmises au modèle.
     *
     * @return void
     */
    public function store(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();
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

    /**
     * Affiche le formulaire de modification d'une entreprise existante.
     *
     * GET /?uri=entreprise_update&id=X
     *
     * Réservé aux admins et pilotes. Retourne une 404 si l'entreprise est introuvable.
     *
     * @param int $id Identifiant de l'entreprise à modifier.
     *
     * @return string HTML du formulaire pré-rempli ou de la page 404.
     */
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

    /**
     * Traite la soumission du formulaire de modification d'une entreprise.
     *
     * POST /?uri=entreprise_update
     *
     * Réservé aux admins et pilotes. La mise à jour n'est effectuée que si
     * un identifiant valide est fourni. Toutes les valeurs POST sont assainies
     * (trim) avant d'être transmises au modèle.
     *
     * @return void
     */
    public function update(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        // Garde-fou : n'effectue la mise à jour que si l'ID est valide (> 0).
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

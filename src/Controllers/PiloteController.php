<?php

require_once __DIR__ . '/BaseController.php';

/**
 * Contrôleur de gestion des pilotes et des étudiants.
 *
 * Un "pilote" est un enseignant référent qui supervise un groupe d'étudiants.
 * Ce contrôleur centralise :
 *  - Le CRUD des comptes pilotes, réservé exclusivement aux administrateurs.
 *  - Le CRUD des comptes étudiants, accessible aux admins et aux pilotes.
 *  - La gestion du lien de supervision (affectation/retrait).
 *  - La consultation des candidatures d'un étudiant depuis la vue admin/pilote.
 *  - Le téléchargement sécurisé des CV déposés par les étudiants lors de leur candidature.
 *
 * Toutes les actions POST vérifient le jeton CSRF avant tout traitement.
 *
 * Responsabilités :
 *  - Opérations CRUD sur les comptes pilotes (admin uniquement).
 *  - Opérations CRUD sur les comptes étudiants (admin ou pilote).
 *  - Affectation et retrait d'étudiants de la supervision d'un pilote.
 *  - Consultation des candidatures d'un étudiant.
 *  - Téléchargement des fichiers CV déposés par les étudiants.
 */
class PiloteController extends BaseController {

    private UserModel  $userModel;
    private StageModel $stageModel;

    /**
     * Injecte le moteur Twig et les deux modèles nécessaires.
     *
     * @param \Twig\Environment $twig        Moteur de rendu Twig.
     * @param UserModel         $userModel   Gère toutes les opérations BDD utilisateur/pilote/étudiant.
     * @param StageModel        $stageModel  Récupère les candidatures et les chemins de CV.
     */
    public function __construct(\Twig\Environment $twig, UserModel $userModel, StageModel $stageModel) {
        parent::__construct($twig);
        $this->userModel  = $userModel;
        $this->stageModel = $stageModel;
    }

    /**
     * Redirige vers la page de création de compte unifiée, pré-filtrée sur le type pilote.
     *
     * GET /?uri=pilote_create
     *
     * @return string Ne retourne jamais réellement ; redirige systématiquement.
     */
    public function showCreate(): string {
        $this->requireRole(fn() => $this->isAdmin());
        $this->redirect('/?uri=register&type=pilote');
    }

    /**
     * Affiche la liste paginée et filtrable de tous les comptes pilotes.
     *
     * GET /?uri=pilote_list
     *
     * @return string HTML de la page de liste des pilotes.
     */
    public function showList(): string {
        $this->requireRole(fn() => $this->isAdmin());

        // Lit les filtres de recherche optionnels depuis la chaîne de requête.
        $nom    = trim($_GET['nom']    ?? '');
        $prenom = trim($_GET['prenom'] ?? '');
        // Borne la page à 1 minimum pour éviter les requêtes avec OFFSET négatif.
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $data   = $this->userModel->getPaginatedPilotes($page, 6, $nom, $prenom);

        return $this->render('admin/pilote_list.twig.html', [
            'uri'          => 'pilote_list',
            'pilotes'      => $data['pilotes'],
            'currentPage'  => $data['currentPage'],
            'totalPages'   => $data['totalPages'],
            'totalPilotes' => $data['totalPilotes'],
            'nom'          => $nom,
            'prenom'       => $prenom,
            // Message flash transmis en GET après une action de modification réussie.
            'success'      => $_GET['success'] ?? null,
        ]);
    }

    /**
     * Affiche le formulaire de modification d'un compte pilote.
     *
     * Redirige vers la liste si l'identifiant ne correspond à aucun pilote en base.
     *
     * GET /?uri=pilote_update&id=X
     *
     * @param int $id Clé primaire utilisateur (id_utilisateur) du pilote.
     * @return string HTML du formulaire de modification.
     */
    public function showEdit(int $id): string {
        $this->requireRole(fn() => $this->isAdmin());
        $pilote = $this->userModel->getPiloteById($id);

        // Protège contre la manipulation directe d'URL avec un ID invalide ou supprimé.
        if (!$pilote) {
            $this->redirect('/?uri=pilote_list');
        }

        return $this->render('admin/modifier_pilote.twig.html', [
            'uri'    => 'pilote_update',
            'pilote' => $pilote,
        ]);
    }

    /**
     * Traite la soumission du formulaire de modification d'un pilote.
     *
     * Le changement de mot de passe est facultatif : si le champ est laissé vide,
     * le hash existant est conservé (null est transmis au modèle, qui ignore la mise à jour).
     * Si un nouveau mot de passe est fourni, les deux saisies doivent correspondre
     * avant toute écriture en base.
     *
     * POST /?uri=pilote_update
     *
     * @return void Redirige vers la liste des pilotes en cas de succès, ou réaffiche
     *              le formulaire avec un message d'erreur si les mots de passe ne correspondent pas.
     */
    public function update(): void {
        $this->requireRole(fn() => $this->isAdmin());
        $this->verifyCsrf();

        $id           = (int)($_POST['id']         ?? 0);
        $nom          = trim($_POST['nom']          ?? '');
        $prenom       = trim($_POST['prenom']       ?? '');
        $email        = trim($_POST['email']        ?? '');
        $telephone    = trim($_POST['telephone']    ?? '');
        $promotion    = trim($_POST['promotion']    ?? '');
        $motdepasse   = $_POST['motdepasse']        ?? '';
        $confirmation = $_POST['confirmation']      ?? '';

        // Valide les champs mot de passe uniquement si l'admin souhaite réellement le modifier.
        if ($motdepasse !== '' && $motdepasse !== $confirmation) {
            // Réaffiche le formulaire en place plutôt que de rediriger pour conserver le contexte.
            $pilote = $this->userModel->getPiloteById($id);
            echo $this->render('admin/modifier_pilote.twig.html', [
                'uri'    => 'pilote_update',
                'pilote' => $pilote,
                'erreur' => 'Les mots de passe ne correspondent pas.',
            ]);
            return;
        }

        // Transmet null pour le mot de passe afin de signaler au modèle de ne pas le modifier.
        $this->userModel->updatePilote($id, $nom, $prenom, $email, $telephone, $promotion, $motdepasse !== '' ? $motdepasse : null);
        $this->redirect('/?uri=pilote_list&success=modifie');
    }

    /**
     * Supprime définitivement un compte pilote.
     *
     * POST /?uri=pilote_delete
     *
     * @return void Redirige vers la liste des pilotes avec un message de succès.
     */
    public function destroy(): void {
        $this->requireRole(fn() => $this->isAdmin());
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->userModel->supprimerPilote($id);
        }
        $this->redirect('/?uri=pilote_list&success=supprime');
    }

    /**
     * Affiche la liste paginée et filtrable des comptes étudiants.
     *
     * Accessible aux admins et aux pilotes. Les pilotes utilisent cette page
     * pour trouver des étudiants à affecter à leur supervision.
     *
     * GET /?uri=etudiant_list
     *
     * @return string HTML de la page de liste des étudiants.
     */
    public function showEtudiantList(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        $prenom = trim($_GET['prenom'] ?? '');
        $nom    = trim($_GET['nom']    ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $data   = $this->userModel->getPaginatedEtudiants($page, 6, $prenom, $nom);

        return $this->render('admin/etudiant_list.twig.html', [
            'uri'            => 'etudiant_list',
            'etudiants'      => $data['etudiants'],
            'currentPage'    => $data['currentPage'],
            'totalPages'     => $data['totalPages'],
            'totalEtudiants' => $data['totalEtudiants'],
            'prenom'         => $prenom,
            'nom'            => $nom,
            'success'        => $_GET['success'] ?? null,
        ]);
    }

    /**
     * Affiche le formulaire de modification d'un étudiant.
     *
     * Redirige vers la liste si l'identifiant est introuvable.
     *
     * GET /?uri=etudiant_update&id=X
     *
     * @param int $id Clé primaire utilisateur (id_utilisateur) de l'étudiant.
     * @return string HTML du formulaire de modification de l'étudiant.
     */
    public function showEtudiantUpdate(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $etudiant = $this->userModel->getEtudiantById($id);
        if (!$etudiant) {
            $this->redirect('/?uri=etudiant_list');
        }
        return $this->render('admin/modifier_etudiant.twig.html', [
            'uri'      => 'etudiant_update',
            'etudiant' => $etudiant,
        ]);
    }

    /**
     * Traite la soumission du formulaire de modification d'un étudiant.
     *
     * POST /?uri=etudiant_update
     *
     * @return void Redirige vers la liste des étudiants avec un message de succès.
     */
    public function updateEtudiant(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->userModel->modifierEtudiant(
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

    /**
     * Affecte un étudiant à la supervision du pilote connecté.
     *
     * L'identifiant du pilote est toujours lu depuis la session pour éviter
     * toute usurpation d'identité via manipulation du POST.
     *
     * POST /?uri=etudiant_affecter
     *
     * @return void Redirige vers la liste des étudiants avec un message de succès.
     */
    public function affecterEtudiant(): void {
        $this->requireRole(fn() => $this->isPilote());
        $this->verifyCsrf();
        $idEtudiant = (int)($_POST['id_etudiant'] ?? 0);
        // L'ID du pilote est toujours pris depuis la session, jamais depuis le POST.
        $idPilote   = (int)$_SESSION['user']['id_utilisateur'];
        if ($idEtudiant) {
            $this->userModel->affecterEtudiantAuPilote($idPilote, $idEtudiant);
        }
        $this->redirect('/?uri=etudiant_list&success=affecte');
    }

    /**
     * Affiche les candidatures d'un étudiant pour un pilote ou un admin.
     *
     * Retourne une page 404 si l'identifiant de l'étudiant est introuvable.
     *
     * GET /?uri=etudiant_offres&id=X
     *
     * @param int $id Clé primaire utilisateur (id_utilisateur) de l'étudiant.
     * @return string HTML de la page des candidatures de l'étudiant, ou page 404.
     */
    public function showEtudiantOffres(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $etudiant = $this->userModel->getEtudiantById($id);
        if (!$etudiant) {
            return $this->render('404.twig.html', ['uri' => 'etudiant_offres']);
        }
        $candidatures = $this->stageModel->getCandidaturesEtudiant($etudiant['id_etudiant']);
        return $this->render('admin/etudiant_offres.twig.html', [
            'uri'          => 'etudiant_offres',
            'etudiant'     => $etudiant,
            'candidatures' => $candidatures,
        ]);
    }

    /**
     * Sert le fichier CV d'un étudiant en téléchargement direct.
     *
     * Deux niveaux de vérification :
     *  - Le chemin est d'abord récupéré depuis la base (enregistrement de candidature).
     *  - Le fichier est ensuite vérifié sur le système de fichiers.
     * Retourne une erreur 404 si l'un ou l'autre est absent.
     *
     * GET /?uri=cv_download&id_etudiant=X&id_offre=Y
     *
     * @return void Envoie le fichier ou termine avec un code 404.
     */
    public function downloadCv(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $idEtudiant = (int)($_GET['id_etudiant'] ?? 0);
        $idOffre    = (int)($_GET['id_offre']    ?? 0);
        $cvPath     = $this->stageModel->getCvPath($idEtudiant, $idOffre);

        if (!$cvPath) {
            http_response_code(404);
            exit('CV introuvable.');
        }

        $fullPath = __DIR__ . '/../../' . ltrim($cvPath, '/');
        if (!file_exists($fullPath)) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }

        $filename = basename($fullPath);
        $mime     = mime_content_type($fullPath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    /**
     * Retire un étudiant de la supervision du pilote connecté (supprime le lien).
     *
     * POST /?uri=etudiant_retirer
     *
     * @return void Redirige vers la liste des étudiants avec un message de succès.
     */
    public function retirerEtudiant(): void {
        $this->requireRole(fn() => $this->isPilote());
        $this->verifyCsrf();
        $id = (int)($_POST['id_etudiant'] ?? 0);
        if ($id) {
            $this->userModel->retirerEtudiantPilote($id);
        }
        $this->redirect('/?uri=etudiant_list&success=retire');
    }

    /**
     * Supprime définitivement un compte étudiant.
     *
     * POST /?uri=etudiant_delete
     *
     * @return void Redirige vers la liste des étudiants avec un message de succès.
     */
    public function destroyEtudiant(): void {
        $this->requireRole(fn() => $this->isAdmin());
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->userModel->supprimerEtudiant($id);
        }
        $this->redirect('/?uri=etudiant_list&success=supprime');
    }
}

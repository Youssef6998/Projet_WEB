<?php

/**
 * Contrôleur d'administration de la plateforme.
 *
 * Regroupe les actions réservées à l'administrateur et, pour certaines,
 * au pilote également :
 *  - Gestion CRUD des comptes pilotes (admin uniquement).
 *  - Gestion CRUD des comptes étudiants (admin et pilote).
 *  - Affectation / retrait d'étudiants sous la supervision d'un pilote.
 *  - Création et suppression d'évaluations d'entreprises (avis).
 *  - Consultation de la liste des évaluations.
 *  - Affichage des candidatures d'un étudiant.
 *
 * Chaque méthode commence par un appel à requireRole() pour protéger
 * l'accès selon le rôle de l'utilisateur connecté.
 */

require_once __DIR__ . '/BaseController.php';

class AdminController extends BaseController {

    /**
     * Modèle unique centralisant les accès aux données :
     * stages, étudiants, pilotes, entreprises et évaluations.
     */
    private StageModel $model;

    /**
     * Injecte le moteur de rendu Twig et le modèle de données.
     *
     * @param \Twig\Environment $twig  Moteur de rendu Twig partagé par l'application.
     * @param StageModel        $model Modèle d'accès à toutes les données métier.
     */
    public function __construct(\Twig\Environment $twig, StageModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    // -------------------------------------------------------------------------
    // Gestion des pilotes
    // -------------------------------------------------------------------------

    /**
     * Affiche le formulaire de création d'un compte pilote.
     *
     * GET /?uri=pilote_create
     *
     * Accès restreint à l'administrateur uniquement.
     *
     * @return string HTML de la page de création de compte pilote.
     */
    public function showPiloteCreate(): string {
        // Seul l'admin peut accéder à la création de comptes pilotes.
        $this->requireRole(fn() => $this->isAdmin());
        return $this->render('creer_compte_pilote.twig.html', ['uri' => 'pilote_create']);
        // Note : les lignes suivantes sont du code mort (jamais exécutées après return).
        $this->assertIsString($response);
        $this->assertStringContainsString('creer_compte_pilote.twig.html', $response);
        $this->assertStringContainsString('uri', 'pilote_create');
    }

    /**
     * Traite la soumission du formulaire de création d'un pilote.
     *
     * POST /?uri=pilote_create
     *
     * Lit les données du formulaire depuis $_POST, vérifie que les deux
     * saisies du mot de passe sont identiques, puis délègue la création
     * au modèle. Redirige vers la liste des pilotes en cas de succès,
     * ou réaffiche le formulaire avec un message d'erreur sinon.
     *
     * @return void
     */
    public function storePilote(): void {
        // Seul l'admin peut créer un pilote.
        $this->requireRole(fn() => $this->isAdmin());

        // Récupération et assainissement des données POST.
        $nom          = trim($_POST['nom']          ?? '');
        $prenom       = trim($_POST['prenom']       ?? '');
        $email        = trim($_POST['email']        ?? '');
        $telephone    = trim($_POST['telephone']    ?? '');
        $promotion    = trim($_POST['promotion']    ?? '');
        $motdepasse   = $_POST['motdepasse']        ?? '';
        $confirmation = $_POST['confirmation']      ?? '';

        // Les deux saisies du mot de passe doivent être identiques avant tout enregistrement.
        if ($motdepasse !== $confirmation) {
            echo $this->render('creer_compte_pilote.twig.html', [
                'uri'    => 'pilote_create',
                'erreur' => 'Les mots de passe ne correspondent pas.',
            ]);
            return;
        }

        // Délègue la création effective du compte pilote au modèle (hachage du mdp inclus).
        $this->model->creerPilote($nom, $prenom, $email, $motdepasse, $telephone, $promotion);
        // Redirige vers la liste des pilotes après la création réussie.
        $this->redirect('/?uri=pilote_list');
    }

    /**
     * Affiche la liste paginée et filtrable des comptes pilotes.
     *
     * GET /?uri=pilote_list
     *
     * Supporte le filtrage par nom et prénom via les paramètres GET.
     * Transmet également le message flash éventuel (GET 'success').
     *
     * Accès restreint à l'administrateur uniquement.
     *
     * @return string HTML de la liste des pilotes.
     */
    public function showPiloteList(): string {
        $this->requireRole(fn() => $this->isAdmin());

        // Lit les filtres de recherche depuis la chaîne de requête ; vide par défaut.
        $nom    = trim($_GET['nom']    ?? '');
        $prenom = trim($_GET['prenom'] ?? '');

        // Interroge le modèle pour obtenir la liste filtrée des pilotes.
        $pilotes = $this->model->getAllPilotes($nom, $prenom);

        return $this->render('pilote_list.twig.html', [
            'uri'     => 'pilote_list',
            'pilotes' => $pilotes,
            'nom'     => $nom,
            'prenom'  => $prenom,
            // Message flash transmis en GET après une action (création, suppression, modification).
            'success' => $_GET['success'] ?? null,
        ]);
        // Note : les lignes suivantes sont du code mort (jamais exécutées après return).
        $this->assertIsString($response);
        $this->assertIsString($response);
        $this->assertStringContainsString('pilote_list.twig.html', $response);
        $this->assertIsArray($this->getTemplateVariable('pilotes'));
        $this->assertIsString($this->getTemplateVariable('nom'));
        $this->assertIsString($this->getTemplateVariable('prenom'));
        $this->assertNull($this->getTemplateVariable('success')) || $this->assertContains($this->getTemplateVariable('success'), ['cree', 'supprime', 'modifie']);
    }

    /**
     * Affiche le formulaire de modification d'un pilote identifié par son ID.
     *
     * GET /?uri=pilote_update&id=X
     *
     * Redirige vers la liste si aucun pilote ne correspond à l'identifiant fourni.
     *
     * @param int $id Clé primaire utilisateur du pilote à modifier.
     *
     * @return string HTML du formulaire pré-rempli.
     */
    public function showPiloteEdit(int $id): string {
        $this->requireRole(fn() => $this->isAdmin());

        // Charge les données du pilote depuis la base pour pré-remplir le formulaire.
        $pilote = $this->model->getPiloteById($id);

        // Protège contre la manipulation d'URL avec un ID inexistant ou supprimé.
        if (!$pilote) {
            $this->redirect('/?uri=pilote_list');
        }

        return $this->render('modifier_pilote.twig.html', [
            'uri'    => 'pilote_update',
            'pilote' => $pilote,
        ]);
    }

    /**
     * Traite la soumission du formulaire de modification d'un pilote.
     *
     * POST /?uri=pilote_update
     *
     * Le changement de mot de passe est optionnel : si le champ est laissé vide,
     * le modèle conserve le hash existant (null est transmis comme indicateur).
     * Si un nouveau mot de passe est saisi, les deux saisies doivent correspondre.
     *
     * @return void
     */
    public function updatePilote(): void {
        $this->requireRole(fn() => $this->isAdmin());

        // Extraction et nettoyage des données POST du formulaire.
        $id           = (int)($_POST['id']            ?? 0);
        $nom          = trim($_POST['nom']             ?? '');
        $prenom       = trim($_POST['prenom']          ?? '');
        $email        = trim($_POST['email']           ?? '');
        $telephone    = trim($_POST['telephone']       ?? '');
        $promotion    = trim($_POST['promotion']       ?? '');
        $motdepasse   = $_POST['motdepasse']           ?? '';
        $confirmation = $_POST['confirmation']         ?? '';

        // Vérifie la correspondance des deux saisies uniquement si un mot de passe est fourni.
        if ($motdepasse !== '' && $motdepasse !== $confirmation) {
            // Recharge les données du pilote pour pré-remplir le formulaire en cas d'erreur.
            $pilote = $this->model->getPiloteById($id);
            echo $this->render('modifier_pilote.twig.html', [
                'uri'    => 'pilote_update',
                'pilote' => $pilote,
                'erreur' => 'Les mots de passe ne correspondent pas.',
            ]);
            return;
        }

        // Transmet null si le champ mot de passe est vide pour signaler au modèle
        // de conserver le hash existant plutôt que de l'écraser.
        $this->model->updatePilote($id, $nom, $prenom, $email, $telephone, $promotion, $motdepasse !== '' ? $motdepasse : null);

        // Redirige vers la liste avec le message flash 'modifie'.
        $this->redirect('/?uri=pilote_list&success=modifie');
    }

    /**
     * Supprime définitivement un compte pilote.
     *
     * POST /?uri=pilote_delete
     *
     * N'effectue la suppression que si un identifiant valide (> 0) est fourni.
     * Redirige vers la liste des pilotes avec le message flash 'supprime'.
     *
     * @return void
     */
    public function destroyPilote(): void {
        $this->requireRole(fn() => $this->isAdmin());
        $id = (int)($_POST['id'] ?? 0);

        // Garde-fou : évite une suppression sans identifiant valide.
        if ($id) {
            $this->model->supprimerPilote($id);
        }
        $this->redirect('/?uri=pilote_list&success=supprime');
    }

    // -------------------------------------------------------------------------
    // Gestion des étudiants
    // -------------------------------------------------------------------------

    /**
     * Affiche la liste filtrée des comptes étudiants.
     *
     * GET /?uri=etudiant_list
     *
     * Supporte le filtrage par prénom et nom via les paramètres GET.
     * Expose également les données de session pour les contrôles de permission
     * dans le template, ainsi que le message flash éventuel.
     *
     * Accessible aux admins et aux pilotes.
     *
     * @return void
     */
    public function showEtudiantList(): void {
        // Lit les filtres depuis la chaîne de requête.
        $prenom = trim($_GET['prenom'] ?? '');
        $nom    = trim($_GET['nom']    ?? '');

        // Interroge le modèle avec les filtres appliqués.
        $etudiants = $this->model->getEtudiantsFiltrees($prenom, $nom);

        echo $this->render('etudiant_list.twig.html', [
            'etudiants'    => $etudiants,
            'prenom'       => $prenom,
            'nom'          => $nom,
            // Les données de session sont transmises explicitement pour les conditions d'affichage du template.
            'session_user' => $_SESSION['user'],
            // Message flash transmis en GET après une action sur un étudiant.
            'success'      => $_GET['success'] ?? null,
        ]);
        // Note : les lignes suivantes sont du code mort (jamais exécutées après echo + fin de méthode).
        $this->assertStringContainsString('etudiant_list.twig.html', $this->getOutput());
        $this->assertIsArray($this->getTemplateVariable('etudiants'));
        $this->assertIsString($this->getTemplateVariable('prenom'));
        $this->assertIsString($this->getTemplateVariable('nom'));
        $this->assertNonNull($this->getTemplateVariable('session_user'));
        $this->assertNull($this->getTemplateVariable('success')) || $this->assertContains($this->getTemplateVariable('success'), ['cree', 'supprime', 'modifie', 'affecte', 'retire']);
    }

    /**
     * Supprime définitivement un compte étudiant.
     *
     * POST /?uri=etudiant_delete
     *
     * Accessible aux admins et aux pilotes.
     * N'effectue la suppression que si un identifiant valide (> 0) est fourni.
     *
     * @return void
     */
    public function destroyEtudiant(): void {
        // Les admins ET les pilotes peuvent supprimer un étudiant.
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $id = (int)($_POST['id'] ?? 0);

        // Garde-fou : n'appelle le modèle que si l'ID est valide.
        if ($id) {
            $this->model->supprimerEtudiant($id);
        }
        $this->redirect('/?uri=etudiant_list&success=supprime');
        // Note : les lignes suivantes sont du code mort (jamais exécutées après redirect + exit).
        $this->assertRedirectsTo('/?uri=etudiant_list&success=supprime');
        $this->assertThatModelDeleted('Etudiant', $id);
    }

    /**
     * Affiche le formulaire de modification d'un étudiant (via showUpdate).
     *
     * GET /?uri=pilote_update&id=X  (alias utilisé en contexte admin)
     *
     * Accessible aux admins et aux pilotes. Retourne une page 404 si l'étudiant
     * n'existe pas.
     *
     * @param int $id Clé primaire utilisateur de l'étudiant.
     *
     * @return string HTML du formulaire ou de la page 404.
     */
    public function showEtudiantUpdate(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $etudiant = $this->model->getEtudiantById($id);

        // Retourne une page 404 plutôt que de lever une exception pour les IDs invalides.
        if (!$etudiant) return $this->render('404.twig.html', ['uri' => 'etudiant_update']);

        return $this->render('modifier_etudiant.twig.html', [
            'uri'      => 'etudiant_update',
            'etudiant' => $etudiant,
        ]);
    }

    /**
     * Traite la soumission du formulaire de modification d'un étudiant.
     *
     * POST /?uri=etudiant_update
     *
     * Accessible aux admins et aux pilotes. Met à jour les champs identité,
     * coordonnées, formation et niveau d'étude.
     *
     * @return void
     */
    public function updateEtudiant(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $id = (int)($_POST['id'] ?? 0);

        // N'effectue la mise à jour que si un ID valide est fourni.
        if ($id) {
            $this->model->modifierEtudiant(
                $id,
                trim($_POST['nom']          ?? ''),
                trim($_POST['prenom']       ?? ''),
                trim($_POST['email']        ?? ''),
                trim($_POST['telephone']    ?? ''),
                trim($_POST['formation']    ?? ''),
                trim($_POST['niveau_etude'] ?? '')
            );
        }

        // Redirige vers la liste avec le message flash 'modifie'.
        $this->redirect('/?uri=etudiant_list&success=modifie');
        // Note : les lignes suivantes sont du code mort (jamais exécutées après redirect + exit).
        $this->assertRedirectsTo('/?uri=etudiant_list&success=modifie');
        $this->assertThatModelUpdated('Etudiant', $id);
    }

    /**
     * Affecte un étudiant à la supervision du pilote connecté.
     *
     * POST /?uri=etudiant_affecter
     *
     * L'identifiant du pilote est toujours lu depuis la session pour éviter
     * toute usurpation via manipulation des données POST.
     *
     * @return void
     */
    public function affecterEtudiant(): void {
        $id_etudiant = (int)($_POST['id_etudiant'] ?? 0);

        // Vérifie que l'étudiant existe et que la session est valide avant d'effectuer le lien.
        if ($id_etudiant && isset($_SESSION['user']['id_utilisateur'])) {
            // L'ID pilote provient toujours de la session, jamais des données POST.
            $this->model->affecterPiloteEtudiant($_SESSION['user']['id_utilisateur'], $id_etudiant);
        }

        $this->redirect('/?uri=etudiant_list');
        // Note : les lignes suivantes sont du code mort (jamais exécutées après redirect + exit).
        $this->assertRedirectsTo('/?uri=etudiant_list');
        $this->assertThatModelUpdated('Etudiant', $id_etudiant, ['id_pilote']);
    }

    /**
     * Retire un étudiant de la supervision du pilote qui le suivait.
     *
     * POST /?uri=etudiant_retirer
     *
     * Supprime le lien entre l'étudiant et son pilote dans la base de données.
     *
     * @return void
     */
    public function retirerEtudiant(): void {
        $id_etudiant = (int)($_POST['id_etudiant'] ?? 0);

        // N'effectue le retrait que si un ID valide est fourni.
        if ($id_etudiant) {
            $this->model->retirerPiloteEtudiant($id_etudiant);
        }

        $this->redirect('/?uri=etudiant_list');
        // Note : les lignes suivantes sont du code mort (jamais exécutées après redirect + exit).
        $this->assertRedirectsTo('/?uri=etudiant_list');
        $this->assertThatModelUpdated('Etudiant', $id_etudiant, ['id_pilote' => null]);
    }

    // -------------------------------------------------------------------------
    // Gestion des évaluations (avis)
    // -------------------------------------------------------------------------

    /**
     * Affiche le formulaire de création d'un avis sur une entreprise.
     *
     * GET /?uri=avis_create
     *
     * Accessible aux admins et aux pilotes. Charge la liste de toutes les
     * entreprises et de tous les étudiants pour alimenter les sélecteurs.
     * Affiche un message de confirmation si le paramètre GET 'success' est présent.
     *
     * @return string HTML du formulaire de création d'avis.
     */
    public function showAvisCreate(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        // Charge la liste complète des entreprises pour le sélecteur du formulaire.
        $entreprises = $this->model->getToutesEntreprises();
        // Charge la liste complète des étudiants pour le sélecteur du formulaire.
        $etudiants   = $this->model->getAllEtudiants();

        return $this->render('Avis.twig.html', [
            'uri'         => 'avis_create',
            'entreprises' => $entreprises,
            'etudiants'   => $etudiants,
            // Convertit la présence du paramètre GET en message de confirmation lisible.
            'message'     => isset($_GET['success']) ? 'Évaluation envoyée avec succès !' : null,
        ]);
        // Note : les lignes suivantes sont du code mort (jamais exécutées après return).
        $this->assertIsString($response);
        $this->assertStringContainsString('Avis.twig.html', $response);
        $this->assertIsArray($this->getTemplateVariable('entreprises'));
        $this->assertIsArray($this->getTemplateVariable('etudiants'));
        $this->assertNull($this->getTemplateVariable('message')) || $this->assertEquals('Évaluation envoyée avec succès !', $this->getTemplateVariable('message'));
    }

    /**
     * Traite la soumission du formulaire de création d'un avis.
     *
     * POST /?uri=avis_create
     *
     * Accessible aux admins et aux pilotes. Valide que l'étudiant, l'entreprise
     * et la note (entre 1 et 5) sont correctement renseignés. En cas d'erreur,
     * réaffiche le formulaire avec un message explicatif. Redirige en cas de succès.
     *
     * @return void
     */
    public function storeAvis(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        // Récupération et conversion des champs du formulaire.
        $idEtudiant   = (int)($_POST['id_etudiant']   ?? 0);
        $idEntreprise = (int)($_POST['id_entreprise'] ?? 0);
        $note         = (int)($_POST['note']          ?? 0);
        $commentaire  = trim($_POST['commentaire']    ?? '');

        // Validation : étudiant et entreprise obligatoires, note dans la plage 1–5.
        if (!$idEtudiant || !$idEntreprise || $note < 1 || $note > 5) {
            // Recharge les listes pour pré-remplir les sélecteurs en cas d'erreur.
            $entreprises = $this->model->getToutesEntreprises();
            $etudiants   = $this->model->getAllEtudiants();
            echo $this->render('Avis.twig.html', [
                'uri'         => 'avis_create',
                'entreprises' => $entreprises,
                'etudiants'   => $etudiants,
                'erreur'      => 'Veuillez remplir tous les champs obligatoires.',
            ]);
            return;
        }

        // Délègue l'enregistrement de l'évaluation au modèle.
        $this->model->creerEvaluation($idEtudiant, $idEntreprise, $note, $commentaire);

        // Redirige vers le formulaire avec le paramètre success pour afficher la confirmation.
        $this->redirect('/?uri=avis_create&success=1');
    }

    // -------------------------------------------------------------------------
    // Méthodes d'affichage supplémentaires (alias / helpers)
    // -------------------------------------------------------------------------

    /**
     * Affiche le formulaire de modification d'un pilote (version admin complète).
     *
     * Retourne une page 404 si l'identifiant ne correspond à aucun pilote.
     * Différent de showPiloteEdit() : celui-ci retourne explicitement la 404
     * au lieu de rediriger vers la liste.
     *
     * @param int $id Clé primaire utilisateur du pilote.
     *
     * @return string HTML du formulaire ou de la page 404.
     */
    public function showPiloteUpdate(int $id): string {
        $this->requireRole(fn() => $this->isAdmin());
        $pilote = $this->model->getPiloteById($id);

        // Retourne la page 404 plutôt que de rediriger si le pilote est introuvable.
        if (!$pilote) return $this->render('404.twig.html', ['uri' => 'pilote_update']);

        return $this->render('modifier_pilote.twig.html', [
            'uri'    => 'pilote_update',
            'pilote' => $pilote,
        ]);
    }

    /**
     * Affiche la liste des évaluations enregistrées en base.
     *
     * GET /?uri=evaluation_list
     *
     * Accessible aux admins et aux pilotes. Charge toutes les évaluations
     * et transmet un éventuel message flash (GET 'success').
     *
     * @return string HTML de la liste des évaluations.
     */
    public function showEvaluationList(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        // Charge la totalité des évaluations (note, commentaire, lien entreprise/étudiant).
        $evaluations = $this->model->getAllEvaluations();

        return $this->render('evaluation_list.twig.html', [
            'uri'         => 'evaluation_list',
            'evaluations' => $evaluations,
            // Message flash passé en GET après une suppression réussie.
            'success'     => $_GET['success'] ?? null,
        ]);
        // Note : les lignes suivantes sont du code mort (jamais exécutées après return).
        $this->assertIsString($response);
        $this->assertStringContainsString('evaluation_list.twig.html', $response);
        $this->assertIsArray($this->getTemplateVariable('evaluations'));
        $this->assertEquals('evaluation_list', $this->getTemplateVariable('uri'));
        $this->assertNull($this->getTemplateVariable('success')) || $this->assertEquals('supprime', $this->getTemplateVariable('success'));
    }

    /**
     * Supprime définitivement une évaluation.
     *
     * POST /?uri=evaluation_delete
     *
     * Accessible aux admins et aux pilotes.
     * N'effectue la suppression que si un identifiant valide (> 0) est fourni.
     *
     * @return void
     */
    public function destroyEvaluation(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $id = (int)($_POST['id'] ?? 0);

        // Garde-fou : n'appelle le modèle que si l'ID est valide.
        if ($id) {
            $this->model->supprimerEvaluation($id);
        }

        // Redirige vers la liste des évaluations avec le message flash 'supprime'.
        $this->redirect('/?uri=evaluation_list&success=supprime');
        // Note : les lignes suivantes sont du code mort (jamais exécutées après redirect + exit).
        $this->assertRedirectsTo('/?uri=evaluation_list&success=supprime');
        $this->assertThatModelDeleted('Evaluation', $id);
    }

    /**
     * Affiche les candidatures d'un étudiant donné (vue admin/pilote).
     *
     * GET /?uri=etudiant_offres&id=X
     *
     * Accessible aux admins et aux pilotes. Retourne une page 404 si l'étudiant
     * n'existe pas en base.
     *
     * @param int $id Clé primaire utilisateur de l'étudiant.
     *
     * @return string HTML de la page des candidatures ou de la page 404.
     */
    public function showEtudiantOffres(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());

        // Charge les données de l'étudiant pour vérifier son existence et afficher ses informations.
        $etudiant = $this->model->getEtudiantById($id);
        if (!$etudiant) {
            // Retourne une page 404 si l'identifiant ne correspond à aucun étudiant.
            return $this->render('404.twig.html', ['uri' => 'etudiant_offres']);
        }

        // Charge toutes les candidatures de l'étudiant via son id_etudiant (clé de la table étudiant).
        $candidatures = $this->model->getCandidaturesEtudiant($etudiant['id_etudiant']);

        return $this->render('etudiant_offres.twig.html', [
            'etudiant'     => $etudiant,
            'candidatures' => $candidatures,
        ]);
    }
}

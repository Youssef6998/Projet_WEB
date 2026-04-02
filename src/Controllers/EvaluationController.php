<?php

require_once __DIR__ . '/BaseController.php';

/**
 * Contrôleur de gestion des évaluations d'entreprises.
 *
 * Une évaluation lie une entreprise à un auteur (admin ou pilote) et contient :
 *  - Une note entière entre 1 et 5.
 *  - Un texte "attendus" décrivant les compétences ou attentes constatées.
 *
 * Deux niveaux de lecture sont disponibles :
 *  - Vue résumée (showList) : une ligne agrégée par entreprise.
 *  - Vue détaillée (showDetail) : toutes les évaluations d'une entreprise.
 *
 * Les évaluations sont des notes (1 à 5) et un commentaire libre ("attendus")
 * rédigés par un admin ou un pilote sur une entreprise partenaire.
 * Elles peuvent être consultées sous forme de résumé (une ligne par entreprise)
 * ou en détail (toutes les évaluations individuelles d'une entreprise),
 * et supprimées par les utilisateurs autorisés.
 *
 * Toutes les routes exigent le rôle admin ou pilote.
 */
class EvaluationController extends BaseController {

    private EvaluationModel  $evalModel;
    private EntrepriseModel  $entrepriseModel;

    /**
     * Injecte le moteur Twig et les modèles nécessaires.
     *
     * @param \Twig\Environment $twig             Moteur de rendu Twig.
     * @param EvaluationModel   $evalModel         Gère les requêtes CRUD des évaluations.
     * @param UserModel         $userModel         Injecté pour compatibilité ; non stocké.
     * @param EntrepriseModel   $entrepriseModel   Fournit la liste des entreprises pour le formulaire de création.
     */
    public function __construct(\Twig\Environment $twig, EvaluationModel $evalModel, UserModel $userModel, EntrepriseModel $entrepriseModel) {
        parent::__construct($twig);
        $this->evalModel       = $evalModel;
        $this->entrepriseModel = $entrepriseModel;
    }

    /**
     * Affiche le formulaire de création d'une évaluation avec la liste de toutes les entreprises.
     *
     * GET /?uri=avis_create
     *
     * @return string HTML du formulaire d'évaluation.
     */
    public function showAvisCreate(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('admin/Avis.twig.html', [
            'uri'         => 'avis_create',
            'entreprises' => $this->entrepriseModel->getToutesEntreprises(),
            // Convertit la présence du paramètre GET en booléen pour le template.
            'success'     => isset($_GET['success']),
        ]);
    }

    /**
     * Valide et enregistre une nouvelle évaluation d'entreprise.
     *
     * Règles de validation :
     *  - Une entreprise doit être sélectionnée (ID non nul).
     *  - La note doit être comprise entre 1 et 5 inclus.
     *  - Le champ "attendus" ne doit pas être vide.
     *
     * En cas d'échec de validation, le formulaire est réaffiché avec les valeurs
     * saisies pré-remplies (`old`) pour éviter toute perte de données.
     * L'auteur est toujours dérivé de la session, jamais des données POST.
     *
     * POST /?uri=avis_create
     *
     * @return void Redirige vers le formulaire avec un indicateur de succès, ou
     *              réaffiche le formulaire avec un message d'erreur.
     */
    public function storeAvis(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();

        $idEntreprise = (int)($_POST['id_entreprise'] ?? 0);
        $note         = (int)($_POST['note']          ?? 0);
        $attendus     = trim($_POST['attendus']        ?? '');
        // L'auteur est toujours pris depuis la session pour éviter toute usurpation.
        $idAuteur     = (int)$_SESSION['user']['id_utilisateur'];

        // Les trois champs sont obligatoires ; la note doit être dans la plage 1 à 5.
        if (!$idEntreprise || $note < 1 || $note > 5 || $attendus === '') {
            // Réaffiche le formulaire avec les valeurs POST précédentes pour éviter la perte de données.
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

    /**
     * Affiche la liste résumée des évaluations regroupées par entreprise.
     *
     * Chaque ligne contient des informations agrégées (note moyenne, nombre d'évaluations)
     * plutôt que les détails individuels de chaque évaluation.
     *
     * GET /?uri=evaluation_list
     *
     * @return string HTML de la liste résumée des évaluations.
     */
    public function showList(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('admin/evaluation_list.twig.html', [
            'uri'     => 'evaluation_list',
            'resumes' => $this->evalModel->getResumesParEntreprise(),
            'success' => $_GET['success'] ?? null,
        ]);
    }

    /**
     * Affiche toutes les évaluations individuelles d'une entreprise.
     *
     * Retourne une page 404 si aucune évaluation n'existe pour l'entreprise demandée.
     *
     * GET /?uri=evaluation_detail&id=X
     *
     * @param int $id Clé primaire de l'entreprise à détailler.
     * @return string HTML de la page de détail ou page 404.
     */
    public function showDetail(int $id): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $detail = $this->evalModel->getDetailEntreprise($id);

        // Aucune évaluation pour cette entreprise : traité comme non trouvé.
        if (!$detail) {
            return $this->render('404.twig.html', ['uri' => 'evaluation_detail']);
        }

        return $this->render('admin/evaluation_detail.twig.html', [
            'uri'    => 'evaluation_detail',
            'detail' => $detail,
        ]);
    }

    /**
     * Supprime définitivement une évaluation.
     *
     * L'appelant peut fournir un champ `redirect` dans le POST pour indiquer
     * la page vers laquelle revenir après la suppression (liste ou détail),
     * ce qui permet de déclencher l'action depuis différentes pages.
     *
     * POST /?uri=evaluation_delete
     *
     * @return void Redirige vers la page spécifiée (ou la liste par défaut) avec un indicateur de succès.
     */
    public function destroy(): void {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        // Permet à la page appelante de définir la destination de la redirection après suppression.
        $redirect = $_POST['redirect'] ?? '/?uri=evaluation_list';
        if ($id) {
            $this->evalModel->supprimerEvaluation($id);
        }
        $this->redirect($redirect . '&success=supprime');
    }
}

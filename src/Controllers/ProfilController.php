<?php

require_once __DIR__ . '/BaseController.php';

/**
 * Contrôleur du profil utilisateur.
 *
 * Permet à tout utilisateur connecté (quel que soit son rôle) de :
 *  - Consulter son profil enrichi avec les données les plus récentes de la base.
 *  - Modifier ses informations personnelles champ par champ.
 *  - Changer son mot de passe (vérification de l'ancien mot de passe obligatoire).
 *  - Supprimer définitivement son propre compte.
 *
 * Les données spécifiques au rôle (candidatures pour les étudiants, liste des
 * étudiants supervisés pour les pilotes) sont chargées conditionnellement.
 *
 * Gère toutes les actions liées au profil de l'utilisateur connecté :
 * affichage des données, modification de champs individuels (dont le mot
 * de passe) et suppression définitive du compte.
 *
 * Toutes les routes de ce contrôleur exigent au minimum une session active
 * (n'importe quel rôle), vérifiée via requireRole().
 */
class ProfilController extends BaseController {

    private StageModel $stageModel;
    private UserModel  $userModel;

    /**
     * Injecte le moteur Twig et les deux modèles nécessaires.
     *
     * @param \Twig\Environment $twig        Moteur de rendu Twig.
     * @param StageModel        $stageModel  Récupère les candidatures et la wishlist des étudiants.
     * @param UserModel         $userModel   Récupère et met à jour les données du compte utilisateur.
     */
    public function __construct(\Twig\Environment $twig, StageModel $stageModel, UserModel $userModel) {
        parent::__construct($twig);
        $this->stageModel = $stageModel;
        $this->userModel  = $userModel;
    }

    /**
     * Affiche la page de profil de l'utilisateur connecté.
     *
     * Les données chargées varient selon le rôle :
     *  - Étudiant : ses candidatures et sa wishlist.
     *  - Pilote : la liste des étudiants qu'il supervise.
     *
     * Le snapshot de session est enrichi par une requête complète en base pour
     * que la page reflète toujours les valeurs les plus récentes.
     *
     * GET /?uri=profil
     *
     * @return string HTML de la page de profil.
     */
    public function index(): string {
        $this->requireRole(fn() => $this->isConnecte());

        $user                 = $_SESSION['user'];
        $candidatures         = [];
        $wishlist             = [];
        $etudiants_supervises = [];

        // Charge les données de stage uniquement si l'utilisateur est étudiant avec un enregistrement etudiant lié.
        if ($user['role'] === 'etudiant' && !empty($user['id_etudiant'])) {
            $candidatures = $this->stageModel->getCandidaturesEtudiant($user['id_etudiant']);
            $wishlist     = $this->stageModel->getWishlistEtudiant($user['id_etudiant']);
        }

        // Charge la liste des étudiants supervisés uniquement pour les pilotes.
        if ($user['role'] === 'pilote') {
            $etudiants_supervises = $this->userModel->getEtudiantsSupervisesParPilote($user['id_utilisateur']);
        }

        // Préfère l'enregistrement complet de la base ; repli sur le snapshot de session si indisponible.
        $userComplet = $this->userModel->getUtilisateurComplet($user['id_utilisateur']) ?? $user;

        return $this->render('profil.twig.html', [
            'uri'                  => 'profil',
            'user'                 => $userComplet,
            'candidatures'         => $candidatures,
            'wishlist'             => $wishlist,
            'etudiants_supervises' => $etudiants_supervises,
            // Messages flash transmis en paramètre GET par les redirections précédentes.
            'success'              => $_GET['success'] ?? null,
            'erreur'               => $_GET['erreur']  ?? null,
        ]);
    }

    /**
     * Traite la modification d'un champ du profil soumise par l'utilisateur.
     *
     * Trois chemins de mise à jour distincts selon le champ :
     *  - 'mot_de_passe' : vérifie la correspondance des deux saisies puis délègue
     *    à updateMotDePasse() qui vérifie également l'ancien mot de passe.
     *  - 'promotion' : délègue à la méthode dédiée updatePromotion()
     *    (stockée dans une table de relation séparée, d'où la méthode spécifique).
     *  - Tout autre champ : mise à jour générique ; en cas de succès, le snapshot
     *    de session est aussi mis à jour pour que l'en-tête reflète le changement immédiatement.
     *
     * POST /?uri=profil_update
     *
     * @return void Redirige vers le profil avec un indicateur de succès ou d'erreur.
     */
    public function update(): void {
        $this->requireRole(fn() => $this->isConnecte());
        $this->verifyCsrf();
        $id    = (int) $_SESSION['user']['id_utilisateur'];
        $champ = trim($_POST['champ']  ?? '');
        $val   = trim($_POST['valeur'] ?? '');

        if ($champ === 'mot_de_passe') {
            $ancien  = $_POST['ancien_mdp']  ?? '';
            $nouveau = $_POST['nouveau_mdp'] ?? '';
            $confirm = $_POST['confirm_mdp'] ?? '';

            // Les deux saisies du nouveau mot de passe doivent correspondre avant toute écriture en base.
            if ($nouveau !== $confirm) {
                $this->redirect('/?uri=profil&erreur=Les+mots+de+passe+ne+correspondent+pas');
                return;
            }
            // Le modèle vérifie l'ancien mot de passe et hache le nouveau.
            if (!$this->userModel->updateMotDePasse($id, $ancien, $nouveau)) {
                $this->redirect('/?uri=profil&erreur=Ancien+mot+de+passe+incorrect');
                return;
            }
        } elseif ($champ === 'promotion') {
            // La promotion est stockée dans une table de relation, nécessitant une méthode dédiée.
            $this->userModel->updatePromotion($id, $val);
        } else {
            if (!$this->userModel->updateUtilisateur($id, $champ, $val)) {
                $this->redirect('/?uri=profil&erreur=Modification+impossible');
                return;
            }
            // Répercute le changement dans la session pour que la barre de navigation l'affiche immédiatement.
            $_SESSION['user'][$champ] = $val;
        }

        $this->redirect('/?uri=profil&success=1');
    }

    /**
     * Supprime définitivement le compte de l'utilisateur connecté.
     *
     * Après la suppression en base, la session est détruite pour déconnecter
     * l'utilisateur, puis il est redirigé vers la page de recherche publique.
     *
     * POST /?uri=profil_delete
     *
     * @return void Redirige vers la liste publique des stages après suppression.
     */
    public function delete(): void {
        $this->requireRole(fn() => $this->isConnecte());
        $this->verifyCsrf();
        $id = (int) $_SESSION['user']['id_utilisateur'];
        $this->userModel->supprimerUtilisateur($id);
        // Détruit la session pour déconnecter complètement l'utilisateur après la suppression du compte.
        session_destroy();
        $this->redirect('/?uri=cherche-stage');
    }
}

<?php

require_once __DIR__ . '/../Database.php';

/**
 * Modèle de gestion des utilisateurs.
 *
 * Couche d'accès aux données pour toutes les opérations liées aux utilisateurs :
 * authentification, inscription, gestion du profil et des trois rôles concrets
 * (etudiant, pilote, admin).
 *
 * Chaque rôle est stocké dans deux tables :
 *   - `utilisateur` — champs d'identité partagés (nom, prenom, email, …)
 *   - `etudiant` / `pilote` — champs spécifiques au rôle, liés par id_utilisateur
 *
 * La suppression logique est utilisée partout : les comptes sont anonymisés et
 * marqués actif = 0 plutôt que physiquement supprimés, afin que l'historique
 * des candidatures et des favoris reste intact pour les statistiques.
 */
class UserModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Vérifie si une adresse e-mail est déjà enregistrée.
     *
     * @param string $email L'adresse à rechercher.
     * @return bool Vrai si l'adresse existe dans la table utilisateur.
     */
    public function emailExiste(string $email): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Inscrit un nouveau compte étudiant.
     *
     * Crée une ligne dans `utilisateur` (role = 'etudiant') et une ligne
     * correspondante dans `etudiant` liée par l'id_utilisateur auto-généré.
     * Le mot de passe en clair est haché avec PASSWORD_DEFAULT avant le stockage.
     *
     * @param string $nom        Nom de famille.
     * @param string $prenom     Prénom.
     * @param string $email      Adresse e-mail unique.
     * @param string $motDePasse Mot de passe en clair (sera haché).
     * @param string $telephone  Numéro de téléphone.
     * @return bool Vrai en cas de succès, faux si l'INSERT a échoué.
     */
    public function inscrireUtilisateur(string $nom, string $prenom, string $email, string $motDePasse, string $telephone): bool {
        // Hache le mot de passe avant persistance — ne jamais stocker en clair.
        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role)
             VALUES (:nom, :prenom, :email, :mdp, :tel, 'etudiant')"
        );
        $ok = $stmt->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':email'  => $email,
            ':mdp'    => $hash,
            ':tel'    => $telephone,
        ]);
        if ($ok) {
            // Crée la ligne etudiant associée en utilisant la PK fraîchement générée.
            $id = (int) $this->db->lastInsertId();
            $this->db->prepare("INSERT INTO etudiant (id_utilisateur) VALUES (:id)")
                     ->execute([':id' => $id]);
        }
        return $ok;
    }

    /**
     * Authentifie un utilisateur par e-mail et mot de passe.
     *
     * Seuls les comptes actif = 1 sont pris en compte. Le hash stocké est vérifié
     * avec password_verify() ; en cas de succès, le hash est retiré du tableau
     * retourné. Pour les étudiants, id_etudiant est ajouté afin que la session
     * puisse référencer directement la ligne etudiant.
     *
     * @param string $email      L'adresse e-mail soumise.
     * @param string $motDePasse Le mot de passe en clair soumis.
     * @return array|null Tableau de données utilisateur en cas de succès, null si les identifiants sont invalides.
     */
    public function connecterUtilisateur(string $email, string $motDePasse): ?array {
        $stmt = $this->db->prepare(
            "SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role
             FROM utilisateur WHERE email = :email AND actif = 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        // Rejette si l'utilisateur est introuvable ou si le hash ne correspond pas.
        if (!$user || !password_verify($motDePasse, $user['mot_de_passe'])) {
            return null;
        }
        // Retire le hash — il ne doit pas circuler au-delà de la couche modèle.
        unset($user['mot_de_passe']);

        // Ajoute la PK étudiant pour que les contrôleurs puissent la joindre sans requête supplémentaire.
        if ($user['role'] === 'etudiant') {
            $s = $this->db->prepare("SELECT id_etudiant FROM etudiant WHERE id_utilisateur = :id");
            $s->execute([':id' => $user['id_utilisateur']]);
            $row = $s->fetch();
            $user['id_etudiant'] = $row ? (int) $row['id_etudiant'] : null;
        }
        return $user;
    }

    /**
     * Crée un compte administrateur.
     *
     * Les admins n'ont pas de table complémentaire — seule une ligne dans `utilisateur`
     * avec role = 'admin'. Le mot de passe est haché avant le stockage.
     *
     * @param string $nom        Nom de famille.
     * @param string $prenom     Prénom.
     * @param string $email      Adresse e-mail unique.
     * @param string $motDePasse Mot de passe en clair (sera haché).
     * @param string $telephone  Numéro de téléphone.
     * @return bool Vrai en cas de succès.
     */
    public function creerAdmin(string $nom, string $prenom, string $email, string $motDePasse, string $telephone): bool {
        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role)
             VALUES (:nom, :prenom, :email, :mdp, :tel, 'admin')"
        );
        return $stmt->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':email'  => $email,
            ':mdp'    => $hash,
            ':tel'    => $telephone,
        ]);
    }

    /**
     * Crée un compte pilote avec sa ligne `pilote` complémentaire.
     *
     * Insère dans `utilisateur` (role = 'pilote') puis utilise la nouvelle PK
     * pour créer la ligne `pilote` associée, qui stocke le champ promotion.
     *
     * @param string $nom        Nom de famille.
     * @param string $prenom     Prénom.
     * @param string $email      Adresse e-mail unique.
     * @param string $motDePasse Mot de passe en clair (sera haché).
     * @param string $telephone  Numéro de téléphone.
     * @param string $promotion  Libellé de la promotion supervisée par ce pilote.
     * @return bool Vrai en cas de succès.
     */
    public function creerPilote(string $nom, string $prenom, string $email, string $motDePasse, string $telephone, string $promotion): bool {
        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role)
             VALUES (:nom, :prenom, :email, :mdp, :tel, 'pilote')"
        );
        $ok = $stmt->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':email'  => $email,
            ':mdp'    => $hash,
            ':tel'    => $telephone,
        ]);
        if ($ok) {
            // Lie la nouvelle ligne utilisateur à une ligne pilote avec sa promotion.
            $id = (int) $this->db->lastInsertId();
            $this->db->prepare("INSERT INTO pilote (id_utilisateur, promotion) VALUES (:id, :promo)")
                     ->execute([':id' => $id, ':promo' => $promotion]);
        }
        return $ok;
    }

    /**
     * Retourne tous les pilotes actifs, avec filtre optionnel par nom.
     *
     * Joint `utilisateur` → `pilote` (INNER, chaque pilote doit avoir une ligne)
     * et LEFT JOIN `etudiant` pour compter les étudiants supervisés sans
     * exclure les pilotes qui n'en ont pas encore.
     *
     * @param string $nom    Filtre optionnel sur le nom (correspondance partielle).
     * @param string $prenom Filtre optionnel sur le prénom (correspondance partielle).
     * @return array Liste de lignes pilote, chacune incluant nb_etudiants.
     */
    public function getAllPilotes(string $nom = '', string $prenom = ''): array {
        $conditions = ["u.actif = 1"];
        $params     = [];
        if ($nom !== '') {
            $conditions[]   = "u.nom LIKE :nom";
            $params[':nom'] = '%' . $nom . '%';
        }
        if ($prenom !== '') {
            $conditions[]      = "u.prenom LIKE :prenom";
            $params[':prenom'] = '%' . $prenom . '%';
        }
        $where = implode(' AND ', $conditions);
        $stmt  = $this->db->prepare(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    p.id_pilote, p.centre, p.promotion,
                    COUNT(e.id_etudiant) AS nb_etudiants
             FROM utilisateur u
             JOIN pilote p ON u.id_utilisateur = p.id_utilisateur
             LEFT JOIN etudiant e ON p.id_pilote = e.id_pilote
             WHERE $where
             GROUP BY u.id_utilisateur, p.id_pilote
             ORDER BY u.nom, u.prenom"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retourne une tranche paginée de pilotes actifs, avec filtres optionnels.
     *
     * Suit le modèle en deux passes : une requête COUNT détermine les totaux, puis
     * une requête LIMIT/OFFSET récupère la tranche. La page demandée est bornée
     * à [1, totalPages]. LIMIT et OFFSET sont liés explicitement en entiers.
     *
     * @param int    $page    Numéro de page (base 1, borné automatiquement).
     * @param int    $perPage Nombre de lignes par page.
     * @param string $nom     Filtre optionnel sur le nom (correspondance partielle).
     * @param string $prenom  Filtre optionnel sur le prénom (correspondance partielle).
     * @return array {
     *     pilotes: array,
     *     currentPage: int,
     *     totalPages: int,
     *     totalPilotes: int
     * }
     */
    public function getPaginatedPilotes(int $page = 1, int $perPage = 6, string $nom = '', string $prenom = ''): array {
        $page       = max(1, $page);
        $conditions = ["u.actif = 1"];
        $params     = [];
        if ($nom !== '') {
            $conditions[]   = "u.nom LIKE :nom";
            $params[':nom'] = '%' . $nom . '%';
        }
        if ($prenom !== '') {
            $conditions[]      = "u.prenom LIKE :prenom";
            $params[':prenom'] = '%' . $prenom . '%';
        }
        $where = implode(' AND ', $conditions);

        // Première passe : compte les pilotes correspondants pour calculer les métadonnées de pagination.
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM utilisateur u
             JOIN pilote p ON u.id_utilisateur = p.id_utilisateur
             WHERE $where"
        );
        $countStmt->execute($params);
        $totalPilotes = (int) $countStmt->fetchColumn();
        $totalPages   = max(1, (int) ceil($totalPilotes / $perPage));
        // Borne la page pour que les valeurs hors plage atterrissent toujours sur la dernière page.
        $page         = min($page, $totalPages);
        $offset       = ($page - 1) * $perPage;

        // Deuxième passe : récupère la page réelle. Les paramètres du filtre sont liés en premier,
        // puis LIMIT/OFFSET sont liés séparément en PDO::PARAM_INT car PDO les placerait sinon
        // entre guillemets, ce qui invaliderait le LIMIT MySQL.
        $stmt = $this->db->prepare(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    p.id_pilote, p.centre, p.promotion,
                    COUNT(e.id_etudiant) AS nb_etudiants
             FROM utilisateur u
             JOIN pilote p ON u.id_utilisateur = p.id_utilisateur
             LEFT JOIN etudiant e ON p.id_pilote = e.id_pilote
             WHERE $where
             GROUP BY u.id_utilisateur, p.id_pilote
             ORDER BY u.nom, u.prenom
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'pilotes'      => $stmt->fetchAll(),
            'currentPage'  => $page,
            'totalPages'   => $totalPages,
            'totalPilotes' => $totalPilotes,
        ];
    }

    /**
     * Retourne tous les étudiants actifs avec le nom de leur pilote affecté.
     *
     * La chaîne de LEFT JOIN résout le nom du pilote :
     *   etudiant → pilote (nullable) → utilisateur (ligne d'identité du pilote).
     * Les étudiants sans pilote affecté sont tout de même inclus ; pilote_nom
     * sera NULL pour eux.
     *
     * @return array Tous les étudiants actifs, triés par nom puis prénom.
     */
    public function getAllEtudiants(): array {
        $stmt = $this->db->query(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    e.id_etudiant, e.formation, e.niveau_etude,
                    CONCAT(up.prenom, ' ', up.nom) AS pilote_nom
             FROM utilisateur u
             JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
             LEFT JOIN pilote p ON e.id_pilote = p.id_pilote
             LEFT JOIN utilisateur up ON p.id_utilisateur = up.id_utilisateur
             WHERE u.actif = 1
             ORDER BY u.nom, u.prenom"
        );
        return $stmt->fetchAll();
    }

    /**
     * Récupère le profil complet d'un pilote (champs utilisateur + pilote).
     *
     * @param int $idUtilisateur La PK utilisateur du pilote.
     * @return array|false La ligne pilote, ou false si introuvable ou inactif.
     */
    public function getPiloteById(int $idUtilisateur): array|false {
        $stmt = $this->db->prepare(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    p.id_pilote, p.centre, p.promotion
             FROM utilisateur u
             JOIN pilote p ON u.id_utilisateur = p.id_utilisateur
             WHERE u.id_utilisateur = :id AND u.actif = 1"
        );
        $stmt->execute([':id' => $idUtilisateur]);
        return $stmt->fetch();
    }

    /**
     * Met à jour les champs du profil d'un pilote dans les deux tables utilisateur et pilote.
     *
     * Si un nouveau mot de passe est fourni, il est haché avant le stockage ; si null
     * est transmis, le hash existant est conservé (deux requêtes UPDATE distinctes
     * couvrent les deux cas).
     *
     * @param int         $idUtilisateur La PK utilisateur du pilote.
     * @param string      $nom           Nouveau nom.
     * @param string      $prenom        Nouveau prénom.
     * @param string      $email         Nouvel e-mail.
     * @param string      $telephone     Nouveau téléphone.
     * @param string      $promotion     Nouveau libellé de promotion.
     * @param string|null $motDePasse    Nouveau mot de passe en clair, ou null pour conserver l'actuel.
     * @return bool Vrai si la ligne pilote a été mise à jour avec succès.
     */
    public function updatePilote(int $idUtilisateur, string $nom, string $prenom, string $email, string $telephone, string $promotion, ?string $motDePasse): bool {
        if ($motDePasse !== null) {
            // Un nouveau mot de passe a été fourni — le hacher et l'inclure dans l'UPDATE.
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $this->db->prepare(
                "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel, mot_de_passe=:mdp
                 WHERE id_utilisateur=:id"
            )->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $telephone, ':mdp' => $hash, ':id' => $idUtilisateur]);
        } else {
            // Pas de changement de mot de passe — omettre la colonne mot_de_passe de l'UPDATE.
            $this->db->prepare(
                "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel
                 WHERE id_utilisateur=:id"
            )->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $telephone, ':id' => $idUtilisateur]);
        }
        return $this->db->prepare(
            "UPDATE pilote SET promotion=:promo WHERE id_utilisateur=:id"
        )->execute([':promo' => $promotion, ':id' => $idUtilisateur]);
    }

    /**
     * Suppression logique d'un compte pilote.
     *
     * Avant l'anonymisation, tous les étudiants supervisés par ce pilote sont
     * désaffectés (id_pilote mis à NULL) pour préserver l'intégrité des clés étrangères.
     * Les données personnelles sont ensuite écrasées par des valeurs factices et actif est mis à 0.
     *
     * @param int $idUtilisateur La PK utilisateur du pilote à supprimer.
     * @return bool Vrai si la mise à jour d'anonymisation a réussi.
     */
    public function supprimerPilote(int $idUtilisateur): bool {
        // Désaffecter tous les étudiants supervisés par ce pilote.
        $this->db->prepare(
            "UPDATE etudiant e
             JOIN pilote p ON e.id_pilote = p.id_pilote
             SET e.id_pilote = NULL
             WHERE p.id_utilisateur = :id"
        )->execute([':id' => $idUtilisateur]);
        // Anonymiser les données personnelles et désactiver le compte.
        return $this->db->prepare(
            "UPDATE utilisateur
             SET nom = '[Supprimé]', prenom = '[Supprimé]',
                 email = CONCAT('supprime_', id_utilisateur, '@supprime.local'),
                 telephone = '', mot_de_passe = 'DELETED', actif = 0
             WHERE id_utilisateur = :id"
        )->execute([':id' => $idUtilisateur]);
    }

    /**
     * Suppression logique d'un compte étudiant.
     *
     * Le lien de supervision est effacé en premier. Les données personnelles sont
     * ensuite anonymisées et actif est mis à 0. Les candidatures et favoris sont
     * intentionnellement conservés pour que les statistiques agrégées restent exactes.
     *
     * @param int $idUtilisateur La PK utilisateur de l'étudiant à supprimer.
     * @return bool Vrai si la mise à jour d'anonymisation a réussi.
     */
    public function supprimerEtudiant(int $idUtilisateur): bool {
        // Désaffecter du pilote (le lien de supervision n'a plus de sens).
        $this->db->prepare(
            "UPDATE etudiant SET id_pilote = NULL WHERE id_utilisateur = :id"
        )->execute([':id' => $idUtilisateur]);
        // Anonymiser les données personnelles et désactiver le compte.
        // Les candidatures et favoris sont conservés pour l'intégrité des statistiques.
        return $this->db->prepare(
            "UPDATE utilisateur
             SET nom = '[Supprimé]', prenom = '[Supprimé]',
                 email = CONCAT('supprime_', id_utilisateur, '@supprime.local'),
                 telephone = '', mot_de_passe = 'DELETED', actif = 0
             WHERE id_utilisateur = :id"
        )->execute([':id' => $idUtilisateur]);
    }

    /**
     * Récupère le profil complet d'un étudiant (champs utilisateur + etudiant).
     *
     * @param int $idUtilisateur La PK utilisateur de l'étudiant.
     * @return array|false La ligne étudiant, ou false si introuvable ou inactif.
     */
    public function getEtudiantById(int $idUtilisateur): array|false {
        $stmt = $this->db->prepare(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    e.id_etudiant, e.formation, e.niveau_etude
             FROM utilisateur u
             JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
             WHERE u.id_utilisateur = :id AND u.actif = 1"
        );
        $stmt->execute([':id' => $idUtilisateur]);
        return $stmt->fetch();
    }

    /**
     * Met à jour le profil d'un étudiant dans les tables utilisateur et etudiant.
     *
     * @param int    $idUtilisateur La PK utilisateur de l'étudiant.
     * @param string $nom           Nouveau nom.
     * @param string $prenom        Nouveau prénom.
     * @param string $email         Nouvel e-mail.
     * @param string $telephone     Nouveau téléphone.
     * @param string $formation     Nouveau nom de formation / programme.
     * @param string $niveauEtude   Nouveau niveau d'études.
     * @return bool Vrai si la mise à jour de la table etudiant a réussi.
     */
    public function modifierEtudiant(int $idUtilisateur, string $nom, string $prenom, string $email, string $telephone, string $formation, string $niveauEtude): bool {
        $this->db->prepare(
            "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel WHERE id_utilisateur=:id"
        )->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $telephone, ':id' => $idUtilisateur]);
        return $this->db->prepare(
            "UPDATE etudiant SET formation=:formation, niveau_etude=:niveau WHERE id_utilisateur=:id"
        )->execute([':formation' => $formation, ':niveau' => $niveauEtude, ':id' => $idUtilisateur]);
    }

    /**
     * Affecte un étudiant à un pilote.
     *
     * Utilise une sous-requête pour résoudre id_pilote depuis la PK utilisateur du pilote,
     * car l'appelant ne détient que l'identifiant au niveau utilisateur.
     *
     * @param int $idPiloteUtilisateur    PK utilisateur du pilote cible.
     * @param int $idEtudiantUtilisateur  PK utilisateur de l'étudiant à affecter.
     * @return bool Vrai en cas de succès.
     */
    public function affecterEtudiantAuPilote(int $idPiloteUtilisateur, int $idEtudiantUtilisateur): bool {
        return $this->db->prepare(
            "UPDATE etudiant SET id_pilote = (SELECT id_pilote FROM pilote WHERE id_utilisateur = :id_pilote)
             WHERE id_utilisateur = :id_etudiant"
        )->execute([':id_pilote' => $idPiloteUtilisateur, ':id_etudiant' => $idEtudiantUtilisateur]);
    }

    /**
     * Retourne tous les étudiants actifs correspondant aux filtres optionnels.
     *
     * Résout le nom du pilote via deux LEFT JOIN :
     *   etudiant → pilote → utilisateur (ligne d'identité du pilote, aliasée `up`).
     * Les étudiants sans pilote affecté sont toujours retournés ; pilote_nom est NULL.
     *
     * @param string $prenom Filtre optionnel sur le prénom (correspondance partielle).
     * @param string $nom    Filtre optionnel sur le nom (correspondance partielle).
     * @return array Liste filtrée de lignes étudiant.
     */
    public function getEtudiantsFiltrees(string $prenom = '', string $nom = ''): array {
        $conditions = ["u.actif = 1"];
        $params     = [];
        if ($prenom !== '') {
            $conditions[]      = "u.prenom LIKE :prenom";
            $params[':prenom'] = '%' . $prenom . '%';
        }
        if ($nom !== '') {
            $conditions[]   = "u.nom LIKE :nom";
            $params[':nom'] = '%' . $nom . '%';
        }
        $where = implode(' AND ', $conditions);
        $stmt  = $this->db->prepare(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    e.id_etudiant, e.formation, e.niveau_etude,
                    CONCAT(up.prenom, ' ', up.nom) AS pilote_nom
             FROM utilisateur u
             JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
             LEFT JOIN pilote p ON e.id_pilote = p.id_pilote
             LEFT JOIN utilisateur up ON p.id_utilisateur = up.id_utilisateur
             WHERE $where
             ORDER BY u.nom, u.prenom"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retourne une tranche paginée d'étudiants actifs, avec filtres optionnels.
     *
     * Suit le même modèle en deux passes que getPaginatedPilotes :
     * une requête COUNT détermine les totaux, puis une requête LIMIT/OFFSET récupère
     * la tranche. LIMIT et OFFSET sont liés explicitement en entiers.
     *
     * @param int    $page    Numéro de page (base 1, borné automatiquement).
     * @param int    $perPage Nombre de lignes par page.
     * @param string $prenom  Filtre optionnel sur le prénom (correspondance partielle).
     * @param string $nom     Filtre optionnel sur le nom (correspondance partielle).
     * @return array {
     *     etudiants: array,
     *     currentPage: int,
     *     totalPages: int,
     *     totalEtudiants: int
     * }
     */
    public function getPaginatedEtudiants(int $page = 1, int $perPage = 6, string $prenom = '', string $nom = ''): array {
        $page       = max(1, $page);
        $conditions = ["u.actif = 1"];
        $params     = [];
        if ($prenom !== '') {
            $conditions[]      = "u.prenom LIKE :prenom";
            $params[':prenom'] = '%' . $prenom . '%';
        }
        if ($nom !== '') {
            $conditions[]   = "u.nom LIKE :nom";
            $params[':nom'] = '%' . $nom . '%';
        }
        $where = implode(' AND ', $conditions);

        // Compte le nombre total d'étudiants correspondants pour dériver totalPages.
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM utilisateur u
             JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
             WHERE $where"
        );
        $countStmt->execute($params);
        $totalEtudiants = (int) $countStmt->fetchColumn();
        $totalPages     = max(1, (int) ceil($totalEtudiants / $perPage));
        $page           = min($page, $totalPages);
        $offset         = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    e.id_etudiant, e.formation, e.niveau_etude,
                    CONCAT(up.prenom, ' ', up.nom) AS pilote_nom
             FROM utilisateur u
             JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
             LEFT JOIN pilote p ON e.id_pilote = p.id_pilote
             LEFT JOIN utilisateur up ON p.id_utilisateur = up.id_utilisateur
             WHERE $where
             ORDER BY u.nom, u.prenom
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        // Liaison en entier obligatoire : PDO placerait sinon ces valeurs entre guillemets.
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'etudiants'      => $stmt->fetchAll(),
            'currentPage'    => $page,
            'totalPages'     => $totalPages,
            'totalEtudiants' => $totalEtudiants,
        ];
    }

    /**
     * Récupère le profil complet de n'importe quel utilisateur actif, avec les extras spécifiques au rôle.
     *
     * Après le chargement de la ligne utilisateur de base, une seconde requête ajoute
     * les PK et champs spécifiques au rôle (id_etudiant pour les étudiants ; id_pilote
     * et promotion pour les pilotes) afin que l'appelant obtienne un tableau unifié.
     *
     * @param int $id La PK utilisateur.
     * @return array|null Données complètes de l'utilisateur, ou null si introuvable ou inactif.
     */
    public function getUtilisateurComplet(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT id_utilisateur, nom, prenom, email, telephone, role
             FROM utilisateur WHERE id_utilisateur = :id AND actif = 1"
        );
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) return null;

        // Ajoute la PK étudiant pour que la session / la vue puisse la référencer directement.
        if ($user['role'] === 'etudiant') {
            $s = $this->db->prepare("SELECT id_etudiant FROM etudiant WHERE id_utilisateur = :id");
            $s->execute([':id' => $id]);
            $row = $s->fetch();
            $user['id_etudiant'] = $row ? (int) $row['id_etudiant'] : null;
        }
        // Ajoute les champs spécifiques au pilote nécessaires dans les vues de profil et de gestion.
        if ($user['role'] === 'pilote') {
            $s = $this->db->prepare("SELECT id_pilote, promotion FROM pilote WHERE id_utilisateur = :id");
            $s->execute([':id' => $id]);
            $row = $s->fetch();
            $user['id_pilote']  = $row ? (int) $row['id_pilote'] : null;
            $user['promotion']  = $row['promotion'] ?? null;
        }
        return $user;
    }

    /**
     * Met à jour un seul champ autorisé d'une ligne utilisateur.
     *
     * La liste blanche de champs autorisés empêche les attaques par injection de nom
     * de colonne lorsque le nom de colonne provient d'une entrée utilisateur.
     * Pour les changements d'e-mail, une vérification d'unicité est effectuée au préalable ;
     * la mise à jour est abandonnée si l'adresse est déjà utilisée par un autre compte.
     *
     * @param int    $id     La PK utilisateur.
     * @param string $champ  Nom de la colonne à mettre à jour (doit figurer dans la liste blanche).
     * @param string $valeur Nouvelle valeur.
     * @return bool Faux si le champ n'est pas autorisé ou si l'e-mail est déjà pris.
     */
    public function updateUtilisateur(int $id, string $champ, string $valeur): bool {
        // La liste blanche empêche l'injection de noms de colonnes arbitraires dans la requête.
        $champsAutorises = ['nom', 'prenom', 'email', 'telephone'];
        if (!in_array($champ, $champsAutorises, true)) return false;

        // Vérification d'unicité de l'e-mail : rejette si un autre compte utilise déjà cette adresse.
        if ($champ === 'email') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = :email AND id_utilisateur != :id");
            $stmt->execute([':email' => $valeur, ':id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) return false;
        }

        $stmt = $this->db->prepare("UPDATE utilisateur SET $champ = :val WHERE id_utilisateur = :id");
        return $stmt->execute([':val' => $valeur, ':id' => $id]);
    }

    /**
     * Modifie le mot de passe d'un utilisateur après vérification de l'actuel.
     *
     * Le hash existant est récupéré et validé avec password_verify() avant
     * l'écriture du nouveau hash, empêchant tout changement sans connaissance
     * du mot de passe actuel.
     *
     * @param int    $id          La PK utilisateur.
     * @param string $ancienMdp   Le mot de passe actuel en clair à vérifier.
     * @param string $nouveauMdp  Le nouveau mot de passe en clair (sera haché).
     * @return bool Faux si le mot de passe actuel est incorrect ou si l'utilisateur est introuvable.
     */
    public function updateMotDePasse(int $id, string $ancienMdp, string $nouveauMdp): bool {
        $stmt = $this->db->prepare("SELECT mot_de_passe FROM utilisateur WHERE id_utilisateur = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        // Abandonne si l'utilisateur n'existe pas ou si le mot de passe actuel fourni est incorrect.
        if (!$row || !password_verify($ancienMdp, $row['mot_de_passe'])) return false;

        $hash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
        return $this->db->prepare("UPDATE utilisateur SET mot_de_passe = :mdp WHERE id_utilisateur = :id")
                        ->execute([':mdp' => $hash, ':id' => $id]);
    }

    /**
     * Met à jour le libellé de promotion d'un pilote (modification de profil en libre-service).
     *
     * @param int    $idUtilisateur La PK utilisateur du pilote.
     * @param string $promotion     Nouveau libellé de promotion.
     * @return bool Vrai en cas de succès.
     */
    public function updatePromotion(int $idUtilisateur, string $promotion): bool {
        return $this->db->prepare("UPDATE pilote SET promotion = :promo WHERE id_utilisateur = :id")
                        ->execute([':promo' => $promotion, ':id' => $idUtilisateur]);
    }

    /**
     * Suppression logique d'un compte utilisateur (tous rôles, côté admin).
     *
     * Gère les deux cas avant l'anonymisation :
     *   - Si le compte est un étudiant, le lien de supervision est effacé.
     *   - Si c'est un pilote, tous les étudiants supervisés sont désaffectés.
     * Évite ainsi les références orphelines après la suppression logique.
     *
     * @param int $id La PK utilisateur du compte à supprimer.
     * @return bool Vrai si la mise à jour d'anonymisation a réussi.
     */
    public function supprimerUtilisateur(int $id): bool {
        // Désaffecter si étudiant (efface le lien pilote → étudiant).
        $this->db->prepare(
            "UPDATE etudiant SET id_pilote = NULL WHERE id_utilisateur = :id"
        )->execute([':id' => $id]);
        // Désaffecter les étudiants supervisés si c'est un pilote.
        $this->db->prepare(
            "UPDATE etudiant e
             JOIN pilote p ON e.id_pilote = p.id_pilote
             SET e.id_pilote = NULL
             WHERE p.id_utilisateur = :id"
        )->execute([':id' => $id]);
        // Anonymiser les données personnelles et désactiver le compte.
        return $this->db->prepare(
            "UPDATE utilisateur
             SET nom = '[Supprimé]', prenom = '[Supprimé]',
                 email = CONCAT('supprime_', id_utilisateur, '@supprime.local'),
                 telephone = '', mot_de_passe = 'DELETED', actif = 0
             WHERE id_utilisateur = :id"
        )->execute([':id' => $id]);
    }

    /**
     * Retire un étudiant de la supervision de son pilote actuel (supprime le lien).
     *
     * @param int $idEtudiantUtilisateur La PK utilisateur de l'étudiant.
     * @return bool Vrai en cas de succès.
     */
    public function retirerEtudiantPilote(int $idEtudiantUtilisateur): bool {
        return $this->db->prepare(
            "UPDATE etudiant SET id_pilote = NULL WHERE id_utilisateur = :id"
        )->execute([':id' => $idEtudiantUtilisateur]);
    }

    /**
     * Retourne tous les étudiants actuellement supervisés par un pilote donné.
     *
     * Joint etudiant → pilote → utilisateur pour résoudre le pilote depuis
     * la PK utilisateur transmise par l'appelant.
     *
     * @param int $idUtilisateurPilote La PK utilisateur du pilote.
     * @return array Liste des étudiants supervisés (id, nom, prenom, email).
     */
    public function getEtudiantsSupervisesParPilote(int $idUtilisateurPilote): array {
        $stmt = $this->db->prepare(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email
             FROM utilisateur u
             JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
             JOIN pilote p ON e.id_pilote = p.id_pilote
             WHERE p.id_utilisateur = :id_pilote
             ORDER BY u.nom, u.prenom"
        );
        $stmt->execute([':id_pilote' => $idUtilisateurPilote]);
        return $stmt->fetchAll();
    }
}

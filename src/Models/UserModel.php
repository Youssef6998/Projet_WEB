<?php

require_once __DIR__ . '/../Database.php';

class UserModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function emailExiste(string $email): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function inscrireUtilisateur(string $nom, string $prenom, string $email, string $motDePasse, string $telephone): bool {
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
            $id = (int) $this->db->lastInsertId();
            $this->db->prepare("INSERT INTO etudiant (id_utilisateur) VALUES (:id)")
                     ->execute([':id' => $id]);
        }
        return $ok;
    }

    public function connecterUtilisateur(string $email, string $motDePasse): ?array {
        $stmt = $this->db->prepare(
            "SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role
             FROM utilisateur WHERE email = :email AND actif = 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($motDePasse, $user['mot_de_passe'])) {
            return null;
        }
        unset($user['mot_de_passe']);

        if ($user['role'] === 'etudiant') {
            $s = $this->db->prepare("SELECT id_etudiant FROM etudiant WHERE id_utilisateur = :id");
            $s->execute([':id' => $user['id_utilisateur']]);
            $row = $s->fetch();
            $user['id_etudiant'] = $row ? (int) $row['id_etudiant'] : null;
        }
        return $user;
    }

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
            $id = (int) $this->db->lastInsertId();
            $this->db->prepare("INSERT INTO pilote (id_utilisateur, promotion) VALUES (:id, :promo)")
                     ->execute([':id' => $id, ':promo' => $promotion]);
        }
        return $ok;
    }

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

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM utilisateur u
             JOIN pilote p ON u.id_utilisateur = p.id_utilisateur
             WHERE $where"
        );
        $countStmt->execute($params);
        $totalPilotes = (int) $countStmt->fetchColumn();
        $totalPages   = max(1, (int) ceil($totalPilotes / $perPage));
        $page         = min($page, $totalPages);
        $offset       = ($page - 1) * $perPage;

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

    public function updatePilote(int $idUtilisateur, string $nom, string $prenom, string $email, string $telephone, string $promotion, ?string $motDePasse): bool {
        if ($motDePasse !== null) {
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $this->db->prepare(
                "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel, mot_de_passe=:mdp
                 WHERE id_utilisateur=:id"
            )->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $telephone, ':mdp' => $hash, ':id' => $idUtilisateur]);
        } else {
            $this->db->prepare(
                "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel
                 WHERE id_utilisateur=:id"
            )->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $telephone, ':id' => $idUtilisateur]);
        }
        return $this->db->prepare(
            "UPDATE pilote SET promotion=:promo WHERE id_utilisateur=:id"
        )->execute([':promo' => $promotion, ':id' => $idUtilisateur]);
    }

    public function supprimerPilote(int $idUtilisateur): bool {
        // Désaffecter tous les étudiants supervisés par ce pilote
        $this->db->prepare(
            "UPDATE etudiant e
             JOIN pilote p ON e.id_pilote = p.id_pilote
             SET e.id_pilote = NULL
             WHERE p.id_utilisateur = :id"
        )->execute([':id' => $idUtilisateur]);
        // Anonymiser les données personnelles et désactiver le compte
        return $this->db->prepare(
            "UPDATE utilisateur
             SET nom = '[Supprimé]', prenom = '[Supprimé]',
                 email = CONCAT('supprime_', id_utilisateur, '@supprime.local'),
                 telephone = '', mot_de_passe = 'DELETED', actif = 0
             WHERE id_utilisateur = :id"
        )->execute([':id' => $idUtilisateur]);
    }

    public function supprimerEtudiant(int $idUtilisateur): bool {
        // Désaffecter du pilote (le lien de supervision n'a plus de sens)
        $this->db->prepare(
            "UPDATE etudiant SET id_pilote = NULL WHERE id_utilisateur = :id"
        )->execute([':id' => $idUtilisateur]);
        // Anonymiser les données personnelles et désactiver le compte
        // Les candidatures et favoris sont conservés pour l'intégrité des statistiques
        return $this->db->prepare(
            "UPDATE utilisateur
             SET nom = '[Supprimé]', prenom = '[Supprimé]',
                 email = CONCAT('supprime_', id_utilisateur, '@supprime.local'),
                 telephone = '', mot_de_passe = 'DELETED', actif = 0
             WHERE id_utilisateur = :id"
        )->execute([':id' => $idUtilisateur]);
    }

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

    public function modifierEtudiant(int $idUtilisateur, string $nom, string $prenom, string $email, string $telephone, string $formation, string $niveauEtude): bool {
        $this->db->prepare(
            "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel WHERE id_utilisateur=:id"
        )->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $telephone, ':id' => $idUtilisateur]);
        return $this->db->prepare(
            "UPDATE etudiant SET formation=:formation, niveau_etude=:niveau WHERE id_utilisateur=:id"
        )->execute([':formation' => $formation, ':niveau' => $niveauEtude, ':id' => $idUtilisateur]);
    }

    public function affecterEtudiantAuPilote(int $idPiloteUtilisateur, int $idEtudiantUtilisateur): bool {
        return $this->db->prepare(
            "UPDATE etudiant SET id_pilote = (SELECT id_pilote FROM pilote WHERE id_utilisateur = :id_pilote)
             WHERE id_utilisateur = :id_etudiant"
        )->execute([':id_pilote' => $idPiloteUtilisateur, ':id_etudiant' => $idEtudiantUtilisateur]);
    }

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

    public function getUtilisateurComplet(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT id_utilisateur, nom, prenom, email, telephone, role
             FROM utilisateur WHERE id_utilisateur = :id AND actif = 1"
        );
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) return null;

        if ($user['role'] === 'etudiant') {
            $s = $this->db->prepare("SELECT id_etudiant FROM etudiant WHERE id_utilisateur = :id");
            $s->execute([':id' => $id]);
            $row = $s->fetch();
            $user['id_etudiant'] = $row ? (int) $row['id_etudiant'] : null;
        }
        if ($user['role'] === 'pilote') {
            $s = $this->db->prepare("SELECT id_pilote, promotion FROM pilote WHERE id_utilisateur = :id");
            $s->execute([':id' => $id]);
            $row = $s->fetch();
            $user['id_pilote']  = $row ? (int) $row['id_pilote'] : null;
            $user['promotion']  = $row['promotion'] ?? null;
        }
        return $user;
    }

    public function updateUtilisateur(int $id, string $champ, string $valeur): bool {
        $champsAutorises = ['nom', 'prenom', 'email', 'telephone'];
        if (!in_array($champ, $champsAutorises, true)) return false;

        if ($champ === 'email') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = :email AND id_utilisateur != :id");
            $stmt->execute([':email' => $valeur, ':id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) return false;
        }

        $stmt = $this->db->prepare("UPDATE utilisateur SET $champ = :val WHERE id_utilisateur = :id");
        return $stmt->execute([':val' => $valeur, ':id' => $id]);
    }

    public function updateMotDePasse(int $id, string $ancienMdp, string $nouveauMdp): bool {
        $stmt = $this->db->prepare("SELECT mot_de_passe FROM utilisateur WHERE id_utilisateur = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($ancienMdp, $row['mot_de_passe'])) return false;

        $hash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
        return $this->db->prepare("UPDATE utilisateur SET mot_de_passe = :mdp WHERE id_utilisateur = :id")
                        ->execute([':mdp' => $hash, ':id' => $id]);
    }

    public function updatePromotion(int $idUtilisateur, string $promotion): bool {
        return $this->db->prepare("UPDATE pilote SET promotion = :promo WHERE id_utilisateur = :id")
                        ->execute([':promo' => $promotion, ':id' => $idUtilisateur]);
    }

    public function supprimerUtilisateur(int $id): bool {
        // Désaffecter si étudiant (pilote → étudiant)
        $this->db->prepare(
            "UPDATE etudiant SET id_pilote = NULL WHERE id_utilisateur = :id"
        )->execute([':id' => $id]);
        // Désaffecter les étudiants supervisés si pilote
        $this->db->prepare(
            "UPDATE etudiant e
             JOIN pilote p ON e.id_pilote = p.id_pilote
             SET e.id_pilote = NULL
             WHERE p.id_utilisateur = :id"
        )->execute([':id' => $id]);
        // Anonymiser les données personnelles et désactiver le compte
        return $this->db->prepare(
            "UPDATE utilisateur
             SET nom = '[Supprimé]', prenom = '[Supprimé]',
                 email = CONCAT('supprime_', id_utilisateur, '@supprime.local'),
                 telephone = '', mot_de_passe = 'DELETED', actif = 0
             WHERE id_utilisateur = :id"
        )->execute([':id' => $id]);
    }

    public function retirerEtudiantPilote(int $idEtudiantUtilisateur): bool {
        return $this->db->prepare(
            "UPDATE etudiant SET id_pilote = NULL WHERE id_utilisateur = :id"
        )->execute([':id' => $idEtudiantUtilisateur]);
    }

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

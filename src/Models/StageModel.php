<?php

require_once __DIR__ . '/../Database.php';

class StageModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getPaginatedStages(int $page = 1, int $perPage = 6, string $domaine = ''): array {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        // Filtre optionnel par domaine (titre ou compétence)
        $whereClause = '';
        $params      = [];

        if ($domaine !== '') {
            $whereClause = "WHERE o.titre LIKE :domaine
                               OR EXISTS (
                                   SELECT 1 FROM offre_competence oc2
                                   JOIN competence c2 ON oc2.id_competence = c2.id_competence
                                   WHERE oc2.id_offre = o.id_offre
                                     AND c2.libelle LIKE :domaine2
                               )";
            $params[':domaine']  = '%' . $domaine . '%';
            $params[':domaine2'] = '%' . $domaine . '%';
        }

        // Compte total pour la pagination
        $countSql    = "SELECT COUNT(*) FROM offre o $whereClause";
        $countStmt   = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $totalStages = (int) $countStmt->fetchColumn();
        $totalPages  = max(1, (int) ceil($totalStages / $perPage));
        $page        = min($page, $totalPages);
        $offset      = ($page - 1) * $perPage;

        // Offres paginées avec le nom de l'entreprise
        $sql = "SELECT o.id_offre,
                       o.titre          AS title,
                       o.description,
                       o.duree          AS duration,
                       o.date_publication AS date,
                       e.nom            AS company
                FROM offre o
                JOIN entreprise e ON o.id_entreprise = e.id_entreprise
                $whereClause
                ORDER BY o.date_publication DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Ajoute les compétences (tags) pour chaque offre
        $stages = [];
        foreach ($rows as $row) {
            $tagStmt = $this->db->prepare(
                "SELECT c.libelle
                 FROM offre_competence oc
                 JOIN competence c ON oc.id_competence = c.id_competence
                 WHERE oc.id_offre = :id"
            );
            $tagStmt->execute([':id' => $row['id_offre']]);
            $tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

            $stages[] = [
                'id'          => $row['id_offre'],
                'company'     => $row['company'],
                'title'       => $row['title'],
                'description' => $row['description'],
                'tags'        => $tags,
                'location'    => '',
                'duration'    => $row['duration'],
                'date'        => $row['date'] ? (new DateTime($row['date']))->format('d/m/Y') : '',
            ];
        }

        return [
            'stages'      => $stages,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'totalStages' => $totalStages,
        ];
    }

    public function getPaginatedEntreprises(int $page = 1, int $perPage = 6, string $nom = ''): array {
        $page   = max(1, $page);

        $whereClause = '';
        $params      = [];

        if ($nom !== '') {
            $whereClause    = 'WHERE e.nom LIKE :nom';
            $params[':nom'] = '%' . $nom . '%';
        }

        $countSql  = "SELECT COUNT(*) FROM entreprise e $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        $sql = "SELECT e.id_entreprise, e.nom, e.description, e.email_contact, e.telephone_contact,
                       COALESCE(s.nb_offres, 0) AS nb_offres
                FROM entreprise e
                LEFT JOIN (SELECT id_entreprise, COUNT(*) AS nb_offres FROM offre GROUP BY id_entreprise) s
                       ON s.id_entreprise = e.id_entreprise
                $whereClause
                ORDER BY e.nom ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $entreprises = $stmt->fetchAll();

        return [
            'entreprises' => $entreprises,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
        ];
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
            "SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role FROM utilisateur WHERE email = :email AND actif = 1"
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

    public function getOffreById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT o.id_offre, o.titre, o.description, o.base_remuneration,
                    o.date_offre, o.duree, o.nb_places, o.date_publication,
                    e.id_entreprise, e.nom AS company, e.description AS company_desc,
                    e.email_contact, e.telephone_contact
             FROM offre o
             JOIN entreprise e ON o.id_entreprise = e.id_entreprise
             WHERE o.id_offre = :id"
        );
        $stmt->execute([':id' => $id]);
        $offre = $stmt->fetch();
        if (!$offre) return null;

        $tagStmt = $this->db->prepare(
            "SELECT c.libelle FROM offre_competence oc
             JOIN competence c ON oc.id_competence = c.id_competence
             WHERE oc.id_offre = :id"
        );
        $tagStmt->execute([':id' => $id]);
        $offre['tags'] = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
        return $offre;
    }

    public function isInWishlist(int $idEtudiant, int $idOffre): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM wishlist WHERE id_etudiant = :e AND id_offre = :o"
        );
        $stmt->execute([':e' => $idEtudiant, ':o' => $idOffre]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function toggleWishlist(int $idEtudiant, int $idOffre): bool {
        if ($this->isInWishlist($idEtudiant, $idOffre)) {
            $this->db->prepare("DELETE FROM wishlist WHERE id_etudiant = :e AND id_offre = :o")
                     ->execute([':e' => $idEtudiant, ':o' => $idOffre]);
            return false;
        } else {
            $this->db->prepare("INSERT INTO wishlist (id_etudiant, id_offre) VALUES (:e, :o)")
                     ->execute([':e' => $idEtudiant, ':o' => $idOffre]);
            return true;
        }
    }

    public function dejaCandidate(int $idEtudiant, int $idOffre): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM candidature WHERE id_etudiant = :e AND id_offre = :o"
        );
        $stmt->execute([':e' => $idEtudiant, ':o' => $idOffre]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function candidater(int $idEtudiant, int $idOffre, string $lettreMot, string $cvPath): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO candidature (id_etudiant, id_offre, lettre_motivation, cv_path)
             VALUES (:e, :o, :lm, :cv)"
        );
        return $stmt->execute([
            ':e'  => $idEtudiant,
            ':o'  => $idOffre,
            ':lm' => $lettreMot,
            ':cv' => $cvPath,
        ]);
    }

    public function getWishlistEtudiant(int $idEtudiant): array {
        $stmt = $this->db->prepare(
            "SELECT o.id_offre, o.titre, o.duree, o.date_publication, e.nom AS company, w.date_ajout
             FROM wishlist w
             JOIN offre o ON w.id_offre = o.id_offre
             JOIN entreprise e ON o.id_entreprise = e.id_entreprise
             WHERE w.id_etudiant = :id
             ORDER BY w.date_ajout DESC"
        );
        $stmt->execute([':id' => $idEtudiant]);
        return $stmt->fetchAll();
    }

    public function getCandidaturesEtudiant(int $idEtudiant): array {
        $stmt = $this->db->prepare(
            "SELECT o.titre, e.nom AS entreprise, o.duree,
                    c.date_candidature, c.statut
             FROM candidature c
             JOIN offre o ON c.id_offre = o.id_offre
             JOIN entreprise e ON o.id_entreprise = e.id_entreprise
             WHERE c.id_etudiant = :id
             ORDER BY c.date_candidature DESC"
        );
        $stmt->execute([':id' => $idEtudiant]);
        return $stmt->fetchAll();
    }
}

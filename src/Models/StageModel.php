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
        return $stmt->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':email'  => $email,
            ':mdp'    => $hash,
            ':tel'    => $telephone,
        ]);
    }
}

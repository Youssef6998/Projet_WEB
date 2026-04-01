<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/../Database.php';

class StageModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getPaginatedStages(int $page = 1, int $perPage = 6, string $domaine = '', string $ville = '', string $duree = '', string $competence = '', string $tri = 'date_desc'): array {
        $page  = max(1, $page);
        $params = [];
        $whereConditions = [];

        if (!empty($domaine)) {
            $whereConditions[]   = "o.domaine = :domaine";
            $params[':domaine']  = $domaine;
        }
        if (!empty($ville)) {
            $whereConditions[] = "e.ville LIKE :ville";
            $params[':ville']  = '%' . $ville . '%';
        }
        if (!empty($duree)) {
            $whereConditions[] = "o.duree LIKE :duree";
            $params[':duree']  = '%' . $duree . '%';
        }
        if (!empty($competence)) {
            $whereConditions[]      = "EXISTS (
                SELECT 1 FROM offre_competence oc
                JOIN competence c ON oc.id_competence = c.id_competence
                WHERE oc.id_offre = o.id_offre AND c.libelle LIKE :competence
            )";
            $params[':competence'] = '%' . $competence . '%';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $orderBy = match($tri) {
            'date_asc'   => 'o.date_publication ASC',
            'alpha_asc'  => 'o.titre ASC',
            'alpha_desc' => 'o.titre DESC',
            default      => 'o.date_publication DESC',
        };

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM offre o
             JOIN entreprise e ON o.id_entreprise = e.id_entreprise
             $whereClause"
        );
        $countStmt->execute($params);
        $totalStages = (int) $countStmt->fetchColumn();
        $totalPages  = max(1, (int) ceil($totalStages / $perPage));
        $page        = min($page, $totalPages);
        $offset      = ($page - 1) * $perPage;

        $sql = "SELECT o.id_offre,
                       o.titre          AS title,
                       o.description,
                       o.duree          AS duration,
                       o.date_publication AS date,
                       e.nom            AS company,
                       e.ville          AS ville
                FROM offre o
                JOIN entreprise e ON o.id_entreprise = e.id_entreprise
                $whereClause
                ORDER BY $orderBy
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Récupère les compétences via JOIN pour éviter le N+1
        $ids = array_column($rows, 'id_offre');
        $tagsByOffre = [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $tagStmt = $this->db->prepare(
                "SELECT oc.id_offre, c.libelle
                 FROM offre_competence oc
                 JOIN competence c ON oc.id_competence = c.id_competence
                 WHERE oc.id_offre IN ($placeholders)"
            );
            $tagStmt->execute($ids);
            foreach ($tagStmt->fetchAll() as $t) {
                $tagsByOffre[$t['id_offre']][] = $t['libelle'];
            }
        }

        $stages = [];
        foreach ($rows as $row) {
            $stages[] = [
                'id'          => $row['id_offre'],
                'company'     => $row['company'],
                'title'       => $row['title'],
                'description' => $row['description'],
                'tags'        => $tagsByOffre[$row['id_offre']] ?? [],
                'ville'       => $row['ville'] ?? '',
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

    public function getOffreById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT o.id_offre, o.titre, o.description, o.base_remuneration,
                    o.date_offre, o.duree, o.nb_places, o.date_publication,
                    e.id_entreprise, e.nom AS company, e.description AS company_desc,
                    e.ville, e.email_contact, e.telephone_contact
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
        }
        $this->db->prepare("INSERT INTO wishlist (id_etudiant, id_offre) VALUES (:e, :o)")
                 ->execute([':e' => $idEtudiant, ':o' => $idOffre]);
        return true;
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
            "SELECT c.id_etudiant, c.id_offre, c.statut, c.date_candidature,
                    c.lettre_motivation, c.cv_path,
                    o.titre, e.nom AS company,
                    o.duree, o.base_remuneration,
                    e.ville
             FROM candidature c
             JOIN offre o ON c.id_offre = o.id_offre
             JOIN entreprise e ON o.id_entreprise = e.id_entreprise
             WHERE c.id_etudiant = :id
             ORDER BY c.date_candidature DESC"
        );
        $stmt->execute([':id' => $idEtudiant]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getToutesEntreprises(): array {
        return $this->db->query(
            "SELECT id_entreprise, nom FROM entreprise ORDER BY nom ASC"
        )->fetchAll();
    }

    public function creerOffre(int $idEntreprise, string $titre, ?string $domaine, ?string $description, ?float $baseRemuneration, string $dateOffre, ?string $duree): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO offre (id_entreprise, titre, domaine, description, base_remuneration, date_offre, duree)
             VALUES (:ie, :titre, :domaine, :desc, :remun, :date, :duree)"
        );
        return $stmt->execute([
            ':ie'     => $idEntreprise,
            ':titre'  => $titre,
            ':domaine'=> $domaine,
            ':desc'   => $description,
            ':remun'  => $baseRemuneration,
            ':date'   => $dateOffre,
            ':duree'  => $duree,
        ]);
    }

    public function modifierOffre(int $idOffre, int $idEntreprise, string $titre, string $description, string $duree, string $dateOffre, ?float $baseRemuneration): bool {
        $stmt = $this->db->prepare(
            "UPDATE offre SET id_entreprise=:ie, titre=:titre, description=:desc, duree=:duree, date_offre=:date, base_remuneration=:remun
             WHERE id_offre=:id"
        );
        return $stmt->execute([
            ':ie'    => $idEntreprise,
            ':titre' => $titre,
            ':desc'  => $description,
            ':duree' => $duree,
            ':date'  => $dateOffre,
            ':remun' => $baseRemuneration,
            ':id'    => $idOffre,
        ]);
    }

    public function supprimerCandidaturesOffre(int $idOffre): bool {
        return $this->db->prepare("DELETE FROM candidature WHERE id_offre = :id")
                        ->execute([':id' => $idOffre]);
    }

    public function supprimerOffre(int $idOffre): bool {
        return $this->db->prepare("DELETE FROM offre WHERE id_offre = :id")
                        ->execute([':id' => $idOffre]);
    }

    public function getStatsOffres(): array {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(DISTINCT o.id_offre)       AS nb_offres,
                COUNT(DISTINCT c.id_etudiant)    AS nb_candidatures,
                COUNT(DISTINCT w.id_etudiant)    AS nb_wishlist,
                COALESCE(AVG(o.base_remuneration), 0) AS remuneration_moyenne
             FROM offre o
             LEFT JOIN candidature c ON o.id_offre = c.id_offre
             LEFT JOIN wishlist w    ON o.id_offre = w.id_offre"
        );
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getStatsParOffre(): array {
        $stmt = $this->db->prepare(
            "SELECT o.id_offre, o.titre, e.nom AS entreprise,
                    COUNT(DISTINCT c.id_etudiant) AS nb_candidatures,
                    COUNT(DISTINCT w.id_etudiant) AS nb_wishlist
             FROM offre o
             JOIN entreprise e ON o.id_entreprise = e.id_entreprise
             LEFT JOIN candidature c ON o.id_offre = c.id_offre
             LEFT JOIN wishlist w    ON o.id_offre = w.id_offre
             GROUP BY o.id_offre, o.titre, e.nom
             ORDER BY nb_candidatures DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

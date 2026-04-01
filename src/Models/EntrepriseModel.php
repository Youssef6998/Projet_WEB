<?php

require_once __DIR__ . '/../Database.php';

class EntrepriseModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getPaginatedEntreprises(int $page = 1, int $perPage = 6, string $nom = ''): array {
        $page = max(1, $page);

        $whereClause = '';
        $params      = [];

        if ($nom !== '') {
            $whereClause    = 'WHERE e.nom LIKE :nom';
            $params[':nom'] = '%' . $nom . '%';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM entreprise e $whereClause");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        $sql = "SELECT e.id_entreprise, e.nom, e.description, e.email_contact, e.telephone_contact,
                       e.ville, e.adresse, COALESCE(s.nb_offres, 0) AS nb_offres
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

        return [
            'entreprises' => $stmt->fetchAll(),
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
        ];
    }

    public function getToutesEntreprises(): array {
        $stmt = $this->db->prepare("SELECT id_entreprise, nom FROM entreprise ORDER BY nom ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getEntrepriseById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT e.id_entreprise, e.nom, e.description, e.email_contact, e.telephone_contact,
                    e.ville, e.adresse, COALESCE(s.nb_offres, 0) AS nb_offres
             FROM entreprise e
             LEFT JOIN (SELECT id_entreprise, COUNT(*) AS nb_offres FROM offre GROUP BY id_entreprise) s
                    ON s.id_entreprise = e.id_entreprise
             WHERE e.id_entreprise = :id"
        );
        $stmt->execute([':id' => $id]);
        $entreprise = $stmt->fetch();
        if (!$entreprise) return null;

        $offresStmt = $this->db->prepare(
            "SELECT o.id_offre, o.titre, o.duree, o.base_remuneration, o.date_offre, o.nb_places
             FROM offre o
             WHERE o.id_entreprise = :id
             ORDER BY o.date_publication DESC"
        );
        $offresStmt->execute([':id' => $id]);
        $entreprise['offres'] = $offresStmt->fetchAll();
        return $entreprise;
    }

    public function creerEntreprise(string $nom, string $ville, string $adresse, string $email, string $telephone, string $description): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO entreprise (nom, ville, adresse, email_contact, telephone_contact, description)
             VALUES (:nom, :ville, :adresse, :email, :tel, :desc)"
        );
        return $stmt->execute([
            ':nom'     => $nom,
            ':ville'   => $ville,
            ':adresse' => $adresse,
            ':email'   => $email,
            ':tel'     => $telephone,
            ':desc'    => $description,
        ]);
    }

    public function modifierEntreprise(int $id, string $nom, string $ville, string $adresse, string $email, string $telephone, string $description): bool {
        $stmt = $this->db->prepare(
            "UPDATE entreprise
             SET nom = :nom, ville = :ville, adresse = :adresse,
                 email_contact = :email, telephone_contact = :tel, description = :desc
             WHERE id_entreprise = :id"
        );
        return $stmt->execute([
            ':id'      => $id,
            ':nom'     => $nom,
            ':ville'   => $ville,
            ':adresse' => $adresse,
            ':email'   => $email,
            ':tel'     => $telephone,
            ':desc'    => $description,
        ]);
    }

    public function supprimerEntreprise(int $id): bool {
        // Supprimer wishlist et candidatures liées aux offres de cette entreprise
        $this->db->prepare(
            "DELETE w FROM wishlist w
             JOIN offre o ON w.id_offre = o.id_offre
             WHERE o.id_entreprise = :id"
        )->execute([':id' => $id]);
        $this->db->prepare(
            "DELETE c FROM candidature c
             JOIN offre o ON c.id_offre = o.id_offre
             WHERE o.id_entreprise = :id"
        )->execute([':id' => $id]);
        $this->db->prepare(
            "DELETE oc FROM offre_competence oc
             JOIN offre o ON oc.id_offre = o.id_offre
             WHERE o.id_entreprise = :id"
        )->execute([':id' => $id]);
        $this->db->prepare("DELETE FROM offre WHERE id_entreprise = :id")->execute([':id' => $id]);
        return $this->db->prepare("DELETE FROM entreprise WHERE id_entreprise = :id")
                        ->execute([':id' => $id]);
    }
}

<?php

class StatsModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getNbOffresTotal(): int {
        return (int) $this->db->query('SELECT COUNT(*) FROM offre')->fetchColumn();
    }

    public function getMoyenneCandidaturesParOffre(): float {
        $val = $this->db->query('
            SELECT COALESCE(AVG(nb_cand), 0)
            FROM (SELECT COUNT(*) AS nb_cand FROM candidature GROUP BY id_offre) sub
        ')->fetchColumn();
        return round((float) $val, 1);
    }

    public function getRepartitionParDuree(): array {
        $rows = $this->db->query('
            SELECT
                COALESCE(NULLIF(TRIM(duree), \'\'), \'Non précisée\') AS duree,
                COUNT(*) AS nb
            FROM offre
            GROUP BY duree
            ORDER BY nb DESC
            LIMIT 7
        ')->fetchAll(PDO::FETCH_ASSOC);

        $max = max(array_column($rows, 'nb') ?: [1]);
        foreach ($rows as &$row) {
            $row['pct'] = (int) round($row['nb'] / $max * 100);
        }
        return $rows;
    }

    public function getTopWishlist(int $limit = 5): array {
        $stmt = $this->db->prepare('
            SELECT o.titre, e.nom AS entreprise, COUNT(*) AS nb_favoris
            FROM wishlist w
            JOIN offre o ON w.id_offre = o.id_offre
            JOIN entreprise e ON o.id_entreprise = e.id_entreprise
            GROUP BY w.id_offre, o.titre, e.nom
            ORDER BY nb_favoris DESC
            LIMIT :lim
        ');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

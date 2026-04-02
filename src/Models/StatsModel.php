<?php

/**
 * Modèle de statistiques agrégées.
 *
 * Fournit des indicateurs globaux sur les offres de stage, les candidatures
 * et les favoris. Chaque méthode exécute une requête SQL en lecture seule
 * et retourne une valeur scalaire ou un tableau prêt à être consommé par
 * StatsController et le template Twig associé.
 * Aucune écriture n'est effectuée dans ce modèle.
 */
class StatsModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Retourne le nombre total d'offres de stage enregistrées.
     *
     * @return int Nombre de lignes dans la table `offre`.
     */
    public function getNbOffresTotal(): int {
        return (int) $this->db->query('SELECT COUNT(*) FROM offre')->fetchColumn();
    }

    /**
     * Retourne la moyenne de candidatures par offre, arrondie à une décimale.
     *
     * La sous-requête compte d'abord les candidatures par offre ; la requête
     * externe calcule ensuite la moyenne de ces totaux par offre.
     * COALESCE garantit le retour de 0 lorsqu'aucune candidature n'existe
     * (AVG d'un ensemble vide retournerait NULL sans cela).
     *
     * @return float Moyenne du nombre de candidatures par offre.
     */
    public function getMoyenneCandidaturesParOffre(): float {
        $val = $this->db->query('
            SELECT COALESCE(AVG(nb_cand), 0)
            FROM (SELECT COUNT(*) AS nb_cand FROM candidature GROUP BY id_offre) sub
        ')->fetchColumn();
        return round((float) $val, 1);
    }

    /**
     * Retourne les 7 durées de stage les plus représentées, avec un pourcentage relatif pour les graphiques.
     *
     * Les valeurs de durée vides ou composées uniquement d'espaces sont normalisées
     * en "Non précisée" via NULLIF(TRIM(...), '') afin d'être regroupées.
     * Le champ `pct` est calculé en PHP comme pourcentage par rapport à la durée
     * la plus fréquente (la barre la plus haute vaut toujours 100 %).
     *
     * @return array<int, array{duree: string, nb: int, pct: int}>
     */
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

        // Utilise le nombre maximal comme référence à 100 % ; repli sur 1 pour éviter la division par zéro.
        $max = max(array_column($rows, 'nb') ?: [1]);
        foreach ($rows as &$row) {
            // Exprime la hauteur de chaque barre en pourcentage entier par rapport à la barre la plus haute.
            $row['pct'] = (int) round($row['nb'] / $max * 100);
        }
        return $rows;
    }

    /**
     * Retourne les offres de stage les plus ajoutées en favoris, avec le nom de l'entreprise et le compteur.
     *
     * Joint wishlist → offre → entreprise pour résoudre le titre et le nom de l'entreprise.
     * Les résultats sont triés par nombre décroissant de favoris.
     *
     * @param int $limit Nombre maximum d'offres à retourner (défaut : 5).
     * @return array<int, array{titre: string, entreprise: string, nb_favoris: int}>
     */
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
        // LIMIT doit être lié en PARAM_INT ; PDO le placerait sinon entre guillemets, ce que MySQL refuserait.
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

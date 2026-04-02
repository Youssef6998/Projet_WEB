<?php

require_once __DIR__ . '/../Database.php';

/**
 * Modèle de gestion des évaluations d'entreprises.
 *
 * Une évaluation est constituée d'une note (1 à 5) et d'un commentaire libre
 * ("attendus") rédigé par un admin ou un pilote à propos d'une entreprise.
 * Ce modèle assemble également la vue détaillée complète d'une entreprise :
 * informations de base, évaluations associées et offres de stage passées.
 */
class EvaluationModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Insère une nouvelle évaluation pour une entreprise.
     *
     * Remarque : $idAuteur est accepté pour la cohérence de l'API mais n'est pas
     * encore persisté (la table `evaluation` stocke id_etudiant, pas id_auteur).
     *
     * @param int    $idEntreprise Identifiant de l'entreprise évaluée.
     * @param int    $note         Note attribuée à l'entreprise (1 à 5).
     * @param string $attendus     Commentaire libre / champ "attendus".
     * @param int    $idAuteur     Identifiant de l'auteur (réservé pour usage futur).
     * @return bool Vrai en cas de succès, faux sinon.
     */
    public function creerEvaluation(int $idEntreprise, int $note, string $attendus, int $idAuteur): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO evaluation (id_entreprise, note, commentaire)
             VALUES (:ent, :note, :commentaire)"
        );
        return $stmt->execute([
            ':ent'         => $idEntreprise,
            ':note'        => $note,
            ':commentaire' => $attendus,
        ]);
    }

    /**
     * Retourne un résumé par entreprise pour toutes les entreprises ayant au moins une évaluation.
     *
     * Le JOIN INNER sur `evaluation` exclut intentionnellement les entreprises sans évaluation.
     * Le LEFT JOIN sur `offre` est nécessaire pour le comptage des offres sans exclure
     * les entreprises qui ont des évaluations mais aucune offre.
     * Une sous-requête corrélée récupère le commentaire de la dernière évaluation
     * sans GROUP_CONCAT ni tri applicatif supplémentaire.
     * Les résultats sont triés par note moyenne décroissante, puis par nombre d'évaluations.
     *
     * @return array<int, array<string, mixed>> Une ligne associative par entreprise évaluée.
     */
    public function getResumesParEntreprise(): array {
        $stmt = $this->db->query(
            "SELECT
                ent.id_entreprise,
                ent.nom            AS entreprise_nom,
                ent.ville,
                ent.description,
                COUNT(DISTINCT ev.id_evaluation)  AS nb_evaluations,
                ROUND(AVG(ev.note), 1)             AS note_moyenne,
                MAX(ev.note)                       AS meilleure_note,
                MIN(ev.note)                       AS moins_bonne_note,
                MAX(ev.date_evaluation)            AS derniere_eval,
                COUNT(DISTINCT o.id_offre)         AS nb_offres,
                (
                    SELECT ev2.commentaire
                    FROM evaluation ev2
                    WHERE ev2.id_entreprise = ent.id_entreprise
                    ORDER BY ev2.date_evaluation DESC
                    LIMIT 1
                ) AS dernier_attendus
             FROM entreprise ent
             JOIN evaluation ev ON ent.id_entreprise = ev.id_entreprise
             LEFT JOIN offre o  ON ent.id_entreprise = o.id_entreprise
             GROUP BY ent.id_entreprise, ent.nom, ent.ville, ent.description
             ORDER BY note_moyenne DESC, nb_evaluations DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Retourne les détails complets d'une entreprise : informations, évaluations et offres.
     *
     * Le nombre d'offres est calculé via une table dérivée (sous-requête aliasée `s`) jointure
     * LEFT JOIN pour que les entreprises sans offre apparaissent tout de même. COALESCE convertit
     * un compteur NULL en 0. Retourne null si l'entreprise est introuvable.
     * Les évaluations sont chargées séparément et rattachées sous la clé `evaluations` ;
     * le nom de l'auteur est remplacé par "Inconnu" si le compte utilisateur n'existe plus.
     *
     * @param int $idEntreprise Identifiant de l'entreprise à afficher.
     * @return array<string, mixed>|null Tableau associatif avec les clés `evaluations` et `offres`, ou null si introuvable.
     */
    public function getDetailEntreprise(int $idEntreprise): ?array {
        $stmt = $this->db->prepare(
            "SELECT e.id_entreprise, e.nom, e.ville, e.adresse,
                    e.email_contact, e.telephone_contact, e.description,
                    COALESCE(s.nb_offres, 0) AS nb_offres
             FROM entreprise e
             LEFT JOIN (SELECT id_entreprise, COUNT(*) AS nb_offres FROM offre GROUP BY id_entreprise) s
                    ON s.id_entreprise = e.id_entreprise
             WHERE e.id_entreprise = :id"
        );
        $stmt->execute([':id' => $idEntreprise]);
        $entreprise = $stmt->fetch();
        if (!$entreprise) return null;

        // Charge toutes les évaluations ; LEFT JOIN sur utilisateur pour conserver les lignes
        // même si le compte de l'auteur a été supprimé (id_etudiant devient alors NULL).
        $evStmt = $this->db->prepare(
            "SELECT ev.id_evaluation, ev.note, ev.commentaire AS attendus, ev.date_evaluation,
                    COALESCE(CONCAT(u.prenom, ' ', u.nom), 'Inconnu') AS auteur_nom
             FROM evaluation ev
             LEFT JOIN utilisateur u ON ev.id_etudiant = u.id_utilisateur
             WHERE ev.id_entreprise = :id
             ORDER BY ev.date_evaluation DESC"
        );
        $evStmt->execute([':id' => $idEntreprise]);
        $entreprise['evaluations'] = $evStmt->fetchAll();

        // Charge toutes les offres publiées par cette entreprise, de la plus récente à la plus ancienne.
        $offresStmt = $this->db->prepare(
            "SELECT o.id_offre, o.titre, o.domaine, o.duree,
                    o.base_remuneration, o.date_offre, o.description
             FROM offre o
             WHERE o.id_entreprise = :id
             ORDER BY o.date_publication DESC"
        );
        $offresStmt->execute([':id' => $idEntreprise]);
        $entreprise['offres'] = $offresStmt->fetchAll();

        return $entreprise;
    }

    /**
     * Retourne toutes les évaluations d'une entreprise, de la plus récente à la plus ancienne.
     *
     * Plus légère que getDetailEntreprise() : aucune info entreprise ni offre n'est chargée.
     * Adaptée pour les affichages compacts (fiche entreprise publique).
     *
     * @param int $idEntreprise Identifiant de l'entreprise.
     * @return array<int, array<string, mixed>>
     */
    public function getEvaluationsParEntreprise(int $idEntreprise): array {
        $stmt = $this->db->prepare(
            "SELECT ev.id_evaluation, ev.note, ev.commentaire AS attendus, ev.date_evaluation
             FROM evaluation ev
             WHERE ev.id_entreprise = :id
             ORDER BY ev.date_evaluation DESC"
        );
        $stmt->execute([':id' => $idEntreprise]);
        return $stmt->fetchAll();
    }

    /**
     * Supprime une évaluation par sa clé primaire.
     *
     * @param int $id Clé primaire de l'évaluation à supprimer.
     * @return bool Vrai si une ligne a été supprimée, faux sinon.
     */
    public function supprimerEvaluation(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM evaluation WHERE id_evaluation = :id");
        return $stmt->execute([':id' => $id]);
    }
}

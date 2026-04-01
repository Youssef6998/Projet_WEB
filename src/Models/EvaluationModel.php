<?php

require_once __DIR__ . '/../Database.php';

class EvaluationModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // Créer une évaluation (par un admin/pilote)
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

    // Résumé par entreprise (pour la liste)
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

    // Détail d'une entreprise : infos + évaluations + offres passées
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

        // Évaluations
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

        // Offres / missions passées
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

    public function supprimerEvaluation(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM evaluation WHERE id_evaluation = :id");
        return $stmt->execute([':id' => $id]);
    }
}

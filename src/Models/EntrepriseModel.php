<?php

require_once __DIR__ . '/../Database.php';

/**
 * Modèle de gestion des entreprises partenaires.
 *
 * Gère toutes les opérations CRUD sur la table `entreprise` : liste paginée
 * avec filtre optionnel par nom, consultation d'une fiche avec ses offres
 * associées, création, modification et suppression en cascade.
 * Le nombre d'offres par entreprise est calculé à la volée via une sous-requête
 * LEFT JOIN, évitant ainsi de maintenir une colonne dénormalisée.
 */
class EntrepriseModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Retourne une liste paginée d'entreprises, avec filtre optionnel par nom.
     *
     * La pagination repose sur deux passes :
     *   1. Une requête COUNT avec la même clause WHERE détermine le nombre total
     *      de résultats et donc le nombre de pages.
     *   2. La page demandée est bornée à [1, totalPages] pour qu'une valeur
     *      hors plage ne retourne jamais un résultat vide.
     * Le nombre d'offres par entreprise est calculé via une table dérivée jointure
     * LEFT JOIN afin d'inclure les entreprises sans offre.
     * LIMIT et OFFSET sont liés en PARAM_INT pour éviter que PDO les mette entre guillemets.
     *
     * @param int    $page    Numéro de page (base 1, défaut : 1).
     * @param int    $perPage Nombre de lignes par page (défaut : 6).
     * @param string $nom     Filtre partiel sur le nom (recherche LIKE insensible à la casse).
     * @return array{entreprises: array, currentPage: int, totalPages: int, total: int}
     */
    public function getPaginatedEntreprises(int $page = 1, int $perPage = 6, string $nom = ''): array {
        $page = max(1, $page);

        $whereClause = '';
        $params      = [];

        // N'ajoute WHERE que si un terme de recherche est fourni, pour ne pas pénaliser les listes non filtrées.
        if ($nom !== '') {
            $whereClause    = 'WHERE e.nom LIKE :nom';
            $params[':nom'] = '%' . $nom . '%';
        }

        // Première passe : compte les lignes correspondantes pour le calcul de la pagination.
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM entreprise e $whereClause");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        // Borne la page à la plage valide une fois le total connu.
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
        // Lie le paramètre :nom optionnel s'il a été défini.
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

    /**
     * Retourne la liste minimale de toutes les entreprises (id + nom) pour les menus déroulants.
     *
     * @return array<int, array{id_entreprise: int, nom: string}>
     */
    public function getToutesEntreprises(): array {
        $stmt = $this->db->prepare("SELECT id_entreprise, nom FROM entreprise ORDER BY nom ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retourne la fiche complète d'une entreprise, avec ses offres de stage associées.
     *
     * Le nombre d'offres est calculé via la même table dérivée que dans getPaginatedEntreprises().
     * Les offres sont chargées dans une seconde requête et attachées sous la clé `offres`.
     * Retourne null si aucune entreprise ne correspond à l'identifiant fourni.
     *
     * @param int $id Clé primaire de l'entreprise à récupérer.
     * @return array<string, mixed>|null Tableau associatif avec un sous-tableau `offres`, ou null si introuvable.
     */
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

        // Attache les offres liées, triées par date de publication décroissante.
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

    /**
     * Insère une nouvelle entreprise en base.
     *
     * @param string $nom         Nom de l'entreprise.
     * @param string $ville       Ville du siège.
     * @param string $adresse     Adresse complète.
     * @param string $email       Adresse e-mail de contact.
     * @param string $telephone   Numéro de téléphone de contact.
     * @param string $description Description libre de l'entreprise.
     * @return bool Vrai en cas d'insertion réussie.
     */
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

    /**
     * Met à jour tous les champs modifiables d'une entreprise existante.
     *
     * @param int    $id          Clé primaire de l'entreprise à modifier.
     * @param string $nom         Nouveau nom.
     * @param string $ville       Nouvelle ville.
     * @param string $adresse     Nouvelle adresse.
     * @param string $email       Nouvel e-mail de contact.
     * @param string $telephone   Nouveau téléphone de contact.
     * @param string $description Nouvelle description.
     * @return bool Vrai si la requête UPDATE a réussi.
     */
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

    /**
     * Supprime une entreprise et toutes ses données dépendantes dans le bon ordre de clés étrangères.
     *
     * Les enregistrements enfants sont supprimés manuellement car le schéma peut ne pas
     * configurer de suppressions en cascade. Ordre de suppression :
     *   1. Entrées wishlist référençant les offres de cette entreprise.
     *   2. Candidatures référençant ces offres.
     *   3. Lignes de la table pivot offre_competence pour ces offres.
     *   4. Les offres elles-mêmes.
     *   5. L'enregistrement de l'entreprise.
     * La syntaxe DELETE alias FROM ... est spécifique à MySQL et permet de filtrer
     * par id_entreprise via la jointure sur `offre` en une seule instruction.
     *
     * @param int $id Clé primaire de l'entreprise à supprimer.
     * @return bool Vrai si la suppression finale (table `entreprise`) a réussi.
     */
    public function supprimerEntreprise(int $id): bool {
        // Supprime les favoris liés aux offres de cette entreprise.
        $this->db->prepare(
            "DELETE w FROM wishlist w
             JOIN offre o ON w.id_offre = o.id_offre
             WHERE o.id_entreprise = :id"
        )->execute([':id' => $id]);

        // Supprime les candidatures liées aux offres de cette entreprise.
        $this->db->prepare(
            "DELETE c FROM candidature c
             JOIN offre o ON c.id_offre = o.id_offre
             WHERE o.id_entreprise = :id"
        )->execute([':id' => $id]);

        // Supprime les lignes de la table pivot compétences/offres.
        $this->db->prepare(
            "DELETE oc FROM offre_competence oc
             JOIN offre o ON oc.id_offre = o.id_offre
             WHERE o.id_entreprise = :id"
        )->execute([':id' => $id]);

        // Supprime les offres avant de supprimer l'entreprise parente.
        $this->db->prepare("DELETE FROM offre WHERE id_entreprise = :id")->execute([':id' => $id]);

        return $this->db->prepare("DELETE FROM entreprise WHERE id_entreprise = :id")
                        ->execute([':id' => $id]);
    }
}

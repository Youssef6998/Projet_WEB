<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/../Database.php';

/**
 * Modèle de gestion des offres de stage.
 *
 * Couche d'accès aux données pour les offres de stage (offre), les candidatures
 * (candidature) et les favoris (wishlist).
 *
 * Relations principales gérées ici :
 *   offre ←→ entreprise      (chaque offre appartient à une entreprise)
 *   offre ←→ competence      (many-to-many via offre_competence)
 *   offre ←→ candidature     (les étudiants postulent aux offres)
 *   offre ←→ wishlist        (les étudiants mettent des offres en favoris)
 *
 * La pagination suit un modèle en deux passes : une requête COUNT détermine les totaux,
 * puis une requête LIMIT/OFFSET récupère la tranche demandée.
 */
class StageModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Retourne une liste paginée, filtrée et triée d'offres de stage.
     *
     * Les filtres sont optionnels et combinés avec AND. Le filtre compétence utilise
     * une sous-requête EXISTS corrélée pour éviter de dupliquer les lignes lorsqu'une
     * offre possède plusieurs compétences correspondantes. Les tags de compétences sont
     * chargés en un seul lot (clause IN) après la récupération principale pour éviter
     * le problème N+1. LIMIT/OFFSET sont liés en entiers pour empêcher PDO de les mettre
     * entre guillemets.
     *
     * @param int    $page       Numéro de page (base 1, borné automatiquement).
     * @param int    $perPage    Nombre d'offres par page.
     * @param string $domaine    Filtre exact sur le domaine (ex. 'Informatique').
     * @param string $ville      Filtre partiel sur la ville.
     * @param string $duree      Filtre partiel sur la durée.
     * @param string $competence Filtre partiel sur le libellé de compétence.
     * @param string $tri        Clé de tri : 'date_desc' (défaut), 'date_asc', 'alpha_asc', 'alpha_desc'.
     * @return array {
     *     stages: array,
     *     currentPage: int,
     *     totalPages: int,
     *     totalStages: int
     * }
     */
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
            // EXISTS corrélé évite les lignes dupliquées quand une offre correspond
            // à plusieurs compétences, contrairement à un JOIN + DISTINCT.
            $whereConditions[]      = "EXISTS (
                SELECT 1 FROM offre_competence oc
                JOIN competence c ON oc.id_competence = c.id_competence
                WHERE oc.id_offre = o.id_offre AND c.libelle LIKE :competence
            )";
            $params[':competence'] = '%' . $competence . '%';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Associe la clé de tri à une clause ORDER BY sécurisée ; par défaut les plus récentes en premier.
        $orderBy = match($tri) {
            'date_asc'   => 'o.date_publication ASC',
            'alpha_asc'  => 'o.titre ASC',
            'alpha_desc' => 'o.titre DESC',
            default      => 'o.date_publication DESC',
        };

        // Première passe : compte le total d'offres correspondantes pour les métadonnées de pagination.
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM offre o
             JOIN entreprise e ON o.id_entreprise = e.id_entreprise
             $whereClause"
        );
        $countStmt->execute($params);
        $totalStages = (int) $countStmt->fetchColumn();
        $totalPages  = max(1, (int) ceil($totalStages / $perPage));
        // Borne la page à [1, totalPages].
        $page        = min($page, $totalPages);
        $offset      = ($page - 1) * $perPage;

        // Deuxième passe : récupère les lignes d'offres pour la page courante.
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
        // Liaison en entier obligatoire ; PDO placerait sinon LIMIT/OFFSET entre guillemets.
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Récupère les compétences via JOIN pour éviter le N+1 :
        // au lieu d'une requête par offre, charge tous les tags de la page courante
        // en une seule requête IN() et les groupe par id_offre en PHP.
        $ids = array_column($rows, 'id_offre');
        $tagsByOffre = [];
        if (!empty($ids)) {
            // Construit des marqueurs positionnels correspondant au nombre d'IDs d'offres.
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $tagStmt = $this->db->prepare(
                "SELECT oc.id_offre, c.libelle
                 FROM offre_competence oc
                 JOIN competence c ON oc.id_competence = c.id_competence
                 WHERE oc.id_offre IN ($placeholders)"
            );
            $tagStmt->execute($ids);
            // Indexe les tags par id_offre pour une recherche en O(1) dans la boucle d'assemblage.
            foreach ($tagStmt->fetchAll() as $t) {
                $tagsByOffre[$t['id_offre']][] = $t['libelle'];
            }
        }

        // Assemble le tableau final des stages en formatant la date pour l'affichage.
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
                // Formate la chaîne de date brute en jj/mm/AAAA pour la vue.
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

    /**
     * Récupère les détails complets d'une offre avec ses tags de compétences.
     *
     * Joint offre → entreprise pour les coordonnées de l'entreprise.
     * Les tags sont chargés dans une requête séparée et ajoutés au tableau résultat
     * sous forme de liste de libellés.
     *
     * @param int $id La PK de l'offre (id_offre).
     * @return array|null Le tableau de l'offre avec une clé 'tags', ou null si introuvable.
     */
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

        // Charge les libellés de compétences associés et les ajoute sous la clé 'tags'.
        $tagStmt = $this->db->prepare(
            "SELECT c.libelle FROM offre_competence oc
             JOIN competence c ON oc.id_competence = c.id_competence
             WHERE oc.id_offre = :id"
        );
        $tagStmt->execute([':id' => $id]);
        $offre['tags'] = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
        return $offre;
    }

    /**
     * Vérifie si une offre est déjà dans la wishlist d'un étudiant.
     *
     * @param int $idEtudiant La PK étudiant.
     * @param int $idOffre    La PK de l'offre.
     * @return bool Vrai si la paire (étudiant, offre) existe dans la wishlist.
     */
    public function isInWishlist(int $idEtudiant, int $idOffre): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM wishlist WHERE id_etudiant = :e AND id_offre = :o"
        );
        $stmt->execute([':e' => $idEtudiant, ':o' => $idOffre]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Ajoute ou retire une offre de la wishlist d'un étudiant (bascule).
     *
     * Utilise isInWishlist() pour déterminer la direction. Retourne le nouvel état :
     * vrai signifie que l'offre vient d'être ajoutée, faux qu'elle a été retirée.
     *
     * @param int $idEtudiant La PK étudiant.
     * @param int $idOffre    La PK de l'offre.
     * @return bool Vrai si l'offre est maintenant dans la wishlist, faux si elle a été retirée.
     */
    public function toggleWishlist(int $idEtudiant, int $idOffre): bool {
        if ($this->isInWishlist($idEtudiant, $idOffre)) {
            // Déjà en wishlist — la retirer et signaler le nouvel état (faux).
            $this->db->prepare("DELETE FROM wishlist WHERE id_etudiant = :e AND id_offre = :o")
                     ->execute([':e' => $idEtudiant, ':o' => $idOffre]);
            return false;
        }
        // Pas encore en wishlist — l'ajouter et signaler le nouvel état (vrai).
        $this->db->prepare("INSERT INTO wishlist (id_etudiant, id_offre) VALUES (:e, :o)")
                 ->execute([':e' => $idEtudiant, ':o' => $idOffre]);
        return true;
    }

    /**
     * Vérifie si un étudiant a déjà postulé à une offre spécifique.
     *
     * Empêche l'insertion de lignes de candidature dupliquées.
     *
     * @param int $idEtudiant La PK étudiant.
     * @param int $idOffre    La PK de l'offre.
     * @return bool Vrai si une ligne de candidature existe déjà.
     */
    public function dejaCandidate(int $idEtudiant, int $idOffre): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM candidature WHERE id_etudiant = :e AND id_offre = :o"
        );
        $stmt->execute([':e' => $idEtudiant, ':o' => $idOffre]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Enregistre la candidature d'un étudiant à une offre.
     *
     * Le chemin du CV ($cvPath) est le chemin côté serveur retourné après déplacement
     * du fichier téléversé dans le répertoire uploads ; il est stocké en chemin relatif
     * pour que l'application puisse le servir quelle que soit la racine de déploiement.
     *
     * @param int    $idEtudiant La PK étudiant.
     * @param int    $idOffre    La PK de l'offre.
     * @param string $lettreMot  Texte de la lettre de motivation.
     * @param string $cvPath     Chemin relatif côté serveur vers le fichier CV téléversé.
     * @return bool Vrai en cas de succès.
     */
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

    /**
     * Retourne la liste des IDs d'offres présentes dans la wishlist d'un étudiant.
     *
     * Utile pour marquer les offres mises en favoris dans une vue de liste sans
     * charger les détails complets de chaque offre.
     *
     * @param int $idEtudiant La PK étudiant.
     * @return array Tableau plat d'entiers id_offre.
     */
    public function getWishlistIds(int $idEtudiant): array {
        $stmt = $this->db->prepare("SELECT id_offre FROM wishlist WHERE id_etudiant = :id");
        $stmt->execute([':id' => $idEtudiant]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Retourne la wishlist complète d'un étudiant, triée par date d'ajout décroissante.
     *
     * Joint wishlist → offre → entreprise pour fournir suffisamment de contexte à la vue
     * wishlist (titre, durée, nom de l'entreprise, date d'ajout aux favoris).
     *
     * @param int $idEtudiant La PK étudiant.
     * @return array Liste des lignes wishlist triées par date_ajout DESC.
     */
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

    /**
     * Retourne toutes les candidatures soumises par un étudiant, de la plus récente à la plus ancienne.
     *
     * Joint candidature → offre → entreprise pour fournir le titre de l'offre, le nom de
     * l'entreprise, la ville et les détails financiers avec le statut et les chemins des documents.
     *
     * @param int $idEtudiant La PK étudiant.
     * @return array Liste des lignes de candidature triées par date_candidature DESC.
     */
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

    /**
     * Récupère le chemin du fichier CV stocké pour une candidature spécifique.
     *
     * Utilisé lorsqu'un utilisateur souhaite télécharger ou prévisualiser son CV téléversé.
     *
     * @param int $idEtudiant La PK étudiant.
     * @param int $idOffre    La PK de l'offre.
     * @return string|null Le chemin relatif du CV, ou null si aucune candidature trouvée.
     */
    public function getCvPath(int $idEtudiant, int $idOffre): ?string {
        $stmt = $this->db->prepare(
            "SELECT cv_path FROM candidature WHERE id_etudiant = :e AND id_offre = :o"
        );
        $stmt->execute([':e' => $idEtudiant, ':o' => $idOffre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['cv_path'] : null;
    }

    /**
     * Retourne toutes les entreprises (id + nom) pour les menus déroulants de création/modification d'offre.
     *
     * @return array Liste de lignes {id_entreprise, nom} triées alphabétiquement.
     */
    public function getToutesEntreprises(): array {
        return $this->db->query(
            "SELECT id_entreprise, nom FROM entreprise ORDER BY nom ASC"
        )->fetchAll();
    }

    /**
     * Insère une nouvelle offre de stage liée à une entreprise existante.
     *
     * Les champs optionnels (domaine, description, base_remuneration, duree) acceptent
     * null pour permettre des offres partielles en phase de création initiale.
     *
     * @param int         $idEntreprise      PK de l'entreprise.
     * @param string      $titre             Titre de l'offre.
     * @param string|null $domaine           Domaine / secteur (nullable).
     * @param string|null $description       Description complète (nullable).
     * @param float|null  $baseRemuneration  Rémunération mensuelle de base en euros (nullable).
     * @param string      $dateOffre         Date de début du stage (Y-m-d).
     * @param string|null $duree             Libellé de durée ex. '6 mois' (nullable).
     * @return bool Vrai en cas d'INSERT réussi.
     */
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

    /**
     * Met à jour les champs modifiables d'une offre de stage existante.
     *
     * @param int        $idOffre          PK de l'offre.
     * @param int        $idEntreprise     Nouvelle PK entreprise (peut changer l'entreprise liée).
     * @param string     $titre            Nouveau titre.
     * @param string     $description      Nouvelle description.
     * @param string     $duree            Nouveau libellé de durée.
     * @param string     $dateOffre        Nouvelle date de début (Y-m-d).
     * @param float|null $baseRemuneration Nouvelle rémunération de base, ou null pour la vider.
     * @return bool Vrai en cas d'UPDATE réussi.
     */
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

    /**
     * Supprime toutes les candidatures liées à une offre.
     *
     * Doit être appelée avant supprimerOffre() si ON DELETE CASCADE n'est pas
     * configuré sur la clé étrangère candidature.
     *
     * @param int $idOffre La PK de l'offre dont les candidatures doivent être supprimées.
     * @return bool Vrai en cas de succès.
     */
    public function supprimerCandidaturesOffre(int $idOffre): bool {
        return $this->db->prepare("DELETE FROM candidature WHERE id_offre = :id")
                        ->execute([':id' => $idOffre]);
    }

    /**
     * Supprime définitivement une offre de stage.
     *
     * Appeler supprimerCandidaturesOffre() au préalable si les suppressions en cascade
     * ne sont pas configurées au niveau de la base de données.
     *
     * @param int $idOffre La PK de l'offre à supprimer.
     * @return bool Vrai en cas de succès.
     */
    public function supprimerOffre(int $idOffre): bool {
        return $this->db->prepare("DELETE FROM offre WHERE id_offre = :id")
                        ->execute([':id' => $idOffre]);
    }

    /**
     * Calcule des statistiques globales sur toutes les offres.
     *
     * Utilise COUNT(DISTINCT …) pour compter les étudiants uniques ayant postulé ou
     * mis en favori au moins une offre, évitant l'inflation due à plusieurs actions.
     * COALESCE garantit que remuneration_moyenne retourne 0 au lieu de NULL
     * quand aucune offre n'a de salaire défini.
     *
     * @return array {
     *     nb_offres: int,
     *     nb_candidatures: int,
     *     nb_wishlist: int,
     *     remuneration_moyenne: float
     * }
     */
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

    /**
     * Retourne les statistiques par offre (candidatures et favoris) pour toutes les offres.
     *
     * Les résultats sont triés par nb_candidatures DESC pour que les offres les plus populaires
     * apparaissent en premier dans les tableaux de bord admin/pilote. COUNT(DISTINCT …) évite
     * les comptages gonflés causés par les deux LEFT JOIN qui multiplient les lignes.
     *
     * @return array Liste de lignes statistiques par offre, chacune avec nb_candidatures et nb_wishlist.
     */
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

    /**
     * Retourne une tranche paginée des statistiques par offre.
     *
     * Le total est dérivé d'un simple COUNT(*) sur la table offre (sans filtre) pour
     * garder la requête légère. LIMIT/OFFSET sont liés en entiers pour la même raison
     * que dans getPaginatedStages().
     *
     * @param int $page    Numéro de page (base 1, borné automatiquement).
     * @param int $perPage Nombre de lignes par page.
     * @return array {
     *     statsOffres: array,
     *     currentPage: int,
     *     totalPages: int
     * }
     */
    public function getPaginatedStatsParOffre(int $page = 1, int $perPage = 6): array {
        $page = max(1, $page);

        // Compte le total d'offres pour calculer totalPages.
        $countStmt   = $this->db->query("SELECT COUNT(*) FROM offre");
        $totalOffres = (int) $countStmt->fetchColumn();
        $totalPages  = max(1, (int) ceil($totalOffres / $perPage));
        $page        = min($page, $totalPages);
        $offset      = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT o.id_offre, o.titre, e.nom AS entreprise,
                    COUNT(DISTINCT c.id_etudiant) AS nb_candidatures,
                    COUNT(DISTINCT w.id_etudiant) AS nb_wishlist
             FROM offre o
             JOIN entreprise e ON o.id_entreprise = e.id_entreprise
             LEFT JOIN candidature c ON o.id_offre = c.id_offre
             LEFT JOIN wishlist w    ON o.id_offre = w.id_offre
             GROUP BY o.id_offre, o.titre, e.nom
             ORDER BY nb_candidatures DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'statsOffres' => $stmt->fetchAll(),
            'currentPage' => $page,
            'totalPages'  => $totalPages,
        ];
    }
}

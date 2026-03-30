<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/../Database.php';

class StageModel {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getPaginatedStages(int $page = 1, int $perPage = 6, string $domaine = '', string $ville ='', string $duree='',string $competence='', string $tri = 'date_desc'): array {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $whereClause = '';
        $params      = [];
        // Filtres multiples
        $whereConditions = [];

        if (!empty($domaine)) {
            $whereConditions[] = "o.domaine = :domaine";
            $params[':domaine'] = $domaine;
        }

        if (!empty($ville)) {
            $whereConditions[] = "e.ville LIKE :ville";
            $params[':ville'] = '%' . $ville . '%';
        }

        if (!empty($duree)) {
            $whereConditions[] = "o.duree LIKE :duree";
            $params[':duree'] = '%' . $duree . '%';
        }

        if (!empty($competence)) {
            $whereConditions[] = "EXISTS (
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

        $countSql = "SELECT COUNT(*) 
              FROM offre o
              JOIN entreprise e ON o.id_entreprise = e.id_entreprise
              $whereClause";
        $countStmt   = $this->db->prepare($countSql);
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
                       e.ville AS ville
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
                'ville'       => $row['ville'] ?? '',
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

        $countSql = "SELECT COUNT(*) 
                    FROM entreprise e
                    $whereClause";

        $countStmt = $this->db->prepare($countSql);
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
                    e.id_entreprise, e.nom AS company, e.ville AS ville,
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
    public function getToutesEntreprises(): array {
        $stmt = $this->db->prepare(
            "SELECT id_entreprise, nom FROM entreprise ORDER BY nom ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function creerEntreprise(string $nom, string $ville, string $adresse, string $email, string $telephone, string $description): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO entreprise (nom, ville, adresse, email_contact, telephone_contact, description)
             VALUES (:nom, :ville, :adresse, :email, :tel, :desc)"
        );
        return $stmt->execute([
            ':nom'    => $nom,
            ':ville'  => $ville,
            ':adresse' => $adresse,
            ':email'  => $email,
            ':tel'    => $telephone,
            ':desc'   => $description,
        ]);
    }

    public function modifierEntreprise(int $id, string $nom, string $ville, string $adresse, string $email, string $telephone, string $description): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE entreprise
             SET nom = :nom, ville = :ville, adresse = :adresse,
                 email_contact = :email, telephone_contact = :tel, description = :desc
             WHERE id_entreprise = :id"
        );
        return $stmt->execute([
            ':id'    => $id,
            ':nom'   => $nom,
            ':ville' => $ville,
            ':adresse' => $adresse,
            ':email' => $email,
            ':tel'   => $telephone,
            ':desc'  => $description,
        ]);
    }

    public function supprimerEntreprise(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM entreprise WHERE id_entreprise = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function creerEvaluation(int $idEtudiant, int $idEntreprise, int $note, string $commentaire): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO evaluation (id_etudiant, id_entreprise, note, commentaire)
             VALUES (:e, :ent, :note, :comm)"
        );
        return $stmt->execute([
            ':e'    => $idEtudiant,
            ':ent'  => $idEntreprise,
            ':note' => $note,
            ':comm' => $commentaire,
        ]);
    }



    public function getEtudiantsSupervisesParPilote(int $idPilote): array
    {
        $sql = "SELECT u.id_utilisateur, u.nom, u.prenom, u.email
                FROM utilisateur u
                JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
                JOIN pilote_etudiant pe ON e.id_etudiant = pe.id_etudiant
                WHERE pe.id_pilote = :id_pilote
                ORDER BY u.nom, u.prenom";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_pilote' => $idPilote]);
        return $stmt->fetchAll();
    }

    public function ajouterEtudiantAuPilote(int $idPilote, int $idEtudiant): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO pilote_etudiant (id_pilote, id_etudiant)
             VALUES (:pilote, :etudiant)"
        );
        return $stmt->execute([
            ':pilote'   => $idPilote,
            ':etudiant' => $idEtudiant
        ]);
    }

    public function creerPilote(string $nom, string $prenom, string $email, string $motDePasse, string $telephone, string $promotion): bool
    {
        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role)
             VALUES (:nom, :prenom, :email, :mdp, :tel, 'pilote')"
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
            $this->db->prepare("INSERT INTO pilote (id_utilisateur, promotion) VALUES (:id, :promo)")
                     ->execute([':id' => $id, ':promo' => $promotion]);
        }
        return $ok;
    }

    public function getAllPilotes(): array
    {
        $stmt = $this->db->query(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    p.id_pilote, p.centre, p.promotion,
                    COUNT(e.id_etudiant) AS nb_etudiants
             FROM utilisateur u
             JOIN pilote p ON u.id_utilisateur = p.id_utilisateur
             LEFT JOIN etudiant e ON p.id_pilote = e.id_pilote
             WHERE u.actif = 1
             GROUP BY u.id_utilisateur, p.id_pilote
             ORDER BY u.nom, u.prenom"
        );
        return $stmt->fetchAll();
    }

    public function getAllEtudiants(): array
    {
        $stmt = $this->db->query(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    e.id_etudiant, e.formation, e.niveau_etude,
                    CONCAT(up.prenom, ' ', up.nom) AS pilote_nom
             FROM utilisateur u
             JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
             LEFT JOIN pilote p ON e.id_pilote = p.id_pilote
             LEFT JOIN utilisateur up ON p.id_utilisateur = up.id_utilisateur
             WHERE u.actif = 1
             ORDER BY u.nom, u.prenom"
        );
        return $stmt->fetchAll();
    }

    public function getPiloteById(int $idUtilisateur): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                    p.id_pilote, p.centre, p.promotion
             FROM utilisateur u
             JOIN pilote p ON u.id_utilisateur = p.id_utilisateur
             WHERE u.id_utilisateur = :id AND u.actif = 1"
        );
        $stmt->execute([':id' => $idUtilisateur]);
        return $stmt->fetch();
    }

    public function updatePilote(int $idUtilisateur, string $nom, string $prenom, string $email, string $telephone, string $promotion, ?string $motDePasse): bool
    {
        if ($motDePasse !== null) {
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $this->db->prepare(
                "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel, mot_de_passe=:mdp
                 WHERE id_utilisateur=:id"
            )->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $telephone, ':mdp' => $hash, ':id' => $idUtilisateur]);
        } else {
            $this->db->prepare(
                "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel
                 WHERE id_utilisateur=:id"
            )->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $telephone, ':id' => $idUtilisateur]);
        }
        return $this->db->prepare(
            "UPDATE pilote SET promotion=:promo WHERE id_utilisateur=:id"
        )->execute([':promo' => $promotion, ':id' => $idUtilisateur]);
    }

    public function supprimerPilote(int $idUtilisateur): bool
    {
        $this->db->prepare("DELETE FROM pilote WHERE id_utilisateur = :id")->execute([':id' => $idUtilisateur]);
        return $this->db->prepare("DELETE FROM utilisateur WHERE id_utilisateur = :id")->execute([':id' => $idUtilisateur]);
    }

    public function supprimerEtudiant(int $idUtilisateur): bool
    {
        $this->db->prepare("DELETE FROM etudiant WHERE id_utilisateur = :id")->execute([':id' => $idUtilisateur]);
        return $this->db->prepare("DELETE FROM utilisateur WHERE id_utilisateur = :id")->execute([':id' => $idUtilisateur]);
    }   
    public function getEvaluationsParEntreprise(int $idEntreprise): array
    {
        $stmt = $this->db->prepare(
            "SELECT ev.id_evaluation, ev.note, ev.commentaire, ev.date_evaluation,
                    u.nom AS etudiant_nom, u.prenom AS etudiant_prenom
             FROM evaluation ev
             JOIN etudiant e ON ev.id_etudiant = e.id_etudiant
             JOIN utilisateur u ON e.id_utilisateur = u.id_utilisateur
             WHERE ev.id_entreprise = :id
             ORDER BY ev.date_evaluation DESC"
        );
        $stmt->execute([':id' => $idEntreprise]);
        return $stmt->fetchAll();
    }

    public function getAllEvaluations(): array
    {
        $stmt = $this->db->query(
            "SELECT ev.id_evaluation, ev.note, ev.commentaire, ev.date_evaluation,
                    u.nom AS etudiant_nom, u.prenom AS etudiant_prenom,
                    ent.nom AS entreprise_nom, ent.id_entreprise
             FROM evaluation ev
             JOIN etudiant e ON ev.id_etudiant = e.id_etudiant
             JOIN utilisateur u ON e.id_utilisateur = u.id_utilisateur
             JOIN entreprise ent ON ev.id_entreprise = ent.id_entreprise
             ORDER BY ev.date_evaluation DESC"
        );
        return $stmt->fetchAll();
    }

    public function supprimerEvaluation(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM evaluation WHERE id_evaluation = :id");
        return $stmt->execute([':id' => $id]);
    }
    public function getStatsOffres(): array {
    $stmt = $this->db->prepare(
        "SELECT 
            COUNT(DISTINCT o.id_offre) AS nb_offres,
            COUNT(DISTINCT c.id_etudiant) AS nb_candidatures,
            COUNT(DISTINCT w.id_etudiant) AS nb_wishlist,
            COALESCE(AVG(o.base_remuneration), 0) AS remuneration_moyenne
         FROM offre o
         LEFT JOIN candidature c ON o.id_offre = c.id_offre
         LEFT JOIN wishlist w ON o.id_offre = w.id_offre"
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
         LEFT JOIN wishlist w ON o.id_offre = w.id_offre
         GROUP BY o.id_offre, o.titre, e.nom
         ORDER BY nb_candidatures DESC"
    );
    $stmt->execute();
    return $stmt->fetchAll();
}
public function getUtilisateurComplet(int $id): ?array {
    $stmt = $this->db->prepare(
        "SELECT id_utilisateur, nom, prenom, email, telephone, role
         FROM utilisateur WHERE id_utilisateur = :id"
    );
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    if (!$user) return null;

    if ($user['role'] === 'etudiant') {
        $s = $this->db->prepare("SELECT id_etudiant FROM etudiant WHERE id_utilisateur = :id");
        $s->execute([':id' => $id]);
        $row = $s->fetch();
        $user['id_etudiant'] = $row ? (int)$row['id_etudiant'] : null;
    }

    if ($user['role'] === 'pilote') {
        $s = $this->db->prepare("SELECT id_pilote, promotion FROM pilote WHERE id_utilisateur = :id");
        $s->execute([':id' => $id]);
        $row = $s->fetch();
        $user['id_pilote'] = $row ? (int)$row['id_pilote'] : null;
        $user['promotion'] = $row['promotion'] ?? null;
    }

    return $user;
}

public function updateUtilisateur(int $id, string $champ, string $valeur): bool {
    $champsAutorises = ['nom', 'prenom', 'email', 'telephone'];
    if (!in_array($champ, $champsAutorises, true)) return false;

    if ($champ === 'email') {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = :email AND id_utilisateur != :id");
        $stmt->execute([':email' => $valeur, ':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) return false;
    }

    $stmt = $this->db->prepare("UPDATE utilisateur SET $champ = :val WHERE id_utilisateur = :id");
    return $stmt->execute([':val' => $valeur, ':id' => $id]);
}

public function updateMotDePasse(int $id, string $ancienMdp, string $nouveauMdp): bool {
    $stmt = $this->db->prepare("SELECT mot_de_passe FROM utilisateur WHERE id_utilisateur = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($ancienMdp, $row['mot_de_passe'])) return false;

    $hash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
    $stmt2 = $this->db->prepare("UPDATE utilisateur SET mot_de_passe = :mdp WHERE id_utilisateur = :id");
    return $stmt2->execute([':mdp' => $hash, ':id' => $id]);
}

public function updatePromotion(int $idUtilisateur, string $promotion): bool {
    $stmt = $this->db->prepare("UPDATE pilote SET promotion = :promo WHERE id_utilisateur = :id");
    return $stmt->execute([':promo' => $promotion, ':id' => $idUtilisateur]);
}

public function supprimerUtilisateur(int $id): bool {
    $stmt = $this->db->prepare("UPDATE utilisateur SET actif = 0 WHERE id_utilisateur = :id");
    return $stmt->execute([':id' => $id]);
}

public function getEtudiantsParPiloteUtilisateur(int $idUtilisateur): array {
    $stmt = $this->db->prepare(
        "SELECT u.id_utilisateur, u.nom, u.prenom, u.email
         FROM utilisateur u
         JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
         JOIN pilote p ON e.id_pilote = p.id_pilote
         WHERE p.id_utilisateur = :id
         ORDER BY u.nom, u.prenom"
    );
    $stmt->execute([':id' => $idUtilisateur]);
    return $stmt->fetchAll();
}

public function getEtudiantById(int $id): ?array {
    $stmt = $this->db->prepare("
        SELECT 
            u.*, e.formation, e.niveau_etude
        FROM utilisateur u
        INNER JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
        WHERE u.id_utilisateur = :id AND u.role = 'etudiant'
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

public function affecterEtudiantAuPilote(int $idPiloteUtilisateur, int $idEtudiantUtilisateur): bool {
    $stmt = $this->db->prepare("SELECT id_pilote FROM pilote WHERE id_utilisateur = :id");
    $stmt->execute([':id' => $idPiloteUtilisateur]);
    $pilote = $stmt->fetch();
    if (!$pilote) return false;

    $stmt2 = $this->db->prepare(
        "UPDATE etudiant SET id_pilote = :id_pilote WHERE id_utilisateur = :id_etudiant"
    );
    return $stmt2->execute([
        ':id_pilote'   => $pilote['id_pilote'],
        ':id_etudiant' => $idEtudiantUtilisateur,
    ]);
}

public function getEtudiantsFiltrees(string $prenom = '', string $nom = ''): array {
    try {
        $sql = "
            SELECT 
                u.id_utilisateur, u.prenom, u.nom, u.email, u.telephone,
                e.formation, e.niveau_etude,
                CASE 
                    WHEN pu.id_utilisateur IS NOT NULL 
                    THEN CONCAT(pu.prenom, ' ', pu.nom)
                    ELSE NULL 
                END as pilote_nom
            FROM utilisateur u
            INNER JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur  
            LEFT JOIN pilote p ON e.id_pilote = p.id_pilote
            LEFT JOIN utilisateur pu ON p.id_utilisateur = pu.id_utilisateur
            WHERE u.role = 'etudiant'
            AND u.id_utilisateur NOT IN (
                SELECT id_utilisateur FROM pilote
            )
        ";
        
        $params = [];
        if (!empty($prenom)) {
            $sql .= " AND u.prenom LIKE ?";
            $params[] = "%$prenom%";
        }
        if (!empty($nom)) {
            $sql .= " AND u.nom LIKE ?";
            $params[] = "%$nom%";
        }
        
        $sql .= " ORDER BY u.nom, u.prenom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur getEtudiantsFiltrees: " . $e->getMessage());
        return [];
    }
}

public function modifierEtudiant(
    int $idUtilisateur,
    string $nom,
    string $prenom,
    string $email,
    string $telephone,
    string $formation,
    string $niveauEtude
): bool {
    try {
        // UPDATE utilisateur
        $stmt = $this->db->prepare("
            UPDATE utilisateur 
            SET nom = :nom, 
                prenom = :prenom, 
                email = :email, 
                telephone = :telephone 
            WHERE id_utilisateur = :id
        ");
        $stmt->execute([
            ':nom' => $nom,
            ':prenom' => $prenom,
            ':email' => $email,
            ':telephone' => $telephone,
            ':id' => $idUtilisateur
        ]);

        // UPDATE etudiant
        $stmt = $this->db->prepare("
            UPDATE etudiant 
            SET formation = :formation, 
                niveau_etude = :niveau_etude 
            WHERE id_utilisateur = :id
        ");
        $stmt->execute([
            ':formation' => $formation,
            ':niveau_etude' => $niveauEtude,
            ':id' => $idUtilisateur
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Erreur modifierEtudiant: " . $e->getMessage());
        return false;
    }
}

public function creerOffre(
    int $idEntreprise,
    string $titre,
    ?string $domaine,
    ?string $description,
    ?float $baseRemuneration,
    string $dateOffre,
    ?string $duree
): bool {
    try {
        $stmt = $this->db->prepare("
            INSERT INTO offre (
                id_entreprise, titre, domaine, description, 
                base_remuneration, date_offre, duree
            ) VALUES (
                :id_entreprise, :titre, :domaine, :description,
                :base_remuneration, :date_offre, :duree
            )
        ");
        return $stmt->execute([
            ':id_entreprise' => $idEntreprise,
            ':titre' => $titre,
            ':domaine' => $domaine,
            ':description' => $description,
            ':base_remuneration' => $baseRemuneration,
            ':date_offre' => $dateOffre,     // ← Nom exact !
            ':duree' => $duree
        ]);
    } catch (PDOException $e) {
        error_log("Erreur creerOffre: " . $e->getMessage());
        return false;
    }
}
public function supprimerOffre(int $idOffre): bool {
    try {
        $stmt = $this->db->prepare("DELETE FROM offre WHERE id_offre = :id");
        return $stmt->execute([':id' => $idOffre]);
    } catch (PDOException $e) {
        error_log("Erreur supprimerOffre: " . $e->getMessage());
        return false;
    }
}

public function supprimerCandidaturesOffre(int $idOffre): bool {
    try {
        $stmt = $this->db->prepare("DELETE FROM candidature WHERE id_offre = :id");
        return $stmt->execute([':id' => $idOffre]);
    } catch (PDOException $e) {
        return false;
    }
}

public function getEtudiantById(int $id): ?array {
    $stmt = $this->db->prepare(
        "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone,
                e.id_etudiant, e.formation, e.niveau_etude
         FROM utilisateur u
         JOIN etudiant e ON u.id_utilisateur = e.id_utilisateur
         WHERE u.id_utilisateur = :id"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

public function modifierEtudiant(int $id, string $nom, string $prenom, string $email, string $telephone, string $formation, string $niveauEtude): bool {
    $this->db->prepare(
        "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel
         WHERE id_utilisateur=:id"
    )->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $telephone, ':id' => $id]);

    $stmt2 = $this->db->prepare(
        "UPDATE etudiant SET formation=:formation, niveau_etude=:niveau WHERE id_utilisateur=:id"
    );
    return $stmt2->execute([':formation' => $formation, ':niveau' => $niveauEtude, ':id' => $id]);
}

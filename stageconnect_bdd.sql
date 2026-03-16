-- ============================================================
-- StageConnect - Script de création de la base de données
-- SGBD : MySQL 8.x
-- Démarche : MCD (Merise) → MLD → SQL
-- ============================================================

-- ============================================================
-- 0. CRÉATION DE LA BASE
-- ============================================================

DROP DATABASE IF EXISTS stageconnect;
CREATE DATABASE stageconnect
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE stageconnect;


-- ============================================================
-- 1. ENTITÉS SANS DÉPENDANCES (tables mères)
-- ============================================================

-- UTILISATEUR : table mère de l'héritage XT
-- Regroupe les attributs communs (email, mdp, role)
-- Le champ "role" détermine le type : admin, pilote, etudiant
CREATE TABLE utilisateur (
    id_utilisateur  INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(50)     NOT NULL,
    prenom          VARCHAR(50)     NOT NULL,
    email           VARCHAR(100)    NOT NULL UNIQUE,
    mot_de_passe    VARCHAR(255)    NOT NULL COMMENT 'Stocké hashé via password_hash()',
    telephone       VARCHAR(20)     DEFAULT NULL,
    role            ENUM('admin', 'pilote', 'etudiant') NOT NULL,
    date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actif           BOOLEAN         NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;


-- ENTREPRISE : entité indépendante (pas un utilisateur)
-- Données issues de SFx 2-3-4 : nom, description, email, tel
CREATE TABLE entreprise (
    id_entreprise       INT AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(100)    NOT NULL,
    description         TEXT            DEFAULT NULL,
    email_contact       VARCHAR(100)    NOT NULL,
    telephone_contact   VARCHAR(20)     DEFAULT NULL,
    ville               VARCHAR(100)    DEFAULT NULL,
    adresse             VARCHAR(255)    DEFAULT NULL
) ENGINE=InnoDB;


-- COMPETENCE : référentiel fixe (SFx 7-9)
-- "il n'est pas nécessaire de pouvoir modifier la liste"
CREATE TABLE competence (
    id_competence   INT AUTO_INCREMENT PRIMARY KEY,
    libelle         VARCHAR(100)    NOT NULL UNIQUE
) ENGINE=InnoDB;


-- ============================================================
-- 2. ENTITÉS FILLES (héritage XT de UTILISATEUR)
-- ============================================================

-- ADMIN : entité fille sans attribut propre supplémentaire
-- Existe pour matérialiser le rôle dans la matrice des permissions
CREATE TABLE admin (
    id_admin        INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur  INT NOT NULL UNIQUE,

    CONSTRAINT fk_admin_utilisateur
        FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- PILOTE : entité fille avec attributs propres
-- centre = campus CESI, promotion = promo supervisée
CREATE TABLE pilote (
    id_pilote       INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur  INT NOT NULL UNIQUE,
    centre          VARCHAR(100)    DEFAULT NULL,
    promotion       VARCHAR(50)     DEFAULT NULL,

    CONSTRAINT fk_pilote_utilisateur
        FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- ETUDIANT : entité fille avec attributs propres
-- id_pilote vient de l'association SUPERVISER (1,1 côté étudiant)
CREATE TABLE etudiant (
    id_etudiant     INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur  INT NOT NULL UNIQUE,
    id_pilote       INT DEFAULT NULL COMMENT 'FK issue de SUPERVISER (0,1 si pas encore affecté)',
    formation       VARCHAR(100)    DEFAULT NULL,
    niveau_etude    VARCHAR(50)     DEFAULT NULL,

    CONSTRAINT fk_etudiant_utilisateur
        FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_etudiant_pilote
        FOREIGN KEY (id_pilote) REFERENCES pilote(id_pilote)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- 3. ENTITÉS DÉPENDANTES
-- ============================================================

-- OFFRE : liée à ENTREPRISE via PROPOSER (1,1 côté offre)
-- La FK id_entreprise absorbe l'association PROPOSER
CREATE TABLE offre (
    id_offre            INT AUTO_INCREMENT PRIMARY KEY,
    id_entreprise       INT NOT NULL COMMENT 'FK issue de PROPOSER',
    titre               VARCHAR(150)    NOT NULL,
    description         TEXT            DEFAULT NULL,
    base_remuneration   DECIMAL(8,2)    DEFAULT NULL COMMENT 'En euros/mois',
    date_offre          DATE            NOT NULL,
    duree               VARCHAR(50)     DEFAULT NULL COMMENT 'Ex: 4 mois, 6 mois',
    nb_places           INT             NOT NULL DEFAULT 1,
    date_publication    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_offre_entreprise
        FOREIGN KEY (id_entreprise) REFERENCES entreprise(id_entreprise)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- 4. TABLES D'ASSOCIATION (issues des associations N:M)
-- ============================================================

-- REQUERIR : OFFRE (0,N) --- COMPETENCE (0,N)
-- Table d'association pure, clé composite
CREATE TABLE offre_competence (
    id_offre        INT NOT NULL,
    id_competence   INT NOT NULL,

    PRIMARY KEY (id_offre, id_competence),

    CONSTRAINT fk_oc_offre
        FOREIGN KEY (id_offre) REFERENCES offre(id_offre)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_oc_competence
        FOREIGN KEY (id_competence) REFERENCES competence(id_competence)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- POSTULER : ETUDIANT (0,N) --- OFFRE (0,N)
-- Association avec attributs : date, statut, LM, CV (SFx 20-21)
CREATE TABLE candidature (
    id_etudiant         INT NOT NULL,
    id_offre            INT NOT NULL,
    date_candidature    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut              ENUM('en_attente', 'acceptee', 'refusee') NOT NULL DEFAULT 'en_attente',
    lettre_motivation   TEXT            DEFAULT NULL,
    cv_path             VARCHAR(255)    DEFAULT NULL COMMENT 'Chemin vers le fichier CV uploadé',

    PRIMARY KEY (id_etudiant, id_offre),

    CONSTRAINT fk_cand_etudiant
        FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_cand_offre
        FOREIGN KEY (id_offre) REFERENCES offre(id_offre)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- AJOUTER_WISHLIST : ETUDIANT (0,N) --- OFFRE (0,N)
-- Association avec attribut : date_ajout (SFx 23-25)
CREATE TABLE wishlist (
    id_etudiant     INT NOT NULL,
    id_offre        INT NOT NULL,
    date_ajout      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_etudiant, id_offre),

    CONSTRAINT fk_wl_etudiant
        FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_wl_offre
        FOREIGN KEY (id_offre) REFERENCES offre(id_offre)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- EVALUER : ETUDIANT (0,N) --- ENTREPRISE (0,N)
-- Association avec attributs : note, commentaire, date (SFx 5)
CREATE TABLE evaluation (
    id_evaluation   INT AUTO_INCREMENT PRIMARY KEY,
    id_etudiant     INT NOT NULL,
    id_entreprise   INT NOT NULL,
    note            TINYINT NOT NULL COMMENT '1 à 5',
    commentaire     TEXT            DEFAULT NULL,
    date_evaluation DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_note CHECK (note BETWEEN 1 AND 5),

    CONSTRAINT fk_eval_etudiant
        FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_eval_entreprise
        FOREIGN KEY (id_entreprise) REFERENCES entreprise(id_entreprise)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- GERER : PILOTE (0,N) --- ENTREPRISE (0,N)
-- Un pilote peut gérer plusieurs entreprises et vice versa
CREATE TABLE pilote_entreprise (
    id_pilote       INT NOT NULL,
    id_entreprise   INT NOT NULL,

    PRIMARY KEY (id_pilote, id_entreprise),

    CONSTRAINT fk_pe_pilote
        FOREIGN KEY (id_pilote) REFERENCES pilote(id_pilote)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_pe_entreprise
        FOREIGN KEY (id_entreprise) REFERENCES entreprise(id_entreprise)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- 5. INDEX POUR LES PERFORMANCES (pagination, recherche)
-- ============================================================

-- Recherche d'entreprise par nom (SFx 2)
CREATE INDEX idx_entreprise_nom ON entreprise(nom);

-- Recherche d'offre par titre et date (SFx 7)
CREATE INDEX idx_offre_titre ON offre(titre);
CREATE INDEX idx_offre_date ON offre(date_offre);
CREATE INDEX idx_offre_entreprise ON offre(id_entreprise);

-- Recherche d'étudiant par nom/prenom (SFx 16)
CREATE INDEX idx_utilisateur_nom_prenom ON utilisateur(nom, prenom);
CREATE INDEX idx_utilisateur_role ON utilisateur(role);

-- Candidatures par étudiant et par offre (SFx 21-22)
CREATE INDEX idx_cand_etudiant ON candidature(id_etudiant);
CREATE INDEX idx_cand_offre ON candidature(id_offre);

-- Évaluations par entreprise pour calcul moyenne (SFx 2)
CREATE INDEX idx_eval_entreprise ON evaluation(id_entreprise);


-- ============================================================
-- 6. VUES UTILES (données calculées - SFx 2, SFx 11)
-- ============================================================

-- Vue : moyenne des évaluations par entreprise (SFx 2)
CREATE VIEW v_entreprise_stats AS
SELECT
    e.id_entreprise,
    e.nom,
    ROUND(AVG(ev.note), 1)  AS moyenne_evaluations,
    COUNT(DISTINCT c.id_etudiant) AS nb_stagiaires_postulants
FROM entreprise e
LEFT JOIN evaluation ev ON e.id_entreprise = ev.id_entreprise
LEFT JOIN offre o ON e.id_entreprise = o.id_entreprise
LEFT JOIN candidature c ON o.id_offre = c.id_offre
GROUP BY e.id_entreprise, e.nom;


-- Vue : statistiques des offres pour le carrousel (SFx 11)
CREATE VIEW v_offre_stats AS
SELECT
    COUNT(*)                                        AS nb_total_offres,
    ROUND(AVG(nb_cand), 1)                          AS moyenne_candidatures_par_offre
FROM (
    SELECT
        o.id_offre,
        COUNT(c.id_etudiant) AS nb_cand
    FROM offre o
    LEFT JOIN candidature c ON o.id_offre = c.id_offre
    GROUP BY o.id_offre
) AS sub;


-- Vue : top offres les plus ajoutées en wishlist (SFx 11)
CREATE VIEW v_top_wishlist AS
SELECT
    o.id_offre,
    o.titre,
    e.nom AS entreprise,
    COUNT(w.id_etudiant) AS nb_ajouts_wishlist
FROM offre o
JOIN entreprise e ON o.id_entreprise = e.id_entreprise
JOIN wishlist w ON o.id_offre = w.id_offre
GROUP BY o.id_offre, o.titre, e.nom
ORDER BY nb_ajouts_wishlist DESC
LIMIT 10;


-- Vue : répartition des offres par durée (SFx 11)
CREATE VIEW v_offres_par_duree AS
SELECT
    duree,
    COUNT(*) AS nb_offres
FROM offre
WHERE duree IS NOT NULL
GROUP BY duree
ORDER BY nb_offres DESC;


-- ============================================================
-- 7. JEU DE DONNÉES DE TEST
-- ============================================================

-- Utilisateurs (mdp = password_hash('password123', PASSWORD_BCRYPT))
-- Hash simulé pour les tests
INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role) VALUES
('Dupont',  'Jean',     'admin@stageconnect.fr',    '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0601020304', 'admin'),
('Martin',  'Sophie',   'pilote1@stageconnect.fr',  '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0611223344', 'pilote'),
('Bernard', 'Pierre',   'pilote2@stageconnect.fr',  '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0622334455', 'pilote'),
('Durand',  'Alice',    'alice@etudiant.fr',        '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0633445566', 'etudiant'),
('Leroy',   'Thomas',   'thomas@etudiant.fr',       '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0644556677', 'etudiant'),
('Moreau',  'Emma',     'emma@etudiant.fr',         '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0655667788', 'etudiant');

-- Admin
INSERT INTO admin (id_utilisateur) VALUES (1);

-- Pilotes
INSERT INTO pilote (id_utilisateur, centre, promotion) VALUES
(2, 'CESI Orleans', 'Promo 2025'),
(3, 'CESI Paris', 'Promo 2025');

-- Étudiants
INSERT INTO etudiant (id_utilisateur, id_pilote, formation, niveau_etude) VALUES
(4, 1, 'Informatique',         'Bac+3'),
(5, 1, 'Informatique',         'Bac+4'),
(6, 2, 'Systèmes embarqués',   'Bac+3');

-- Entreprises
INSERT INTO entreprise (nom, description, email_contact, telephone_contact) VALUES
('TechCorp',       'Entreprise spécialisée en développement web',                        'contact@techcorp.fr',        '0140506070'),
('DataSoft',       'Éditeur de logiciels de gestion de données',                         'rh@datasoft.fr',             '0150607080'),
('CyberSec SA',    'Cabinet de conseil en cybersécurité',                                'stages@cybersec.fr',         '0160708090'),
('GreenIT',        'Solutions numériques éco-responsables',                              'recrutement@greenit.fr',     '0170809010'),
('CloudNova',      'Hébergement cloud et services SaaS pour PME',                        'stages@cloudnova.fr',        '0181920210'),
('UX Studio',      'Agence spécialisée en design UX/UI et recherche utilisateur',        'rh@uxstudio.fr',             '0191011121'),
('FinTechHub',     'Start-up innovante dans le secteur de la finance digitale',          'recrutement@fintechhub.fr',  '0201112131'),
('MobileSoft',     'Développement d\'applications mobiles iOS et Android',               'contact@mobilesoft.fr',      '0211213141'),
('DataViz Pro',    'Visualisation de données et business intelligence pour grands comptes', 'stages@datavizpro.fr',    '0221314151');

-- Compétences (référentiel fixe)
INSERT INTO competence (libelle) VALUES
('PHP'),
('JavaScript'),
('HTML/CSS'),
('MySQL'),
('Python'),
('React'),
('Cybersécurité'),
('Docker'),
('Git'),
('API REST');

-- Offres
INSERT INTO offre (id_entreprise, titre, description, base_remuneration, date_offre, duree, nb_places) VALUES
(1, 'Développeur web PHP',             'Stage en développement web full-stack PHP/MySQL',                        600.00, '2025-09-01', '6 mois', 2),
(1, 'Développeur frontend JS',         'Intégration et développement frontend JavaScript',                       550.00, '2025-09-01', '4 mois', 1),
(2, 'Data Analyst Python',             'Analyse de données et reporting avec Python',                            650.00, '2025-10-01', '6 mois', 1),
(3, 'Consultant cybersécurité',        'Audit et pentesting pour clients grands comptes',                        700.00, '2025-09-15', '5 mois', 2),
(4, 'DevOps Green IT',                 'Mise en place CI/CD et optimisation infrastructure',                     600.00, '2025-11-01', '6 mois', 1),
(5, 'Ingénieur DevOps Cloud',          'Déploiement et supervision de services cloud AWS/Azure',                 680.00, '2025-09-01', '6 mois', 2),
(6, 'Designer UX/UI',                  'Conception de maquettes et tests utilisateurs sur produits SaaS',        575.00, '2025-10-01', '4 mois', 1),
(7, 'Développeur React Native',        'Création de l\'application mobile de la plateforme de paiement',        620.00, '2025-09-15', '5 mois', 1),
(8, 'Développeur iOS Swift',           'Développement de nouvelles fonctionnalités sur l\'app iOS grand public', 590.00, '2025-10-15', '6 mois', 2),
(9, 'Data Visualisation Analyst',      'Conception de dashboards interactifs avec Power BI et Tableau',         640.00, '2025-11-01', '4 mois', 1);

-- Offre ↔ Compétences
INSERT INTO offre_competence (id_offre, id_competence) VALUES
(1, 1), (1, 3), (1, 4), (1, 9),        -- PHP, HTML/CSS, MySQL, Git
(2, 2), (2, 3), (2, 6),                 -- JS, HTML/CSS, React
(3, 5), (3, 4),                          -- Python, MySQL
(4, 7), (4, 10),                         -- Cybersécurité, API REST
(5, 8), (5, 9), (5, 10),                -- Docker, Git, API REST
(6, 8), (6, 9), (6, 10),               -- Docker, Git, API REST (Cloud DevOps)
(7, 3),                                  -- HTML/CSS (UX/UI)
(8, 2), (8, 6),                          -- JS, React (React Native)
(9, 2),                                  -- JavaScript (iOS Swift — approximation)
(10, 4), (10, 5);                        -- MySQL, Python (Data Viz)

-- Pilote ↔ Entreprise
INSERT INTO pilote_entreprise (id_pilote, id_entreprise) VALUES
(1, 1), (1, 2), (1, 3),
(2, 3), (2, 4);

-- Candidatures
INSERT INTO candidature (id_etudiant, id_offre, statut, lettre_motivation, cv_path) VALUES
(1, 1, 'en_attente',   'Motivé par le développement web...',   '/uploads/cv/alice_cv.pdf'),
(1, 3, 'en_attente',   'Passionnée par la data...',            '/uploads/cv/alice_cv.pdf'),
(2, 1, 'acceptee',     'Fort intérêt pour PHP...',             '/uploads/cv/thomas_cv.pdf'),
(2, 4, 'en_attente',   'La cybersécurité me passionne...',     '/uploads/cv/thomas_cv.pdf'),
(3, 5, 'refusee',      'Envie de DevOps...',                   '/uploads/cv/emma_cv.pdf');

-- Wishlist
INSERT INTO wishlist (id_etudiant, id_offre) VALUES
(1, 1), (1, 2), (1, 4),
(2, 3), (2, 5),
(3, 1), (3, 2);

-- Évaluations
INSERT INTO evaluation (id_etudiant, id_entreprise, note, commentaire) VALUES
(1, 1, 4, 'Très bonne ambiance, missions intéressantes'),
(2, 1, 5, 'Excellent encadrement technique'),
(3, 3, 3, 'Correct mais peu de suivi du tuteur'),
(1, 2, 4, 'Projets variés et formateurs');


-- ============================================================
-- 8. REQUÊTES DE VÉRIFICATION
-- ============================================================

-- Vérifier les stats entreprise (SFx 2)
-- SELECT * FROM v_entreprise_stats;

-- Vérifier les stats offres (SFx 11)
-- SELECT * FROM v_offre_stats;

-- Vérifier le top wishlist (SFx 11)
-- SELECT * FROM v_top_wishlist;

-- Vérifier la répartition par durée (SFx 11)
-- SELECT * FROM v_offres_par_duree;

-- Candidatures des étudiants d'un pilote (SFx 22)
-- SELECT u.nom, u.prenom, o.titre, c.statut
-- FROM candidature c
-- JOIN etudiant e ON c.id_etudiant = e.id_etudiant
-- JOIN utilisateur u ON e.id_utilisateur = u.id_utilisateur
-- JOIN offre o ON c.id_offre = o.id_offre
-- WHERE e.id_pilote = 1;

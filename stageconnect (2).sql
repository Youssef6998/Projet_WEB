-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : lun. 16 mars 2026 à 15:04
-- Version du serveur : 8.0.45-0ubuntu0.24.04.1
-- Version de PHP : 8.5.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `stageconnect`
--

-- --------------------------------------------------------

--
-- Structure de la table `admin`
--

CREATE TABLE `admin` (
  `id_admin` int NOT NULL,
  `id_utilisateur` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `admin`
--

INSERT INTO `admin` (`id_admin`, `id_utilisateur`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `candidature`
--

CREATE TABLE `candidature` (
  `id_etudiant` int NOT NULL,
  `id_offre` int NOT NULL,
  `date_candidature` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('en_attente','acceptee','refusee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `lettre_motivation` text COLLATE utf8mb4_unicode_ci,
  `cv_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chemin vers le fichier CV uploadé'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `candidature`
--

INSERT INTO `candidature` (`id_etudiant`, `id_offre`, `date_candidature`, `statut`, `lettre_motivation`, `cv_path`) VALUES
(1, 1, '2026-03-13 14:39:01', 'en_attente', 'Motivé par le développement web...', '/uploads/cv/alice_cv.pdf'),
(1, 3, '2026-03-13 14:39:01', 'en_attente', 'Passionnée par la data...', '/uploads/cv/alice_cv.pdf'),
(2, 1, '2026-03-13 14:39:01', 'acceptee', 'Fort intérêt pour PHP...', '/uploads/cv/thomas_cv.pdf'),
(2, 4, '2026-03-13 14:39:01', 'en_attente', 'La cybersécurité me passionne...', '/uploads/cv/thomas_cv.pdf');

-- --------------------------------------------------------

--
-- Structure de la table `competence`
--

CREATE TABLE `competence` (
  `id_competence` int NOT NULL,
  `libelle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `competence`
--

INSERT INTO `competence` (`id_competence`, `libelle`) VALUES
(10, 'API REST'),
(7, 'Cybersécurité'),
(8, 'Docker'),
(9, 'Git'),
(3, 'HTML/CSS'),
(2, 'JavaScript'),
(4, 'MySQL'),
(1, 'PHP'),
(5, 'Python'),
(6, 'React');

-- --------------------------------------------------------

--
-- Structure de la table `entreprise`
--

CREATE TABLE `entreprise` (
  `id_entreprise` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `email_contact` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone_contact` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `entreprise`
--

INSERT INTO `entreprise` (`id_entreprise`, `nom`, `description`, `email_contact`, `telephone_contact`, `ville`, `adresse`) VALUES
(1, 'TechCorp', 'Entreprise spécialisée en développement web', 'contact@techcorp.fr', '0140506070', NULL, NULL),
(2, 'DataSoft', 'Éditeur de logiciels de gestion de données', 'rh@datasoft.fr', '0150607080', NULL, NULL),
(3, 'CyberSec SA', 'Cabinet de conseil en cybersécurité', 'stages@cybersec.fr', '0160708090', NULL, NULL),
(4, 'GreenIT', 'Solutions numériques éco-responsables', 'recrutement@greenit.fr', '0170809010', NULL, NULL),
(5, 'CloudNova', 'Hébergement cloud et services SaaS pour PME', 'stages@cloudnova.fr', '0181920210', NULL, NULL),
(6, 'UX Studio', 'Agence spécialisée en design UX/UI et recherche utilisateur', 'rh@uxstudio.fr', '0191011121', NULL, NULL),
(7, 'FinTechHub', 'Start-up innovante dans le secteur de la finance digitale', 'recrutement@fintechhub.fr', '0201112131', NULL, NULL),
(8, 'MobileSoft', 'Développement d\'applications mobiles iOS et Android', 'contact@mobilesoft.fr', '0211213141', NULL, NULL),
(9, 'DataViz Pro', 'Visualisation de données et business intelligence pour grands comptes', 'stages@datavizpro.fr', '0221314151', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `etudiant`
--

CREATE TABLE `etudiant` (
  `id_etudiant` int NOT NULL,
  `id_utilisateur` int NOT NULL,
  `id_pilote` int DEFAULT NULL COMMENT 'FK issue de SUPERVISER (0,1 si pas encore affecté)',
  `formation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `niveau_etude` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `etudiant`
--

INSERT INTO `etudiant` (`id_etudiant`, `id_utilisateur`, `id_pilote`, `formation`, `niveau_etude`) VALUES
(1, 4, 1, 'Informatique', 'Bac+3'),
(2, 5, 1, 'Informatique', 'Bac+4');

-- --------------------------------------------------------

--
-- Structure de la table `evaluation`
--

CREATE TABLE `evaluation` (
  `id_evaluation` int NOT NULL,
  `id_etudiant` int NOT NULL,
  `id_entreprise` int NOT NULL,
  `note` tinyint NOT NULL COMMENT '1 à 5',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `date_evaluation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Déchargement des données de la table `evaluation`
--

INSERT INTO `evaluation` (`id_evaluation`, `id_etudiant`, `id_entreprise`, `note`, `commentaire`, `date_evaluation`) VALUES
(1, 1, 1, 4, 'Très bonne ambiance, missions intéressantes', '2026-03-13 14:39:01'),
(2, 2, 1, 5, 'Excellent encadrement technique', '2026-03-13 14:39:01'),
(4, 1, 2, 4, 'Projets variés et formateurs', '2026-03-13 14:39:01');

-- --------------------------------------------------------

--
-- Structure de la table `offre`
--

CREATE TABLE `offre` (
  `id_offre` int NOT NULL,
  `id_entreprise` int NOT NULL COMMENT 'FK issue de PROPOSER',
  `titre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `base_remuneration` decimal(8,2) DEFAULT NULL COMMENT 'En euros/mois',
  `date_offre` date NOT NULL,
  `duree` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex: 4 mois, 6 mois',
  `nb_places` int NOT NULL DEFAULT '1',
  `date_publication` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `offre`
--

INSERT INTO `offre` (`id_offre`, `id_entreprise`, `titre`, `description`, `base_remuneration`, `date_offre`, `duree`, `nb_places`, `date_publication`) VALUES
(1, 1, 'Développeur web PHP', 'Stage en développement web full-stack PHP/MySQL', 600.00, '2025-09-01', '6 mois', 2, '2026-03-13 14:39:01'),
(2, 1, 'Développeur frontend JS', 'Intégration et développement frontend JavaScript', 550.00, '2025-09-01', '4 mois', 1, '2026-03-13 14:39:01'),
(3, 2, 'Data Analyst Python', 'Analyse de données et reporting avec Python', 650.00, '2025-10-01', '6 mois', 1, '2026-03-13 14:39:01'),
(4, 3, 'Consultant cybersécurité', 'Audit et pentesting pour clients grands comptes', 700.00, '2025-09-15', '5 mois', 2, '2026-03-13 14:39:01'),
(5, 4, 'DevOps Green IT', 'Mise en place CI/CD et optimisation infrastructure', 600.00, '2025-11-01', '6 mois', 1, '2026-03-13 14:39:01'),
(6, 5, 'Ingénieur DevOps Cloud', 'Déploiement et supervision de services cloud AWS/Azure', 680.00, '2025-09-01', '6 mois', 2, '2026-03-16 09:59:44'),
(7, 6, 'Designer UX/UI', 'Conception de maquettes et tests utilisateurs sur produits SaaS', 575.00, '2025-10-01', '4 mois', 1, '2026-03-16 09:59:44'),
(8, 7, 'Développeur React Native', 'Création de l\'application mobile de la plateforme de paiement', 620.00, '2025-09-15', '5 mois', 1, '2026-03-16 09:59:44'),
(9, 8, 'Développeur iOS Swift', 'Développement de nouvelles fonctionnalités sur l\'app iOS grand public', 590.00, '2025-10-15', '6 mois', 2, '2026-03-16 09:59:44'),
(10, 9, 'Data Visualisation Analyst', 'Conception de dashboards interactifs avec Power BI et Tableau', 640.00, '2025-11-01', '4 mois', 1, '2026-03-16 09:59:44');

-- --------------------------------------------------------

--
-- Structure de la table `offre_competence`
--

CREATE TABLE `offre_competence` (
  `id_offre` int NOT NULL,
  `id_competence` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `offre_competence`
--

INSERT INTO `offre_competence` (`id_offre`, `id_competence`) VALUES
(1, 1),
(2, 2),
(8, 2),
(9, 2),
(1, 3),
(2, 3),
(7, 3),
(1, 4),
(3, 4),
(10, 4),
(3, 5),
(10, 5),
(2, 6),
(8, 6),
(4, 7),
(5, 8),
(6, 8),
(1, 9),
(5, 9),
(6, 9),
(4, 10),
(5, 10),
(6, 10);

-- --------------------------------------------------------

--
-- Structure de la table `pilote`
--

CREATE TABLE `pilote` (
  `id_pilote` int NOT NULL,
  `id_utilisateur` int NOT NULL,
  `centre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promotion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `pilote`
--

INSERT INTO `pilote` (`id_pilote`, `id_utilisateur`, `centre`, `promotion`) VALUES
(1, 2, 'CESI Orleans', 'Promo 2025'),
(2, 3, 'CESI Paris', 'Promo 2025');

-- --------------------------------------------------------

--
-- Structure de la table `pilote_entreprise`
--

CREATE TABLE `pilote_entreprise` (
  `id_pilote` int NOT NULL,
  `id_entreprise` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `pilote_entreprise`
--

INSERT INTO `pilote_entreprise` (`id_pilote`, `id_entreprise`) VALUES
(1, 1),
(1, 2),
(1, 3),
(2, 3),
(2, 4);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id_utilisateur` int NOT NULL,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Stocké hashé via password_hash()',
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','pilote','etudiant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actif` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id_utilisateur`, `nom`, `prenom`, `email`, `mot_de_passe`, `telephone`, `role`, `date_creation`, `actif`) VALUES
(1, 'Dupont', 'Jean', 'admin@stageconnect.fr', '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0601020304', 'admin', '2026-03-13 14:39:01', 1),
(2, 'Martin', 'Sophie', 'pilote1@stageconnect.fr', '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0611223344', 'pilote', '2026-03-13 14:39:01', 1),
(3, 'Bernard', 'Pierre', 'pilote2@stageconnect.fr', '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0622334455', 'pilote', '2026-03-13 14:39:01', 1),
(4, 'Durand', 'Alice', 'alice@etudiant.fr', '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0633445566', 'etudiant', '2026-03-13 14:39:01', 1),
(5, 'Leroy', 'Thomas', 'thomas@etudiant.fr', '$2y$10$YXNkZmFzZGZhc2RmYXNkZuK1eN3qX7H5vR8sT2wP4kL9mJ6nO', '0644556677', 'etudiant', '2026-03-13 14:39:01', 1),
(7, 'KRAISS', 'Ryad', 'ryad.kraiss@viacesi.fr', '$2y$12$uVeaCiQAGuLBJZ047oDWv.QhQZRWYkPrbDsQeeYOheOPWMnukrr0.', '0769804430', 'etudiant', '2026-03-13 16:15:13', 1);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_entreprise_stats`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_entreprise_stats` (
`id_entreprise` int
,`nom` varchar(100)
,`moyenne_evaluations` decimal(5,1)
,`nb_stagiaires_postulants` bigint
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_offres_par_duree`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_offres_par_duree` (
`duree` varchar(50)
,`nb_offres` bigint
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_offre_stats`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_offre_stats` (
`nb_total_offres` bigint
,`moyenne_candidatures_par_offre` decimal(22,1)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_top_wishlist`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_top_wishlist` (
`id_offre` int
,`titre` varchar(150)
,`entreprise` varchar(100)
,`nb_ajouts_wishlist` bigint
);

-- --------------------------------------------------------

--
-- Structure de la table `wishlist`
--

CREATE TABLE `wishlist` (
  `id_etudiant` int NOT NULL,
  `id_offre` int NOT NULL,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `wishlist`
--

INSERT INTO `wishlist` (`id_etudiant`, `id_offre`, `date_ajout`) VALUES
(1, 1, '2026-03-13 14:39:01'),
(1, 2, '2026-03-13 14:39:01'),
(1, 4, '2026-03-13 14:39:01'),
(2, 3, '2026-03-13 14:39:01'),
(2, 5, '2026-03-13 14:39:01');

-- --------------------------------------------------------

--
-- Structure de la vue `v_entreprise_stats`
--
DROP TABLE IF EXISTS `v_entreprise_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_entreprise_stats`  AS SELECT `e`.`id_entreprise` AS `id_entreprise`, `e`.`nom` AS `nom`, round(avg(`ev`.`note`),1) AS `moyenne_evaluations`, count(distinct `c`.`id_etudiant`) AS `nb_stagiaires_postulants` FROM (((`entreprise` `e` left join `evaluation` `ev` on((`e`.`id_entreprise` = `ev`.`id_entreprise`))) left join `offre` `o` on((`e`.`id_entreprise` = `o`.`id_entreprise`))) left join `candidature` `c` on((`o`.`id_offre` = `c`.`id_offre`))) GROUP BY `e`.`id_entreprise`, `e`.`nom` ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_offres_par_duree`
--
DROP TABLE IF EXISTS `v_offres_par_duree`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_offres_par_duree`  AS SELECT `offre`.`duree` AS `duree`, count(0) AS `nb_offres` FROM `offre` WHERE (`offre`.`duree` is not null) GROUP BY `offre`.`duree` ORDER BY `nb_offres` DESC ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_offre_stats`
--
DROP TABLE IF EXISTS `v_offre_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_offre_stats`  AS SELECT count(0) AS `nb_total_offres`, round(avg(`sub`.`nb_cand`),1) AS `moyenne_candidatures_par_offre` FROM (select `o`.`id_offre` AS `id_offre`,count(`c`.`id_etudiant`) AS `nb_cand` from (`offre` `o` left join `candidature` `c` on((`o`.`id_offre` = `c`.`id_offre`))) group by `o`.`id_offre`) AS `sub` ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_top_wishlist`
--
DROP TABLE IF EXISTS `v_top_wishlist`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_top_wishlist`  AS SELECT `o`.`id_offre` AS `id_offre`, `o`.`titre` AS `titre`, `e`.`nom` AS `entreprise`, count(`w`.`id_etudiant`) AS `nb_ajouts_wishlist` FROM ((`offre` `o` join `entreprise` `e` on((`o`.`id_entreprise` = `e`.`id_entreprise`))) join `wishlist` `w` on((`o`.`id_offre` = `w`.`id_offre`))) GROUP BY `o`.`id_offre`, `o`.`titre`, `e`.`nom` ORDER BY `nb_ajouts_wishlist` DESC LIMIT 0, 10 ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `id_utilisateur` (`id_utilisateur`);

--
-- Index pour la table `candidature`
--
ALTER TABLE `candidature`
  ADD PRIMARY KEY (`id_etudiant`,`id_offre`),
  ADD KEY `idx_cand_etudiant` (`id_etudiant`),
  ADD KEY `idx_cand_offre` (`id_offre`);

--
-- Index pour la table `competence`
--
ALTER TABLE `competence`
  ADD PRIMARY KEY (`id_competence`),
  ADD UNIQUE KEY `libelle` (`libelle`);

--
-- Index pour la table `entreprise`
--
ALTER TABLE `entreprise`
  ADD PRIMARY KEY (`id_entreprise`),
  ADD KEY `idx_entreprise_nom` (`nom`);

--
-- Index pour la table `etudiant`
--
ALTER TABLE `etudiant`
  ADD PRIMARY KEY (`id_etudiant`),
  ADD UNIQUE KEY `id_utilisateur` (`id_utilisateur`),
  ADD KEY `fk_etudiant_pilote` (`id_pilote`);

--
-- Index pour la table `evaluation`
--
ALTER TABLE `evaluation`
  ADD PRIMARY KEY (`id_evaluation`),
  ADD KEY `fk_eval_etudiant` (`id_etudiant`),
  ADD KEY `idx_eval_entreprise` (`id_entreprise`);

--
-- Index pour la table `offre`
--
ALTER TABLE `offre`
  ADD PRIMARY KEY (`id_offre`),
  ADD KEY `idx_offre_titre` (`titre`),
  ADD KEY `idx_offre_date` (`date_offre`),
  ADD KEY `idx_offre_entreprise` (`id_entreprise`);

--
-- Index pour la table `offre_competence`
--
ALTER TABLE `offre_competence`
  ADD PRIMARY KEY (`id_offre`,`id_competence`),
  ADD KEY `fk_oc_competence` (`id_competence`);

--
-- Index pour la table `pilote`
--
ALTER TABLE `pilote`
  ADD PRIMARY KEY (`id_pilote`),
  ADD UNIQUE KEY `id_utilisateur` (`id_utilisateur`);

--
-- Index pour la table `pilote_entreprise`
--
ALTER TABLE `pilote_entreprise`
  ADD PRIMARY KEY (`id_pilote`,`id_entreprise`),
  ADD KEY `fk_pe_entreprise` (`id_entreprise`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id_utilisateur`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_utilisateur_nom_prenom` (`nom`,`prenom`),
  ADD KEY `idx_utilisateur_role` (`role`);

--
-- Index pour la table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id_etudiant`,`id_offre`),
  ADD KEY `fk_wl_offre` (`id_offre`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `competence`
--
ALTER TABLE `competence`
  MODIFY `id_competence` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `entreprise`
--
ALTER TABLE `entreprise`
  MODIFY `id_entreprise` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `etudiant`
--
ALTER TABLE `etudiant`
  MODIFY `id_etudiant` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `evaluation`
--
ALTER TABLE `evaluation`
  MODIFY `id_evaluation` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `offre`
--
ALTER TABLE `offre`
  MODIFY `id_offre` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `pilote`
--
ALTER TABLE `pilote`
  MODIFY `id_pilote` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_utilisateur` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_utilisateur` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `candidature`
--
ALTER TABLE `candidature`
  ADD CONSTRAINT `fk_cand_etudiant` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiant` (`id_etudiant`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cand_offre` FOREIGN KEY (`id_offre`) REFERENCES `offre` (`id_offre`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `etudiant`
--
ALTER TABLE `etudiant`
  ADD CONSTRAINT `fk_etudiant_pilote` FOREIGN KEY (`id_pilote`) REFERENCES `pilote` (`id_pilote`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_etudiant_utilisateur` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `evaluation`
--
ALTER TABLE `evaluation`
  ADD CONSTRAINT `fk_eval_entreprise` FOREIGN KEY (`id_entreprise`) REFERENCES `entreprise` (`id_entreprise`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_eval_etudiant` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiant` (`id_etudiant`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `offre`
--
ALTER TABLE `offre`
  ADD CONSTRAINT `fk_offre_entreprise` FOREIGN KEY (`id_entreprise`) REFERENCES `entreprise` (`id_entreprise`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `offre_competence`
--
ALTER TABLE `offre_competence`
  ADD CONSTRAINT `fk_oc_competence` FOREIGN KEY (`id_competence`) REFERENCES `competence` (`id_competence`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_oc_offre` FOREIGN KEY (`id_offre`) REFERENCES `offre` (`id_offre`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `pilote`
--
ALTER TABLE `pilote`
  ADD CONSTRAINT `fk_pilote_utilisateur` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `pilote_entreprise`
--
ALTER TABLE `pilote_entreprise`
  ADD CONSTRAINT `fk_pe_entreprise` FOREIGN KEY (`id_entreprise`) REFERENCES `entreprise` (`id_entreprise`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pe_pilote` FOREIGN KEY (`id_pilote`) REFERENCES `pilote` (`id_pilote`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `fk_wl_etudiant` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiant` (`id_etudiant`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wl_offre` FOREIGN KEY (`id_offre`) REFERENCES `offre` (`id_offre`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

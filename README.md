# Stage Finder — Documentation complète

Application web de gestion d'offres de stages, de candidatures étudiantes et d'évaluations d'entreprises.

---

## Table des matières

1. [Stack technique](#1-stack-technique)
2. [Structure du projet](#2-structure-du-projet)
3. [Prérequis](#3-prérequis)
4. [Installation pas à pas](#4-installation-pas-à-pas)
5. [Configuration Apache](#5-configuration-apache)
6. [Configuration HTTPS (optionnel)](#6-configuration-https-optionnel)
7. [Comptes de test](#7-comptes-de-test)
8. [Rôles et permissions](#8-rôles-et-permissions)
9. [Routes disponibles](#9-routes-disponibles)
10. [Base de données](#10-base-de-données)
11. [Uploads de fichiers](#11-uploads-de-fichiers)
12. [SEO — robots.txt et sitemap.xml](#12-seo--robotstxt-et-sitemapxml)
13. [Tests unitaires (PHPUnit)](#13-tests-unitaires-phpunit)
14. [Notes importantes](#14-notes-importantes)

---

## 1. Stack technique

| Couche | Technologie |
|--------|-------------|
| Langage back-end | PHP 8.1+ (testé sur 8.4) |
| Base de données | MySQL 8.0+ |
| Templating | Twig 3.23+ |
| Serveur web | Apache 2.4 |
| Gestion des dépendances | Composer |
| Tests unitaires | PHPUnit 11 |
| Front-end | HTML5, CSS3, JavaScript vanilla (aucun framework) |

---

## 2. Structure du projet

```
Projet_WEB/
├── index.php                        # Point d'entrée unique
├── composer.json                    # Dépendances PHP (Twig)
├── composer.lock
├── stageconnect.sql                 # Schéma + données de test de la BDD
├── robots.txt                       # Directives pour les moteurs de recherche
├── sitemap.xml                      # Plan du site pour l'indexation
│
├── src/
│   ├── Database.php                 # Connexion PDO (singleton) ← modifier ici les credentials
│   ├── Router.php                   # Dispatcher d'URLs (?uri=...)
│   ├── Controllers/
│   │   ├── BaseController.php       # Classe abstraite parente
│   │   ├── AuthController.php       # Connexion / inscription / déconnexion
│   │   ├── StageController.php      # Offres de stages (CRUD, candidatures, favoris)
│   │   ├── EntrepriseController.php # Entreprises (CRUD)
│   │   ├── ProfilController.php     # Profil utilisateur
│   │   ├── PiloteController.php     # Gestion étudiants/pilotes, téléchargement CV
│   │   ├── EvaluationController.php # Évaluations d'entreprises
│   │   └── StatsController.php      # Statistiques
│   └── Models/
│       ├── UserModel.php            # Utilisateurs, rôles, authentification
│       ├── StageModel.php           # Offres, candidatures, wishlist
│       ├── EntrepriseModel.php      # Entreprises
│       ├── EvaluationModel.php      # Évaluations / avis
│       └── StatsModel.php           # Requêtes statistiques
│
├── templates/                       # Templates Twig
│   ├── base.twig.html               # Layout maître (header, nav, footer)
│   ├── 404.twig.html
│   ├── mentions.twig.html
│   ├── nous.twig.html
│   ├── profil.twig.html
│   ├── stats.twig.html
│   ├── auth/
│   │   ├── connexion.twig.html
│   │   └── inscription.twig.html
│   ├── stages/
│   │   ├── cherche_stage.twig.html  # Page d'accueil / recherche
│   │   ├── stages.twig.html         # Liste des offres
│   │   ├── offre.twig.html          # Détail d'une offre + formulaire de candidature
│   │   ├── creer_offre.twig.html
│   │   ├── modifier_offre.twig.html
│   │   └── stats_offres.twig.html
│   ├── entreprises/
│   │   ├── cherche_entreprises.twig.html
│   │   ├── entreprise.twig.html
│   │   ├── entreprise_list.twig.html
│   │   ├── creer_entreprise.twig.html
│   │   └── modifier_entreprise.twig.html
│   └── admin/
│       ├── etudiant_list.twig.html
│       ├── etudiant_offres.twig.html  # Candidatures d'un étudiant (vue pilote/admin)
│       ├── modifier_etudiant.twig.html
│       ├── pilote_list.twig.html
│       ├── modifier_pilote.twig.html
│       ├── creer_compte_pilote.twig.html
│       ├── evaluation_list.twig.html
│       ├── evaluation_detail.twig.html
│       └── Avis.twig.html
│
├── assets/
│   ├── css/
│   │   └── variables.css            # Variables CSS globales (couleurs, espacements)
│   ├── style-stage.css
│   ├── style-acceuil.css
│   ├── profil.css
│   ├── stats.css
│   ├── offre.css
│   ├── evaluation.css
│   ├── connexion.css
│   ├── inscription.css
│   ├── pilote_list.css
│   ├── favoris.css
│   ├── avis.css
│   ├── confirm-modal.css
│   ├── mentions.css
│   ├── nous.css
│   └── js/
│       ├── main.js
│       ├── favoris.js               # Toggle wishlist (AJAX)
│       ├── CV.js                    # Gestion upload CV
│       ├── confirm-modal.js         # Modale de confirmation (suppression)
│       ├── menu-burger.js           # Menu mobile
│       ├── bouton-haut.js           # Scroll to top
│       └── bouton-retour.js         # Bouton retour
│
└── uploads/
    └── cv/                          # CVs uploadés par les étudiants (non versionné)
```

---

## 3. Prérequis

- **PHP** ≥ 8.1 avec les extensions : `pdo`, `pdo_mysql`, `fileinfo`, `mbstring`, `xml`, `dom`
- **MySQL** ≥ 8.0
- **Apache** 2.4
- **Composer**
- **Git** (optionnel)

> Les extensions `mbstring`, `xml` et `dom` sont requises par PHPUnit. Sur Ubuntu/Debian :
> ```bash
> sudo apt-get install -y php8.4-xml php8.4-mbstring
> ```

---

## 4. Installation pas à pas

### Étape 1 — Récupérer le projet

```bash
git clone <url-du-repo> /var/www/Projet_WEB
cd /var/www/Projet_WEB
```

### Étape 2 — Installer les dépendances PHP

```bash
composer install
```

### Étape 3 — Créer la base de données

```bash
mysql -u root -p -e "CREATE DATABASE stageconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p stageconnect < stageconnect.sql
```

### Étape 4 — Configurer les credentials de base de données

Ouvrir `src/Database.php` et modifier :

```php
private static string $host     = 'localhost';
private static string $dbname   = 'stageconnect';
private static string $user     = 'votre_user_mysql';
private static string $password = 'votre_mot_de_passe_mysql';
```

### Étape 5 — Créer le dossier uploads et définir les permissions

```bash
mkdir -p /var/www/Projet_WEB/uploads/cv
sudo chown -R www-data:www-data /var/www/Projet_WEB/uploads
sudo chmod -R 775 /var/www/Projet_WEB/uploads
```

### Étape 6 — Configurer Apache

```bash
sudo nano /etc/apache2/sites-available/projet-web.conf
```

Coller :

```apache
<VirtualHost *:80>
    ServerName projet-web.local
    ServerAlias www.projet-web.local
    DocumentRoot /var/www/Projet_WEB

    <Directory /var/www/Projet_WEB>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/projet_web_error.log
    CustomLog ${APACHE_LOG_DIR}/projet_web_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite projet-web.conf
sudo a2enmod rewrite
sudo apache2ctl configtest
sudo systemctl restart apache2
```

### Étape 7 — Ajouter le domaine local

**Linux / Mac :**
```bash
echo "127.0.0.1    projet-web.local" | sudo tee -a /etc/hosts
```

**Windows** — fichier `C:\Windows\System32\drivers\etc\hosts` (bloc-notes en administrateur) :
```
127.0.0.1    projet-web.local
```

### Étape 8 — Accéder au site

```
http://projet-web.local
```

---

## 5. Configuration Apache

**Modules requis :**
```bash
sudo a2enmod rewrite
sudo a2enmod php8.x    # remplacer x par votre version (ex: php8.3)
```

**Vérifications utiles :**
```bash
sudo apache2ctl configtest          # doit retourner "Syntax OK"
sudo systemctl status apache2
tail -f /var/log/apache2/projet_web_error.log
```

---

## 6. Configuration HTTPS (optionnel)

```bash
# Générer un certificat auto-signé
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/projet-web.local.key \
  -out /etc/ssl/certs/projet-web.local.crt \
  -subj "/CN=projet-web.local"

sudo a2enmod ssl
sudo nano /etc/apache2/sites-available/projet-web-ssl.conf
```

Contenu :

```apache
<VirtualHost *:443>
    ServerName projet-web.local
    DocumentRoot /var/www/Projet_WEB

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/projet-web.local.crt
    SSLCertificateKeyFile /etc/ssl/private/projet-web.local.key

    <Directory /var/www/Projet_WEB>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/projet_web_error.log
    CustomLog ${APACHE_LOG_DIR}/projet_web_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite projet-web-ssl.conf
sudo apache2ctl configtest && sudo systemctl restart apache2
```

Accès : `https://projet-web.local`
Le navigateur affichera un avertissement pour le certificat auto-signé — cliquer sur "Avancé" puis "Continuer".

---

## 7. Comptes de test

### Administrateur

| Email | Mot de passe | Nom |
|-------|-------------|-----|
| `admin@stageconnect.fr` | `password` | Jean Dupont |

> Si la connexion échoue, réinitialiser le mot de passe directement en base :
> ```sql
> UPDATE utilisateur
> SET mot_de_passe = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
> WHERE email = 'admin@stageconnect.fr';
> ```

### Pilotes

| Email | Mot de passe | Nom |
|-------|-------------|-----|
| `pilote1@stageconnect.fr` | `password` | Sophie Martin |
| `pilote2@stageconnect.fr` | `password` | Pierre Bernard |

> Même procédure si la connexion échoue (remplacer l'email dans la requête SQL ci-dessus).

### Étudiants (mot de passe : **`password`**)

| Email | Nom |
|-------|-----|
| `lucas.martin@cesi.fr` | Lucas Martin |
| `emma.dubois@cesi.fr` | Emma Dubois |
| `noah.roux@cesi.fr` | Noah Roux |
| `chloe.fournier@cesi.fr` | Chloé Fournier |
| `hugo.moreau@cesi.fr` | Hugo Moreau |
| `lea.lefevre@cesi.fr` | Léa Lefèvre |

---

## 8. Rôles et permissions

### `etudiant`
- Consulter et rechercher les offres (filtres : domaine, ville, durée, compétence — tri par date ou ordre alphabétique)
- Postuler à une offre (upload CV + lettre de motivation)
- Gérer sa wishlist (favoris)
- Consulter et modifier son profil (nom, prénom, email, téléphone, formation, niveau d'étude, mot de passe)
- Supprimer son compte

### `pilote`
- Toutes les actions étudiant +
- Créer, modifier, supprimer des offres de stages
- Créer, modifier, supprimer des entreprises
- Consulter les étudiants qui lui sont affectés
- Voir les candidatures d'un étudiant (offres, lettres de motivation, CV)
- Télécharger le CV d'un étudiant
- Créer et consulter des évaluations d'entreprises (note 1-5 + commentaire)
- Consulter les statistiques des offres

### `admin`
- Toutes les actions pilote +
- Créer, modifier, supprimer des comptes pilotes
- Affecter / retirer des étudiants à un pilote
- Accès complet à l'administration

---

## 9. Routes disponibles

Pattern d'URL : `http://projet-web.local/?uri=<route>`

### Pages publiques

| URI | Description |
|-----|-------------|
| *(vide)* ou `cherche-stage` | Page d'accueil — recherche d'offres |
| `stages` | Liste de toutes les offres |
| `offre&id=X` | Détail d'une offre |
| `entreprises` | Liste des entreprises |
| `entreprise&id=X` | Détail d'une entreprise |
| `stats` | Statistiques générales |
| `nous` | À propos |
| `mentions` | Mentions légales |
| `login` | Connexion |

### Pages avec connexion requise

| URI | Rôle | Description |
|-----|------|-------------|
| `profil` | Tous | Profil utilisateur |
| `candidater` (POST) | Étudiant | Postuler à une offre |
| `wishlist-toggle` (POST) | Étudiant | Ajouter/retirer un favori |
| `offre_create` | Admin/Pilote | Créer une offre |
| `offre_update&id=X` | Admin/Pilote | Modifier une offre |
| `offre_delete` (POST) | Admin/Pilote | Supprimer une offre |
| `entreprise_list` | Admin/Pilote | Gestion des entreprises |
| `entreprise_create` | Admin/Pilote | Créer une entreprise |
| `entreprise_update&id=X` | Admin/Pilote | Modifier une entreprise |
| `etudiant_list` | Admin/Pilote | Liste des étudiants |
| `etudiant_offres&id=X` | Admin/Pilote | Candidatures d'un étudiant |
| `cv_download&id_etudiant=X&id_offre=Y` | Admin/Pilote | Télécharger le CV d'un étudiant |
| `evaluation_list` | Admin/Pilote | Liste des évaluations |
| `evaluation_detail&id=X` | Admin/Pilote | Détail d'une évaluation |
| `avis_create` | Admin/Pilote | Créer une évaluation |
| `stats_offres` | Admin/Pilote | Statistiques des offres (paginées) |
| `pilote_list` | Admin | Gestion des pilotes |
| `pilote_update&id=X` | Admin | Modifier un pilote |
| `register` | Admin | Créer un compte |

---

## 10. Base de données

**Nom :** `stageconnect` | **Encodage :** `utf8mb4` | **Fichier :** `stageconnect.sql`

### Tables

| Table | Description |
|-------|-------------|
| `utilisateur` | Tous les comptes (email unique, mot de passe bcrypt) |
| `admin` | Liaison utilisateur → rôle admin |
| `pilote` | Liaison utilisateur → rôle pilote |
| `etudiant` | Liaison utilisateur → rôle étudiant |
| `entreprise` | Entreprises (nom, description, contact, ville) |
| `offre` | Offres de stages (titre, domaine, durée, rémunération, dates) |
| `candidature` | Candidatures (statut : en_attente / acceptee / refusee, lettre, chemin CV) |
| `evaluation` | Avis sur les entreprises (note 1-5, commentaire) |
| `wishlist` | Favoris étudiants (many-to-many offre ↔ étudiant) |
| `competence` | Compétences (PHP, JS, Python, MySQL, React, HTML/CSS, Docker, Git, Cybersécurité, API REST) |
| `offre_competence` | Compétences requises par une offre |
| `pilote_entreprise` | Entreprises suivies par un pilote |
| `pilote_etudiant` | Étudiants supervisés par un pilote |

### Vues SQL

| Vue | Description |
|-----|-------------|
| `v_offre_stats` | Nombre total d'offres et candidatures moyennes |
| `v_offres_par_duree` | Répartition des offres par durée |
| `v_entreprise_stats` | Note moyenne et nombre de candidats par entreprise |
| `v_top_wishlist` | Top 10 des offres les plus mises en favoris |

---

## 11. Uploads de fichiers

Les CVs sont stockés dans `uploads/cv/`.

**Format :** `cv_<id_etudiant>_<id_offre>_<timestamp>.<extension>`  
**Extensions autorisées :** `.pdf`, `.doc`, `.docx`

> Le dossier `uploads/` est dans `.gitignore` — il doit être créé manuellement à chaque nouvelle installation (voir Étape 5).

---

## 12. SEO — robots.txt et sitemap.xml

- **`/robots.txt`** : autorise les pages publiques, bloque les pages privées et le dossier `uploads/`
- **`/sitemap.xml`** : liste les 6 pages publiques indexables avec priorité et fréquence de mise à jour

---

## 13. Tests unitaires (PHPUnit)

Le projet inclut une suite de tests unitaires couvrant `StatsController`.

### Prérequis système

PHPUnit nécessite les extensions PHP suivantes :

```bash
sudo apt-get install -y php8.4-xml php8.4-mbstring
```

> Adapter `8.4` à votre version PHP (`php --version` pour vérifier).

### Installation des dépendances de test

PHPUnit est déclaré dans `composer.json` en tant que dépendance de développement. Il s'installe avec :

```bash
composer install
```

Ou si `vendor/` est déjà présent mais que PHPUnit est absent :

```bash
composer install --ignore-platform-reqs
```

### Lancer les tests

Depuis la racine du projet :

```bash
composer test
```

Résultat attendu :

```
PHPUnit 11.x.x by Sebastian Bergmann and contributors.

Runtime: PHP 8.4.x

.............                                             13 / 13 (100%)

Time: 00:00.xxx, Memory: xx.xx MB

Stats Controller
   Show redirectVersLogin siNonConnecte
   Show redirectVersLogin siEtudiant
   Show retourneHtml siAdmin
   Show retourneHtml siPilote
   Show passesNbOffres auTemplate
   Show passesMoyCandidatures auTemplate
   Show passesRepartition auTemplate
   Show passesTopWishlist auTemplate
   Show passesUri stats auTemplate
   Show appelleGetTopWishlist avecLimite5
   Show appelleGetNbOffresTotal uneFois
   Show appelleGetMoyenneCandidatures uneFois
   Show appelleGetRepartitionParDuree uneFois

OK (13 tests, 28 assertions)
```

### Ce qui est testé

Les tests se trouvent dans `tests/StatsControllerTest.php`.

**Contrôle d'accès** — vérification que les rôles non autorisés sont redirigés :

| Test | Scénario |
|------|----------|
| `test_show_redirectVersLogin_siNonConnecte` | Aucune session → redirection vers `/login` |
| `test_show_redirectVersLogin_siEtudiant` | Rôle `etudiant` → accès refusé |

**Accès autorisé** — vérification que les rôles admin et pilote accèdent bien à la page :

| Test | Scénario |
|------|----------|
| `test_show_retourneHtml_siAdmin` | Rôle `admin` → retourne du HTML |
| `test_show_retourneHtml_siPilote` | Rôle `pilote` → retourne du HTML |

**Données transmises au template** — vérification que chaque clé est bien passée à Twig :

| Test | Clé vérifiée |
|------|-------------|
| `test_show_passesNbOffres_auTemplate` | `nb_offres` |
| `test_show_passesMoyCandidatures_auTemplate` | `moy_candidatures` |
| `test_show_passesRepartition_auTemplate` | `repartition` |
| `test_show_passesTopWishlist_auTemplate` | `top_wishlist` |
| `test_show_passesUri_stats_auTemplate` | `uri = 'stats'` |

**Appels au modèle** — vérification que chaque méthode du modèle est bien appelée :

| Test | Méthode vérifiée |
|------|-----------------|
| `test_show_appelleGetTopWishlist_avecLimite5` | `getTopWishlist(5)` avec la limite exacte |
| `test_show_appelleGetNbOffresTotal_uneFois` | `getNbOffresTotal()` appelé 1 fois |
| `test_show_appelleGetMoyenneCandidatures_uneFois` | `getMoyenneCandidaturesParOffre()` appelé 1 fois |
| `test_show_appelleGetRepartitionParDuree_uneFois` | `getRepartitionParDuree()` appelé 1 fois |

### Architecture des tests

- **`tests/bootstrap.php`** : initialise la session, définit un stub `Database` qui empêche toute connexion réelle à MySQL, puis charge les classes sources.
- **`TestableStatsController`** : sous-classe de `StatsController` définie dans le fichier de test, qui surcharge `redirect()` pour lever une `RuntimeException` au lieu d'appeler `header()+exit()`. Cela permet de tester les refus d'accès sans terminer le processus PHP.
- **`phpunit.xml`** : configuration PHPUnit (bootstrap, couleurs, répertoire des tests).

---

## 14. Notes importantes

- Les mots de passe sont hashés avec `password_hash(PASSWORD_DEFAULT)` (bcrypt)
- Les credentials BDD sont dans `src/Database.php` — à adapter sur chaque machine
- Le téléchargement de CV passe par un endpoint PHP sécurisé : le chemin du fichier n'est jamais exposé au client
- Le mode debug Twig et `display_errors` sont activés (environnement de développement) — à désactiver en production
- Architecture MVC sans framework : routeur unique via `?uri=`, Controllers → Models → Templates Twig

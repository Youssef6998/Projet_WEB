#!/bin/bash
# ============================================================
# Script de configuration MySQL pour accès distant (WSL2/LAMP)
# À exécuter sur la machine qui héberge la BDD
# Usage : sudo bash setup_mysql_remote.sh
# ============================================================

set -e

# ── Couleurs ────────────────────────────────────────────────
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Erreur : lance ce script avec sudo${NC}"
  exit 1
fi

echo -e "${GREEN}=== Configuration MySQL pour accès distant ===${NC}"

# ── 1. Modifier bind-address ─────────────────────────────────
MYSQL_CONF="/etc/mysql/mysql.conf.d/mysqld.cnf"

if [ ! -f "$MYSQL_CONF" ]; then
  echo -e "${RED}Fichier de config MySQL introuvable : $MYSQL_CONF${NC}"
  exit 1
fi

echo -e "${YELLOW}[1/4] Modification du bind-address...${NC}"
sed -i 's/^bind-address\s*=.*/bind-address = 0.0.0.0/' "$MYSQL_CONF"
echo "      bind-address = 0.0.0.0  ✓"

# ── 2. Redémarrer MySQL ──────────────────────────────────────
echo -e "${YELLOW}[2/4] Redémarrage de MySQL...${NC}"
service mysql restart
echo "      MySQL redémarré ✓"

# ── 3. Créer l'utilisateur distant ──────────────────────────
echo -e "${YELLOW}[3/4] Création de l'utilisateur MySQL distant...${NC}"

read -p "      Nom de la BDD [stageconnect] : " DBNAME
DBNAME=${DBNAME:-stageconnect}

read -p "      Nom d'utilisateur distant [partage_user] : " DBUSER
DBUSER=${DBUSER:-partage_user}

read -s -p "      Mot de passe : " DBPASS
echo ""

mysql -u root <<EOF
CREATE USER IF NOT EXISTS '${DBUSER}'@'%' IDENTIFIED BY '${DBPASS}';
GRANT ALL PRIVILEGES ON \`${DBNAME}\`.* TO '${DBUSER}'@'%';
FLUSH PRIVILEGES;
EOF

echo "      Utilisateur '${DBUSER}' créé avec accès sur '${DBNAME}' ✓"

# ── 4. Afficher l'IP WSL2 ────────────────────────────────────
WSL_IP=$(hostname -I | awk '{print $1}')
echo -e "${YELLOW}[4/4] Informations de connexion :${NC}"
echo ""
echo -e "  ${GREEN}IP WSL2         :${NC} $WSL_IP"
echo -e "  ${GREEN}BDD             :${NC} $DBNAME"
echo -e "  ${GREEN}Utilisateur     :${NC} $DBUSER"
echo -e "  ${GREEN}Port            :${NC} 3306"
echo ""
echo -e "${YELLOW}⚠ Lance maintenant setup_windows.ps1 en PowerShell admin sur Windows${NC}"
echo -e "   avec l'IP WSL2 : ${GREEN}$WSL_IP${NC}"
echo ""

# ── Générer le Database.php mis à jour ───────────────────────
read -p "Générer un Database.php avec ces paramètres ? [o/N] : " GEN
if [[ "$GEN" =~ ^[oO]$ ]]; then
  WIN_IP=$(ip route | grep default | awk '{print $3}' | head -1)
  # L'IP Windows dans WSL2 est la passerelle par défaut
  cat > /var/www/Projet_WEB/src/Database.php <<PHPEOF
<?php

class Database {
    private static ?PDO \$instance = null;

    // ⚠ Remplace par l'IP Windows du serveur (ipconfig sur Windows)
    private static string \$host     = '${WIN_IP}';
    private static string \$dbname   = '${DBNAME}';
    private static string \$user     = '${DBUSER}';
    private static string \$password = '${DBPASS}';

    public static function getConnection(): PDO {
        if (self::\$instance === null) {
            \$dsn = 'mysql:host=' . self::\$host . ';dbname=' . self::\$dbname . ';charset=utf8mb4';
            self::\$instance = new PDO(\$dsn, self::\$user, self::\$password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::\$instance;
    }
}
PHPEOF
  echo -e "  ${GREEN}Database.php mis à jour ✓${NC}"
  echo -e "  ${YELLOW}Remplace l'IP par ton IP Windows (ipconfig)${NC}"
fi

echo -e "${GREEN}=== Terminé ===${NC}"

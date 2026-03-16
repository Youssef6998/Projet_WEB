# ============================================================
# Script PowerShell - Port forwarding WSL2 -> MySQL
# À exécuter en PowerShell ADMINISTRATEUR sur Windows
# Usage : .\setup_windows.ps1 -WslIp 172.x.x.x
# ============================================================

param(
    [Parameter(Mandatory=$false)]
    [string]$WslIp = ""
)

# Récupère l'IP WSL2 automatiquement si non fournie
if (-not $WslIp) {
    $WslIp = (wsl hostname -I).Trim().Split(" ")[0]
}

if (-not $WslIp) {
    Write-Host "Impossible de détecter l'IP WSL2. Passe-la en parametre :" -ForegroundColor Red
    Write-Host "  .\setup_windows.ps1 -WslIp 172.x.x.x" -ForegroundColor Yellow
    exit 1
}

Write-Host "=== Configuration port forwarding WSL2 ===" -ForegroundColor Green
Write-Host "  IP WSL2 detectee : $WslIp" -ForegroundColor Cyan

# ── 1. Supprimer l'ancienne règle si elle existe ─────────────
$existing = netsh interface portproxy show v4tov4 | Select-String "3306"
if ($existing) {
    Write-Host "[1/3] Suppression de l'ancienne regle portproxy..." -ForegroundColor Yellow
    netsh interface portproxy delete v4tov4 listenport=3306 listenaddress=0.0.0.0 | Out-Null
}

# ── 2. Ajouter le port forwarding ───────────────────────────
Write-Host "[1/3] Ajout du port forwarding 3306 -> WSL2..." -ForegroundColor Yellow
netsh interface portproxy add v4tov4 `
    listenport=3306 `
    listenaddress=0.0.0.0 `
    connectport=3306 `
    connectaddress=$WslIp

Write-Host "      Port forwarding configure ✓" -ForegroundColor Green

# ── 3. Règle pare-feu ────────────────────────────────────────
Write-Host "[2/3] Configuration du pare-feu Windows..." -ForegroundColor Yellow

$ruleName = "MySQL WSL2 - Acces distant"
$existingRule = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue

if ($existingRule) {
    Write-Host "      Regle pare-feu deja existante, mise a jour..." -ForegroundColor Cyan
    Set-NetFirewallRule -DisplayName $ruleName -LocalPort 3306 -Protocol TCP
} else {
    New-NetFirewallRule `
        -DisplayName $ruleName `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 3306 `
        -Action Allow | Out-Null
}

Write-Host "      Regle pare-feu configuree ✓" -ForegroundColor Green

# ── 4. Afficher l'IP Windows ─────────────────────────────────
Write-Host "[3/3] Recuperation de l'IP Windows..." -ForegroundColor Yellow

$WindowsIp = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.InterfaceAlias -notmatch "Loopback|WSL|vEthernet" -and $_.IPAddress -notmatch "^169" } |
    Select-Object -First 1).IPAddress

Write-Host ""
Write-Host "=== Informations a partager avec tes camarades ===" -ForegroundColor Green
Write-Host "  IP a utiliser dans Database.php : $WindowsIp" -ForegroundColor Cyan
Write-Host "  Port                            : 3306" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Dans src/Database.php :" -ForegroundColor Yellow
Write-Host "  private static string `$host = '$WindowsIp';" -ForegroundColor White
Write-Host ""

# ── Vérification de la config portproxy ─────────────────────
Write-Host "=== Configuration portproxy active ===" -ForegroundColor Green
netsh interface portproxy show v4tov4

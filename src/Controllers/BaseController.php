<?php

/**
 * Classe de base abstraite pour tous les contrôleurs de l'application.
 *
 * Fournit les services transversaux partagés par tous les contrôleurs :
 * protection CSRF, rendu Twig, redirections HTTP et vérification des rôles
 * utilisateur stockés en session. Chaque contrôleur concret doit étendre
 * cette classe plutôt que de réimplémenter ces mécanismes.
 */
abstract class BaseController {

    /** Instance du moteur de templates Twig, injectée par le constructeur. */
    protected \Twig\Environment $twig;

    /**
     * Initialise le contrôleur avec l'instance Twig fournie par le conteneur.
     *
     * @param \Twig\Environment $twig Moteur de rendu Twig partagé par l'application.
     */
    public function __construct(\Twig\Environment $twig) {
        $this->twig = $twig;
    }

    /**
     * Génère (ou récupère) le jeton CSRF de la session courante.
     *
     * Un jeton de 64 caractères hexadécimaux est créé une seule fois par session
     * et réutilisé pour tous les formulaires de la même visite.
     *
     * @return string Jeton CSRF hexadécimal de 64 caractères.
     */
    protected function getCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            // Génère un jeton aléatoire cryptographiquement sûr au premier appel.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifie que le jeton CSRF soumis via POST correspond à celui de la session.
     *
     * Utilise hash_equals() pour une comparaison en temps constant, résistante
     * aux attaques de temporisation. Termine l'exécution avec un code 403
     * si la vérification échoue.
     *
     * @return void
     */
    protected function verifyCsrf(): void {
        $token = $_POST['csrf_token'] ?? '';
        // hash_equals évite les attaques de timing sur la comparaison de chaînes.
        if (!hash_equals($this->getCsrfToken(), $token)) {
            http_response_code(403);
            exit('Requête invalide (token CSRF manquant ou incorrect).');
        }
    }

    /**
     * Compile et retourne le rendu HTML d'un template Twig.
     *
     * Injecte automatiquement dans chaque template les données de session
     * (utilisateur connecté) et le jeton CSRF pour les formulaires.
     *
     * @param string $template Chemin du template relatif au répertoire Twig (ex. 'auth/connexion.twig.html').
     * @param array  $data     Variables supplémentaires passées au template.
     *
     * @return string HTML généré par Twig.
     */
    protected function render(string $template, array $data = []): string {
        // Expose l'utilisateur de session à tous les templates sous la clé 'session_user'.
        $data['session_user'] = $_SESSION['user'] ?? null;
        $data['csrf_token']   = $this->getCsrfToken();
        return $this->twig->render($template, $data);
    }

    /**
     * Redirige le navigateur vers l'URL indiquée et stoppe l'exécution.
     *
     * @param string $url URL absolue ou relative vers laquelle rediriger.
     *
     * @return void
     */
    protected function redirect(string $url): void {
        header("Location: $url");
        exit;
    }

    /**
     * Indique si l'utilisateur connecté a le rôle 'admin'.
     *
     * @return bool True si le rôle de session est exactement 'admin'.
     */
    protected function isAdmin(): bool {
        return ($_SESSION['user']['role'] ?? '') === 'admin';
    }

    /**
     * Indique si l'utilisateur connecté a le rôle 'pilote'.
     *
     * @return bool True si le rôle de session est exactement 'pilote'.
     */
    protected function isPilote(): bool {
        return ($_SESSION['user']['role'] ?? '') === 'pilote';
    }

    /**
     * Indique si l'utilisateur connecté a le rôle 'etudiant'.
     *
     * @return bool True si le rôle de session est exactement 'etudiant'.
     */
    protected function isEtudiant(): bool {
        return ($_SESSION['user']['role'] ?? '') === 'etudiant';
    }

    /**
     * Indique si l'utilisateur connecté est admin ou pilote.
     *
     * Utilisé pour protéger les actions de gestion (CRUD offres, entreprises,
     * comptes) qui sont accessibles aux deux rôles à la fois.
     *
     * @return bool True si le rôle de session est 'admin' ou 'pilote'.
     */
    protected function isAdminOrPilote(): bool {
        return in_array($_SESSION['user']['role'] ?? '', ['admin', 'pilote']);
    }

    /**
     * Indique si une session utilisateur est active (utilisateur connecté).
     *
     * @return bool True si la clé 'user' existe et n'est pas vide en session.
     */
    protected function isConnecte(): bool {
        return !empty($_SESSION['user']);
    }

    /**
     * Protège une action en vérifiant un rôle via un callable.
     *
     * Si le callable retourne false, l'utilisateur est redirigé vers l'URL
     * de secours (par défaut la page de connexion) et l'exécution s'arrête.
     *
     * @param callable $check    Fonction sans argument retournant un bool (ex. fn() => $this->isAdmin()).
     * @param string   $redirect URL de redirection en cas de refus (défaut : page de connexion).
     *
     * @return void
     */
    protected function requireRole(callable $check, string $redirect = '/?uri=login'): void {
        if (!$check()) {
            $this->redirect($redirect);
        }
    }
}

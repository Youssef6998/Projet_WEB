<?php

abstract class BaseController {

    protected \Twig\Environment $twig;

    public function __construct(\Twig\Environment $twig) {
        $this->twig = $twig;
    }

    protected function render(string $template, array $data = []): string {
        $data['session_user'] = $_SESSION['user'] ?? null;
        return $this->twig->render($template, $data);
    }

    protected function redirect(string $url): void {
        header("Location: $url");
        exit;
    }

    protected function isAdmin(): bool {
        return ($_SESSION['user']['role'] ?? '') === 'admin';
    }

    protected function isPilote(): bool {
        return ($_SESSION['user']['role'] ?? '') === 'pilote';
    }

    protected function isEtudiant(): bool {
        return ($_SESSION['user']['role'] ?? '') === 'etudiant';
    }

    protected function isAdminOrPilote(): bool {
        return in_array($_SESSION['user']['role'] ?? '', ['admin', 'pilote']);
    }

    protected function isConnecte(): bool {
        return !empty($_SESSION['user']);
    }

    protected function requireRole(callable $check, string $redirect = '/?uri=login'): void {
        if (!$check()) {
            $this->redirect($redirect);
        }
    }
}

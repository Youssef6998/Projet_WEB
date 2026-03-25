<?php

require_once __DIR__ . '/BaseController.php';

class ProfilController extends BaseController {

    private StageModel $model;

    public function __construct(\Twig\Environment $twig, StageModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    // GET /?uri=profil
    public function index(): string {
        $this->requireRole(fn() => $this->isConnecte());

        $user         = $_SESSION['user'];
        $candidatures = [];
        $wishlist     = [];

        if ($user['role'] === 'etudiant' && !empty($user['id_etudiant'])) {
            $candidatures = $this->model->getCandidaturesEtudiant($user['id_etudiant']);
            $wishlist     = $this->model->getWishlistEtudiant($user['id_etudiant']);
        }

        return $this->render('profil.twig.html', [
            'uri'          => 'profil',
            'user'         => $user,
            'candidatures' => $candidatures,
            'wishlist'     => $wishlist,
        ]);
    }
}

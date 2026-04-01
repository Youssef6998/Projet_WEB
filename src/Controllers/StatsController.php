<?php

class StatsController extends BaseController {

    private StatsModel $model;

    public function __construct(\Twig\Environment $twig, StatsModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    public function show(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('stats.twig.html', [
            'uri'              => 'stats',
            'nb_offres'        => $this->model->getNbOffresTotal(),
            'moy_candidatures' => $this->model->getMoyenneCandidaturesParOffre(),
            'repartition'      => $this->model->getRepartitionParDuree(),
            'top_wishlist'     => $this->model->getTopWishlist(5),
        ]);
    }
}

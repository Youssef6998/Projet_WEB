<?php

require_once __DIR__ . '/BaseController.php';

/**
 * Contrôleur du tableau de bord statistique.
 *
 * Agrège les métriques globales de la plateforme via StatsModel et les
 * transmet au template. Toutes les opérations sont en lecture seule.
 *
 * Accès restreint aux rôles admin et pilote.
 */
class StatsController extends BaseController {

    /** Modèle fournissant toutes les requêtes d'agrégation statistique. */
    private StatsModel $model;

    /**
     * Injecte le moteur Twig et le modèle de statistiques.
     *
     * @param \Twig\Environment $twig  Moteur de rendu Twig.
     * @param StatsModel        $model Modèle d'accès aux statistiques agrégées.
     */
    public function __construct(\Twig\Environment $twig, StatsModel $model) {
        parent::__construct($twig);
        $this->model = $model;
    }

    /**
     * Affiche le tableau de bord statistique.
     *
     * Collecte quatre indicateurs distincts :
     *  - Nombre total d'offres de stage publiées.
     *  - Moyenne de candidatures par offre.
     *  - Répartition des offres par durée.
     *  - Top 5 des offres les plus ajoutées en favoris.
     *
     * GET /?uri=stats
     *
     * @return string HTML du tableau de bord statistique.
     */
    public function show(): string {
        $this->requireRole(fn() => $this->isAdminOrPilote());
        return $this->render('stats.twig.html', [
            'uri'              => 'stats',
            'nb_offres'        => $this->model->getNbOffresTotal(),
            'moy_candidatures' => $this->model->getMoyenneCandidaturesParOffre(),
            // Tableau de lignes : chaque ligne contient un libellé de durée et le nombre d'offres correspondant.
            'repartition'      => $this->model->getRepartitionParDuree(),
            // Limite le classement aux 5 offres les plus mises en favoris.
            'top_wishlist'     => $this->model->getTopWishlist(5),
        ]);
    }
}

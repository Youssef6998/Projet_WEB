<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Sous-classe de StatsController dont redirect() lève une exception
 * au lieu d'appeler header() + exit, ce qui permet de tester les accès refusés.
 */
class TestableStatsController extends StatsController
{
    protected function redirect(string $url): void
    {
        throw new \RuntimeException('redirect:' . $url);
    }
}

/**
 * Tests unitaires de StatsController.
 *
 * Cas couverts :
 *  - Accès refusé  : utilisateur non connecté, rôle étudiant
 *  - Accès autorisé : rôle admin, rôle pilote
 *  - Données passées au template : nb_offres, moy_candidatures,
 *    repartition, top_wishlist, uri
 *  - Appel du modèle : getTopWishlist appelé avec la limite 5
 */
class StatsControllerTest extends TestCase
{
    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Crée un mock de StatsModel avec des valeurs par défaut.
     *
     * @return StatsModel&MockObject
     */
    private function makeModel(
        int   $nbOffres       = 10,
        float $moyCandidatures = 2.5,
        array $repartition    = [['duree' => '6 mois', 'nb' => 5, 'pct' => 100]],
        array $topWishlist    = [['titre' => 'Dev PHP', 'entreprise' => 'Acme', 'nb_favoris' => 3]]
    ): StatsModel {
        $model = $this->createMock(StatsModel::class);
        $model->method('getNbOffresTotal')->willReturn($nbOffres);
        $model->method('getMoyenneCandidaturesParOffre')->willReturn($moyCandidatures);
        $model->method('getRepartitionParDuree')->willReturn($repartition);
        $model->method('getTopWishlist')->willReturn($topWishlist);
        return $model;
    }

    /**
     * Crée un mock de Twig\Environment qui retourne une chaîne HTML factice.
     *
     * @return \Twig\Environment&MockObject
     */
    private function makeTwig(): \Twig\Environment
    {
        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('render')->willReturn('<html>stats</html>');
        return $twig;
    }

    /** Définit l'utilisateur en session. */
    private function setSessionRole(string $role): void
    {
        $_SESSION['user'] = ['role' => $role, 'nom' => 'Test', 'prenom' => 'User'];
    }

    // ── setUp / tearDown ─────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ── Tests : contrôle d'accès ─────────────────────────────────────────────

    /**
     * Un visiteur non connecté doit être redirigé vers la page de connexion.
     */
    public function test_show_redirectVersLogin_siNonConnecte(): void
    {
        $controller = new TestableStatsController($this->makeTwig(), $this->makeModel());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('redirect:/?uri=login');

        $controller->show();
    }

    /**
     * Un étudiant connecté doit être redirigé (accès refusé).
     */
    public function test_show_redirectVersLogin_siEtudiant(): void
    {
        $this->setSessionRole('etudiant');
        $controller = new TestableStatsController($this->makeTwig(), $this->makeModel());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('redirect:/?uri=login');

        $controller->show();
    }

    // ── Tests : accès autorisé ────────────────────────────────────────────────

    /**
     * Un admin peut accéder à la page et obtient du HTML en retour.
     */
    public function test_show_retourneHtml_siAdmin(): void
    {
        $this->setSessionRole('admin');
        $controller = new TestableStatsController($this->makeTwig(), $this->makeModel());

        $result = $controller->show();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Un pilote peut accéder à la page et obtient du HTML en retour.
     */
    public function test_show_retourneHtml_siPilote(): void
    {
        $this->setSessionRole('pilote');
        $controller = new TestableStatsController($this->makeTwig(), $this->makeModel());

        $result = $controller->show();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // ── Tests : données passées au template ───────────────────────────────────

    /**
     * Le template doit recevoir la clé 'nb_offres' avec la valeur du modèle.
     */
    public function test_show_passesNbOffres_auTemplate(): void
    {
        $this->setSessionRole('admin');

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->expects($this->once())
             ->method('render')
             ->with(
                 'stats.twig.html',
                 $this->callback(fn(array $data) => $data['nb_offres'] === 42)
             )
             ->willReturn('');

        $controller = new TestableStatsController($twig, $this->makeModel(nbOffres: 42));
        $controller->show();
    }

    /**
     * Le template doit recevoir la clé 'moy_candidatures' avec la valeur du modèle.
     */
    public function test_show_passesMoyCandidatures_auTemplate(): void
    {
        $this->setSessionRole('admin');

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->expects($this->once())
             ->method('render')
             ->with(
                 'stats.twig.html',
                 $this->callback(fn(array $data) => $data['moy_candidatures'] === 3.7)
             )
             ->willReturn('');

        $controller = new TestableStatsController($twig, $this->makeModel(moyCandidatures: 3.7));
        $controller->show();
    }

    /**
     * Le template doit recevoir la clé 'repartition' avec le tableau du modèle.
     */
    public function test_show_passesRepartition_auTemplate(): void
    {
        $this->setSessionRole('pilote');

        $repartition = [
            ['duree' => '3 mois', 'nb' => 8, 'pct' => 100],
            ['duree' => '6 mois', 'nb' => 4, 'pct' => 50],
        ];

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->expects($this->once())
             ->method('render')
             ->with(
                 'stats.twig.html',
                 $this->callback(fn(array $data) => $data['repartition'] === $repartition)
             )
             ->willReturn('');

        $controller = new TestableStatsController($twig, $this->makeModel(repartition: $repartition));
        $controller->show();
    }

    /**
     * Le template doit recevoir la clé 'top_wishlist' avec le tableau du modèle.
     */
    public function test_show_passesTopWishlist_auTemplate(): void
    {
        $this->setSessionRole('admin');

        $topWishlist = [
            ['titre' => 'Dev PHP', 'entreprise' => 'Acme', 'nb_favoris' => 9],
            ['titre' => 'Data Analyst', 'entreprise' => 'Beta', 'nb_favoris' => 5],
        ];

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->expects($this->once())
             ->method('render')
             ->with(
                 'stats.twig.html',
                 $this->callback(fn(array $data) => $data['top_wishlist'] === $topWishlist)
             )
             ->willReturn('');

        $controller = new TestableStatsController($twig, $this->makeModel(topWishlist: $topWishlist));
        $controller->show();
    }

    /**
     * Le template doit recevoir uri = 'stats'.
     */
    public function test_show_passesUri_stats_auTemplate(): void
    {
        $this->setSessionRole('admin');

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->expects($this->once())
             ->method('render')
             ->with(
                 'stats.twig.html',
                 $this->callback(fn(array $data) => $data['uri'] === 'stats')
             )
             ->willReturn('');

        $controller = new TestableStatsController($twig, $this->makeModel());
        $controller->show();
    }

    // ── Tests : appels au modèle ──────────────────────────────────────────────

    /**
     * getTopWishlist doit être appelé avec la limite 5.
     */
    public function test_show_appelleGetTopWishlist_avecLimite5(): void
    {
        $this->setSessionRole('admin');

        $model = $this->createMock(StatsModel::class);
        $model->method('getNbOffresTotal')->willReturn(0);
        $model->method('getMoyenneCandidaturesParOffre')->willReturn(0.0);
        $model->method('getRepartitionParDuree')->willReturn([]);
        $model->expects($this->once())
              ->method('getTopWishlist')
              ->with(5)
              ->willReturn([]);

        $controller = new TestableStatsController($this->makeTwig(), $model);
        $controller->show();
    }

    /**
     * getNbOffresTotal doit être appelé exactement une fois.
     */
    public function test_show_appelleGetNbOffresTotal_uneFois(): void
    {
        $this->setSessionRole('pilote');

        $model = $this->createMock(StatsModel::class);
        $model->expects($this->once())->method('getNbOffresTotal')->willReturn(5);
        $model->method('getMoyenneCandidaturesParOffre')->willReturn(0.0);
        $model->method('getRepartitionParDuree')->willReturn([]);
        $model->method('getTopWishlist')->willReturn([]);

        $controller = new TestableStatsController($this->makeTwig(), $model);
        $controller->show();
    }

    /**
     * getMoyenneCandidaturesParOffre doit être appelé exactement une fois.
     */
    public function test_show_appelleGetMoyenneCandidatures_uneFois(): void
    {
        $this->setSessionRole('admin');

        $model = $this->createMock(StatsModel::class);
        $model->method('getNbOffresTotal')->willReturn(0);
        $model->expects($this->once())->method('getMoyenneCandidaturesParOffre')->willReturn(1.5);
        $model->method('getRepartitionParDuree')->willReturn([]);
        $model->method('getTopWishlist')->willReturn([]);

        $controller = new TestableStatsController($this->makeTwig(), $model);
        $controller->show();
    }

    /**
     * getRepartitionParDuree doit être appelé exactement une fois.
     */
    public function test_show_appelleGetRepartitionParDuree_uneFois(): void
    {
        $this->setSessionRole('admin');

        $model = $this->createMock(StatsModel::class);
        $model->method('getNbOffresTotal')->willReturn(0);
        $model->method('getMoyenneCandidaturesParOffre')->willReturn(0.0);
        $model->expects($this->once())->method('getRepartitionParDuree')->willReturn([]);
        $model->method('getTopWishlist')->willReturn([]);

        $controller = new TestableStatsController($this->makeTwig(), $model);
        $controller->show();
    }
}

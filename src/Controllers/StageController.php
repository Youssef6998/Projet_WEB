<?php
class StageController {
    private $twig;
    
    public function __construct($twig) {
        $this->twig = $twig;
    }
    
    public function index() {
        $model = new StageModel();
        $stages = $model->getPaginatedStages();
        
        return $this->twig->render('stages.twig.html', [
            'uri' => 'stages',
            'stages' => $stages['stages'],
            'currentPage' => $stages['currentPage'],
            'totalPages' => $stages['totalPages'],
            'totalStages' => $stages['totalStages']
        ]);
    }
}

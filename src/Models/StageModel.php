<?php

class StageModel {
    private $stages = [
        ['company' => 'Figma France', 'title' => 'Stage UX Designer – Design System', 'description' => 'Participe à la refonte du design system interne...', 'tags' => ['UX Design', 'Figma', 'Design System'], 'location' => 'Paris', 'duration' => '6 mois', 'date' => '2 jours'],
        ['company' => 'Doctolib', 'title' => 'Développeur·se Front-End React', 'description' => 'Rejoins l\'équipe produit pour développer...', 'tags' => ['React', 'TypeScript', 'API REST'], 'location' => 'Paris', 'duration' => '4-6 mois', 'date' => '3 jours'],
        ['company' => 'BlaBlaCar', 'title' => 'Stage Marketing Digital & Growth', 'description' => 'Contribue aux campagnes d\'acquisition...', 'tags' => ['SEO', 'SEA', 'Growth'], 'location' => 'Paris', 'duration' => '6 mois', 'date' => '4 jours'],
        ['company' => 'OVHcloud', 'title' => 'Stage Développeur·se Back-End Python', 'description' => 'Intègre l\'équipe infrastructure...', 'tags' => ['Python', 'Django', 'Cloud'], 'location' => 'Roubaix', 'duration' => '4-6 mois', 'date' => '5 jours'],
        ['company' => 'Deezer', 'title' => 'UI Designer – Application Mobile', 'description' => 'Travaille sur les parcours utilisateurs...', 'tags' => ['UI Design', 'Mobile', 'Prototypage'], 'location' => 'Paris', 'duration' => '5 mois', 'date' => '1 semaine'],
        ['company' => 'L\'Oréal', 'title' => 'Stage Chef·fe de Projet Communication', 'description' => 'Accompagne l\'équipe communication...', 'tags' => ['Communication', 'Rédaction', 'Événementiel'], 'location' => 'Clichy', 'duration' => '6 mois', 'date' => '1 semaine'],
        ['company' => 'Alan', 'title' => 'Stage Product Manager – Parcours Santé', 'description' => 'Aide à prioriser la roadmap produit...', 'tags' => ['Product', 'Agile', 'Healthtech'], 'location' => 'Paris', 'duration' => '6 mois', 'date' => '1 semaine'],
        ['company' => 'ManoMano', 'title' => 'Stage Data Analyst – Marketplace', 'description' => 'Analyse les données de la marketplace...', 'tags' => ['SQL', 'Python', 'Data Viz'], 'location' => 'Bordeaux', 'duration' => '4-6 mois', 'date' => '9 jours'],
    ];

    public function getPaginatedStages($page = 1, $perPage = 6) {
        $page = max(1, (int)$page);
        $totalStages = count($this->stages);
        $totalPages = ceil($totalStages / $perPage);
        $offset = ($page - 1) * $perPage;
        
        return [
            'stages' => array_slice($this->stages, $offset, $perPage),
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalStages' => $totalStages
        ];
    }
}
?>
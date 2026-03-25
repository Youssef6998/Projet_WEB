<?php
namespace App\Models;

use PDO;  // 

class Stage {
    protected $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    public function getPaginatedStages($page = 1, $perPage = 6) {
        $offset = ($page - 1) * $perPage;
        
        // Total stages
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM stages WHERE is_active = 1");
        $total = (int)$stmt->fetchColumn();
        $totalPages = ceil($total / $perPage);
        
        // ✅ LIMIT/OFFSET DIRECT (pas de ?)
        $sql = "
            SELECT s.*, e.name as entreprise_name
            FROM stages s
            LEFT JOIN entreprises e ON s.entreprise_id = e.id
            WHERE s.is_active = 1
            ORDER BY s.published_at DESC
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = $this->pdo->query($sql);
        $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);  // ✅ PDO global OK
        
        return [
            'stages' => $stages,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ];
    }
}

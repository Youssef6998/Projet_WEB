<?php
require_once 'Database.php';
require 'vendor/autoload.php';
class Stage extends Database {
    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT s.*, e.name as entreprise_name, 
                   GROUP_CONCAT(t.name SEPARATOR ', ') as tags
            FROM stages s
            LEFT JOIN entreprises e ON s.entreprise_id = e.id
            LEFT JOIN stage_tags st ON s.id = st.stage_id
            LEFT JOIN tags t ON st.tag_id = t.id
            WHERE s.is_active = 1
            GROUP BY s.id
            ORDER BY s.published_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, e.name as entreprise_name, e.email, e.website,
                   GROUP_CONCAT(t.name) as tags
            FROM stages s
            LEFT JOIN entreprises e ON s.entreprise_id = e.id
            LEFT JOIN stage_tags st ON s.id = st.stage_id
            LEFT JOIN tags t ON st.tag_id = t.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
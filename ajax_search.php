<?php
// ajax_search.php
require_once __DIR__ . '/db.php';
if (isset($_GET['term'])) {
    $term = trim($_GET['term']);
    
    if (!empty($term)) {
        $searchTerm = '%' . $term . '%';
        
        $sql = "SELECT user_id, first_name, last_name, email 
                FROM job_applications 
                WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? 
                ORDER BY first_name, last_name 
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $applicants = [];
        while ($row = $result->fetch_assoc()) {
            $applicants[] = $row;
        }
        
        echo json_encode($applicants);
        
        $stmt->close();
        $conn->close();
        exit;
    }
}

echo json_encode([]);
?>
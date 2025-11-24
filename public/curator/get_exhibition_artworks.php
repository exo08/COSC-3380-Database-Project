<?php
// ajax endpoint to fetch artworks for an exhibition
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Don't display errors in json 
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Medium mapping
$mediums = [
    1 => 'Oil Painting',
    2 => 'Watercolor',
    3 => 'Acrylic',
    4 => 'Sculpture',
    5 => 'Photography',
    6 => 'Drawing',
    7 => 'Mixed Media',
    8 => 'Digital Art',
    9 => 'Printmaking',
    10 => 'Textile'
];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // Check permission
    if (!hasPermission('view_exhibitions')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    $exhibition_id = isset($_GET['exhibition_id']) ? intval($_GET['exhibition_id']) : 0;

    if ($exhibition_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid exhibition ID']);
        exit;
    }

    $db = db();

    // Query to get all artworks for this exhibition
    $query = "
        SELECT a.artwork_id,
               a.title,
               a.medium,
               a.creation_year,
               GROUP_CONCAT(CONCAT(ar.first_name, ' ', ar.last_name) SEPARATOR ', ') as artist_name,
               ea.start_view_date,
               ea.end_view_date
        FROM EXHIBITION_ARTWORK ea
        INNER JOIN ARTWORK a ON ea.artwork_id = a.artwork_id
        LEFT JOIN ARTWORK_CREATOR ac ON a.artwork_id = ac.artwork_id
        LEFT JOIN ARTIST ar ON ac.artist_id = ar.artist_id AND (ar.is_deleted = FALSE OR ar.is_deleted IS NULL)
        WHERE ea.exhibition_id = ?
        GROUP BY a.artwork_id, a.title, a.medium, a.creation_year, ea.start_view_date, ea.end_view_date
        ORDER BY ea.start_view_date, a.title
    ";

    $stmt = $db->prepare($query);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Query preparation failed: ' . $db->error]);
        exit;
    }
    
    $stmt->bind_param('i', $exhibition_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $artworks = $result->fetch_all(MYSQLI_ASSOC);
        
        // Map medium IDs to names
        foreach ($artworks as &$artwork) {
            if (isset($artwork['medium']) && isset($mediums[$artwork['medium']])) {
                $artwork['medium_name'] = $mediums[$artwork['medium']];
            } else {
                $artwork['medium_name'] = 'Unknown';
            }
        }
        
        echo json_encode([
            'success' => true,
            'artworks' => $artworks,
            'count' => count($artworks)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $stmt->error
        ]);
    }

    $stmt->close();
    $db->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
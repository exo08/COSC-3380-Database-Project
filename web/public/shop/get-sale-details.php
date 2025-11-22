<?php
// API endpoint to get sale details
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

header('Content-Type: application/json');

// Require shop_staff or admin permission
if (!in_array($_SESSION['user_type'], ['shop_staff', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if (!hasPermission('view_sales')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$db = db();
$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

if ($sale_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid sale ID']);
    exit;
}

try {
    // Get sale information
    $sale_query = "
        SELECT s.*,
               CONCAT(COALESCE(m.first_name, v.first_name, 'Walk-in'), ' ', COALESCE(m.last_name, v.last_name, 'Customer')) as customer_name,
               m.member_id as is_member,
               v.visitor_id as is_visitor
        FROM SALE s
        LEFT JOIN MEMBER m ON s.member_id = m.member_id
        LEFT JOIN VISITOR v ON s.visitor_id = v.visitor_id
        WHERE s.sale_id = ?
    ";
    
    $stmt = $db->prepare($sale_query);
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sale = $result->fetch_assoc();
    $stmt->close();
    
    if (!$sale) {
        echo json_encode(['success' => false, 'error' => 'Sale not found']);
        exit;
    }
    
    // Get sale items with category information
    $items_query = "
        SELECT sli.*, 
               si.item_name, 
               si.category_id,
               c.name as category_name
        FROM SALE_ITEM sli
        JOIN SHOP_ITEM si ON sli.item_id = si.item_id
        LEFT JOIN CATEGORY c ON si.category_id = c.category_id
        WHERE sli.sale_id = ?
        ORDER BY si.item_name
    ";
    
    $stmt = $db->prepare($items_query);
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $sale['items'] = $items;
    
    echo json_encode(['success' => true, 'sale' => $sale]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
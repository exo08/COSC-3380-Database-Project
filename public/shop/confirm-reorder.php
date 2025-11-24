<?php
// shop staff confirm or reject automatic reorder of stock
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

requirePermission('edit_shop_item');

$db = db();
$error = '';
$success = '';

// confirm reorder: keep the auto added stock
if (isset($_POST['confirm_reorder'])) {
    $item_id = intval($_POST['item_id']);
    
    try {
        // get item details before confirming
        $stmt = $db->prepare("
            SELECT item_name, pending_reorder_quantity, quantity_in_stock
            FROM SHOP_ITEM 
            WHERE item_id = ? AND did_auto_reorder = 1
        ");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $item_name = $row['item_name'];
            $qty = $row['pending_reorder_quantity'];
            $current_stock = $row['quantity_in_stock'];
            
            // clear the auto reorder flag keep stock as is
            $update_stmt = $db->prepare("
                UPDATE SHOP_ITEM 
                SET did_auto_reorder = 0,
                    pending_reorder_quantity = 0
                WHERE item_id = ?
            ");
            $update_stmt->bind_param("i", $item_id);
            
            if ($update_stmt->execute()) {
                $success = "Confirmed reorder for '$item_name' (+$qty units). Stock remains at $current_stock units. Please place supplier order.";
                logActivity('reorder_confirmed', 'SHOP_ITEM', $item_id, "Confirmed automatic reorder of $qty units for $item_name");
            } else {
                $error = "Error confirming reorder: " . $db->error;
            }
            
            $update_stmt->close();
        } else {
            $error = "Item not found or not flagged for reorder.";
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// reject reorder: remove the auto added stock
if (isset($_POST['reject_reorder'])) {
    $item_id = intval($_POST['item_id']);
    
    try {
        // get item details before rejecting
        $stmt = $db->prepare("
            SELECT item_name, pending_reorder_quantity, quantity_in_stock
            FROM SHOP_ITEM 
            WHERE item_id = ? AND did_auto_reorder = 1
        ");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $item_name = $row['item_name'];
            $qty_to_remove = $row['pending_reorder_quantity'];
            $current_stock = $row['quantity_in_stock'];
            $new_stock = $current_stock - $qty_to_remove;
            
            // make sure we dont go negative
            if ($new_stock < 0) {
                $new_stock = 0;
            }
            
            // remove the auto added stock and clear the flag
            $update_stmt = $db->prepare("
                UPDATE SHOP_ITEM 
                SET quantity_in_stock = ?,
                    did_auto_reorder = 0,
                    pending_reorder_quantity = 0
                WHERE item_id = ?
            ");
            $update_stmt->bind_param("ii", $new_stock, $item_id);
            
            if ($update_stmt->execute()) {
                $success = "Rejected reorder for '$item_name'. Removed $qty_to_remove units. Stock is now $new_stock units.";
                logActivity('reorder_rejected', 'SHOP_ITEM', $item_id, "Rejected automatic reorder of $qty_to_remove units for $item_name - stock reduced to $new_stock");
            } else {
                $error = "Error rejecting reorder: " . $db->error;
            }
            
            $update_stmt->close();
        } else {
            $error = "Item not found or not flagged for reorder.";
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// redirect back to dashboard with message
if ($success) {
    header("Location: /dashboard.php?success=" . urlencode($success));
} else {
    header("Location: /dashboard.php?error=" . urlencode($error));
}
exit;
?>
<?php
session_start();
require_once __DIR__ . '/../app/db.php';

header('Content-Type: application/json');

// Only admins can access user details
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin' || !isset($_GET['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = db();
$user_id = intval($_GET['user_id']);

// Get user account info
$user_query = "SELECT * FROM USER_ACCOUNT WHERE user_id = ?";
$stmt = $db->prepare($user_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

$result = [
    'user_id' => $user['user_id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'user_type' => $user['user_type'],
    'is_active' => $user['is_active'],
    'linked_id' => $user['linked_id']
];

// Get linked record details based on user type
if ($user['user_type'] === 'member' && $user['linked_id']) {
    $member_query = "SELECT * FROM MEMBER WHERE member_id = ?";
    $stmt = $db->prepare($member_query);
    $stmt->bind_param('i', $user['linked_id']);
    $stmt->execute();
    $member_result = $stmt->get_result();
    $member = $member_result->fetch_assoc();
    
    if ($member) {
        $result['first_name'] = $member['first_name'];
        $result['last_name'] = $member['last_name'];
        $result['phone'] = $member['phone'];
        $result['address'] = $member['address'];
        $result['membership_type'] = $member['membership_type'];
        $result['is_student'] = $member['is_student'];
        $result['start_date'] = $member['start_date'];
        $result['expiration_date'] = $member['expiration_date'];
        $result['auto_renew'] = $member['auto_renew'];
    }
    
} elseif (in_array($user['user_type'], ['curator', 'shop_staff', 'event_staff', 'admin']) && $user['linked_id']) {
    $staff_query = "SELECT s.*, d.department_name 
                    FROM STAFF s 
                    LEFT JOIN DEPARTMENT d ON s.department_id = d.department_id 
                    WHERE s.staff_id = ?";
    $stmt = $db->prepare($staff_query);
    $stmt->bind_param('i', $user['linked_id']);
    $stmt->execute();
    $staff_result = $stmt->get_result();
    $staff = $staff_result->fetch_assoc();
    
    if ($staff) {
        // Parse name into first and last
        $name_parts = explode(' ', $staff['name'], 2);
        $result['first_name'] = $name_parts[0] ?? '';
        $result['last_name'] = $name_parts[1] ?? '';
        
        $result['full_name'] = $staff['name'];
        $result['ssn'] = $staff['ssn'];
        $result['department_id'] = $staff['department_id'];
        $result['department_name'] = $staff['department_name'] ?? '';
        $result['title'] = $staff['title'];
        $result['hire_date'] = $staff['hire_date'];
        $result['supervisor_id'] = $staff['supervisor_id'];
    }
}

echo json_encode($result);
?>
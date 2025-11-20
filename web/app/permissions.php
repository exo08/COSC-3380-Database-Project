<?php
// role based access control permissions

// check if user has permission for action
function hasPermission(string $action): bool {
    if(!isset($_SESSION['user_type'])){
        return false;
    }

    $role = $_SESSION['user_type'];

    // admin has all perms
    if($role === 'admin'){
        return true;
    }

    // define perms for each role
    $permissions = [
        'curator' => [
            'view_artworks', 'add_artwork', 'edit_artwork', 'delete_artwork',
            'view_artists', 'add_artist', 'edit_artist', 'view_exhibitions',
            'add_exhibition', 'edit_exhibition', 'delete_exhibition',
            'view_acquisitions', 'add_acquisition', 'view_locations', 'add_location', 'edit_location', 'report_collection', 'report_exhibitions', 'report_acquisitions'
        ],
        'shop_staff' => [
            'view_shop_items', 'add_shop_item', 'edit_shop_item',
            'view_sales', 'process_sale',
            'view_inventory',
            'report_sales', 'report_inventory'
        ],
        'event_staff' => [
            'view_events', 'add_event', 'edit_event',
            'view_tickets', 'sell_ticket', 'checkin_ticket',
            'view_visitors',
            'report_events', 'report_attendance'
        ],
        'member' => [
            'purchase_ticket',
            'view_shop_items', 'purchase_shop',
            'view_own_membership', 'view_own_tickets', 'view_own_purchases'
        ]
    ];

    return in_array($action, $permissions[$role] ?? []);
}

// require specific permission or redirect
function requirePermission(string $action, string $redirect = '/dashboard/php') {
    if(!hasPermission($action)){
        header('Location: ' . $redirect . '?error=access_denied');
        exit;
    }
}

// get list of allowed menu items for current user
function getAllowedMenuItems() : array {
    if(!isset($_SESSION['user_type'])){
        return [];
    }

    $role = $_SESSION['user_type'];

    $menus = [
        'admin' => [
            ['name' => 'Dashboard', 'url' => '/dashboard.php', 'icon' => 'speedometer2'],
            ['name' => 'Users', 'url' => '/admin/users.php', 'icon' => 'people'],
            ['name' => 'Artworks', 'url' => '/curator/artworks.php', 'icon' => 'palette'],
            ['name' => 'Artists', 'url' => '/curator/artists.php', 'icon' => 'brush'],
            ['name' => 'Exhibitions', 'url' => '/curator/exhibitions.php', 'icon' => 'building'],
            ['name' => 'Events', 'url' => '/admin/events.php', 'icon' => 'calendar-event'],
            ['name' => 'Shop', 'url' => '/admin/shop.php', 'icon' => 'shop'],
            ['name' => 'Reports', 'url' => '/reports/index.php', 'icon' => 'file-bar-graph'],
        ],
        'curator' => [
            ['name' => 'Dashboard', 'url' => '/dashboard.php', 'icon' => 'speedometer2'],
            ['name' => 'Artworks', 'url' => '/curator/artworks.php', 'icon' => 'palette'],
            ['name' => 'Artists', 'url' => '/curator/artists.php', 'icon' => 'brush'],
            ['name' => 'Exhibitions', 'url' => '/curator/exhibitions.php', 'icon' => 'building'],
            ['name' => 'Acquisitions', 'url' => '/curator/acquisitions.php', 'icon' => 'cart-plus'],
            ['name' => 'Reports', 'url' => '/reports/index.php', 'icon' => 'file-bar-graph'],
        ],
        'shop_staff' => [
            ['name' => 'Dashboard', 'url' => '/dashboard.php', 'icon' => 'speedometer2'],
            ['name' => 'Process Sale', 'url' => '/shop/new-sale.php', 'icon' => 'cart-check'],
            ['name' => 'Inventory', 'url' => '/shop/inventory.php', 'icon' => 'box-seam'],
            ['name' => 'Sales History', 'url' => '/shop/sales.php', 'icon' => 'receipt'],
            ['name' => 'Reports', 'url' => '/reports/index.php', 'icon' => 'file-bar-graph'],
        ],
        'event_staff' => [
            ['name' => 'Dashboard', 'url' => '/dashboard.php', 'icon' => 'speedometer2'],
            ['name' => 'Events', 'url' => '/events/manage.php', 'icon' => 'calendar-event'],
            ['name' => 'Check-in', 'url' => '/events/checkin.php', 'icon' => 'check-square'],
            ['name' => 'Sell Tickets', 'url' => '/events/sell-ticket.php', 'icon' => 'ticket'],
            ['name' => 'Reports', 'url' => '/reports/index.php', 'icon' => 'file-bar-graph'],
        ],
        'member' => [
            ['name' => 'Home', 'url' => '/index.php', 'icon' => 'house'],
            ['name' => 'Exhibitions', 'url' => '/exhibitions.php', 'icon' => 'building'],
            ['name' => 'Events', 'url' => '/events.php', 'icon' => 'calendar-event'],
            ['name' => 'Gift Shop', 'url' => '/shop.php', 'icon' => 'shop'],
            ['name' => 'My Membership', 'url' => '/member/membership.php', 'icon' => 'star'],
            ['name' => 'My Tickets', 'url' => '/member/tickets.php', 'icon' => 'ticket'],
            ['name' => 'Settings', 'url' => '/member/settings.php', 'icon' => 'gear'],
        ]
    ];

    return $menus[$role] ?? [];
}

// get role display name
function getRoleDisplayName(string $role): string {
    $names = [
        'admin' => 'Administrator',
        'curator' => 'Curator',
        'shop_staff' => 'Shop Staff',
        'event_staff' => 'Event Staff',
        'member' => 'Member'
    ];

    return $names[$role] ?? ucfirst($role);
}

// log user activity
function logActivity(string $action_type, ?string $table_name = null, ?int $record_id = null, ?string $description = null): bool {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    require_once __DIR__ . '/db.php';
    $db = db();
    
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = $db->prepare("
        INSERT INTO ACTIVITY_LOG (user_id, action_type, table_name, record_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("ississ", $user_id, $action_type, $table_name, $record_id, $description, $ip_address);
    
    return $stmt->execute();
}

// Check if user has access to a specific report
function hasReportAccess(string $report_name): bool {
    if (!isset($_SESSION['user_type'])) {
        return false;
    }
    
    $role = $_SESSION['user_type'];
    
    // Admin has access to all reports
    if ($role === 'admin') {
        return true;
    }
    
    // Define report access by role
    $report_access = [
        'curator' => [
            'acquisition-history',
            'artwork-catalog',
            'artwork-by-artist',
            'artwork-by-medium',
            'artwork-by-period',
            'artwork-dimensions',
            'unlocated-artworks',
            'top-donors',
            'current-exhibitions',
            'exhibition-attendance',
            'curator-portfolio',
            'exhibition-artwork-list',
            'exhibition-timeline',
            'human-donor-summary',
            'org-donor-summary',
            'advanced-artwork-search'
        ],
        'shop_staff' => [
            'revenue-by-item',
            'revenue-by-category',
            'member-sales',
            'visitor-sales'
        ],
        'event_staff' => [
            'event-attendance',
            'upcoming-events',
            'events-near-capacity'
        ]
    ];
    
    return in_array($report_name, $report_access[$role] ?? []);
}
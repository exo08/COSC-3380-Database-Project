<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set page title
$page_title = 'Upcoming Events';

// Database connection
require_once __DIR__ . '/app/db.php';

try {
    $db = db();
    
    // Get upcoming events
    $events_query = "
        SELECT 
            e.event_id,
            e.name as event_name,
            e.description,
            e.event_date,
            e.capacity,
            l.name as location_name,
            ex.title as exhibition_title,
            COALESCE(SUM(t.quantity), 0) as tickets_sold
        FROM EVENT e
        LEFT JOIN LOCATION l ON e.location_id = l.location_id
        LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
        LEFT JOIN TICKET t ON e.event_id = t.event_id
        WHERE e.event_date >= CURDATE()
        GROUP BY e.event_id, e.name, e.description, e.event_date, e.capacity, l.name, ex.title
        ORDER BY e.event_date ASC
    ";
    
    $events_result = $db->query($events_query);
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Include header
include __DIR__ . '/templates/header.php';
?>

<!-- Page-specific styles -->
<style>
    body {
        background: #f5f7fa;
    }

    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
        padding: 60px 0;
        margin-bottom: 40px;
    }

    .page-header h1 {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .event-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        transition: all 0.3s;
        margin-bottom: 2rem;
        height: 100%;
    }

    .event-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }

    .event-card .event-image {
        height: 200px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 4rem;
    }

    .event-card .event-body {
        padding: 1.5rem;
    }

    .event-date {
        background: var(--accent-color);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        display: inline-block;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .event-title {
        color: var(--primary-color);
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .event-detail {
        color: #666;
        margin-bottom: 0.5rem;
    }

    .event-detail i {
        color: var(--accent-color);
        width: 20px;
    }

    .availability-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .availability-badge.available {
        background: #d4edda;
        color: #155724;
    }

    .availability-badge.limited {
        background: #fff3cd;
        color: #856404;
    }

    .availability-badge.sold-out {
        background: #f8d7da;
        color: #721c24;
    }

    .btn-get-tickets {
        background: var(--accent-color);
        color: white;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-get-tickets:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    .btn-get-tickets:disabled {
        background: #95a5a6;
        cursor: not-allowed;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="container text-center">
        <h1><i class="bi bi-calendar-event"></i> Upcoming Events</h1>
        <p class="lead">Join us for exciting programs, lectures, workshops, and special exhibitions</p>
    </div>
</div>

<!-- Events List -->
<div class="container pb-5">
    <?php if ($events_result && $events_result->num_rows > 0): ?>
        <div class="row">
            <?php while ($event = $events_result->fetch_assoc()): 
                $available_tickets = $event['capacity'] - $event['tickets_sold'];
                $percentage_sold = $event['capacity'] > 0 ? ($event['tickets_sold'] / $event['capacity']) * 100 : 0;
                
                if ($available_tickets <= 0) {
                    $availability_class = 'sold-out';
                    $availability_text = 'Sold Out';
                } elseif ($percentage_sold >= 75) {
                    $availability_class = 'limited';
                    $availability_text = 'Limited Availability';
                } else {
                    $availability_class = 'available';
                    $availability_text = 'Available';
                }
                
                $event_date = new DateTime($event['event_date']);
            ?>
            <div class="col-md-6">
                <div class="event-card">
                    <div class="event-image">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>
                    <div class="event-body">
                        <div class="event-date">
                            <i class="bi bi-calendar3"></i> 
                            <?= $event_date->format('l, F j, Y') ?>
                        </div>
                        
                        <h3 class="event-title"><?= htmlspecialchars($event['event_name']) ?></h3>
                        
                        <?php if ($event['exhibition_title']): ?>
                            <div class="event-detail">
                                <i class="bi bi-easel"></i>
                                Related to: <strong><?= htmlspecialchars($event['exhibition_title']) ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-detail">
                            <i class="bi bi-geo-alt"></i>
                            <?= htmlspecialchars($event['location_name'] ?? 'TBA') ?>
                        </div>
                        
                        <div class="event-detail">
                            <i class="bi bi-people"></i>
                            Capacity: <?= $event['tickets_sold'] ?> / <?= $event['capacity'] ?> attendees
                        </div>
                        
                        <div class="event-detail">
                            <i class="bi bi-tag"></i>
                            <strong>Free Admission</strong>
                        </div>
                        
                        <?php if ($event['description']): ?>
                            <p class="mt-2"><?= htmlspecialchars(substr($event['description'], 0, 150)) ?>...</p>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="availability-badge <?= $availability_class ?>">
                                <?= $availability_text ?>
                            </span>
                            
                            <?php if ($available_tickets > 0): ?>
                                <a href="/events/buy_ticket.php?event_id=<?= $event['event_id'] ?>" 
                                   class="btn btn-get-tickets">
                                    <i class="bi bi-ticket"></i> Get Tickets
                                </a>
                            <?php else: ?>
                                <button class="btn btn-get-tickets" disabled>
                                    <i class="bi bi-x-circle"></i> Sold Out
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar-x" style="font-size: 5rem; color: #ccc;"></i>
            <h3 class="mt-3">No Upcoming Events</h3>
            <p class="text-muted">Check back soon for exciting new programs and events!</p>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include __DIR__ . '/templates/footer.php';
?>
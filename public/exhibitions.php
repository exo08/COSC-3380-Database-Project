<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set page title
$page_title = 'Current Exhibitions';

// Database connection
require_once __DIR__ . '/app/db.php';

try {
    $db = db();
    
    // Get current and upcoming exhibitions with artwork count and curator name
    $exhibitions_query = "
        SELECT 
            ex.exhibition_id,
            ex.title,
            ex.description,
            ex.start_date,
            ex.end_date,
            ex.theme_sponsor,
            s.name as curator_name,
            COUNT(DISTINCT ea.artwork_id) as artwork_count
        FROM EXHIBITION ex
        LEFT JOIN STAFF s ON ex.curator_id = s.staff_id
        LEFT JOIN EXHIBITION_ARTWORK ea ON ex.exhibition_id = ea.exhibition_id
        WHERE ex.end_date >= CURDATE()
        GROUP BY ex.exhibition_id, ex.title, ex.description, ex.start_date, ex.end_date, ex.theme_sponsor, s.name
        ORDER BY ex.start_date ASC
    ";
    
    $exhibitions_result = $db->query($exhibitions_query);
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Include header
include __DIR__ . '/templates/header.php';
?>

<!-- Page-specific styles -->
<style>
    :root {
        --purple: #9b59b6;
    }

    body {
        background: #f5f7fa;
    }

    .page-header {
        background: linear-gradient(135deg, var(--purple), var(--accent-color));
        color: white;
        padding: 60px 0;
        margin-bottom: 40px;
    }

    .page-header h1 {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .exhibition-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transition: all 0.3s;
        margin-bottom: 3rem;
    }

    .exhibition-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }

    .exhibition-image {
        height: 350px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 6rem;
    }

    .exhibition-status {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255,255,255,0.95);
        color: var(--primary-color);
        padding: 0.5rem 1.5rem;
        border-radius: 25px;
        font-weight: 700;
        font-size: 0.875rem;
    }

    .exhibition-status.current {
        background: #28a745;
        color: white;
    }

    .exhibition-status.upcoming {
        background: #ffc107;
        color: #000;
    }

    .exhibition-status.ending-soon {
        background: #dc3545;
        color: white;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }

    .exhibition-body {
        padding: 2rem;
    }

    .exhibition-title {
        color: var(--primary-color);
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .exhibition-dates {
        background: var(--accent-color);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 25px;
        display: inline-block;
        font-weight: 600;
        margin-bottom: 1.5rem;
    }

    .exhibition-info {
        color: #666;
        margin-bottom: 0.75rem;
    }

    .exhibition-info i {
        color: var(--accent-color);
        width: 25px;
    }

    .sponsor-badge {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        display: inline-block;
        margin-top: 1rem;
    }

    .countdown-badge {
        background: rgba(0,0,0,0.1);
        padding: 0.5rem 1rem;
        border-radius: 15px;
        display: inline-block;
        margin-top: 1rem;
        font-weight: 600;
    }

    .countdown-badge i {
        margin-right: 0.25rem;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="container text-center">
        <h1><i class="bi bi-easel"></i> Current & Upcoming Exhibitions</h1>
        <p class="lead">Explore our carefully curated art exhibitions featuring masterpieces from around the world</p>
    </div>
</div>

<!-- Exhibitions List -->
<div class="container pb-5">
    <?php if ($exhibitions_result && $exhibitions_result->num_rows > 0): ?>
        <?php while ($exhibition = $exhibitions_result->fetch_assoc()): 
            $start_date = new DateTime($exhibition['start_date']);
            $end_date = new DateTime($exhibition['end_date']);
            $today = new DateTime();

            // set all times to midnight for date only comparison
            $start_date->setTime(0, 0, 0);
            $end_date->setTime(23, 59, 59); // end of day
            $today->setTime(0, 0, 0);
            
            $is_current = ($today >= $start_date && $today <= $end_date);
            $is_upcoming = ($today < $start_date);
            
            // Calculate days remaining or days until start
            $days_value = 0;
            if ($is_current) {
                $interval = $today->diff($end_date);
                $days_value = (int)$interval->format('%a');
            } elseif ($is_upcoming) {
                $interval = $today->diff($start_date);
                $days_value = (int)$interval->format('%a');
            }
            
            // Determine status
            if ($is_upcoming) {
                $status = 'upcoming';
                $status_text = 'Coming Soon';
            } elseif ($is_current && $days_value <= 7) {
                $status = 'ending-soon';
                $status_text = 'Ending Soon';
            } elseif ($is_current) {
                $status = 'current';
                $status_text = 'Now Showing';
            } else {
                $status = 'past';
                $status_text = 'Past';
            }
        ?>
        <div class="exhibition-card">
            <div class="exhibition-image">
                <span class="exhibition-status <?= $status ?>">
                    <?= $status_text ?>
                    <?php if ($status === 'ending-soon'): ?>
                        <i class="bi bi-exclamation-circle-fill"></i>
                    <?php endif; ?>
                </span>
                <i class="bi bi-palette-fill"></i>
            </div>
            <div class="exhibition-body">
                <h2 class="exhibition-title"><?= htmlspecialchars($exhibition['title']) ?></h2>
                
                <div class="exhibition-dates">
                    <i class="bi bi-calendar-range"></i>
                    <?= $start_date->format('F j, Y') ?> - <?= $end_date->format('F j, Y') ?>
                </div>
                <!-- correct badges for exhibitions -->
                <?php if ($is_current): ?>
                    <div class="countdown-badge">
                        <?php if ($days_value == 0): ?>
                            <i class="bi bi-clock-history"></i> Last Day!
                        <?php elseif ($days_value == 1): ?>
                            <i class="bi bi-clock-history"></i> 1 Day Remaining
                        <?php else: ?>
                            <i class="bi bi-clock-history"></i> <?= $days_value ?> Days Remaining
                        <?php endif; ?>
                    </div>
                <?php elseif ($is_upcoming): ?>
                    <div class="countdown-badge">
                        <?php if ($days_value == 0): ?>
                            <i class="bi bi-calendar-check"></i> Opening Today!
                        <?php elseif ($days_value == 1): ?>
                            <i class="bi bi-calendar-check"></i> Opening Tomorrow
                        <?php else: ?>
                            <i class="bi bi-calendar-check"></i> Opens in <?= $days_value ?> Days
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($exhibition['description']): ?>
                    <p class="lead mt-3"><?= htmlspecialchars($exhibition['description']) ?></p>
                <?php endif; ?>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="exhibition-info">
                            <i class="bi bi-palette"></i>
                            <strong>Featured Artworks:</strong> <?= $exhibition['artwork_count'] ?> pieces
                        </div>
                        
                        <?php if ($exhibition['curator_name']): ?>
                            <div class="exhibition-info">
                                <i class="bi bi-person"></i>
                                <strong>Curated by:</strong> <?= htmlspecialchars($exhibition['curator_name']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($exhibition['theme_sponsor']): ?>
                            <div class="sponsor-badge">
                                <i class="bi bi-star-fill"></i>
                                Sponsor/Theme: <strong><?= htmlspecialchars($exhibition['theme_sponsor']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-easel-fill" style="font-size: 5rem; color: #ccc;"></i>
            <h3 class="mt-3">No Current Exhibitions</h3>
            <p class="text-muted">Check back soon for exciting new exhibitions!</p>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include __DIR__ . '/templates/footer.php';
?>
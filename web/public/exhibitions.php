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

    .btn-view-artworks {
        background: var(--purple);
        color: white;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-view-artworks:hover {
        background: #8e44ad;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        color: white;
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
            
            $is_current = ($today >= $start_date && $today <= $end_date);
            $status = $is_current ? 'current' : 'upcoming';
            $status_text = $is_current ? 'Now Showing' : 'Coming Soon';
        ?>
        <div class="exhibition-card">
            <div class="exhibition-image">
                <span class="exhibition-status <?= $status ?>">
                    <?= $status_text ?>
                </span>
                <i class="bi bi-palette-fill"></i>
            </div>
            <div class="exhibition-body">
                <h2 class="exhibition-title"><?= htmlspecialchars($exhibition['title']) ?></h2>
                
                <div class="exhibition-dates">
                    <i class="bi bi-calendar-range"></i>
                    <?= $start_date->format('F j, Y') ?> - <?= $end_date->format('F j, Y') ?>
                </div>
                
                <?php if ($exhibition['description']): ?>
                    <p class="lead"><?= htmlspecialchars($exhibition['description']) ?></p>
                <?php endif; ?>
                
                <div class="row mt-4">
                    <div class="col-md-6">
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
                                Sponsored by: <strong><?= htmlspecialchars($exhibition['theme_sponsor']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6 text-end">
                        <?php if ($is_current): ?>
                            <a href="/exhibitions/view.php?id=<?= $exhibition['exhibition_id'] ?>" 
                               class="btn btn-view-artworks">
                                <i class="bi bi-eye"></i> View Exhibition
                            </a>
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
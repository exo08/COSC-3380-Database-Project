<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Advanced Artwork Search';

// Only admin and curator can access
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'curator') {
    header('Location: index.php?error=access_denied');
    exit;
}

$db = db();

$artworks = [];
$search_performed = false;

// Get all artists for dropdown
$artists_result = $db->query("SELECT DISTINCT first_name, last_name FROM ARTIST ORDER BY last_name, first_name");
$artists = $artists_result->fetch_all(MYSQLI_ASSOC);

// Get all locations for dropdown
$locations_result = $db->query("SELECT location_id, name FROM LOCATION ORDER BY name");
$locations = $locations_result->fetch_all(MYSQLI_ASSOC);

// Define medium options 
$mediums = [
    1 => 'Oil Painting',
    2 => 'Watercolor',
    3 => 'Acrylic',
    4 => 'Sculpture',
    5 => 'Photography',
    6 => 'Digital Art',
    7 => 'Mixed Media',
    8 => 'Drawing',
    9 => 'Printmaking',
    10 => 'Installation'
];

if (isset($_POST['search'])) {
    // Get filter values
    $artist_first = !empty($_POST['artist_first_name']) ? $_POST['artist_first_name'] : null;
    $artist_last = !empty($_POST['artist_last_name']) ? $_POST['artist_last_name'] : null;
    $medium = !empty($_POST['medium']) ? (int)$_POST['medium'] : null;
    $start_year = !empty($_POST['start_year']) ? (int)$_POST['start_year'] : null;
    $end_year = !empty($_POST['end_year']) ? (int)$_POST['end_year'] : null;
    $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
    
    // Prepare the procedure call
    $stmt = $db->prepare("CALL AdvancedArtworkSearch(?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiiii", $artist_first, $artist_last, $medium, $start_year, $end_year, $location_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $artworks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->next_result();
    
    $search_performed = true;
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    parse_str($_GET['filters'], $filters);
    
    $artist_first = $filters['artist_first_name'] ?? null;
    $artist_last = $filters['artist_last_name'] ?? null;
    $medium = !empty($filters['medium']) ? (int)$filters['medium'] : null;
    $start_year = !empty($filters['start_year']) ? (int)$filters['start_year'] : null;
    $end_year = !empty($filters['end_year']) ? (int)$filters['end_year'] : null;
    $location_id = !empty($filters['location_id']) ? (int)$filters['location_id'] : null;
    
    $stmt = $db->prepare("CALL AdvancedArtworkSearch(?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiiii", $artist_first, $artist_last, $medium, $start_year, $end_year, $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $export_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="artwork-search-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Artwork ID', 'Title', 'Artist', 'Year', 'Medium', 'Location']);
    
    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['artwork_id'],
            $row['title'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['creation_year'],
            $mediums[$row['medium']] ?? 'Unknown',
            $row['location_name'] ?? 'Unknown'
        ]);
    }
    
    fclose($output);
    exit;
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-funnel"></i> Advanced Artwork Search</h1>
            <p class="text-muted">Use multiple filters to find specific artworks</p>
        </div>
    </div>
    
    <!-- Search Filters -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-sliders"></i> Search Filters (All Optional)</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <!-- Artist Filter -->
                    <div class="col-md-12">
                        <h6 class="text-primary"><i class="bi bi-person"></i> Filter by Artist</h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Artist First Name</label>
                        <input type="text" name="artist_first_name" class="form-control" 
                               value="<?= htmlspecialchars($_POST['artist_first_name'] ?? '') ?>" 
                               placeholder="e.g., John" list="first-names">
                        <datalist id="first-names">
                            <?php foreach (array_unique(array_column($artists, 'first_name')) as $fname): ?>
                                <option value="<?= htmlspecialchars($fname) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Artist Last Name</label>
                        <input type="text" name="artist_last_name" class="form-control" 
                               value="<?= htmlspecialchars($_POST['artist_last_name'] ?? '') ?>" 
                               placeholder="e.g., Smith" list="last-names">
                        <datalist id="last-names">
                            <?php foreach (array_unique(array_column($artists, 'last_name')) as $lname): ?>
                                <option value="<?= htmlspecialchars($lname) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="col-md-12"><hr></div>
                    
                    <!-- Medium Filter -->
                    <div class="col-md-12">
                        <h6 class="text-primary"><i class="bi bi-brush"></i> Filter by Medium</h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Medium</label>
                        <select name="medium" class="form-select">
                            <option value="">Any Medium</option>
                            <?php foreach ($mediums as $id => $name): ?>
                                <option value="<?= $id ?>" 
                                    <?= (isset($_POST['medium']) && $_POST['medium'] == $id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-12"><hr></div>
                    
                    <!-- Year Range Filter -->
                    <div class="col-md-12">
                        <h6 class="text-primary"><i class="bi bi-calendar-range"></i> Filter by Period</h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From Year</label>
                        <input type="number" name="start_year" class="form-control" 
                               value="<?= htmlspecialchars($_POST['start_year'] ?? '') ?>" 
                               placeholder="e.g., 1900" min="1000" max="2100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">To Year</label>
                        <input type="number" name="end_year" class="form-control" 
                               value="<?= htmlspecialchars($_POST['end_year'] ?? '') ?>" 
                               placeholder="e.g., 2000" min="1000" max="2100">
                    </div>
                    
                    <div class="col-md-12"><hr></div>
                    
                    <!-- Location Filter -->
                    <div class="col-md-12">
                        <h6 class="text-primary"><i class="bi bi-geo-alt"></i> Filter by Location</h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <select name="location_id" class="form-select">
                            <option value="">Any Location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['location_id'] ?>" 
                                    <?= (isset($_POST['location_id']) && $_POST['location_id'] == $location['location_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($location['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-12"><hr></div>
                    
                    <!-- Action Buttons -->
                    <div class="col-md-6">
                        <button type="submit" name="search" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-search"></i> Search Artworks
                        </button>
                    </div>
                    <div class="col-md-6">
                        <a href="advanced-artwork-search.php" class="btn btn-outline-secondary btn-lg w-100">
                            <i class="bi bi-x-circle"></i> Clear All Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Results -->
    <?php if ($search_performed): ?>
        <?php
        // Build active filters display
        $active_filters = [];
        if (!empty($_POST['artist_first_name']) || !empty($_POST['artist_last_name'])) {
            $artist_name = trim(($_POST['artist_first_name'] ?? '') . ' ' . ($_POST['artist_last_name'] ?? ''));
            $active_filters[] = "Artist: <strong>$artist_name</strong>";
        }
        if (!empty($_POST['medium'])) {
            $active_filters[] = "Medium: <strong>" . $mediums[$_POST['medium']] . "</strong>";
        }
        if (!empty($_POST['start_year']) && !empty($_POST['end_year'])) {
            $active_filters[] = "Period: <strong>" . $_POST['start_year'] . " - " . $_POST['end_year'] . "</strong>";
        } elseif (!empty($_POST['start_year'])) {
            $active_filters[] = "From: <strong>" . $_POST['start_year'] . "</strong>";
        } elseif (!empty($_POST['end_year'])) {
            $active_filters[] = "Until: <strong>" . $_POST['end_year'] . "</strong>";
        }
        if (!empty($_POST['location_id'])) {
            $location_name = array_filter($locations, fn($l) => $l['location_id'] == $_POST['location_id']);
            $location_name = reset($location_name)['name'] ?? 'Unknown';
            $active_filters[] = "Location: <strong>" . htmlspecialchars($location_name) . "</strong>";
        }
        ?>
        
        <!-- Active Filters Display -->
        <?php if (!empty($active_filters)): ?>
        <div class="alert alert-info">
            <h6 class="mb-2"><i class="bi bi-funnel-fill"></i> Active Filters:</h6>
            <?= implode(' • ', $active_filters) ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($artworks)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No artworks found matching your search criteria. Try adjusting your filters.
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">
                    <span class="badge bg-success"><?= count($artworks) ?></span> Artwork(s) Found
                </h4>
                <?php
                $filter_string = http_build_query([
                    'artist_first_name' => $_POST['artist_first_name'] ?? '',
                    'artist_last_name' => $_POST['artist_last_name'] ?? '',
                    'medium' => $_POST['medium'] ?? '',
                    'start_year' => $_POST['start_year'] ?? '',
                    'end_year' => $_POST['end_year'] ?? '',
                    'location_id' => $_POST['location_id'] ?? ''
                ]);
                ?>
                <a href="?export=csv&filters=<?= urlencode($filter_string) ?>" class="btn btn-success">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
                </a>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="artworkTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Artist</th>
                                    <th>Year</th>
                                    <th>Medium</th>
                                    <th>Dimensions (cm)</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($artworks as $artwork): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($artwork['artwork_id']) ?></td>
                                        <td><strong><?= htmlspecialchars($artwork['title']) ?></strong></td>
                                        <td>
                                            <?php if ($artwork['first_name'] && $artwork['last_name']): ?>
                                                <?= htmlspecialchars($artwork['first_name'] . ' ' . $artwork['last_name']) ?>
                                            <?php else: ?>
                                                <em class="text-muted">Unknown</em>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($artwork['creation_year']) ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= $mediums[$artwork['medium']] ?? 'Unknown' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($artwork['height'] && $artwork['width']): ?>
                                                <?= number_format($artwork['height'], 1) ?> × <?= number_format($artwork['width'], 1) ?>
                                                <?php if ($artwork['depth']): ?>
                                                    × <?= number_format($artwork['depth'], 1) ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <em class="text-muted">N/A</em>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($artwork['location_name'] ?? 'Unknown') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-light border">
            <i class="bi bi-info-circle"></i> <strong>Tip:</strong> Use the filters above to search for artworks. You can combine multiple filters or use just one.
            <ul class="mt-2 mb-0">
                <li>Leave fields empty to skip that filter</li>
                <li>Use artist name alone to find all works by that artist</li>
                <li>Combine artist + medium to find specific types of work</li>
                <li>Add year range to narrow results to a specific period</li>
                <li>Filter by location to find works in a specific gallery or storage</li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#artworkTable').DataTable({
        pageLength: 25,
        order: [[3, 'asc']] // Sort by year
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>
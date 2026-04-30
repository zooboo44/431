<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) $publicPage = true;
$pageTitle = 'Circuits';
require_once __DIR__ . '/../includes/header.php';
if (!($publicPage ?? false)) requireRole('admin', 'team_manager', 'driver');

$db = getDB();

// ── Detail view ───────────────────────────────────────────────────────────────
$isDetail = isset($_GET['id']);
if ($isDetail) {
    $circuitId = intval($_GET['id']);

    $stmt = $db->prepare("
        SELECT c.*,
               CONCAT(p.first_name,' ',p.last_name) AS record_holder_name,
               p.id AS record_holder_id
        FROM circuits c
        LEFT JOIN people p ON p.id = c.lap_record_person_id
        WHERE c.id = ? AND c.is_active = 1
    ");
    $stmt->execute([$circuitId]);
    $circuit = $stmt->fetch();
    if (!$circuit) { include __DIR__ . '/../includes/404.php'; exit; }

    $pageTitle = h($circuit['name']);

    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, r.has_sprint, s.year,
               (SELECT COUNT(*) FROM race_entries re WHERE re.race_id=r.id) AS entry_count
        FROM races r
        JOIN seasons s ON s.id = r.season_id
        WHERE r.circuit_id = ? AND r.status = 'completed'
        ORDER BY s.year DESC, r.round_number DESC
    ");
    $stmt->execute([$circuitId]);
    $races = $stmt->fetchAll();

    renderFlash();
    ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= h($circuit['name']) ?></h1>
        <p class="page-subtitle"><?= h($circuit['city']) ?>, <?= h($circuit['country']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/shared/circuits.php" class="btn btn-outline">&larr; Circuits</a>
</div>

<div class="grid-2" style="margin-bottom:1.5rem">
    <div class="card">
        <div class="card-title">Circuit Info</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Country</div><div class="info-value"><?= h($circuit['country']) ?></div></div>
            <div class="info-item"><div class="info-label">City</div><div class="info-value"><?= h($circuit['city']) ?></div></div>
            <div class="info-item">
                <div class="info-label">Type</div>
                <div class="info-value"><span class="status-badge <?= $circuit['circuit_type'] === 'street' ? 'status-warning' : 'status-scheduled' ?>"><?= h($circuit['circuit_type']) ?></span></div>
            </div>
            <div class="info-item"><div class="info-label">Length</div><div class="info-value"><?= h(number_format($circuit['length_km'], 3)) ?> km</div></div>
            <div class="info-item"><div class="info-label">Laps</div><div class="info-value"><?= h((string)$circuit['number_of_laps']) ?></div></div>
            <div class="info-item"><div class="info-label">Race Distance</div><div class="info-value"><?= h(number_format($circuit['length_km'] * $circuit['number_of_laps'], 2)) ?> km</div></div>
            <?php if ($circuit['lap_record_ms']): ?>
            <div class="info-item">
                <div class="info-label">Lap Record</div>
                <div class="info-value text-success fw-bold">
                    <?= h(formatLapTime($circuit['lap_record_ms'])) ?>
                    <?php if ($circuit['record_holder_name']): ?>
                    — <a href="<?= APP_URL ?>/shared/standings.php?view=driver&id=<?= (int)$circuit['record_holder_id'] ?>" style="font-weight:400;font-size:0.85rem"><?= h($circuit['record_holder_name']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Quick Stats</div>
        <div class="stats-grid" style="grid-template-columns:repeat(2,1fr)">
            <div class="stat-card">
                <div class="stat-value"><?= count($races) ?></div>
                <div class="stat-label">Races Held</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count(array_unique(array_column($races, 'year'))) ?></div>
                <div class="stat-label">Seasons</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title">Races at <?= h($circuit['name']) ?></div>
    <?php if (empty($races)): ?>
    <div class="empty-state"><p>No completed races held here yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Season</th><th>Rd</th><th>Race</th><th>Date</th><th>Sprint</th><th>Entries</th></tr></thead>
        <tbody>
        <?php foreach ($races as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/shared/results.php?id=<?= (int)$r['id'] ?>">
            <td><strong><?= h((string)$r['year']) ?></strong></td>
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><?= h($r['name']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '—' ?></td>
            <td><?= h((string)$r['entry_count']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── List view ─────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT c.*,
           CONCAT(p.first_name, ' ', p.last_name) AS lap_record_holder
    FROM circuits c
    LEFT JOIN people p ON p.id = c.lap_record_person_id
    WHERE c.is_active = 1
    ORDER BY c.country, c.name
");
$stmt->execute();
$circuits = $stmt->fetchAll();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Circuits</h1>
        <p class="page-subtitle"><?= count($circuits) ?> active circuit<?= count($circuits) != 1 ? 's' : '' ?></p>
    </div>
</div>

<?php if (empty($circuits)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">&#9940;</div>
        <p>No circuits available.</p>
    </div>
</div>
<?php else: ?>
<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="circuits-table" placeholder="Search circuits...">
        </div>
    </div>
    <table class="sortable" id="circuits-table">
        <thead><tr>
            <th>Circuit</th><th>Country</th><th>City</th><th>Type</th>
            <th>Length</th><th>Laps</th><th>Lap Record</th><th>Record Holder</th>
        </tr></thead>
        <tbody>
        <?php foreach ($circuits as $c): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/shared/circuits.php?id=<?= (int)$c['id'] ?>">
            <td><strong><?= h($c['name']) ?></strong></td>
            <td><?= h($c['country']) ?></td>
            <td class="text-muted"><?= h($c['city']) ?></td>
            <td>
                <span class="status-badge <?= $c['circuit_type'] === 'street' ? 'status-warning' : 'status-scheduled' ?>">
                    <?= h($c['circuit_type']) ?>
                </span>
            </td>
            <td><?= h(number_format($c['length_km'], 3)) ?> km</td>
            <td><?= h((string)$c['number_of_laps']) ?></td>
            <td class="mono text-success">
                <?= $c['lap_record_ms'] ? h(formatLapTime($c['lap_record_ms'])) : '—' ?>
            </td>
            <td class="text-muted"><?= $c['lap_record_holder'] ? h($c['lap_record_holder']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

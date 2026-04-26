<?php
$pageTitle = 'Pit Stops';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager');

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);

$seasons    = getSeasonList();
$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
}

// IDOR: join through team_seasons.team_id = teamId
$stmt = $db->prepare("
    SELECT ps.stop_number, ps.lap_number, ps.duration_ms,
           ps.tyre_in, ps.tyre_out,
           p.id AS person_id, p.first_name, p.last_name, p.racing_number,
           r.name AS race_name, r.round_number
    FROM pit_stops ps
    JOIN race_entries re ON re.id = ps.race_entry_id
    JOIN team_seasons ts ON ts.id = re.team_season_id AND ts.team_id = ?
    JOIN races r ON r.id = re.race_id AND r.season_id = ?
    JOIN people p ON p.id = re.person_id
    ORDER BY r.round_number ASC, p.last_name ASC, ps.stop_number ASC
");
$stmt->execute([$teamId, $selectedSeasonId]);
$pitStops = $stmt->fetchAll();

$selectedYear = '';
foreach ($seasons as $s) { if ($s['id'] == $selectedSeasonId) { $selectedYear = $s['year']; break; } }
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Pit Stops</h1>
        <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Season' : '' ?></p>
    </div>
    <form class="season-selector" method="get">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons as $s): ?>
            <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>><?= h((string)$s['year']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="pitstops-table" placeholder="Search...">
        </div>
        <span class="text-muted" style="font-size:0.85rem"><?= count($pitStops) ?> stop<?= count($pitStops) != 1 ? 's' : '' ?></span>
    </div>
    <table class="sortable" id="pitstops-table">
        <thead><tr>
            <th>Rd</th><th>Race</th><th>Driver</th><th>Stop #</th>
            <th>Lap</th><th>Duration</th><th>Tyre In</th><th>Tyre Out</th>
        </tr></thead>
        <tbody>
        <?php if (empty($pitStops)): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:2rem">No pit stop data for this season.</td></tr>
        <?php else: ?>
        <?php foreach ($pitStops as $ps): ?>
        <tr>
            <td><span class="round-chip"><?= h((string)$ps['round_number']) ?></span></td>
            <td><?= h($ps['race_name']) ?></td>
            <td><a href="<?= APP_URL ?>/team_manager/driver_profile.php?person_id=<?= (int)$ps['person_id'] ?>" style="font-weight:600">#<?= h((string)$ps['racing_number']) ?> <?= h($ps['first_name'] . ' ' . $ps['last_name']) ?></a></td>
            <td class="text-accent fw-bold"><?= h((string)$ps['stop_number']) ?></td>
            <td class="text-muted">Lap <?= h((string)$ps['lap_number']) ?></td>
            <td><?= $ps['duration_ms'] ? h(number_format($ps['duration_ms'] / 1000, 3)) . 's' : '—' ?></td>
            <td>
                <?php if ($ps['tyre_in']): ?>
                <span class="status-badge" style="background:var(--border);color:var(--text-primary)"><?= h($ps['tyre_in']) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <?php if ($ps['tyre_out']): ?>
                <span class="status-badge" style="background:var(--border);color:var(--text-primary)"><?= h($ps['tyre_out']) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

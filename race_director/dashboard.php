<?php
$pageTitle = 'Race Director Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('race_director');

$db = getDB();
$activeSeason = getActiveSeason();
$seasonId = $activeSeason['id'] ?? null;

$upcomingRaces  = [];
$completedRaces = [];

if ($seasonId) {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, r.has_sprint, r.status,
               c.name AS circuit, c.country
        FROM races r JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ? AND r.status IN ('scheduled','in_progress')
        ORDER BY r.race_date ASC
        LIMIT 5
    ");
    $stmt->execute([$seasonId]);
    $upcomingRaces = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, r.has_sprint,
               c.name AS circuit, c.country,
               (SELECT COUNT(*) FROM race_entries re2 WHERE re2.race_id=r.id) AS entry_count,
               (SELECT COUNT(*) FROM qualifying_results qr JOIN race_entries re3 ON re3.id=qr.race_entry_id WHERE re3.race_id=r.id) AS qual_count,
               (SELECT COUNT(*) FROM race_results rr JOIN race_entries re4 ON re4.id=rr.race_entry_id WHERE re4.race_id=r.id) AS result_count
        FROM races r JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ? AND r.status = 'completed'
        ORDER BY r.race_date DESC
        LIMIT 5
    ");
    $stmt->execute([$seasonId]);
    $completedRaces = $stmt->fetchAll();
}

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Race Director Dashboard</h1>
        <p class="page-subtitle"><?= $activeSeason ? h((string)$activeSeason['year']) . ' Season' : 'No active season' ?></p>
    </div>
</div>

<?php if ($upcomingRaces): ?>
<div class="card">
    <div class="card-title">&#128197; Upcoming Races</div>
    <div class="table-container" style="border:0;margin:0">
        <table>
            <thead><tr>
                <th>Round</th><th>Race</th><th>Circuit</th><th>Date</th><th>Sprint</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($upcomingRaces as $r): ?>
            <tr class="clickable-row" data-href="<?= APP_URL ?>/race_director/results.php?race_id=<?= (int)$r['id'] ?>">
                <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
                <td><strong><?= h($r['name']) ?></strong></td>
                <td class="text-muted"><?= h($r['circuit']) ?></td>
                <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
                <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '—' ?></td>
                <td><span class="status-badge status-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
                <td class="no-row-click" style="display:flex;gap:0.4rem;flex-wrap:wrap">
                    <a href="<?= APP_URL ?>/race_director/race_entries.php?race_id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">Entries</a>
                    <a href="<?= APP_URL ?>/race_director/qualifying.php?race_id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">Qualifying</a>
                    <a href="<?= APP_URL ?>/race_director/results.php?race_id=<?= (int)$r['id'] ?>" class="btn btn-primary btn-sm">Results</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($completedRaces): ?>
<div class="card">
    <div class="card-title">&#9989; Recent Completed Races</div>
    <div class="table-container" style="border:0;margin:0">
        <table>
            <thead><tr>
                <th>Round</th><th>Race</th><th>Entries</th><th>Qualifying</th><th>Results</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($completedRaces as $r): ?>
            <tr class="clickable-row" data-href="<?= APP_URL ?>/race_director/results.php?race_id=<?= (int)$r['id'] ?>">
                <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
                <td><strong><?= h($r['name']) ?></strong><div class="text-muted" style="font-size:0.8rem"><?= h(date('d M Y', strtotime($r['race_date']))) ?></div></td>
                <td><?= $r['entry_count'] > 0 ? '<span class="text-success">'.$r['entry_count'].'</span>' : '<span class="text-muted">0</span>' ?></td>
                <td><?= $r['qual_count'] > 0 ? '<span class="text-success">'.$r['qual_count'].'</span>' : '<span class="text-muted">0</span>' ?></td>
                <td><?= $r['result_count'] > 0 ? '<span class="text-success">'.$r['result_count'].'</span>' : '<span class="text-muted">0</span>' ?></td>
                <td class="no-row-click">
                    <a href="<?= APP_URL ?>/race_director/results.php?race_id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">Edit Results</a>
                    <a href="<?= APP_URL ?>/race_director/penalties.php?race_id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">Penalties</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!$upcomingRaces && !$completedRaces): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">&#127937;</div>
        <p>No races found for the active season.</p>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

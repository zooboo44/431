<?php
$pageTitle = 'Race Detail';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$raceId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT r.*, c.name AS circuit_name, c.city, c.country, c.length_km, c.number_of_laps,
           s.year AS season_year, s.id AS season_id
    FROM races r
    JOIN circuits c ON c.id = r.circuit_id
    JOIN seasons s ON s.id = r.season_id
    WHERE r.id = ?
");
$stmt->execute([$raceId]);
$race = $stmt->fetch();
if (!$race) { include __DIR__ . '/../includes/404.php'; exit; }

$csrfToken = generateCSRFToken();

// Handle penalty delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_penalty'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId, 'danger', 'Invalid token.');
    }
    $penId = intval($_POST['penalty_id'] ?? 0);
    $db->prepare('DELETE FROM penalties WHERE id = ?')->execute([$penId]);
    logAudit($_SESSION['user_id'], 'delete', 'penalties', $penId, "Deleted from race $raceId");
    rotateCSRFToken();
    redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId, 'success', 'Penalty deleted.');
}

// Handle add penalty inline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_penalty'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId, 'danger', 'Invalid token.');
    }
    $personId   = intval($_POST['person_id'] ?? 0);
    $type       = $_POST['penalty_type'] ?? '';
    $reason     = strip_tags(trim($_POST['reason'] ?? ''));
    $timePenS   = intval($_POST['time_penalty_s'] ?? 0) ?: null;
    $gridPen    = intval($_POST['grid_penalty_positions'] ?? 0) ?: null;
    $licPoints  = intval($_POST['licence_points_awarded'] ?? 0) ?: null;
    $isDsq      = ($type === 'dsq') ? 1 : 0;

    if ($personId && $reason && in_array($type, ['time_penalty','grid_penalty','licence_points','dsq','warning'])) {
        $db->prepare("INSERT INTO penalties (race_id,person_id,issued_by,penalty_type,reason,time_penalty_s,grid_penalty_positions,licence_points_awarded,is_dsq) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$raceId,$personId,$_SESSION['user_id'],$type,$reason,$timePenS,$gridPen,$licPoints,$isDsq]);
        if ($isDsq) {
            $db->prepare("
                UPDATE race_results rr
                JOIN race_entries re ON re.id = rr.race_entry_id
                SET rr.status='DSQ', rr.points_scored=0
                WHERE re.race_id=? AND re.person_id=?
            ")->execute([$raceId, $personId]);
            recalculateStandings($race['season_id']);
        }
        logAudit($_SESSION['user_id'], 'create', 'penalties', null, "Race $raceId, person $personId");
        rotateCSRFToken();
        redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId, 'success', 'Penalty issued.');
    }
}

// Race entries
$stmt = $db->prepare("
    SELECT re.id, p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name
    FROM race_entries re
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE re.race_id = ?
    ORDER BY p.racing_number
");
$stmt->execute([$raceId]);
$entries = $stmt->fetchAll();

// Race results
$stmt = $db->prepare("
    SELECT rr.*, p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE re.race_id = ?
    ORDER BY COALESCE(rr.finish_position,99), p.racing_number
");
$stmt->execute([$raceId]);
$results = $stmt->fetchAll();

// Qualifying results
$stmt = $db->prepare("
    SELECT qr.*, p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.short_name
    FROM qualifying_results qr
    JOIN race_entries re ON re.id = qr.race_entry_id
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE re.race_id = ?
    ORDER BY qr.grid_position ASC
");
$stmt->execute([$raceId]);
$qualifying = $stmt->fetchAll();

// Sprint results
$sprintResults = [];
if ($race['has_sprint']) {
    $stmt = $db->prepare("
        SELECT sr.*, p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name
        FROM sprint_results sr
        JOIN race_entries re ON re.id = sr.race_entry_id
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE re.race_id = ?
        ORDER BY COALESCE(sr.finish_position,99)
    ");
    $stmt->execute([$raceId]);
    $sprintResults = $stmt->fetchAll();
}

// Penalties
$stmt = $db->prepare("
    SELECT pen.*, p.first_name, p.last_name, p.racing_number, u.name AS issued_by_name
    FROM penalties pen
    JOIN people p ON p.id = pen.person_id
    JOIN users u ON u.id = pen.issued_by
    WHERE pen.race_id = ?
    ORDER BY pen.issued_at DESC
");
$stmt->execute([$raceId]);
$penalties = $stmt->fetchAll();

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Rd <?= h((string)$race['round_number']) ?> — <?= h($race['name']) ?></h1>
        <p class="page-subtitle"><?= h($race['circuit_name']) ?> &bull; <?= h($race['city']) ?>, <?= h($race['country']) ?> &bull; <?= h(date('d M Y', strtotime($race['race_date']))) ?></p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <a href="<?= APP_URL ?>/admin/races_edit.php?id=<?= $raceId ?>" class="btn btn-outline">Edit Race</a>
        <a href="<?= APP_URL ?>/admin/races.php?season=<?= $race['season_id'] ?>" class="btn btn-outline">&larr; Races</a>
    </div>
</div>

<!-- Info bar -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-value"><span class="status-badge status-<?= h($race['status']) ?>"><?= h(ucfirst(str_replace('_',' ',$race['status']))) ?></span></div>
        <div class="stat-label">Status</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)$race['season_year']) ?></div>
        <div class="stat-label">Season</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)$race['number_of_laps']) ?></div>
        <div class="stat-label">Laps</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($entries) ?></div>
        <div class="stat-label">Entries</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $race['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '—' ?></div>
        <div class="stat-label">Sprint</div>
    </div>
</div>

<!-- Quick links -->
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.5rem">
    <a href="<?= APP_URL ?>/race_director/race_entries.php?race_id=<?= $raceId ?>" class="btn btn-outline">Manage Entries</a>
    <a href="<?= APP_URL ?>/race_director/qualifying.php?race_id=<?= $raceId ?>" class="btn btn-outline">Enter Qualifying</a>
    <a href="<?= APP_URL ?>/race_director/results.php?race_id=<?= $raceId ?>" class="btn btn-primary">Enter Results</a>
    <?php if ($race['has_sprint']): ?>
    <a href="<?= APP_URL ?>/race_director/sprint.php?race_id=<?= $raceId ?>" class="btn btn-outline">Sprint Results</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/public/race_detail.php?id=<?= $raceId ?>" class="btn btn-secondary" target="_blank">Public View</a>
</div>

<div class="grid-2">
<!-- Race Entries -->
<div class="card">
    <div class="card-title">Race Entries (<?= count($entries) ?>)</div>
    <?php if (empty($entries)): ?>
    <div class="empty-state"><p>No entries yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>#</th><th>Driver</th><th>Team</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($entries as $e): ?>
        <tr>
            <td><strong class="text-accent"><?= h((string)$e['racing_number']) ?></strong></td>
            <td><a href="<?= APP_URL ?>/admin/person_detail.php?id=<?= (int)$e['person_id'] ?>"><?= h($e['first_name'] . ' ' . $e['last_name']) ?></a></td>
            <td class="text-muted"><?= h($e['team_name']) ?></td>
            <td>
                <form method="post" action="<?= APP_URL ?>/admin/delete.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="race_entry">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/race_detail.php?id=' . $raceId) ?>">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove entry for <?= h($e['first_name'] . ' ' . $e['last_name']) ?>?">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Qualifying -->
<div class="card">
    <div class="card-title">Qualifying</div>
    <?php if (empty($qualifying)): ?>
    <div class="empty-state"><p>No qualifying data. <a href="<?= APP_URL ?>/race_director/qualifying.php?race_id=<?= $raceId ?>">Enter qualifying →</a></p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Grid</th><th>Driver</th><th>Team</th><th>Q1</th><th>Q2</th><th>Q3</th></tr></thead>
        <tbody>
        <?php foreach ($qualifying as $q): ?>
        <tr>
            <td><span class="position-badge pos-<?= $q['grid_position'] <= 3 ? $q['grid_position'] : 'other' ?>"><?= h((string)$q['grid_position']) ?></span></td>
            <td><a href="<?= APP_URL ?>/admin/person_detail.php?id=<?= (int)$q['person_id'] ?>"><?= h($q['first_name'] . ' ' . $q['last_name']) ?></a></td>
            <td class="text-muted"><?= h($q['short_name']) ?></td>
            <td class="mono"><?= $q['q1_time_ms'] ? h(formatLapTime($q['q1_time_ms'])) : '—' ?></td>
            <td class="mono"><?= $q['q2_time_ms'] ? h(formatLapTime($q['q2_time_ms'])) : '—' ?></td>
            <td class="mono"><?= $q['q3_time_ms'] ? h(formatLapTime($q['q3_time_ms'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>

<!-- Race Results -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Race Results</div>
    <?php if (empty($results)): ?>
    <div class="empty-state"><p>No results yet. <a href="<?= APP_URL ?>/race_director/results.php?race_id=<?= $raceId ?>">Enter results →</a></p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Grid</th><th>Laps</th><th>Status</th><th>Points</th><th>FL</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
        <tr>
            <td><?= $r['finish_position'] ? '<span class="position-badge pos-'.($r['finish_position']<=3?$r['finish_position']:'other').'">' . h((string)$r['finish_position']) . '</span>' : '—' ?></td>
            <td><a href="<?= APP_URL ?>/admin/person_detail.php?id=<?= (int)$r['person_id'] ?>" style="font-weight:600"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></a></td>
            <td class="text-muted"><?= h($r['team_name']) ?></td>
            <td class="text-muted"><?= h((string)$r['start_position']) ?></td>
            <td class="text-muted"><?= h((string)$r['laps_completed']) ?></td>
            <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
            <td class="text-accent fw-bold"><?= h((string)$r['points_scored']) ?></td>
            <td><?= $r['fastest_lap_bonus'] ? '<span class="fl-indicator" title="Fastest Lap">&#9889;</span>' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if ($race['has_sprint']): ?>
<!-- Sprint Results -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Sprint Results</div>
    <?php if (empty($sprintResults)): ?>
    <div class="empty-state"><p>No sprint results. <a href="<?= APP_URL ?>/race_director/sprint.php?race_id=<?= $raceId ?>">Enter sprint results →</a></p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Points</th></tr></thead>
        <tbody>
        <?php foreach ($sprintResults as $sr): ?>
        <tr>
            <td><?= $sr['finish_position'] ? '<span class="position-badge pos-'.($sr['finish_position']<=3?$sr['finish_position']:'other').'">' . h((string)$sr['finish_position']) . '</span>' : '—' ?></td>
            <td><a href="<?= APP_URL ?>/admin/person_detail.php?id=<?= (int)$sr['person_id'] ?>" style="font-weight:600"><?= h($sr['first_name'] . ' ' . $sr['last_name']) ?></a></td>
            <td class="text-muted"><?= h($sr['team_name']) ?></td>
            <td class="text-accent fw-bold"><?= h((string)$sr['points_scored']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Penalties -->
<div class="grid-2" style="margin-top:1.5rem">
<div class="card">
    <div class="card-title">Penalties (<?= count($penalties) ?>)</div>
    <?php if (empty($penalties)): ?>
    <div class="empty-state"><p>No penalties for this race.</p></div>
    <?php else: ?>
    <?php foreach ($penalties as $pen): ?>
    <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <a href="<?= APP_URL ?>/admin/person_detail.php?id=<?= (int)$pen['person_id'] ?>" style="font-weight:600"><?= h($pen['first_name'] . ' ' . $pen['last_name']) ?></a>
                <span class="status-badge status-<?= $pen['is_dsq'] ? 'dsq' : 'warning' ?>" style="margin-left:0.5rem"><?= h(str_replace('_',' ',$pen['penalty_type'])) ?></span>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="penalty_id" value="<?= $pen['id'] ?>">
                <input type="hidden" name="delete_penalty" value="1">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this penalty?">Delete</button>
            </form>
        </div>
        <p style="margin:0.35rem 0 0;font-size:0.85rem;color:var(--text-secondary)"><?= h($pen['reason']) ?></p>
        <?php if ($pen['time_penalty_s']): ?><div style="font-size:0.8rem">+<?= $pen['time_penalty_s'] ?>s</div><?php endif; ?>
        <?php if ($pen['licence_points_awarded']): ?><div style="font-size:0.8rem"><?= $pen['licence_points_awarded'] ?> licence pts</div><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Penalty -->
<div class="card">
    <div class="card-title">Issue Penalty</div>
    <?php if (empty($entries)): ?>
    <div class="notice">Add race entries first.</div>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-group">
            <label class="form-label required">Driver</label>
            <select name="person_id" class="form-control">
                <option value="">— Select —</option>
                <?php foreach ($entries as $e): ?>
                <option value="<?= h((string)$e['person_id']) ?>">#<?= h((string)$e['racing_number']) ?> <?= h($e['first_name'] . ' ' . $e['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required">Type</label>
            <select name="penalty_type" class="form-control" id="pen_type" onchange="togglePenFields(this.value)">
                <option value="time_penalty">Time Penalty</option>
                <option value="grid_penalty">Grid Penalty</option>
                <option value="licence_points">Licence Points</option>
                <option value="dsq">Disqualification</option>
                <option value="warning">Warning</option>
            </select>
        </div>
        <div class="form-group" id="pf-time">
            <label class="form-label">Seconds</label>
            <input type="number" name="time_penalty_s" class="form-control" min="1" max="120">
        </div>
        <div class="form-group" id="pf-grid" style="display:none">
            <label class="form-label">Grid Positions</label>
            <input type="number" name="grid_penalty_positions" class="form-control" min="1" max="30">
        </div>
        <div class="form-group" id="pf-lic" style="display:none">
            <label class="form-label">Licence Points</label>
            <input type="number" name="licence_points_awarded" class="form-control" min="1" max="12">
        </div>
        <div class="form-group">
            <label class="form-label required">Reason</label>
            <textarea name="reason" class="form-control" rows="2" required maxlength="500"></textarea>
        </div>
        <button type="submit" name="issue_penalty" class="btn btn-primary">Issue Penalty</button>
    </form>
    <?php endif; ?>
</div>
</div>

<script>
function togglePenFields(type) {
    document.getElementById('pf-time').style.display = type === 'time_penalty' ? '' : 'none';
    document.getElementById('pf-grid').style.display = type === 'grid_penalty' ? '' : 'none';
    document.getElementById('pf-lic').style.display  = type === 'licence_points' ? '' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

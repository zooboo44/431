<?php
$pageTitle = 'Penalties';
require_once __DIR__ . '/../includes/header.php';
requireRole('race_director');

$db     = getDB();
$errors = [];

$filterRaceId = intval($_GET['race_id'] ?? 0);
$activeSeason = getActiveSeason();
$seasonId     = $activeSeason['id'] ?? null;

// Races for filter
$races = [];
if ($seasonId) {
    $stmt = $db->prepare("SELECT id, name, round_number FROM races WHERE season_id=? ORDER BY round_number ASC");
    $stmt->execute([$seasonId]);
    $races = $stmt->fetchAll();
}

// Drivers per race for the season (for dynamic dropdown filtering)
$raceDriverMap = [];
if ($seasonId) {
    $stmt = $db->prepare("
        SELECT re.race_id, p.id, CONCAT(p.first_name,' ',p.last_name,' (#',p.racing_number,')') AS label
        FROM race_entries re
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN seasons s ON s.id = ts.season_id
        WHERE s.id = ?
        ORDER BY p.last_name
    ");
    $stmt->execute([$seasonId]);
    foreach ($stmt->fetchAll() as $row) {
        $raceDriverMap[$row['race_id']][] = ['id' => $row['id'], 'label' => $row['label']];
    }
}
// All distinct drivers across all races this season (for initial state)
$allDrivers = [];
foreach ($raceDriverMap as $driverList) {
    foreach ($driverList as $d) {
        $allDrivers[$d['id']] = $d['label'];
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_penalty'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $penId = intval($_POST['penalty_id'] ?? 0);
        $db->prepare('DELETE FROM penalties WHERE id = ?')->execute([$penId]);
        logAudit($_SESSION['user_id'], 'delete', 'penalties', $penId, 'Deleted by race director');
        rotateCSRFToken();
        redirectWithMessage(APP_URL . '/race_director/penalties.php' . ($filterRaceId ? '?race_id=' . $filterRaceId : ''), 'success', 'Penalty deleted.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_penalty'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $penRaceId    = intval($_POST['race_id'] ?? 0);
        $personId     = intval($_POST['person_id'] ?? 0);
        $type         = $_POST['penalty_type'] ?? '';
        $reason       = strip_tags(trim($_POST['reason'] ?? ''));
        $timePenS     = intval($_POST['time_penalty_s'] ?? 0) ?: null;
        $gridPenPos   = intval($_POST['grid_penalty_positions'] ?? 0) ?: null;
        $licPoints    = intval($_POST['licence_points_awarded'] ?? 0) ?: null;
        $isDsq        = ($type === 'dsq') ? 1 : 0;

        $validTypes = ['time_penalty','grid_penalty','licence_points','dsq','warning'];
        if (!in_array($type, $validTypes)) $errors[] = 'Invalid penalty type.';
        if (!$penRaceId) $errors[] = 'Race is required.';
        if (!$personId)  $errors[] = 'Driver is required.';
        if (!$reason)    $errors[] = 'Reason is required.';
        if ($type === 'time_penalty' && !$timePenS)  $errors[] = 'Time penalty amount (seconds) is required.';
        if ($type === 'grid_penalty' && !$gridPenPos) $errors[] = 'Grid penalty positions are required.';
        if ($type === 'licence_points' && !$licPoints) $errors[] = 'Licence points awarded are required.';

        if (empty($errors)) {
            $db->prepare("INSERT INTO penalties (race_id,person_id,issued_by,penalty_type,reason,time_penalty_s,grid_penalty_positions,licence_points_awarded,is_dsq) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$penRaceId,$personId,$_SESSION['user_id'],$type,$reason,$timePenS,$gridPenPos,$licPoints,$isDsq]);
            if ($isDsq) {
                $db->prepare("UPDATE race_results SET status='DSQ', points_scored=0 WHERE race_entry_id IN (SELECT id FROM race_entries WHERE race_id=? AND person_id=?)")
                   ->execute([$penRaceId, $personId]);
                if ($seasonId) recalculateStandings($seasonId);
            }
            logAudit($_SESSION['user_id'], 'create', 'penalties', null, "Type: $type, Person: $personId, Race: $penRaceId");
            rotateCSRFToken();
            redirectWithMessage(APP_URL . '/race_director/penalties.php' . ($filterRaceId ? '?race_id=' . $filterRaceId : ''), 'success', 'Penalty issued.');
        }
    }
}

// Load existing penalties
$penParams = [$seasonId];
$penWhere  = '';
if ($filterRaceId) {
    $penWhere   = 'AND pen.race_id = ?';
    $penParams[] = $filterRaceId;
}
$stmt = $db->prepare("
    SELECT pen.*, r.name AS race_name, r.round_number,
           p.first_name, p.last_name, p.racing_number,
           u.name AS issued_by_name
    FROM penalties pen
    JOIN races r ON r.id = pen.race_id
    JOIN people p ON p.id = pen.person_id
    JOIN users u ON u.id = pen.issued_by
    WHERE r.season_id = ? $penWhere
    ORDER BY pen.issued_at DESC
");
$stmt->execute($penParams);
$penalties = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Penalties</h1>
        <p class="page-subtitle"><?= $filterRaceId ? 'Filtered by race' : 'All races this season' ?></p>
    </div>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="grid-2">
    <!-- Issue Penalty Form -->
    <div class="card">
        <div class="card-title">Issue Penalty</div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <div class="form-group">
                <label class="form-label required">Race</label>
                <select name="race_id" id="pen_race_id" class="form-control" onchange="filterPenaltyDrivers(this.value)">
                    <option value="">— Select Race —</option>
                    <?php foreach ($races as $r): ?>
                    <option value="<?= h((string)$r['id']) ?>"<?= $filterRaceId == $r['id'] ? ' selected' : '' ?>>Rd <?= h((string)$r['round_number']) ?> — <?= h($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label required">Driver</label>
                <select name="person_id" id="pen_person_id" class="form-control">
                    <option value="">— Select Driver —</option>
                    <?php foreach ($allDrivers as $dId => $dLabel): ?>
                    <option value="<?= h((string)$dId) ?>"><?= h($dLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label required">Penalty Type</label>
                <select name="penalty_type" id="penalty_type" class="form-control" onchange="updatePenaltyFields(this.value)">
                    <option value="time_penalty">Time Penalty</option>
                    <option value="grid_penalty">Grid Penalty</option>
                    <option value="licence_points">Licence Points</option>
                    <option value="dsq">Disqualification</option>
                    <option value="warning">Warning</option>
                </select>
            </div>
            <div class="form-group" id="time-pen-group">
                <label class="form-label">Time Penalty (seconds)</label>
                <input type="number" name="time_penalty_s" class="form-control" min="1" max="120" placeholder="e.g. 5">
            </div>
            <div class="form-group" id="grid-pen-group" style="display:none">
                <label class="form-label">Grid Positions (dropped)</label>
                <input type="number" name="grid_penalty_positions" class="form-control" min="1" max="30" placeholder="e.g. 3">
            </div>
            <div class="form-group" id="lic-points-group" style="display:none">
                <label class="form-label">Licence Points Awarded</label>
                <input type="number" name="licence_points_awarded" class="form-control" min="1" max="12" placeholder="e.g. 2">
            </div>
            <div class="form-group">
                <label class="form-label required">Reason</label>
                <textarea name="reason" class="form-control" rows="3" required maxlength="500" placeholder="Describe the infringement..."></textarea>
            </div>
            <button type="submit" name="issue_penalty" class="btn btn-primary">Issue Penalty</button>
        </form>
    </div>

    <!-- Penalties list -->
    <div>
        <?php if ($races): ?>
        <div class="card" style="margin-bottom:1rem">
            <form method="get" class="d-flex gap-1 align-center">
                <select name="race_id" class="form-control" style="width:auto">
                    <option value="">All Races</option>
                    <?php foreach ($races as $r): ?>
                    <option value="<?= h((string)$r['id']) ?>"<?= $filterRaceId == $r['id'] ? ' selected' : '' ?>>Rd <?= h((string)$r['round_number']) ?> — <?= h($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline btn-sm">Filter</button>
                <a href="<?= APP_URL ?>/race_director/penalties.php" class="btn btn-secondary btn-sm">Clear</a>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title">Issued Penalties (<?= count($penalties) ?>)</div>
            <?php if (empty($penalties)): ?>
            <div class="empty-state"><p>No penalties issued yet.</p></div>
            <?php else: ?>
            <?php foreach ($penalties as $pen): ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem">
                <div style="display:flex;justify-content:space-between;align-items:flex-start">
                    <div>
                        <strong><?= h($pen['first_name'] . ' ' . $pen['last_name']) ?></strong>
                        <span class="text-muted" style="font-size:0.8rem"> #<?= h((string)$pen['racing_number']) ?></span>
                        <span class="status-badge status-<?= $pen['is_dsq'] ? 'dsq' : 'warning' ?>" style="margin-left:0.5rem"><?= h(str_replace('_',' ',$pen['penalty_type'])) ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.5rem">
                        <span class="text-muted" style="font-size:0.8rem">Rd <?= h((string)$pen['round_number']) ?> <?= h($pen['race_name']) ?></span>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="penalty_id" value="<?= (int)$pen['id'] ?>">
                            <input type="hidden" name="delete_penalty" value="1">
                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this penalty?">Delete</button>
                        </form>
                    </div>
                </div>
                <p style="margin-top:0.4rem;font-size:0.85rem;color:var(--text-secondary)"><?= h($pen['reason']) ?></p>
                <?php if ($pen['time_penalty_s']): ?>
                <div style="font-size:0.8rem"><strong>+<?= h((string)$pen['time_penalty_s']) ?>s</strong> time penalty</div>
                <?php endif; ?>
                <?php if ($pen['grid_penalty_positions']): ?>
                <div style="font-size:0.8rem"><strong><?= h((string)$pen['grid_penalty_positions']) ?> place</strong> grid drop</div>
                <?php endif; ?>
                <?php if ($pen['licence_points_awarded']): ?>
                <div style="font-size:0.8rem"><strong><?= h((string)$pen['licence_points_awarded']) ?> licence point<?= $pen['licence_points_awarded'] != 1 ? 's' : '' ?></strong></div>
                <?php endif; ?>
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem">By <?= h($pen['issued_by_name']) ?> — <?= h(date('d M Y H:i', strtotime($pen['issued_at']))) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
var raceDriverMap = <?= json_encode($raceDriverMap, JSON_HEX_TAG) ?>;

function filterPenaltyDrivers(raceId) {
    var sel = document.getElementById('pen_person_id');
    var prev = sel.value;
    sel.innerHTML = '<option value="">— Select Driver —</option>';
    var drivers = raceId && raceDriverMap[raceId] ? raceDriverMap[raceId] : [];
    drivers.forEach(function(d) {
        var opt = document.createElement('option');
        opt.value = d.id;
        opt.textContent = d.label;
        if (d.id == prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

function updatePenaltyFields(type) {
    document.getElementById('time-pen-group').style.display  = type === 'time_penalty' ? '' : 'none';
    document.getElementById('grid-pen-group').style.display  = type === 'grid_penalty' ? '' : 'none';
    document.getElementById('lic-points-group').style.display= type === 'licence_points' ? '' : 'none';
}

// Initialize driver list for pre-selected race
(function() {
    var raceId = document.getElementById('pen_race_id').value;
    if (raceId) filterPenaltyDrivers(raceId);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

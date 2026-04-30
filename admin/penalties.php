<?php
$pageTitle = 'Penalties';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db      = getDB();
$seasons = getSeasonList();
$errors  = [];

$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
}
$filterRaceId   = intval($_GET['race_id'] ?? 0);
$filterPersonId = intval($_GET['person_id'] ?? 0);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_penalty'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $penId = intval($_POST['penalty_id'] ?? 0);
        $db->prepare('DELETE FROM penalties WHERE id = ?')->execute([$penId]);
        logAudit($_SESSION['user_id'], 'delete', 'penalties', $penId, 'Deleted by admin');
        rotateCSRFToken();
        $qs = http_build_query(['season'=>$selectedSeasonId,'race_id'=>$filterRaceId,'person_id'=>$filterPersonId]);
        redirectWithMessage(APP_URL . '/admin/penalties.php?' . $qs, 'success', 'Penalty deleted.');
    }
}

// Handle CSV export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="penalties_season.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Season','Round','Race','Driver','#','Type','Reason','Time(s)','Grid Positions','Licence Pts','DSQ']);
    $stmt = $db->prepare("
        SELECT s.year, r.round_number, r.name AS race_name, p.first_name, p.last_name, p.racing_number,
               pen.penalty_type, pen.reason, pen.time_penalty_s, pen.grid_penalty_positions, pen.licence_points_awarded, pen.is_dsq
        FROM penalties pen
        JOIN races r ON r.id = pen.race_id
        JOIN seasons s ON s.id = r.season_id
        JOIN people p ON p.id = pen.person_id
        WHERE s.id = ?
        ORDER BY s.year DESC, r.round_number DESC, pen.issued_at DESC
    ");
    $stmt->execute([$selectedSeasonId]);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [$row['year'],$row['round_number'],$row['race_name'],$row['first_name'].' '.$row['last_name'],'#'.$row['racing_number'],$row['penalty_type'],$row['reason'],$row['time_penalty_s']??'',$row['grid_penalty_positions']??'',$row['licence_points_awarded']??'',$row['is_dsq']?'Yes':'No']);
    }
    fclose($out);
    exit;
}

// Races for filter dropdown
$races = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("SELECT id, name, round_number FROM races WHERE season_id=? ORDER BY round_number");
    $stmt->execute([$selectedSeasonId]);
    $races = $stmt->fetchAll();
}

// Drivers for form + filter
$drivers = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT p.id, CONCAT(p.first_name,' ',p.last_name,' (#',p.racing_number,')') AS label
        FROM people p JOIN driver_seasons ds ON ds.person_id=p.id
        WHERE ds.season_id=? ORDER BY p.last_name
    ");
    $stmt->execute([$selectedSeasonId]);
    $drivers = $stmt->fetchAll();
}

// Handle issue penalty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_penalty'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $penRaceId  = intval($_POST['pen_race_id'] ?? 0);
        $personId   = intval($_POST['person_id'] ?? 0);
        $type       = $_POST['penalty_type'] ?? '';
        $reason     = strip_tags(trim($_POST['reason'] ?? ''));
        $timePenS   = intval($_POST['time_penalty_s'] ?? 0) ?: null;
        $gridPen    = intval($_POST['grid_penalty_positions'] ?? 0) ?: null;
        $licPoints  = intval($_POST['licence_points_awarded'] ?? 0) ?: null;
        $isDsq      = ($type === 'dsq') ? 1 : 0;

        $validTypes = ['time_penalty','grid_penalty','licence_points','dsq','warning'];
        if (!in_array($type, $validTypes)) $errors[] = 'Invalid penalty type.';
        if (!$penRaceId) $errors[] = 'Race is required.';
        if (!$personId)  $errors[] = 'Driver is required.';
        if (!$reason)    $errors[] = 'Reason is required.';

        if (empty($errors)) {
            $db->prepare("INSERT INTO penalties (race_id,person_id,issued_by,penalty_type,reason,time_penalty_s,grid_penalty_positions,licence_points_awarded,is_dsq) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$penRaceId,$personId,$_SESSION['user_id'],$type,$reason,$timePenS,$gridPen,$licPoints,$isDsq]);
            if ($isDsq) {
                $db->prepare("UPDATE race_results rr JOIN race_entries re ON re.id=rr.race_entry_id SET rr.status='DSQ' WHERE re.race_id=? AND re.person_id=?")
                   ->execute([$penRaceId, $personId]);
            }
            logAudit($_SESSION['user_id'], 'create', 'penalties', null, "Race $penRaceId, Person $personId");
            rotateCSRFToken();
            $qs = http_build_query(['season'=>$selectedSeasonId,'race_id'=>$filterRaceId,'person_id'=>$filterPersonId]);
            redirectWithMessage(APP_URL . '/admin/penalties.php?' . $qs, 'success', 'Penalty issued.');
        }
    }
}

// Load penalties with filters
$penWhere  = 's.id = ?';
$penParams = [$selectedSeasonId];
if ($filterRaceId)   { $penWhere .= ' AND pen.race_id = ?';   $penParams[] = $filterRaceId; }
if ($filterPersonId) { $penWhere .= ' AND pen.person_id = ?'; $penParams[] = $filterPersonId; }

$stmt = $db->prepare("
    SELECT pen.*, r.name AS race_name, r.round_number, s.year,
           p.first_name, p.last_name, p.racing_number,
           u.name AS issued_by_name
    FROM penalties pen
    JOIN races r ON r.id = pen.race_id
    JOIN seasons s ON s.id = r.season_id
    JOIN people p ON p.id = pen.person_id
    JOIN users u ON u.id = pen.issued_by
    WHERE $penWhere
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
        <p class="page-subtitle"><?= count($penalties) ?> penalt<?= count($penalties)!=1?'ies':'y' ?></p>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <form method="get">
            <select name="season" class="form-control" style="width:auto" onchange="this.form.submit()">
                <?php foreach ($seasons as $s): ?>
                <option value="<?= h((string)$s['id']) ?>"<?= $s['id']==$selectedSeasonId?' selected':'' ?>><?= h((string)$s['year']) ?><?= $s['is_active']?' ★':'' ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="?season=<?= $selectedSeasonId ?>&export=1" class="btn btn-outline">Export CSV</a>
    </div>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="grid-2">
<!-- Issue Penalty -->
<div class="card">
    <div class="card-title">Issue Penalty</div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-group">
            <label class="form-label required">Race</label>
            <select name="pen_race_id" class="form-control">
                <option value="">— Select Race —</option>
                <?php foreach ($races as $r): ?>
                <option value="<?= h((string)$r['id']) ?>"<?= $filterRaceId==$r['id']?' selected':'' ?>>Rd <?= h((string)$r['round_number']) ?> — <?= h($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required">Driver</label>
            <select name="person_id" class="form-control">
                <option value="">— Select Driver —</option>
                <?php foreach ($drivers as $d): ?>
                <option value="<?= h((string)$d['id']) ?>"<?= $filterPersonId==$d['id']?' selected':'' ?>><?= h($d['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required">Penalty Type</label>
            <select name="penalty_type" id="pen_type" class="form-control" onchange="togglePenFields(this.value)">
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
            <textarea name="reason" class="form-control" rows="3" required maxlength="500"></textarea>
        </div>
        <button type="submit" name="issue_penalty" class="btn btn-primary">Issue Penalty</button>
    </form>
</div>

<!-- Filters + List -->
<div>
    <div class="card" style="margin-bottom:1rem">
        <form method="get" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="season" value="<?= $selectedSeasonId ?>">
            <div class="form-group" style="margin:0;flex:1;min-width:160px">
                <label class="form-label">Filter by Race</label>
                <select name="race_id" class="form-control">
                    <option value="">All Races</option>
                    <?php foreach ($races as $r): ?>
                    <option value="<?= h((string)$r['id']) ?>"<?= $filterRaceId==$r['id']?' selected':'' ?>>Rd <?= h((string)$r['round_number']) ?> — <?= h($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:160px">
                <label class="form-label">Filter by Driver</label>
                <select name="person_id" class="form-control">
                    <option value="">All Drivers</option>
                    <?php foreach ($drivers as $d): ?>
                    <option value="<?= h((string)$d['id']) ?>"<?= $filterPersonId==$d['id']?' selected':'' ?>><?= h($d['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            <a href="<?= APP_URL ?>/admin/penalties.php?season=<?= $selectedSeasonId ?>" class="btn btn-secondary btn-sm">Clear</a>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Penalties (<?= count($penalties) ?>)</div>
        <?php if (empty($penalties)): ?>
        <div class="empty-state"><p>No penalties found.</p></div>
        <?php else: ?>
        <?php foreach ($penalties as $pen): ?>
        <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                    <a href="<?= APP_URL ?>/admin/people.php?id=<?= $pen['person_id'] ?>" class="fw-bold"><?= h($pen['first_name'] . ' ' . $pen['last_name']) ?></a>
                    <span class="text-muted" style="font-size:0.8rem"> #<?= h((string)$pen['racing_number']) ?></span>
                    <span class="status-badge status-<?= $pen['is_dsq'] ? 'dsq' : 'warning' ?>" style="margin-left:0.5rem"><?= h(str_replace('_',' ',$pen['penalty_type'])) ?></span>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="penalty_id" value="<?= $pen['id'] ?>">
                    <input type="hidden" name="delete_penalty" value="1">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this penalty?">Delete</button>
                </form>
            </div>
            <div class="text-muted" style="font-size:0.8rem">
                <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $pen['race_id'] ?>">Rd <?= h((string)$pen['round_number']) ?> <?= h($pen['race_name']) ?></a>
                &bull; <?= h((string)$pen['year']) ?>
            </div>
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:var(--text-secondary)"><?= h($pen['reason']) ?></p>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.2rem">
                <?php if ($pen['time_penalty_s']): ?>+<?= h((string)$pen['time_penalty_s']) ?>s &bull; <?php endif; ?>
                <?php if ($pen['grid_penalty_positions']): ?><?= h((string)$pen['grid_penalty_positions']) ?> place grid drop &bull; <?php endif; ?>
                <?php if ($pen['licence_points_awarded']): ?><?= h((string)$pen['licence_points_awarded']) ?> licence pt<?= $pen['licence_points_awarded']!=1?'s':'' ?> &bull; <?php endif; ?>
                By <?= h($pen['issued_by_name']) ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
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

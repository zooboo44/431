<?php
$_isEdit   = isset($_GET['id']) && ($_GET['action'] ?? '') === 'edit';
$_isCreate = ($_GET['action'] ?? '') === 'create';
$_isForm   = $_isEdit || $_isCreate;

if ($_isForm) $pageTitle = $_isEdit ? 'Edit Race' : 'Add Race';
else          $pageTitle = 'Races';

require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db      = getDB();
$errors  = [];

// ─── FORM VIEW (CREATE / EDIT) ────────────────────────────────────────────────
if ($_isForm) {
    $isEdit  = $_isEdit;
    $raceId  = $isEdit ? intval($_GET['id']) : 0;
    $race    = null;

    if ($isEdit) {
        $stmt = $db->prepare('SELECT * FROM races WHERE id = ?');
        $stmt->execute([$raceId]);
        $race = $stmt->fetch();
        if (!$race) { include __DIR__ . '/../includes/404.php'; exit; }
    }

    $circuits = $db->query("SELECT id, name, country FROM circuits WHERE is_active=1 ORDER BY name")->fetchAll();

    if (!$isEdit) {
        $seasons = getSeasonList();
        $defaultSeasonId = intval($_GET['season_id'] ?? 0);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid request token.';
        } else {
            $circuitId   = intval($_POST['circuit_id'] ?? 0);
            $name        = strip_tags(trim($_POST['name'] ?? ''));
            $roundNumber = intval($_POST['round_number'] ?? 0);
            $raceDate    = $_POST['race_date'] ?? '';
            $qualDate    = $_POST['qualifying_date'] ?? '';
            $hasSprint   = isset($_POST['has_sprint']) ? 1 : 0;
            $sprintDate  = $_POST['sprint_date'] ?? '';

            if (!$name || !$circuitId || !$raceDate || $roundNumber < 1) $errors[] = 'Required fields missing.';
            if ($qualDate && $raceDate && $raceDate <= $qualDate) $errors[] = 'Race date must be after qualifying date.';

            if ($isEdit) {
                $seasonId = $race['season_id'];
                $status   = $_POST['status'] ?? 'scheduled';
                $validStatuses = ['scheduled','in_progress','completed','cancelled'];
                if (!in_array($status, $validStatuses)) $status = 'scheduled';
            } else {
                $seasonId = intval($_POST['season_id'] ?? 0);
                if (!$seasonId) $errors[] = 'Season is required.';
            }

            if (empty($errors) && !$isEdit) {
                $chk = $db->prepare('SELECT id FROM races WHERE season_id=? AND round_number=?');
                $chk->execute([$seasonId, $roundNumber]);
                if ($chk->fetch()) $errors[] = "Round $roundNumber already exists for this season.";
            }
            if (empty($errors) && $raceDate) {
                $chkDate = $db->prepare('SELECT id FROM races WHERE season_id=? AND race_date=?' . ($isEdit ? ' AND id!=?' : ''));
                $chkDate->execute($isEdit ? [$seasonId, $raceDate, $raceId] : [$seasonId, $raceDate]);
                if ($chkDate->fetch()) $errors[] = 'Another race in this season already has that date.';
            }

            if (empty($errors)) {
                $sprintVal = $hasSprint && $sprintDate ? $sprintDate : null;
                if ($isEdit) {
                    $db->prepare("UPDATE races SET circuit_id=?,name=?,round_number=?,race_date=?,qualifying_date=?,has_sprint=?,sprint_date=?,status=? WHERE id=?")
                       ->execute([$circuitId,$name,$roundNumber,$raceDate,$qualDate ?: null,$hasSprint,$sprintVal,$status,$raceId]);
                    logAudit($_SESSION['user_id'], 'update', 'races', $raceId, $name);
                } else {
                    $db->prepare("INSERT INTO races (season_id,circuit_id,name,round_number,race_date,qualifying_date,has_sprint,sprint_date) VALUES (?,?,?,?,?,?,?,?)")
                       ->execute([$seasonId,$circuitId,$name,$roundNumber,$raceDate,$qualDate ?: null,$hasSprint,$sprintVal]);
                    $raceId = (int)$db->lastInsertId();
                    logAudit($_SESSION['user_id'], 'create', 'races', $raceId, $name);
                }
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/races.php?season=' . ($isEdit ? $race['season_id'] : $seasonId), 'success', "Race '{$name}' " . ($isEdit ? 'updated' : 'created') . '.');
            }
        }
    }

    $csrfToken = generateCSRFToken();
    $backUrl   = APP_URL . '/admin/races.php' . ($isEdit && $race ? '?season=' . $race['season_id'] : '');
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? 'Edit Race' : 'Add Race' ?></h1>
        <?php if ($isEdit && $race): ?><p class="page-subtitle"><?= h($race['name']) ?></p><?php endif; ?>
    </div>
    <a href="<?= $backUrl ?>" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post" id="race-form">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <?php if (!$isEdit): ?>
            <div class="form-group">
                <label class="form-label required">Season</label>
                <select name="season_id" class="form-control">
                    <?php foreach ($seasons as $s): ?>
                    <option value="<?= h((string)$s['id']) ?>"<?= ($s['id'] == ($defaultSeasonId ?: ($seasons[0]['id'] ?? 0))) ? ' selected' : '' ?>><?= h((string)$s['year']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label class="form-label required">Round Number</label>
                <input type="number" name="round_number" class="form-control" value="<?= h((string)($_POST['round_number'] ?? ($race['round_number'] ?? ''))) ?>" required min="1" max="30">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label required">Circuit</label>
            <select name="circuit_id" id="circuit_id" class="form-control"<?= !$isEdit ? ' onchange="autoFillName(this)"' : '' ?>>
                <?php if (!$isEdit): ?><option value="">— Select Circuit —</option><?php endif; ?>
                <?php foreach ($circuits as $c): ?>
                <option value="<?= h((string)$c['id']) ?>"
                    data-name="<?= h($c['name']) ?>"
                    <?= (($_POST['circuit_id'] ?? null) == $c['id'] || ($race['circuit_id'] ?? null) == $c['id']) ? ' selected' : '' ?>>
                    <?= h($c['name']) ?> (<?= h($c['country']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required">Race Name</label>
            <input type="text" id="race_name" name="name" class="form-control" value="<?= h($_POST['name'] ?? ($race['name'] ?? '')) ?>" required maxlength="100"<?= !$isEdit ? ' placeholder="e.g. Bahrain Grand Prix"' : '' ?>>
            <?php if (!$isEdit): ?><div class="form-hint">Auto-filled from circuit — edit to override</div><?php endif; ?>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Qualifying Date</label>
                <input type="date" name="qualifying_date" class="form-control" value="<?= h($_POST['qualifying_date'] ?? ($race['qualifying_date'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label required">Race Date</label>
                <input type="date" name="race_date" class="form-control" value="<?= h($_POST['race_date'] ?? ($race['race_date'] ?? '')) ?>" required>
            </div>
        </div>
        <?php if ($isEdit): ?>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <?php foreach (['scheduled','in_progress','completed','cancelled'] as $s): ?>
                <option value="<?= h($s) ?>"<?= ($race['status'] ?? 'scheduled') === $s ? ' selected' : '' ?>><?= h(ucfirst(str_replace('_',' ',$s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
                <input type="checkbox" name="has_sprint" value="1" id="has_sprint"
                    <?= (isset($_POST['has_sprint']) || ($race['has_sprint'] ?? false)) ? ' checked' : '' ?>
                    onchange="toggleSprintDate(this.checked)">
                <span><?= $isEdit ? 'Has Sprint Race' : 'This weekend has a Sprint Race' ?></span>
            </label>
        </div>
        <div class="form-group" id="sprint-date-group" style="<?= (isset($_POST['has_sprint']) || ($race['has_sprint'] ?? false)) ? '' : 'display:none' ?>">
            <label class="form-label">Sprint Date</label>
            <input type="date" name="sprint_date" class="form-control" value="<?= h($_POST['sprint_date'] ?? ($race['sprint_date'] ?? '')) ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Race' ?></button>
            <a href="<?= $backUrl ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
<?php if (!$isEdit): ?>
function autoFillName(sel) {
    const opt = sel.options[sel.selectedIndex];
    const nameField = document.getElementById('race_name');
    if (nameField.value === '' || nameField.dataset.autofilled === '1') {
        if (opt.dataset.name) {
            nameField.value = opt.dataset.name + ' Grand Prix';
            nameField.dataset.autofilled = '1';
        }
    }
}
document.getElementById('race_name').addEventListener('input', function() {
    this.dataset.autofilled = '0';
});
<?php endif; ?>
function toggleSprintDate(show) {
    document.getElementById('sprint-date-group').style.display = show ? '' : 'none';
}
</script>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── LIST VIEW ────────────────────────────────────────────────────────────────
$seasons = getSeasonList();

$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
}

$selectedYear = '';
foreach ($seasons as $s) { if ($s['id'] == $selectedSeasonId) { $selectedYear = $s['year']; break; } }

// CSV export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="results_' . $selectedYear . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Season','Round','Race','Driver','#','Team','Start','Finish','Status','Points','FL Bonus','Laps','Fastest Lap (s)','Total Time (s)']);
    $stmt = $db->prepare("
        SELECT s.year, r.round_number, r.name AS race_name,
               p.first_name, p.last_name, p.racing_number,
               t.name AS team_name,
               rr.start_position, rr.finish_position, rr.status, rr.points_scored,
               rr.fastest_lap_bonus, rr.laps_completed, rr.fastest_lap_ms, rr.total_race_time_ms
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id
        JOIN races r ON r.id = re.race_id
        JOIN seasons s ON s.id = r.season_id
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE s.id = ? AND rr.is_sprint = 0
        ORDER BY r.round_number ASC, rr.finish_position ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [
            $row['year'], $row['round_number'], $row['race_name'],
            $row['first_name'] . ' ' . $row['last_name'], '#' . $row['racing_number'],
            $row['team_name'], $row['start_position'], $row['finish_position'] ?? '',
            $row['status'], number_format((float)$row['points_scored'], 1),
            $row['fastest_lap_bonus'] ? 'Yes' : '',
            $row['laps_completed'],
            $row['fastest_lap_ms'] ? round($row['fastest_lap_ms'] / 1000, 3) : '',
            $row['total_race_time_ms'] ? round($row['total_race_time_ms'] / 1000, 3) : '',
        ]);
    }
    fclose($out);
    exit;
}

$races = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT r.*, c.name AS circuit, c.country,
               (SELECT COUNT(*) FROM race_entries re WHERE re.race_id=r.id) AS entry_count,
               (SELECT COUNT(*) FROM qualifying_results qr JOIN race_entries re2 ON re2.id = qr.race_entry_id WHERE re2.race_id = r.id) AS qual_count,
               (SELECT COUNT(*) FROM race_results rr JOIN race_entries re3 ON re3.id = rr.race_entry_id WHERE re3.race_id = r.id AND rr.is_sprint=0) AS result_count,
               (SELECT CONCAT(p.first_name,' ',p.last_name)
                FROM race_results rr2
                JOIN race_entries re4 ON re4.id = rr2.race_entry_id
                JOIN people p ON p.id = re4.person_id
                WHERE re4.race_id = r.id AND rr2.finish_position = 1 AND rr2.is_sprint = 0 LIMIT 1) AS winner
        FROM races r JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ?
        ORDER BY r.round_number ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $races = $stmt->fetchAll();
}

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Races</h1>
        <p class="page-subtitle"><?= count($races) ?> race<?= count($races) != 1 ? 's' : '' ?><?= $selectedYear ? ' — ' . h((string)$selectedYear) : '' ?></p>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <form method="get" class="season-selector">
            <select name="season" onchange="this.form.submit()">
                <?php foreach ($seasons as $s): ?>
                <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>><?= h((string)$s['year']) ?><?= $s['is_active'] ? ' ★' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="?season=<?= $selectedSeasonId ?>&export=1" class="btn btn-outline">Export CSV</a>
        <a href="<?= APP_URL ?>/admin/races.php?action=create&season_id=<?= $selectedSeasonId ?>" class="btn btn-primary">+ Add Race</a>
    </div>
</div>

<div class="table-container">
    <table class="sortable">
        <thead><tr>
            <th>Round</th><th>Name</th><th>Circuit</th><th>Date</th><th>Sprint</th><th>Status</th><th>Entries</th><th>Qual</th><th>Results</th><th>Winner</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($races)): ?>
        <tr><td colspan="11" class="text-center text-muted" style="padding:2rem">No races for this season.</td></tr>
        <?php else: ?>
        <?php foreach ($races as $r): ?>
        <?php $delMsg = "Delete race '{$r['name']}'? This will remove all {$r['entry_count']} entries, results and qualifying data. Cannot be undone."; ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $r['id'] ?>">
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><strong><?= h($r['name']) ?></strong></td>
            <td class="text-muted"><?= h($r['circuit']) ?>, <?= h($r['country']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '<span class="text-muted">No</span>' ?></td>
            <td><span class="status-badge status-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
            <td><?= h((string)$r['entry_count']) ?></td>
            <td><?= $r['qual_count'] > 0 ? '<span class="text-success">' . h((string)$r['qual_count']) . '</span>' : '<span class="text-muted">0</span>' ?></td>
            <td><?= $r['result_count'] > 0 ? '<span class="text-success">' . h((string)$r['result_count']) . '</span>' : '<span class="text-muted">0</span>' ?></td>
            <td class="text-muted" style="font-size:0.85rem"><?= $r['winner'] ? h($r['winner']) : '—' ?></td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/races.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                <form method="post" action="<?= APP_URL ?>/admin/delete.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="race">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/races.php?season=' . $selectedSeasonId) ?>">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="<?= h($delMsg) ?>">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

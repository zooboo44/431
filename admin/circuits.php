<?php
$_isEdit   = isset($_GET['id']) && ($_GET['action'] ?? '') === 'edit';
$_isCreate = ($_GET['action'] ?? '') === 'create';
$_isForm   = $_isEdit || $_isCreate;
$_isDetail = isset($_GET['id']) && !isset($_GET['action']);

if ($_isForm)        $pageTitle = $_isEdit ? 'Edit Circuit' : 'Add Circuit';
elseif ($_isDetail)  $pageTitle = 'Circuit Detail';
else                 $pageTitle = 'Circuits';

require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$errors = [];

// ─── FORM VIEW (CREATE / EDIT) ────────────────────────────────────────────────
if ($_isForm) {
    $isEdit    = $_isEdit;
    $circuitId = $isEdit ? intval($_GET['id']) : 0;
    $circuit   = null;

    if ($isEdit) {
        $stmt = $db->prepare('SELECT * FROM circuits WHERE id = ?');
        $stmt->execute([$circuitId]);
        $circuit = $stmt->fetch();
        if (!$circuit) { include __DIR__ . '/../includes/404.php'; exit; }
    }

    $people = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM people WHERE is_active=1 ORDER BY last_name")->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid request token.';
        } else {
            $name        = strip_tags(trim($_POST['name'] ?? ''));
            $country     = strip_tags(trim($_POST['country'] ?? ''));
            $city        = strip_tags(trim($_POST['city'] ?? ''));
            $lengthKm    = floatval($_POST['length_km'] ?? 0);
            $laps        = intval($_POST['number_of_laps'] ?? 0);
            $type        = $_POST['circuit_type'] ?? '';
            $lapRecordS  = trim($_POST['lap_record_s'] ?? '');
            $lapRecordMs = $lapRecordS !== '' ? (int)round(floatval($lapRecordS) * 1000) : null;
            $recordHolder= intval($_POST['lap_record_person_id'] ?? 0) ?: null;
            $isActive    = $isEdit ? (isset($_POST['is_active']) ? 1 : 0) : 1;

            if (!$name || !$country || !$city || $lengthKm <= 0 || $laps <= 0) $errors[] = 'All required fields must be filled.';
            if (!in_array($type, ['permanent','street'])) $errors[] = 'Circuit type must be permanent or street.';

            if (empty($errors)) {
                $chk = $db->prepare('SELECT id FROM circuits WHERE name = ?' . ($isEdit ? ' AND id != ?' : ''));
                $chk->execute($isEdit ? [$name, $circuitId] : [$name]);
                if ($chk->fetch()) $errors[] = "A circuit named '{$name}' already exists.";
            }

            if (empty($errors)) {
                if ($isEdit) {
                    $db->prepare("UPDATE circuits SET name=?,country=?,city=?,length_km=?,number_of_laps=?,circuit_type=?,lap_record_ms=?,lap_record_person_id=?,is_active=? WHERE id=?")
                       ->execute([$name,$country,$city,$lengthKm,$laps,$type,$lapRecordMs,$recordHolder,$isActive,$circuitId]);
                    logAudit($_SESSION['user_id'], 'update', 'circuits', $circuitId, $name);
                } else {
                    $db->prepare("INSERT INTO circuits (name,country,city,length_km,number_of_laps,circuit_type,lap_record_ms,lap_record_person_id) VALUES (?,?,?,?,?,?,?,?)")
                       ->execute([$name,$country,$city,$lengthKm,$laps,$type,$lapRecordMs,$recordHolder]);
                    $circuitId = (int)$db->lastInsertId();
                    logAudit($_SESSION['user_id'], 'create', 'circuits', $circuitId, $name);
                }
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/circuits.php', 'success', "Circuit '{$name}' " . ($isEdit ? 'updated' : 'created') . '.');
            }
        }
    }

    $csrfToken  = generateCSRFToken();
    $lapRecordS = $circuit ? ($circuit['lap_record_ms'] ? round($circuit['lap_record_ms'] / 1000, 3) : '') : ($_POST['lap_record_s'] ?? '');
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? 'Edit Circuit' : 'Add Circuit' ?></h1>
        <?php if ($isEdit && $circuit): ?><p class="page-subtitle"><?= h($circuit['name']) ?></p><?php endif; ?>
    </div>
    <a href="<?= APP_URL ?>/admin/circuits.php" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-group">
            <label class="form-label required">Circuit Name</label>
            <input type="text" name="name" class="form-control" value="<?= h($_POST['name'] ?? ($circuit['name'] ?? '')) ?>" required maxlength="100">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Country</label>
                <input type="text" name="country" class="form-control" value="<?= h($_POST['country'] ?? ($circuit['country'] ?? '')) ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required">City</label>
                <input type="text" name="city" class="form-control" value="<?= h($_POST['city'] ?? ($circuit['city'] ?? '')) ?>" required maxlength="50">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Length (km)</label>
                <input type="number" name="length_km" class="form-control" value="<?= h((string)($_POST['length_km'] ?? ($circuit['length_km'] ?? ''))) ?>" required step="0.001" min="1" max="20">
            </div>
            <div class="form-group">
                <label class="form-label required">Number of Laps</label>
                <input type="number" name="number_of_laps" class="form-control" value="<?= h((string)($_POST['number_of_laps'] ?? ($circuit['number_of_laps'] ?? ''))) ?>" required min="1" max="100">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Circuit Type</label>
                <select name="circuit_type" class="form-control">
                    <option value="permanent"<?= ($_POST['circuit_type'] ?? ($circuit['circuit_type'] ?? '')) === 'permanent' ? ' selected' : '' ?>>Permanent</option>
                    <option value="street"<?= ($_POST['circuit_type'] ?? ($circuit['circuit_type'] ?? '')) === 'street' ? ' selected' : '' ?>>Street</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Lap Record (seconds, e.g. 91.447)</label>
                <input type="number" name="lap_record_s" class="form-control" value="<?= h((string)($_POST['lap_record_s'] ?? $lapRecordS)) ?>" step="0.001" min="50">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Lap Record Holder</label>
            <select name="lap_record_person_id" class="form-control">
                <option value="">— None —</option>
                <?php foreach ($people as $p): ?>
                <option value="<?= h((string)$p['id']) ?>"<?= ($circuit['lap_record_person_id'] ?? '') == $p['id'] ? ' selected' : '' ?>><?= h($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($isEdit): ?>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
                <input type="checkbox" name="is_active" value="1"<?= ($circuit['is_active'] ?? 1) ? ' checked' : '' ?>>
                <span>Active circuit</span>
            </label>
        </div>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Circuit' ?></button>
            <a href="<?= APP_URL ?>/admin/circuits.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── DETAIL VIEW ──────────────────────────────────────────────────────────────
if ($_isDetail) {
    $circuitId = intval($_GET['id']);

    $stmt = $db->prepare("
        SELECT c.*, CONCAT(p.first_name,' ',p.last_name) AS record_holder_name
        FROM circuits c
        LEFT JOIN people p ON p.id = c.lap_record_person_id
        WHERE c.id = ?
    ");
    $stmt->execute([$circuitId]);
    $circuit = $stmt->fetch();
    if (!$circuit) { include __DIR__ . '/../includes/404.php'; exit; }

    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, r.status, r.has_sprint, s.year,
               (SELECT COUNT(*) FROM race_entries re WHERE re.race_id=r.id) AS entry_count
        FROM races r
        JOIN seasons s ON s.id = r.season_id
        WHERE r.circuit_id = ?
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
    <div style="display:flex;gap:0.5rem">
        <a href="<?= APP_URL ?>/admin/circuits.php?action=edit&id=<?= $circuitId ?>" class="btn btn-outline">Edit</a>
        <a href="<?= APP_URL ?>/admin/circuits.php" class="btn btn-outline">&larr; Circuits</a>
    </div>
</div>

<div class="grid-2" style="margin-bottom:1.5rem">
<div class="card">
    <div class="card-title">Circuit Info</div>
    <div class="info-grid">
        <div class="info-item"><div class="info-label">Country</div><div class="info-value"><?= h($circuit['country']) ?></div></div>
        <div class="info-item"><div class="info-label">City</div><div class="info-value"><?= h($circuit['city']) ?></div></div>
        <div class="info-item"><div class="info-label">Type</div><div class="info-value"><span class="status-badge <?= $circuit['circuit_type']==='street'?'status-warning':'status-scheduled' ?>"><?= h($circuit['circuit_type']) ?></span></div></div>
        <div class="info-item"><div class="info-label">Length</div><div class="info-value"><?= h(number_format($circuit['length_km'],3)) ?> km</div></div>
        <div class="info-item"><div class="info-label">Laps</div><div class="info-value"><?= h((string)$circuit['number_of_laps']) ?></div></div>
        <div class="info-item"><div class="info-label">Race Distance</div><div class="info-value"><?= h(number_format($circuit['length_km']*$circuit['number_of_laps'],2)) ?> km</div></div>
        <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="status-badge <?= $circuit['is_active']?'status-active':'status-inactive' ?>"><?= $circuit['is_active']?'Active':'Inactive' ?></span></div></div>
        <?php if ($circuit['lap_record_ms']): ?>
        <div class="info-item">
            <div class="info-label">Lap Record</div>
            <div class="info-value text-success fw-bold"><?= h(formatLapTime($circuit['lap_record_ms'])) ?><?= $circuit['record_holder_name'] ? ' <span class="text-muted" style="font-weight:400;font-size:0.85rem">— ' . h($circuit['record_holder_name']) . '</span>' : '' ?></div>
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
            <div class="stat-value"><?= count(array_unique(array_column($races,'year'))) ?></div>
            <div class="stat-label">Seasons</div>
        </div>
    </div>
</div>
</div>

<div class="card">
    <div class="card-title">Races at <?= h($circuit['name']) ?></div>
    <?php if (empty($races)): ?>
    <div class="empty-state"><p>No races held here yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Season</th><th>Rd</th><th>Race</th><th>Date</th><th>Status</th><th>Sprint</th><th>Entries</th></tr></thead>
        <tbody>
        <?php foreach ($races as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $r['id'] ?>">
            <td><strong><?= h((string)$r['year']) ?></strong></td>
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><?= h($r['name']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td><span class="status-badge status-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
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

// ─── LIST VIEW ────────────────────────────────────────────────────────────────
$stmt = $db->query("
    SELECT c.*, CONCAT(p.first_name,' ',p.last_name) AS record_holder,
           COUNT(DISTINCT r.id) AS race_count
    FROM circuits c
    LEFT JOIN people p ON p.id = c.lap_record_person_id
    LEFT JOIN races r ON r.circuit_id = c.id
    GROUP BY c.id
    ORDER BY c.country, c.name
");
$circuits = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Circuits</h1>
        <p class="page-subtitle"><?= count($circuits) ?> circuit<?= count($circuits) != 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/circuits.php?action=create" class="btn btn-primary">+ Add Circuit</a>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="circuits-table" placeholder="Search circuits...">
        </div>
    </div>
    <table class="sortable" id="circuits-table">
        <thead><tr>
            <th>Name</th><th>Country</th><th>City</th><th>Type</th><th>Length</th><th>Laps</th><th>Races</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($circuits)): ?>
        <tr><td colspan="9" class="text-center text-muted" style="padding:2rem">No circuits found.</td></tr>
        <?php else: ?>
        <?php foreach ($circuits as $c): ?>
        <?php $delMsg = "Delete circuit '{$c['name']}'? This will remove all {$c['race_count']} associated races and their results. This cannot be undone."; ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/circuits.php?id=<?= $c['id'] ?>">
            <td><strong><?= h($c['name']) ?></strong></td>
            <td><?= h($c['country']) ?></td>
            <td class="text-muted"><?= h($c['city']) ?></td>
            <td><span class="status-badge <?= $c['circuit_type'] === 'street' ? 'status-warning' : 'status-scheduled' ?>"><?= h($c['circuit_type']) ?></span></td>
            <td><?= h(number_format($c['length_km'], 3)) ?> km</td>
            <td><?= h((string)$c['number_of_laps']) ?></td>
            <td><?= h((string)$c['race_count']) ?></td>
            <td><span class="status-badge <?= $c['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/circuits.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                <form method="post" action="<?= APP_URL ?>/admin/delete.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="circuit">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/circuits.php') ?>">
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

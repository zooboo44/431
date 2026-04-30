<?php
$pageTitle = 'My Drivers';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager');

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);

// ── Driver profile detail view ────────────────────────────────────────────────
if (isset($_GET['person_id'])) {
    $personId = intval($_GET['person_id']);

    $stmt = $db->prepare("
        SELECT p.* FROM people p
        JOIN driver_seasons drs ON drs.person_id = p.id
        JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
        WHERE p.id = ? LIMIT 1
    ");
    $stmt->execute([$teamId, $personId]);
    $person = $stmt->fetch();
    if (!$person) { include __DIR__ . '/../includes/403.php'; exit; }

    $stmt = $db->prepare("
        SELECT s.year, t.id AS team_id, t.name AS team_name, drs.status,
               COALESCE((
                   SELECT SUM(rr2.points_scored)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
               ),0) AS points,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
               ),0) AS wins,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position<=3 AND rr2.finish_position IS NOT NULL AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
               ),0) AS podiums,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.status='DNF' AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
               ),0) AS dnfs,
               NULL AS position
        FROM driver_seasons drs
        JOIN seasons s ON s.id = drs.season_id
        JOIN team_seasons ts ON ts.id = drs.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE drs.person_id = ?
        ORDER BY s.year DESC
    ");
    $stmt->execute([$personId]);
    $careerBySeasons = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT r.id AS race_id, r.name AS race_name, r.round_number, s.year,
               rr.finish_position, rr.points_scored, rr.status, rr.fastest_lap_bonus,
               qr.grid_position
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id AND re.person_id = ?
        JOIN race_entries re2 ON re2.id = rr.race_entry_id
        JOIN team_seasons ts ON ts.id = re2.team_season_id AND ts.team_id = ?
        JOIN races r ON r.id = re2.race_id
        JOIN seasons s ON s.id = r.season_id
        LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
        WHERE rr.is_sprint = 0
        ORDER BY r.race_date DESC
        LIMIT 20
    ");
    $stmt->execute([$personId, $teamId]);
    $recentResults = $stmt->fetchAll();

    $pageTitle = h($person['first_name'] . ' ' . $person['last_name']) . ' — Profile';
    $totalPts  = array_sum(array_column($careerBySeasons, 'points'));
    $totalWins = array_sum(array_column($careerBySeasons, 'wins'));
    $totalPods = array_sum(array_column($careerBySeasons, 'podiums'));
    $totalDnfs = array_sum(array_column($careerBySeasons, 'dnfs'));
    ?>

<div class="page-header">
    <div>
        <h1 class="page-title">#<?= h((string)$person['racing_number']) ?> <?= h($person['first_name'] . ' ' . $person['last_name']) ?></h1>
        <p class="page-subtitle"><?= h($person['nationality']) ?> &bull; Born <?= h(date('d M Y', strtotime($person['date_of_birth']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/team_manager/drivers.php" class="btn btn-outline">&larr; Drivers</a>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card"><div class="stat-value"><?= h(number_format($totalPts,1)) ?></div><div class="stat-label">Career Points</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$totalWins) ?></div><div class="stat-label">Wins</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$totalPods) ?></div><div class="stat-label">Podiums</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$totalDnfs) ?></div><div class="stat-label">DNFs</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title">Season-by-Season</div>
        <?php if (empty($careerBySeasons)): ?>
        <div class="empty-state"><p>No season data.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Year</th><th>Team</th><th>Pos</th><th>Pts</th><th>Wins</th><th>Pods</th></tr></thead>
            <tbody>
            <?php foreach ($careerBySeasons as $cs): ?>
            <tr>
                <td><strong><?= h((string)$cs['year']) ?></strong></td>
                <td><a href="<?= APP_URL ?>/shared/standings.php?view=team&id=<?= (int)$cs['team_id'] ?>" class="text-muted"><?= h($cs['team_name']) ?></a></td>
                <td><?= $cs['position'] ? 'P' . h((string)$cs['position']) : '—' ?></td>
                <td class="text-accent fw-bold"><?= h((string)($cs['points'] ?? 0)) ?></td>
                <td><?= h((string)($cs['wins'] ?? 0)) ?></td>
                <td><?= h((string)($cs['podiums'] ?? 0)) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-title">Recent Results (Our Races)</div>
        <?php if (empty($recentResults)): ?>
        <div class="empty-state"><p>No results yet.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Year</th><th>Race</th><th>Grid</th><th>Finish</th><th>Pts</th></tr></thead>
            <tbody>
            <?php foreach ($recentResults as $r): ?>
            <tr class="clickable-row" data-href="<?= APP_URL ?>/shared/results.php?id=<?= (int)$r['race_id'] ?>">
                <td class="text-muted"><?= h((string)$r['year']) ?></td>
                <td><a href="<?= APP_URL ?>/shared/results.php?id=<?= (int)$r['race_id'] ?>"><?= h($r['race_name']) ?> Rd <?= h((string)$r['round_number']) ?></a></td>
                <td class="text-muted"><?= $r['grid_position'] ? 'P' . h((string)$r['grid_position']) : '—' ?></td>
                <td>
                    <?php if ($r['finish_position']): ?>
                    <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                    <?php else: ?>
                    <span class="status-badge status-dnf"><?= h($r['status']) ?></span>
                    <?php endif; ?>
                    <?= $r['fastest_lap_bonus'] ? ' <span class="fl-indicator">&#9889;</span>' : '' ?>
                </td>
                <td class="text-accent"><?= h((string)$r['points_scored']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── Drivers list view ─────────────────────────────────────────────────────────
$errors = [];

$activeSeason = getActiveSeason();
$activeSeasonId = $activeSeason['id'] ?? null;

// Get team_season id for active season
$currentTeamSeasonId = null;
if ($activeSeasonId) {
    $stmt = $db->prepare('SELECT id FROM team_seasons WHERE team_id=? AND season_id=?');
    $stmt->execute([$teamId, $activeSeasonId]);
    $row = $stmt->fetch();
    $currentTeamSeasonId = $row['id'] ?? null;
}

// Handle roster actions (active season only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeSeasonId && $currentTeamSeasonId) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        // Request a new driver (pending approval)
        if (isset($_POST['request_driver'])) {
            $firstName = strip_tags(trim($_POST['first_name'] ?? ''));
            $lastName  = strip_tags(trim($_POST['last_name'] ?? ''));
            $natl      = strip_tags(trim($_POST['nationality'] ?? ''));
            $dob       = $_POST['date_of_birth'] ?? '';
            $number    = intval($_POST['racing_number'] ?? 0);

            if (!$firstName) $errors[] = 'First name is required.';
            if (!$lastName)  $errors[] = 'Last name is required.';
            if (!$natl)      $errors[] = 'Nationality is required.';
            if ($number < 1 || $number > 99) $errors[] = 'Racing number must be 1–99.';

            $dobError = validateDOB($dob);
            if ($dobError) $errors[] = $dobError;

            if (empty($errors)) {
                $chkNum = $db->prepare('SELECT id FROM people WHERE racing_number = ?');
                $chkNum->execute([$number]);
                if ($chkNum->fetch()) {
                    $errors[] = "Racing number #$number is already assigned.";
                }
                $chkName = $db->prepare('SELECT id FROM people WHERE first_name=? AND last_name=?');
                $chkName->execute([$firstName, $lastName]);
                if ($chkName->fetch()) {
                    $errors[] = "A driver named '$firstName $lastName' already exists.";
                }
            }

            if (empty($errors)) {
                $db->prepare("INSERT INTO people (first_name,last_name,nationality,date_of_birth,racing_number,is_active,requested_by_team_id) VALUES (?,?,?,?,?,0,?)")
                   ->execute([$firstName, $lastName, $natl, $dob, $number, $teamId]);
                $newId = (int)$db->lastInsertId();
                logAudit($_SESSION['user_id'], 'create', 'people', $newId, "Pending request: $firstName $lastName #$number by team $teamId");
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/team_manager/drivers.php', 'success', "Driver request for '$firstName $lastName' submitted. Awaiting admin approval.");
            }
        }

        // Add existing driver to current season
        if (isset($_POST['add_driver'])) {
            $personId = intval($_POST['person_id'] ?? 0);
            if (!$personId) {
                $errors[] = 'Select a driver to add.';
            } else {
                // Check already registered
                $chk = $db->prepare('SELECT id FROM driver_seasons WHERE person_id=? AND season_id=?');
                $chk->execute([$personId, $activeSeasonId]);
                if ($chk->fetch()) {
                    $errors[] = 'This driver is already registered for this season.';
                } else {
                    // Count active drivers for this team this season
                    $cnt = $db->prepare('SELECT COUNT(*) FROM driver_seasons WHERE team_season_id=? AND status="active"');
                    $cnt->execute([$currentTeamSeasonId]);
                    if ((int)$cnt->fetchColumn() >= 2) {
                        $errors[] = 'Team already has 2 active drivers. Deactivate one first.';
                    } else {
                        $db->prepare("INSERT INTO driver_seasons (person_id,team_season_id,season_id,joined_round,status) VALUES (?,?,?,1,'active')")
                           ->execute([$personId, $currentTeamSeasonId, $activeSeasonId]);
                        logAudit($_SESSION['user_id'], 'create', 'driver_seasons', null, "Person $personId added to team $teamId season $activeSeasonId");
                        rotateCSRFToken();
                        redirectWithMessage(APP_URL . '/team_manager/drivers.php', 'success', 'Driver added to current season roster.');
                    }
                }
            }
        }

        // Toggle active/inactive
        if (isset($_POST['toggle_driver'])) {
            $driverSeasonId = intval($_POST['driver_season_id'] ?? 0);
            $newStatus      = $_POST['new_status'] ?? '';

            if (!in_array($newStatus, ['active', 'replaced'])) {
                $errors[] = 'Invalid status.';
            } elseif (!$driverSeasonId) {
                $errors[] = 'Invalid driver season.';
            } else {
                // Verify ownership
                $own = $db->prepare('SELECT ds.id FROM driver_seasons ds JOIN team_seasons ts ON ts.id=ds.team_season_id WHERE ds.id=? AND ts.team_id=? AND ds.season_id=?');
                $own->execute([$driverSeasonId, $teamId, $activeSeasonId]);
                if (!$own->fetch()) {
                    $errors[] = 'Not authorised.';
                } elseif ($newStatus === 'active') {
                    // Check max 2 active
                    $cnt = $db->prepare('SELECT COUNT(*) FROM driver_seasons WHERE team_season_id=? AND status="active" AND id != ?');
                    $cnt->execute([$currentTeamSeasonId, $driverSeasonId]);
                    if ((int)$cnt->fetchColumn() >= 2) {
                        $errors[] = 'Team already has 2 active drivers. Deactivate one first.';
                    } else {
                        $db->prepare('UPDATE driver_seasons SET status="active" WHERE id=?')->execute([$driverSeasonId]);
                        logAudit($_SESSION['user_id'], 'update', 'driver_seasons', $driverSeasonId, 'Activated');
                        rotateCSRFToken();
                        redirectWithMessage(APP_URL . '/team_manager/drivers.php', 'success', 'Driver activated.');
                    }
                } else {
                    $db->prepare('UPDATE driver_seasons SET status="replaced" WHERE id=?')->execute([$driverSeasonId]);
                    logAudit($_SESSION['user_id'], 'update', 'driver_seasons', $driverSeasonId, 'Deactivated');
                    rotateCSRFToken();
                    redirectWithMessage(APP_URL . '/team_manager/drivers.php', 'success', 'Driver deactivated.');
                }
            }
        }
    }
}

// Current season roster
$currentRoster = [];
if ($activeSeasonId && $currentTeamSeasonId) {
    $stmt = $db->prepare("
        SELECT ds.id AS driver_season_id, ds.status, ds.joined_round,
               p.id AS person_id, p.first_name, p.last_name, p.racing_number, p.nationality,
               COALESCE((
                   SELECT SUM(rr2.points_scored)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = ? AND r2.status='completed' AND re2.person_id = p.id
               ),0) AS points,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = ? AND r2.status='completed' AND re2.person_id = p.id
               ),0) AS wins,
               NULL AS championship_pos
        FROM driver_seasons ds
        JOIN people p ON p.id = ds.person_id
        WHERE ds.team_season_id = ? AND ds.season_id = ?
        ORDER BY FIELD(ds.status,'active','replaced','injured'), p.racing_number
    ");
    $stmt->execute([$activeSeasonId, $activeSeasonId, $currentTeamSeasonId, $activeSeasonId]);
    $currentRoster = $stmt->fetchAll();
}

// All-time drivers for this team
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.first_name, p.last_name, p.racing_number, p.nationality,
           COUNT(DISTINCT drs.season_id) AS seasons_count,
           SUM(COALESCE(rr.points_scored,0)) AS career_points,
           SUM(CASE WHEN rr.finish_position=1 THEN 1 ELSE 0 END) AS career_wins
    FROM people p
    JOIN driver_seasons drs ON drs.person_id = p.id
    JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
    LEFT JOIN race_entries re ON re.person_id = p.id
    LEFT JOIN race_results rr ON rr.race_entry_id = re.id
    GROUP BY p.id
    ORDER BY career_points DESC
");
$stmt->execute([$teamId]);
$allDrivers = $stmt->fetchAll();

// Available drivers to add (active people not already in current season)
$availableToAdd = [];
if ($activeSeasonId) {
    $stmt = $db->prepare("
        SELECT p.id, p.first_name, p.last_name, p.racing_number
        FROM people p
        WHERE p.is_active = 1
        AND p.id NOT IN (SELECT person_id FROM driver_seasons WHERE season_id=?)
        ORDER BY p.last_name
    ");
    $stmt->execute([$activeSeasonId]);
    $availableToAdd = $stmt->fetchAll();
}

// Pending driver requests for this team
$pendingRequests = [];
$stmt = $db->prepare("SELECT id, first_name, last_name, racing_number, nationality, created_at FROM people WHERE requested_by_team_id = ? AND is_active = 0 ORDER BY created_at DESC");
$stmt->execute([$teamId]);
$pendingRequests = $stmt->fetchAll();


$activeCount = count(array_filter($currentRoster, fn($d) => $d['status'] === 'active'));
$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Drivers</h1>
        <p class="page-subtitle"><?= $activeSeason ? h((string)$activeSeason['year']) . ' season roster' : 'No active season' ?></p>
    </div>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<?php if ($activeSeasonId && $currentTeamSeasonId): ?>
<div class="grid-2">
    <!-- Current Season Roster -->
    <div class="card">
        <div class="card-title">
            &#127937; <?= h((string)$activeSeason['year']) ?> Roster
            <span class="text-muted" style="font-size:0.8rem;font-weight:400">(<?= $activeCount ?>/2 active)</span>
        </div>
        <?php if (empty($currentRoster)): ?>
        <div class="empty-state"><p>No drivers registered for this season yet.</p></div>
        <?php else: ?>
        <?php foreach ($currentRoster as $d): ?>
        <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem;display:flex;justify-content:space-between;align-items:center">
            <div>
                <a href="<?= APP_URL ?>/team_manager/drivers.php?person_id=<?= (int)$d['person_id'] ?>" style="font-weight:600">#<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?></a>
                <span class="status-badge status-<?= $d['status'] === 'active' ? 'active' : 'inactive' ?>" style="margin-left:0.5rem"><?= h($d['status']) ?></span>
                <?php if ($d['points'] !== null): ?>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:0.2rem"><?= h((string)$d['points']) ?> pts<?= $d['championship_pos'] ? ' &bull; P' . h((string)$d['championship_pos']) : '' ?></div>
                <?php endif; ?>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="driver_season_id" value="<?= (int)$d['driver_season_id'] ?>">
                <input type="hidden" name="new_status" value="<?= $d['status'] === 'active' ? 'replaced' : 'active' ?>">
                <input type="hidden" name="toggle_driver" value="1">
                <button type="submit" class="btn btn-<?= $d['status'] === 'active' ? 'secondary' : 'outline' ?> btn-sm">
                    <?= $d['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Add Driver -->
    <?php if (!empty($availableToAdd)): ?>
    <div class="card">
        <div class="card-title">+ Add Driver to <?= h((string)$activeSeason['year']) ?> Roster</div>
        <?php if ($activeCount >= 2): ?>
        <div class="notice">You already have 2 active drivers. Deactivate one to add another.</div>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <div class="form-group">
                <label class="form-label required">Driver</label>
                <select name="person_id" class="form-control">
                    <option value="">— Select Driver —</option>
                    <?php foreach ($availableToAdd as $p): ?>
                    <option value="<?= (int)$p['id'] ?>">#<?= h((string)$p['racing_number']) ?> <?= h($p['first_name'] . ' ' . $p['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="add_driver" class="btn btn-primary">Add to Roster</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Pending Driver Requests -->
<?php if (!empty($pendingRequests)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">&#9203; Pending Driver Requests</div>
    <p class="text-muted" style="font-size:0.85rem;margin-bottom:0.75rem">These requests are awaiting admin approval.</p>
    <?php foreach ($pendingRequests as $pr): ?>
    <div style="border:1px solid var(--warning);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem;display:flex;justify-content:space-between;align-items:center">
        <div>
            <strong>#<?= h((string)$pr['racing_number']) ?> <?= h($pr['first_name'] . ' ' . $pr['last_name']) ?></strong>
            <span class="status-badge status-warning" style="margin-left:0.5rem">Pending</span>
            <div class="text-muted" style="font-size:0.78rem;margin-top:0.2rem"><?= h($pr['nationality']) ?> &bull; Submitted <?= h(date('d M Y', strtotime($pr['created_at']))) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Request New Driver -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">&#43; Request New Driver</div>
    <p class="text-muted" style="font-size:0.85rem;margin-bottom:1rem">Submit a new driver for admin approval. They will appear in the system once approved.</p>
    <form method="post" style="max-width:560px">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="req_first_name">First Name</label>
                <input type="text" id="req_first_name" name="first_name" class="form-control" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="req_last_name">Last Name</label>
                <input type="text" id="req_last_name" name="last_name" class="form-control" required maxlength="50">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="req_nationality">Nationality</label>
                <input type="text" id="req_nationality" name="nationality" class="form-control" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="req_dob">Date of Birth</label>
                <input type="date" id="req_dob" name="date_of_birth" class="form-control" required>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label required" for="req_number">Racing Number (1–99)</label>
            <input type="number" id="req_number" name="racing_number" class="form-control" min="1" max="99" required style="max-width:120px">
        </div>
        <button type="submit" name="request_driver" class="btn btn-primary">Submit Request</button>
    </form>
</div>

<!-- All-time drivers -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">&#128203; All-Time Drivers</div>
    <div class="table-container" style="border:0;margin:0">
        <div class="table-toolbar">
            <div class="table-search">
                <input type="text" class="table-search-input" data-table="drivers-table" placeholder="Search drivers...">
            </div>
        </div>
        <table class="sortable" id="drivers-table">
            <thead><tr>
                <th>#</th><th>Name</th><th>Nationality</th><th>Seasons</th><th>Career Pts</th><th>Career Wins</th>
            </tr></thead>
            <tbody>
            <?php if (empty($allDrivers)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:2rem">No drivers found.</td></tr>
            <?php else: ?>
            <?php foreach ($allDrivers as $d): ?>
            <tr class="clickable-row" data-href="<?= APP_URL ?>/team_manager/drivers.php?person_id=<?= (int)$d['id'] ?>">
                <td class="text-accent fw-bold"><?= h((string)$d['racing_number']) ?></td>
                <td><a href="<?= APP_URL ?>/team_manager/drivers.php?person_id=<?= (int)$d['id'] ?>" style="font-weight:600"><?= h($d['first_name'] . ' ' . $d['last_name']) ?></a></td>
                <td><?= h($d['nationality']) ?></td>
                <td><?= h((string)$d['seasons_count']) ?></td>
                <td class="text-accent fw-bold"><?= h(number_format($d['career_points'], 1)) ?></td>
                <td><?= h((string)$d['career_wins']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

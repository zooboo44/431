<?php
$pageTitle = 'My Drivers';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager');

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);
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
        // Request a new engineer (pending approval)
        if (isset($_POST['request_engineer'])) {
            $engFirst = strip_tags(trim($_POST['eng_first_name'] ?? ''));
            $engLast  = strip_tags(trim($_POST['eng_last_name'] ?? ''));
            if (!$engFirst) $errors[] = 'Engineer first name is required.';
            if (!$engLast)  $errors[] = 'Engineer last name is required.';
            if (empty($errors)) {
                try {
                    $db->prepare("INSERT INTO engineer_requests (first_name, last_name, requested_by_team_id) VALUES (?,?,?)")
                       ->execute([$engFirst, $engLast, $teamId]);
                    logAudit($_SESSION['user_id'], 'create', 'engineer_requests', null, "Pending engineer: $engFirst $engLast by team $teamId");
                    rotateCSRFToken();
                    redirectWithMessage(APP_URL . '/team_manager/drivers.php', 'success', "Engineer request for '$engFirst $engLast' submitted. Awaiting admin approval.");
                } catch (\PDOException $e) {
                    $errors[] = 'Could not submit request. The engineer_requests table may not exist yet — contact admin.';
                }
            }
        }

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
               dstand.points, dstand.wins, dstand.position AS championship_pos
        FROM driver_seasons ds
        JOIN people p ON p.id = ds.person_id
        LEFT JOIN driver_standings dstand ON dstand.person_id = p.id AND dstand.season_id = ?
        WHERE ds.team_season_id = ? AND ds.season_id = ?
        ORDER BY FIELD(ds.status,'active','replaced','injured'), p.racing_number
    ");
    $stmt->execute([$activeSeasonId, $currentTeamSeasonId, $activeSeasonId]);
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

// Pending engineer requests for this team
$pendingEngineerRequests = [];
try {
    $erStmt = $db->prepare("SELECT id, first_name, last_name, created_at FROM engineer_requests WHERE requested_by_team_id = ? ORDER BY created_at DESC");
    $erStmt->execute([$teamId]);
    $pendingEngineerRequests = $erStmt->fetchAll();
} catch (\PDOException $e) { /* table may not exist yet */ }

$activeCount = count(array_filter($currentRoster, fn($d) => $d['status'] === 'active'));
$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Drivers</h1>
        <p class="page-subtitle"><?= $activeSeason ? h((string)$activeSeason['year']) . ' season roster' : 'No active season' ?></p>
    </div>
    <a href="<?= APP_URL ?>/team_manager/export.php?type=drivers" class="btn btn-outline">Export CSV</a>
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
                <a href="<?= APP_URL ?>/team_manager/driver_profile.php?person_id=<?= (int)$d['person_id'] ?>" style="font-weight:600">#<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?></a>
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

<!-- Pending Engineer Requests -->
<?php if (!empty($pendingEngineerRequests)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">&#9203; Pending Engineer Requests</div>
    <p class="text-muted" style="font-size:0.85rem;margin-bottom:0.75rem">These requests are awaiting admin approval.</p>
    <?php foreach ($pendingEngineerRequests as $er): ?>
    <div style="border:1px solid var(--warning);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem">
        <strong><?= h($er['first_name'] . ' ' . $er['last_name']) ?></strong>
        <span class="status-badge status-warning" style="margin-left:0.5rem">Pending</span>
        <span class="text-muted" style="font-size:0.78rem;margin-left:0.5rem">Submitted <?= h(date('d M Y', strtotime($er['created_at']))) ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Request New Engineer -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">&#43; Request New Engineer</div>
    <p class="text-muted" style="font-size:0.85rem;margin-bottom:1rem">Submit a request to grant engineer portal access to a team member. Admin will set up their login credentials.</p>
    <form method="post" style="max-width:480px">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="eng_first_name">First Name</label>
                <input type="text" id="eng_first_name" name="eng_first_name" class="form-control" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="eng_last_name">Last Name</label>
                <input type="text" id="eng_last_name" name="eng_last_name" class="form-control" required maxlength="50">
            </div>
        </div>
        <button type="submit" name="request_engineer" class="btn btn-primary">Submit Request</button>
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
            <tr class="clickable-row" data-href="<?= APP_URL ?>/team_manager/driver_profile.php?person_id=<?= (int)$d['id'] ?>">
                <td class="text-accent fw-bold"><?= h((string)$d['racing_number']) ?></td>
                <td><a href="<?= APP_URL ?>/team_manager/driver_profile.php?person_id=<?= (int)$d['id'] ?>" style="font-weight:600"><?= h($d['first_name'] . ' ' . $d['last_name']) ?></a></td>
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

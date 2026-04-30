<?php
$pageTitle = 'Seasons';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();
$errors = [];

// --- Detail view (registrations check comes first since it also uses ?id=) ---
if (isset($_GET['id']) && ($_GET['action'] ?? '') === 'registrations') {
    // handled in the registrations block below
} elseif (isset($_GET['id'])) {
    $seasonId = intval($_GET['id']);
    $stmt = $db->prepare('SELECT * FROM seasons WHERE id = ?');
    $stmt->execute([$seasonId]);
    $season = $stmt->fetch();
    if (!$season) { include __DIR__ . '/../includes/404.php'; exit; }

    $pageTitle = $season['year'] . ' Season';

    $stmt = $db->prepare("
        SELECT ts.*, t.name AS team_name, t.id AS team_id, t.nationality,
               COALESCE((
                   SELECT SUM(rr2.points_scored)
                   FROM race_results rr2 JOIN race_entries re2 ON re2.id=rr2.race_entry_id JOIN races r2 ON r2.id=re2.race_id
                   WHERE r2.season_id=ts.season_id AND r2.status='completed' AND re2.team_season_id=ts.id
               ),0) AS points,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2 JOIN race_entries re2 ON re2.id=rr2.race_entry_id JOIN races r2 ON r2.id=re2.race_id
                   WHERE r2.season_id=ts.season_id AND r2.status='completed' AND re2.team_season_id=ts.id
               ),0) AS wins
        FROM team_seasons ts JOIN teams t ON t.id=ts.team_id
        WHERE ts.season_id=? ORDER BY points DESC
    ");
    $stmt->execute([$seasonId]);
    $teams = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT ds.*, ds.person_id AS id, p.first_name, p.last_name, p.racing_number, t.name AS team_name,
               COALESCE((
                   SELECT SUM(rr2.points_scored)
                   FROM race_results rr2 JOIN race_entries re2 ON re2.id=rr2.race_entry_id JOIN races r2 ON r2.id=re2.race_id
                   WHERE r2.season_id=ds.season_id AND r2.status='completed' AND re2.person_id=ds.person_id
               ),0) AS points,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2 JOIN race_entries re2 ON re2.id=rr2.race_entry_id JOIN races r2 ON r2.id=re2.race_id
                   WHERE r2.season_id=ds.season_id AND r2.status='completed' AND re2.person_id=ds.person_id
               ),0) AS wins
        FROM driver_seasons ds
        JOIN people p ON p.id=ds.person_id
        JOIN team_seasons ts ON ts.id=ds.team_season_id
        JOIN teams t ON t.id=ts.team_id
        WHERE ds.season_id=? ORDER BY points DESC, t.name
    ");
    $stmt->execute([$seasonId]);
    $drivers = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT r.*, c.name AS circuit_name, c.country
        FROM races r JOIN circuits c ON c.id=r.circuit_id
        WHERE r.season_id=? ORDER BY r.round_number ASC
    ");
    $stmt->execute([$seasonId]);
    $races = $stmt->fetchAll();

    renderFlash();
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= h((string)$season['year']) ?> Season</h1>
        <p class="page-subtitle"><?= count($races) ?> races &bull; <?= count($teams) ?> teams &bull; <?= count($drivers) ?> drivers</p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <?php if ($season['is_active']): ?>
        <span class="status-badge status-active" style="padding:0.4rem 0.75rem">&#9733; Active Season</span>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/seasons.php?id=<?= $seasonId ?>&action=registrations" class="btn btn-outline">Registrations</a>
        <a href="<?= APP_URL ?>/admin/seasons.php" class="btn btn-outline">&larr; Seasons</a>
    </div>
</div>

<div class="grid-2" style="margin-bottom:1.5rem">
<div class="card">
    <div class="card-title">Driver Championship</div>
    <?php if (empty($drivers)): ?>
    <div class="empty-state"><p>No standings yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Pts</th><th>Wins</th></tr></thead>
        <tbody>
        <?php foreach ($drivers as $pos => $d): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/people.php?id=<?= $d['id'] ?>">
            <td><span class="position-badge pos-<?= ($pos+1)<=3?($pos+1):'other' ?>"><?= $pos+1 ?></span></td>
            <td><strong>#<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?></strong></td>
            <td class="text-muted"><?= h($d['team_name']) ?></td>
            <td class="text-accent fw-bold"><?= h(number_format((float)$d['points'],1)) ?></td>
            <td><?= h((string)$d['wins']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title">Constructor Championship</div>
    <?php if (empty($teams)): ?>
    <div class="empty-state"><p>No standings yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Pos</th><th>Team</th><th>Pts</th><th>Wins</th></tr></thead>
        <tbody>
        <?php foreach ($teams as $pos => $t): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/teams.php?id=<?= $t['team_id'] ?>">
            <td><span class="position-badge pos-<?= ($pos+1)<=3?($pos+1):'other' ?>"><?= $pos+1 ?></span></td>
            <td><strong><?= h($t['team_name']) ?></strong></td>
            <td class="text-accent fw-bold"><?= h(number_format((float)$t['points'],1)) ?></td>
            <td><?= h((string)$t['wins']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>

<div class="card">
    <div class="card-title">Races</div>
    <?php if (empty($races)): ?>
    <div class="empty-state"><p>No races. <a href="<?= APP_URL ?>/admin/races.php?action=create&season_id=<?= $seasonId ?>">Add race →</a></p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Rd</th><th>Race</th><th>Circuit</th><th>Date</th><th>Sprint</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($races as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $r['id'] ?>">
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><strong><?= h($r['name']) ?></strong></td>
            <td class="text-muted"><?= h($r['circuit_name']) ?>, <?= h($r['country']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '—' ?></td>
            <td><span class="status-badge status-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Registered Drivers</div>
    <table>
        <thead><tr><th>#</th><th>Driver</th><th>Team</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($drivers as $d): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/people.php?id=<?= $d['id'] ?>">
            <td><strong class="text-accent"><?= h((string)$d['racing_number']) ?></strong></td>
            <td><?= h($d['first_name'] . ' ' . $d['last_name']) ?></td>
            <td class="text-muted"><?= h($d['team_name']) ?></td>
            <td><span class="status-badge status-<?= h($d['status']) ?>"><?= h($d['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// --- Registrations view ---
if (isset($_GET['action']) && $_GET['action'] === 'registrations') {
    $seasonId = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM seasons WHERE id = ?');
    $stmt->execute([$seasonId]);
    $season = $stmt->fetch();
    if (!$season) { include __DIR__ . '/../includes/404.php'; exit; }

    $registrationsUrl = APP_URL . '/admin/seasons.php?id=' . $seasonId . '&action=registrations';

    // Existing registrations
    $stmt = $db->prepare("
        SELECT ts.id, ts.team_id, ts.principal, ts.car_name, ts.power_unit, ts.base_location, t.name AS team_name
        FROM team_seasons ts JOIN teams t ON t.id = ts.team_id
        WHERE ts.season_id = ? ORDER BY t.name
    ");
    $stmt->execute([$seasonId]);
    $teamSeasons = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT ds.id, ds.person_id, ds.team_season_id, ds.status, ds.joined_round, ds.left_round,
               p.first_name, p.last_name, p.racing_number, t.name AS team_name
        FROM driver_seasons ds
        JOIN people p ON p.id = ds.person_id
        JOIN team_seasons ts ON ts.id = ds.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE ds.season_id = ? ORDER BY t.name, p.last_name
    ");
    $stmt->execute([$seasonId]);
    $driverSeasons = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT t.id, t.name,
               (SELECT ts2.principal FROM team_seasons ts2 WHERE ts2.team_id=t.id ORDER BY ts2.season_id DESC LIMIT 1) AS last_principal,
               (SELECT s2.year FROM team_seasons ts2 JOIN seasons s2 ON s2.id=ts2.season_id WHERE ts2.team_id=t.id ORDER BY ts2.season_id DESC LIMIT 1) AS last_year
        FROM teams t
        WHERE t.is_active=1 AND t.id NOT IN (SELECT team_id FROM team_seasons WHERE season_id=?)
        ORDER BY t.name
    ");
    $stmt->execute([$seasonId]);
    $availableTeams = $stmt->fetchAll();

    $allPeople = $db->query("SELECT id, CONCAT(first_name,' ',last_name,' (#',racing_number,')') AS label FROM people WHERE is_active=1 ORDER BY last_name")->fetchAll();

    // Handle add team_season
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_team'])) {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid token.';
        } else {
            $teamId    = intval($_POST['team_id'] ?? 0);
            $principal = strip_tags(trim($_POST['principal'] ?? ''));
            $carName   = strip_tags(trim($_POST['car_name'] ?? ''));
            $powerUnit = strip_tags(trim($_POST['power_unit'] ?? ''));
            $baseLoc   = strip_tags(trim($_POST['base_location'] ?? ''));
            if (!$teamId || !$principal || !$carName || !$powerUnit) {
                $errors[] = 'All team season fields are required.';
            } else {
                $cntStmt = $db->prepare('SELECT COUNT(*) FROM team_seasons WHERE season_id=?');
                $cntStmt->execute([$seasonId]);
                if ((int)$cntStmt->fetchColumn() >= 10) {
                    $errors[] = 'Maximum 10 teams already registered for this season.';
                }
                if (empty($errors)) {
                    $chk = $db->prepare('SELECT id FROM team_seasons WHERE team_id=? AND season_id=?');
                    $chk->execute([$teamId, $seasonId]);
                    if ($chk->fetch()) {
                        $errors[] = 'This team is already registered for this season.';
                    } else {
                        $db->prepare("INSERT INTO team_seasons (team_id,season_id,principal,car_name,power_unit,base_location) VALUES (?,?,?,?,?,?)")
                           ->execute([$teamId,$seasonId,$principal,$carName,$powerUnit,$baseLoc ?: null]);
                        logAudit($_SESSION['user_id'], 'create', 'team_seasons', null, "Team $teamId in season $seasonId");
                        rotateCSRFToken();
                        redirectWithMessage($registrationsUrl, 'success', 'Team registered successfully.');
                    }
                }
            }
        }
    }

    // Handle add driver_season
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_driver'])) {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid token.';
        } else {
            $personId     = intval($_POST['person_id'] ?? 0);
            $teamSeasonId = intval($_POST['team_season_id'] ?? 0);
            $joinedRound  = intval($_POST['joined_round'] ?? 1);
            if (!$personId || !$teamSeasonId) {
                $errors[] = 'Driver and team are required.';
            } else {
                $drCntStmt = $db->prepare('SELECT COUNT(*) FROM driver_seasons WHERE team_season_id=? AND status="active"');
                $drCntStmt->execute([$teamSeasonId]);
                if ((int)$drCntStmt->fetchColumn() >= 2) {
                    $errors[] = 'This team already has 2 active drivers for this season.';
                }
                if (empty($errors)) {
                    $chk = $db->prepare('SELECT id FROM driver_seasons WHERE person_id=? AND season_id=?');
                    $chk->execute([$personId, $seasonId]);
                    if ($chk->fetch()) {
                        $errors[] = 'This driver is already registered for this season.';
                    } else {
                        $db->prepare("INSERT INTO driver_seasons (person_id,team_season_id,season_id,joined_round) VALUES (?,?,?,?)")
                           ->execute([$personId,$teamSeasonId,$seasonId,$joinedRound]);
                        logAudit($_SESSION['user_id'], 'create', 'driver_seasons', null, "Person $personId in season $seasonId");
                        rotateCSRFToken();
                        redirectWithMessage($registrationsUrl, 'success', 'Driver registered successfully.');
                    }
                }
            }
        }
    }

    $csrfToken = generateCSRFToken();
    renderFlash();
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Season <?= h((string)$season['year']) ?> Registrations</h1>
        <p class="page-subtitle"><?= count($teamSeasons) ?>/10 teams, <?= count($driverSeasons) ?>/<?= count($teamSeasons) * 2 ?> max drivers</p>
    </div>
    <a href="<?= APP_URL ?>/admin/seasons.php?id=<?= $seasonId ?>" class="btn btn-outline">&larr; Season Detail</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-title">&#127937; Registered Teams (<?= count($teamSeasons) ?>/10)</div>
            <?php if (empty($teamSeasons)): ?>
            <div class="empty-state"><p>No teams registered yet.</p></div>
            <?php else: ?>
            <?php foreach ($teamSeasons as $ts): ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem">
                <strong><?= h($ts['team_name']) ?></strong>
                <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:0.25rem">
                    Principal: <?= h($ts['principal']) ?> &bull; Car: <?= h($ts['car_name']) ?> &bull; PU: <?= h($ts['power_unit']) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if ($availableTeams): ?>
        <div class="card">
            <div class="card-title">+ Register Team</div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <div class="form-group">
                    <label class="form-label required">Team</label>
                    <select name="team_id" id="reg_team_id" class="form-control" onchange="autoFillPrincipal(this.value)">
                        <option value="">— Select Team —</option>
                        <?php foreach ($availableTeams as $t): ?>
                        <option value="<?= h((string)$t['id']) ?>" data-principal="<?= h($t['last_principal'] ?? '') ?>"><?= h($t['name']) ?><?= $t['last_principal'] ? ' (' . h($t['last_year'] ?? '') . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Team Principal</label>
                    <input type="text" id="principal_input" name="principal" class="form-control" required maxlength="100" value="<?= h($_POST['principal'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label required">Car Name</label>
                    <input type="text" name="car_name" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label required">Power Unit</label>
                    <input type="text" name="power_unit" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Base Location</label>
                    <input type="text" name="base_location" class="form-control" maxlength="100">
                </div>
                <button type="submit" name="add_team" class="btn btn-primary">Register Team</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <div>
        <div class="card">
            <div class="card-title">&#128100; Registered Drivers (<?= count($driverSeasons) ?>/<?= count($teamSeasons) * 2 ?> max)</div>
            <?php if (empty($driverSeasons)): ?>
            <div class="empty-state"><p>No drivers registered yet.</p></div>
            <?php else: ?>
            <?php foreach ($driverSeasons as $ds): ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.6rem 0.75rem;margin-bottom:0.4rem;display:flex;justify-content:space-between;align-items:center">
                <div>
                    <strong><?= h($ds['first_name'] . ' ' . $ds['last_name']) ?></strong>
                    <span class="text-muted" style="font-size:0.8rem"> #<?= h((string)$ds['racing_number']) ?></span>
                    <div style="font-size:0.78rem;color:var(--text-muted)"><?= h($ds['team_name']) ?></div>
                </div>
                <span class="status-badge status-<?= h($ds['status']) ?>"><?= h($ds['status']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if ($teamSeasons): ?>
        <div class="card">
            <div class="card-title">+ Register Driver</div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <div class="form-group">
                    <label class="form-label required">Driver</label>
                    <select name="person_id" class="form-control">
                        <?php foreach ($allPeople as $p): ?>
                        <option value="<?= h((string)$p['id']) ?>"><?= h($p['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Team (this season)</label>
                    <select name="team_season_id" class="form-control">
                        <?php foreach ($teamSeasons as $ts): ?>
                        <option value="<?= h((string)$ts['id']) ?>"><?= h($ts['team_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Joined at Round</label>
                    <input type="number" name="joined_round" class="form-control" value="1" min="1" max="30" style="max-width:100px">
                </div>
                <button type="submit" name="add_driver" class="btn btn-primary">Register Driver</button>
            </form>
        </div>
        <?php else: ?>
        <div class="notice">Register at least one team before registering drivers.</div>
        <?php endif; ?>
    </div>
</div>
<script>
function autoFillPrincipal(teamId) {
    var sel = document.getElementById('reg_team_id');
    var opt = sel.options[sel.selectedIndex];
    var principal = opt ? (opt.dataset.principal || '') : '';
    var inp = document.getElementById('principal_input');
    if (principal && !inp.value) inp.value = principal;
}
</script>
    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// --- Create form ---
if (isset($_GET['action']) && $_GET['action'] === 'create') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid request token.';
        } else {
            $year = intval($_POST['year'] ?? 0);
            if ($year < 1950 || $year > 2100) $errors[] = 'Valid year is required (1950–2100).';
            if (empty($errors)) {
                $chk = $db->prepare('SELECT id FROM seasons WHERE year = ?');
                $chk->execute([$year]);
                if ($chk->fetch()) {
                    $errors[] = "Season $year already exists.";
                } else {
                    $db->prepare("INSERT INTO seasons (year, is_active) VALUES (?, 0)")->execute([$year]);
                    $newId = $db->lastInsertId();
                    logAudit($_SESSION['user_id'], 'create', 'seasons', (int)$newId, "Year: $year");
                    rotateCSRFToken();
                    redirectWithMessage(APP_URL . '/admin/seasons.php', 'success', "Season $year created.");
                }
            }
        }
    }
    $csrfToken = generateCSRFToken();
    ?>
<div class="page-header">
    <h1 class="page-title">Add Season</h1>
    <a href="<?= APP_URL ?>/admin/seasons.php" class="btn btn-outline">&larr; Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>
<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-group">
            <label class="form-label required" for="year">Season Year</label>
            <input type="number" id="year" name="year" class="form-control" value="<?= h($_POST['year'] ?? date('Y')) ?>" required min="1950" max="2100" style="max-width:160px">
        </div>
        <div class="notice">After creating a season, use <strong>Season Registrations</strong> to assign teams and drivers.</div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Season</button>
            <a href="<?= APP_URL ?>/admin/seasons.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// --- List view ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage(APP_URL . '/admin/seasons.php', 'danger', 'Invalid token.');
    }
    if (isset($_POST['set_active'])) {
        $sid = intval($_POST['season_id'] ?? 0);
        if ($sid) {
            $db->query('UPDATE seasons SET is_active=0');
            $db->prepare('UPDATE seasons SET is_active=1 WHERE id=?')->execute([$sid]);
            logAudit($_SESSION['user_id'], 'update', 'seasons', $sid, 'Set as active season');
        }
        rotateCSRFToken();
        redirectWithMessage(APP_URL . '/admin/seasons.php', 'success', 'Active season updated.');
    }
    if (isset($_POST['delete_season'])) {
        $sid = intval($_POST['season_id'] ?? 0);
        $stmt = $db->prepare('SELECT year, is_active FROM seasons WHERE id = ?');
        $stmt->execute([$sid]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage(APP_URL . '/admin/seasons.php', 'danger', 'Season not found.'); }
        if ($row['is_active']) { redirectWithMessage(APP_URL . '/admin/seasons.php', 'danger', 'Cannot delete the active season.'); }
        $cmpStmt = $db->prepare("SELECT COUNT(*) FROM races WHERE season_id = ? AND status = 'completed'");
        $cmpStmt->execute([$sid]);
        if ((int)$cmpStmt->fetchColumn() > 0) {
            redirectWithMessage(APP_URL . '/admin/seasons.php', 'danger', 'Cannot delete a season that has completed races.');
        }
        $db->prepare('DELETE FROM seasons WHERE id = ?')->execute([$sid]);
        logAudit($_SESSION['user_id'], 'delete', 'seasons', $sid, "Year: {$row['year']}");
        rotateCSRFToken();
        redirectWithMessage(APP_URL . '/admin/seasons.php', 'success', "Season {$row['year']} deleted.");
    }
}

$stmt = $db->query("
    SELECT s.*,
           CONCAT(pd.first_name,' ',pd.last_name) AS champ_driver,
           tc.name AS champ_team,
           COUNT(DISTINCT r.id) AS race_count
    FROM seasons s
    LEFT JOIN people pd ON pd.id = s.champion_person_id
    LEFT JOIN teams tc ON tc.id = s.champion_team_id
    LEFT JOIN races r ON r.season_id = s.id
    GROUP BY s.id
    ORDER BY s.year DESC
");
$seasons = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Seasons</h1>
        <p class="page-subtitle"><?= count($seasons) ?> season<?= count($seasons) != 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/seasons.php?action=create" class="btn btn-primary">+ Add Season</a>
</div>

<div class="table-container">
    <table class="sortable">
        <thead><tr>
            <th>Year</th><th>Races</th><th>Champion Driver</th><th>Champion Team</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($seasons)): ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:2rem">No seasons found.</td></tr>
        <?php else: ?>
        <?php foreach ($seasons as $s): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/seasons.php?id=<?= $s['id'] ?>">
            <td><strong><?= h((string)$s['year']) ?></strong></td>
            <td><?= h((string)$s['race_count']) ?></td>
            <td><?= $s['champ_driver'] ? h($s['champ_driver']) : '<span class="text-muted">TBD</span>' ?></td>
            <td><?= $s['champ_team'] ? h($s['champ_team']) : '<span class="text-muted">TBD</span>' ?></td>
            <td>
                <?php if ($s['is_active']): ?>
                <span class="status-badge status-active">&#9733; Active</span>
                <?php else: ?>
                <span class="status-badge status-inactive">Inactive</span>
                <?php endif; ?>
            </td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/seasons.php?id=<?= $s['id'] ?>&action=registrations" class="btn btn-outline btn-sm">Registrations</a>
                <?php if (!$s['is_active']): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="season_id" value="<?= $s['id'] ?>">
                    <button type="submit" name="set_active" class="btn btn-secondary btn-sm">Set Active</button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="season_id" value="<?= $s['id'] ?>">
                    <button type="submit" name="delete_season" class="btn btn-danger btn-sm"
                        data-confirm="Delete season <?= h((string)$s['year']) ?>? This will remove all its races and data.">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

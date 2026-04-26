<?php
$pageTitle = 'Season Registrations';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db       = getDB();
$errors   = [];
$seasonId = intval($_GET['season_id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM seasons WHERE id = ?');
$stmt->execute([$seasonId]);
$season = $stmt->fetch();
if (!$season) { include __DIR__ . '/../includes/404.php'; exit; }

// Existing registrations
$stmt = $db->prepare("
    SELECT ts.id, ts.team_id, ts.principal, ts.car_name, ts.power_unit, ts.base_location,
           t.name AS team_name
    FROM team_seasons ts
    JOIN teams t ON t.id = ts.team_id
    WHERE ts.season_id = ?
    ORDER BY t.name
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
    WHERE ds.season_id = ?
    ORDER BY t.name, p.last_name
");
$stmt->execute([$seasonId]);
$driverSeasons = $stmt->fetchAll();

// Available teams not yet registered, with most-recent principal
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

// All active people
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
                $errors[] = 'Maximum 10 teams are already active for the ' . $season['year'] . ' season.';
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
                redirectWithMessage(APP_URL . '/admin/season_registrations.php?season_id=' . $seasonId, 'success', 'Team registered successfully.');
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
        $personId    = intval($_POST['person_id'] ?? 0);
        $teamSeasonId= intval($_POST['team_season_id'] ?? 0);
        $joinedRound = intval($_POST['joined_round'] ?? 1);

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
                redirectWithMessage(APP_URL . '/admin/season_registrations.php?season_id=' . $seasonId, 'success', 'Driver registered successfully.');
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
    <a href="<?= APP_URL ?>/admin/seasons.php" class="btn btn-outline">&larr; Back to Seasons</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="grid-2">
    <!-- TEAM SEASONS -->
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
                        <option value="<?= h((string)$t['id']) ?>"
                            data-principal="<?= h($t['last_principal'] ?? '') ?>"><?= h($t['name']) ?><?= $t['last_principal'] ? ' (' . h($t['last_year'] ?? '') . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Team Principal</label>
                    <input type="text" id="principal_input" name="principal" class="form-control" required maxlength="100"
                           value="<?= h($_POST['principal'] ?? '') ?>">
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

    <!-- DRIVER SEASONS -->
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

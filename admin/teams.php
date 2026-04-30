<?php
$_isEdit   = isset($_GET['id']) && ($_GET['action'] ?? '') === 'edit';
$_isCreate = ($_GET['action'] ?? '') === 'create';
$_isForm   = $_isEdit || $_isCreate;
$_isDetail = isset($_GET['id']) && !isset($_GET['action']);

if ($_isForm)        $pageTitle = $_isEdit ? 'Edit Team' : 'Add Team';
elseif ($_isDetail)  $pageTitle = 'Team Detail';
else                 $pageTitle = 'Teams';

require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$errors = [];

// ─── FORM VIEW (CREATE / EDIT) ────────────────────────────────────────────────
if ($_isForm) {
    $isEdit = $_isEdit;
    $teamId = $isEdit ? intval($_GET['id']) : 0;
    $team   = null;

    if ($isEdit) {
        $stmt = $db->prepare('SELECT * FROM teams WHERE id = ?');
        $stmt->execute([$teamId]);
        $team = $stmt->fetch();
        if (!$team) { include __DIR__ . '/../includes/404.php'; exit; }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid request token.';
        } else {
            $name        = strip_tags(trim($_POST['name'] ?? ''));
            $shortName   = strtoupper(strip_tags(trim($_POST['short_name'] ?? '')));
            $nationality = strip_tags(trim($_POST['nationality'] ?? ''));
            $founded     = intval($_POST['founded_year'] ?? 0) ?: null;
            $isActive    = $isEdit ? (isset($_POST['is_active']) ? 1 : 0) : 1;

            if (!$name)        $errors[] = 'Team name is required.';
            if (!$shortName)   $errors[] = 'Short name is required.';
            elseif (!preg_match('/^[A-Z]{3,4}$/', $shortName)) $errors[] = 'Short name must be 3 or 4 uppercase letters only.';
            if (!$nationality) $errors[] = 'Nationality is required.';

            if (empty($errors)) {
                $chk = $db->prepare('SELECT id FROM teams WHERE name = ?' . ($isEdit ? ' AND id != ?' : ''));
                $chk->execute($isEdit ? [$name, $teamId] : [$name]);
                if ($chk->fetch()) $errors[] = ($isEdit ? 'Another' : 'A') . " team named '{$name}' already exists.";
            }
            if (empty($errors)) {
                $chkSn = $db->prepare('SELECT id FROM teams WHERE short_name = ?' . ($isEdit ? ' AND id != ?' : ''));
                $chkSn->execute($isEdit ? [$shortName, $teamId] : [$shortName]);
                if ($chkSn->fetch()) $errors[] = "Short name '{$shortName}' is already taken by another team.";
            }

            if (empty($errors)) {
                if ($isEdit) {
                    $db->prepare("UPDATE teams SET name=?,short_name=?,nationality=?,founded_year=?,is_active=? WHERE id=?")
                       ->execute([$name, $shortName, $nationality, $founded, $isActive, $teamId]);
                    logAudit($_SESSION['user_id'], 'update', 'teams', $teamId, "Updated team: $name");
                    rotateCSRFToken();
                    redirectWithMessage(APP_URL . '/admin/teams.php', 'success', "Team '{$name}' updated.");
                } else {
                    $db->prepare("INSERT INTO teams (name, short_name, nationality, founded_year) VALUES (?,?,?,?)")
                       ->execute([$name, $shortName, $nationality, $founded]);
                    $teamId = (int)$db->lastInsertId();
                    logAudit($_SESSION['user_id'], 'create', 'teams', $teamId, "Created team: $name");
                    rotateCSRFToken();
                    redirectWithMessage(APP_URL . '/admin/teams.php?id=' . $teamId . '&prompt_manager=1', 'success', "Team '{$name}' created. You can now add a team manager account below.");
                }
            }
        }
    }

    $csrfToken = generateCSRFToken();
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? 'Edit Team' : 'Add Team' ?></h1>
        <?php if ($isEdit && $team): ?><p class="page-subtitle"><?= h($team['name']) ?></p><?php endif; ?>
    </div>
    <div style="display:flex;gap:0.5rem">
        <?php if ($isEdit): ?>
        <a href="<?= APP_URL ?>/admin/teams.php?id=<?= $teamId ?>" class="btn btn-outline">View Detail</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/teams.php" class="btn btn-outline">&larr; Back</a>
    </div>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="name">Team Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= h($_POST['name'] ?? ($team['name'] ?? '')) ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label required" for="short_name">Short Name (3-4 letters)</label>
                <input type="text" id="short_name" name="short_name" class="form-control" value="<?= h($_POST['short_name'] ?? ($team['short_name'] ?? '')) ?>" required maxlength="4" minlength="3" <?= !$isEdit ? 'placeholder="e.g. RBR" ' : '' ?>oninput="this.value=this.value.toUpperCase().replace(/[^A-Z]/g,'')">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control" value="<?= h($_POST['nationality'] ?? ($team['nationality'] ?? '')) ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label" for="founded_year">Founded Year</label>
                <input type="number" id="founded_year" name="founded_year" class="form-control" value="<?= h($_POST['founded_year'] ?? ($team['founded_year'] ?? '')) ?>" min="1950" max="<?= date('Y') ?>">
            </div>
        </div>
        <?php if ($isEdit): ?>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
                <input type="checkbox" name="is_active" value="1"<?= ($team['is_active'] ?? 1) ? ' checked' : '' ?>>
                <span>Active team</span>
            </label>
        </div>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Team' ?></button>
            <a href="<?= APP_URL ?>/admin/teams.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── DETAIL VIEW ──────────────────────────────────────────────────────────────
if ($_isDetail) {
    $teamId = intval($_GET['id']);

    $stmt = $db->prepare('SELECT * FROM teams WHERE id = ?');
    $stmt->execute([$teamId]);
    $team = $stmt->fetch();
    if (!$team) { include __DIR__ . '/../includes/404.php'; exit; }

    $stmt = $db->prepare("
        SELECT ts.*, s.year, s.is_active AS season_active,
               COALESCE((
                   SELECT SUM(rr2.points_scored)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = ts.season_id AND r2.status = 'completed' AND re2.team_season_id = ts.id
               ),0) AS constructor_pts,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = ts.season_id AND r2.status = 'completed' AND re2.team_season_id = ts.id
               ),0) AS constructor_wins,
               NULL AS constructor_pos
        FROM team_seasons ts
        JOIN seasons s ON s.id = ts.season_id
        WHERE ts.team_id = ?
        ORDER BY s.year DESC
    ");
    $stmt->execute([$teamId]);
    $teamSeasons = $stmt->fetchAll();

    $activeSeason = getActiveSeason();
    $activeDrivers = [];
    $activeTeamSeason = null;
    if ($activeSeason) {
        $stmt = $db->prepare("
            SELECT ds.*, p.first_name, p.last_name, p.racing_number,
                   COALESCE((
                       SELECT SUM(rr2.points_scored)
                       FROM race_results rr2
                       JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                       JOIN races r2 ON r2.id = re2.race_id
                       WHERE r2.season_id = ? AND r2.status = 'completed' AND re2.person_id = p.id
                   ),0) AS points
            FROM driver_seasons ds
            JOIN people p ON p.id = ds.person_id
            JOIN team_seasons ts ON ts.id = ds.team_season_id AND ts.team_id = ?
            WHERE ds.season_id = ?
            ORDER BY p.racing_number
        ");
        $stmt->execute([$activeSeason['id'], $teamId, $activeSeason['id']]);
        $activeDrivers = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT * FROM team_seasons WHERE team_id=? AND season_id=?");
        $stmt->execute([$teamId, $activeSeason['id']]);
        $activeTeamSeason = $stmt->fetch();
    }

    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE linked_id=? AND role='team_manager' LIMIT 1");
    $stmt->execute([$teamId]);
    $manager = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT rr.id) AS total_races,
            COALESCE(SUM(rr.points_scored),0) AS total_points,
            SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN rr.finish_position<=3 AND rr.finish_position IS NOT NULL AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS podiums
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id
        JOIN team_seasons ts ON ts.id = re.team_season_id AND ts.team_id = ?
        WHERE rr.is_sprint = 0
    ");
    $stmt->execute([$teamId]);
    $career = $stmt->fetch();

    renderFlash();
    ?>

<?php if (!empty($_GET['prompt_manager'])): ?>
<div class="alert" style="background:rgba(var(--warning-rgb,230,160,20),0.15);border:1px solid var(--warning);color:var(--text-primary);margin-bottom:1rem">
    <strong>&#9888; No team manager yet.</strong>
    Would you like to create a team manager account for this team?
    <a href="<?= APP_URL ?>/admin/users.php?action=create&role=team_manager" class="btn btn-primary btn-sm" style="margin-left:1rem">Create Team Manager</a>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= h($team['name']) ?></h1>
        <p class="page-subtitle"><?= h($team['nationality']) ?><?= $team['founded_year'] ? ' &bull; Founded ' . h((string)$team['founded_year']) : '' ?></p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <a href="<?= APP_URL ?>/admin/teams.php?action=edit&id=<?= $teamId ?>" class="btn btn-outline">Edit Team</a>
        <a href="<?= APP_URL ?>/admin/teams.php" class="btn btn-outline">&larr; Teams</a>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
    <div class="stat-card"><div class="stat-value text-accent"><?= h(number_format((float)$career['total_points'],1)) ?></div><div class="stat-label">Career Points</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$career['wins']) ?></div><div class="stat-label">Wins</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$career['podiums']) ?></div><div class="stat-label">Podiums</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$career['total_races']) ?></div><div class="stat-label">Race Entries</div></div>
</div>

<div class="grid-2">
<div class="card">
    <div class="card-title"><?= $activeSeason ? h((string)$activeSeason['year']) . ' Season' : 'Current Season' ?><?php if (!$activeTeamSeason): ?><span class="text-muted" style="font-size:0.8rem"> (not registered)</span><?php endif; ?></div>
    <?php if ($activeTeamSeason): ?>
    <div class="info-grid">
        <div class="info-item"><div class="info-label">Car</div><div class="info-value"><?= h($activeTeamSeason['car_name']) ?></div></div>
        <div class="info-item"><div class="info-label">Power Unit</div><div class="info-value"><?= h($activeTeamSeason['power_unit']) ?></div></div>
        <div class="info-item"><div class="info-label">Principal</div><div class="info-value"><?= h($activeTeamSeason['principal']) ?></div></div>
        <?php if ($activeTeamSeason['base_location']): ?><div class="info-item"><div class="info-label">Base</div><div class="info-value"><?= h($activeTeamSeason['base_location']) ?></div></div><?php endif; ?>
    </div>
    <?php else: ?><div class="empty-state"><p>Team not registered for active season.</p></div><?php endif; ?>
    <?php if ($manager): ?>
    <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
        <div class="info-label">Team Manager</div>
        <div><?= h($manager['name']) ?> — <?= h($manager['email']) ?></div>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title">Drivers — <?= $activeSeason ? h((string)$activeSeason['year']) : 'Active Season' ?></div>
    <?php if (empty($activeDrivers)): ?>
    <div class="empty-state"><p>No drivers registered.</p></div>
    <?php else: ?>
    <?php foreach ($activeDrivers as $d): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--border)">
        <div>
            <a href="<?= APP_URL ?>/admin/people.php?id=<?= $d['id'] ?>" class="fw-bold">#<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?></a>
            <div class="text-muted" style="font-size:0.8rem"><span class="status-badge status-<?= h($d['status']) ?>"><?= h($d['status']) ?></span></div>
        </div>
        <div class="text-accent fw-bold"><?= h(number_format((float)($d['points']??0),1)) ?> pts</div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>

<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Season History</div>
    <?php if (empty($teamSeasons)): ?>
    <div class="empty-state"><p>No season registrations.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Season</th><th>Car</th><th>Principal</th><th>Pos</th><th>Points</th><th>Wins</th></tr></thead>
        <tbody>
        <?php foreach ($teamSeasons as $ts): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/seasons.php?id=<?= (int)$ts['season_id'] ?>">
            <td><strong><?= h((string)$ts['year']) ?></strong><?= $ts['season_active'] ? ' <span class="status-badge status-active" style="font-size:0.7rem">Active</span>' : '' ?></td>
            <td><?= h($ts['car_name']) ?></td>
            <td class="text-muted"><?= h($ts['principal']) ?></td>
            <td><?= $ts['constructor_pos'] ? 'P' . h((string)$ts['constructor_pos']) : '—' ?></td>
            <td class="text-accent"><?= h(number_format((float)($ts['constructor_pts']??0),1)) ?></td>
            <td><?= h((string)($ts['constructor_wins']??0)) ?></td>
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
    SELECT t.*,
           COUNT(DISTINCT ts.season_id) AS season_count,
           COUNT(DISTINCT re.id) AS entry_count
    FROM teams t
    LEFT JOIN team_seasons ts ON ts.team_id = t.id
    LEFT JOIN race_entries re ON re.team_season_id = ts.id
    GROUP BY t.id
    ORDER BY t.name
");
$teams = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Teams</h1>
        <p class="page-subtitle"><?= count($teams) ?> constructor<?= count($teams) != 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/teams.php?action=create" class="btn btn-primary">+ Add Team</a>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="teams-table" placeholder="Search teams...">
        </div>
    </div>
    <table class="sortable" id="teams-table">
        <thead><tr>
            <th>Name</th><th>Short</th><th>Nationality</th><th>Founded</th><th>Seasons</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($teams)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:2rem">No teams found.</td></tr>
        <?php else: ?>
        <?php foreach ($teams as $t): ?>
        <?php $delMsg = "Delete '{$t['name']}'? This will remove all associated team seasons, race entries and results ({$t['entry_count']} entries). This cannot be undone."; ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/teams.php?id=<?= $t['id'] ?>">
            <td><strong><?= h($t['name']) ?></strong></td>
            <td><code><?= h($t['short_name']) ?></code></td>
            <td><?= h($t['nationality']) ?></td>
            <td class="text-muted"><?= $t['founded_year'] ? h((string)$t['founded_year']) : '—' ?></td>
            <td><?= h((string)$t['season_count']) ?></td>
            <td><span class="status-badge <?= $t['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/teams.php?action=edit&id=<?= $t['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                <form method="post" action="<?= APP_URL ?>/admin/toggle.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="team">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="activate" value="<?= $t['is_active'] ? 0 : 1 ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/teams.php') ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $t['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" action="<?= APP_URL ?>/admin/delete.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="team">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/teams.php') ?>">
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

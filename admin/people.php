<?php
$_isEdit   = isset($_GET['id']) && ($_GET['action'] ?? '') === 'edit';
$_isCreate = ($_GET['action'] ?? '') === 'create';
$_isForm   = $_isEdit || $_isCreate;
$_isDetail = isset($_GET['id']) && !isset($_GET['action']);

if ($_isForm)        $pageTitle = $_isEdit ? 'Edit Driver' : 'Add Driver';
elseif ($_isDetail)  $pageTitle = 'Driver Detail';
else                 $pageTitle = 'Driver Records';

require_once __DIR__ . '/../includes/header.php';
requireRole('admin');
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$db     = getDB();
$errors = [];

// ─── FORM VIEW (CREATE / EDIT) ────────────────────────────────────────────────
if ($_isForm) {
    $isEdit   = $_isEdit;
    $personId = $isEdit ? intval($_GET['id']) : 0;
    $person   = null;

    if ($isEdit) {
        $stmt = $db->prepare('SELECT * FROM people WHERE id = ?');
        $stmt->execute([$personId]);
        $person = $stmt->fetch();
        if (!$person) { include __DIR__ . '/../includes/404.php'; exit; }
    }

    if (!$isEdit) {
        $seasons = getSeasonList();
        $activeSeason = getActiveSeason();
        $teamSeasonsBySeason = [];
        $tsStmt = $db->prepare("
            SELECT ts.id, ts.season_id, t.name AS team_name
            FROM team_seasons ts
            JOIN teams t ON t.id = ts.team_id
            ORDER BY ts.season_id DESC, t.name ASC
        ");
        $tsStmt->execute();
        foreach ($tsStmt->fetchAll() as $row) {
            $teamSeasonsBySeason[$row['season_id']][] = $row;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid request token.';
        } else {
            $firstName = strip_tags(trim($_POST['first_name'] ?? ''));
            $lastName  = strip_tags(trim($_POST['last_name'] ?? ''));
            $natl      = strip_tags(trim($_POST['nationality'] ?? ''));
            $dob       = $_POST['date_of_birth'] ?? '';
            $number    = intval($_POST['racing_number'] ?? 0);
            $bio       = strip_tags(trim($_POST['bio'] ?? ''));
            $isActive  = $isEdit ? (isset($_POST['is_active']) ? 1 : 0) : 1;

            if (!$firstName || !$lastName || !$natl) $errors[] = 'All required fields must be filled.';
            if ($number < 1 || $number > 99) $errors[] = 'Racing number must be 1–99.';

            $dobError = validateDOB($dob);
            if ($dobError) $errors[] = $dobError;

            if (empty($errors)) {
                $chkNum = $db->prepare('SELECT id FROM people WHERE racing_number = ?' . ($isEdit ? ' AND id != ?' : ''));
                $chkNum->execute($isEdit ? [$number, $personId] : [$number]);
                if ($chkNum->fetch()) $errors[] = "Racing number #$number is " . ($isEdit ? 'taken by another driver.' : 'already assigned to another driver.');

                $chkName = $db->prepare('SELECT id FROM people WHERE first_name = ? AND last_name = ?' . ($isEdit ? ' AND id != ?' : ''));
                $chkName->execute($isEdit ? [$firstName, $lastName, $personId] : [$firstName, $lastName]);
                if ($chkName->fetch()) $errors[] = ($isEdit ? 'Another d' : 'A d') . "river named '{$firstName} {$lastName}' already exists.";
            }

            if (!$isEdit) {
                $regSeasonId     = intval($_POST['reg_season_id'] ?? 0);
                $regTeamSeasonId = intval($_POST['reg_team_season_id'] ?? 0);
                if ($regTeamSeasonId && !$regSeasonId) $errors[] = 'Select a season for team registration.';
                if ($regSeasonId && !$regTeamSeasonId) $errors[] = 'Select a team for season registration.';
            }

            if (empty($errors)) {
                if ($isEdit) {
                    $db->prepare("UPDATE people SET first_name=?,last_name=?,nationality=?,date_of_birth=?,racing_number=?,bio=?,is_active=? WHERE id=?")
                       ->execute([$firstName, $lastName, $natl, $dob, $number, $bio ?: null, $isActive, $personId]);
                    logAudit($_SESSION['user_id'], 'update', 'people', $personId, "$firstName $lastName");
                    rotateCSRFToken();
                    redirectWithMessage(APP_URL . '/admin/people.php', 'success', "Driver '{$firstName} {$lastName}' updated.");
                } else {
                    $db->beginTransaction();
                    try {
                        $db->prepare("INSERT INTO people (first_name, last_name, nationality, date_of_birth, racing_number, bio) VALUES (?,?,?,?,?,?)")
                           ->execute([$firstName, $lastName, $natl, $dob, $number, $bio ?: null]);
                        $personId = (int)$db->lastInsertId();
                        logAudit($_SESSION['user_id'], 'create', 'people', $personId, "$firstName $lastName #$number");

                        if ($regSeasonId && $regTeamSeasonId) {
                            $db->prepare("INSERT INTO driver_seasons (person_id, team_season_id, season_id, joined_round, status) VALUES (?,?,?,1,'inactive')")
                               ->execute([$personId, $regTeamSeasonId, $regSeasonId]);
                            logAudit($_SESSION['user_id'], 'create', 'driver_seasons', null, "Person $personId → TeamSeason $regTeamSeasonId Season $regSeasonId");
                            $db->commit();
                            rotateCSRFToken();
                            redirectWithMessage(APP_URL . '/admin/people.php?id=' . $personId, 'success', "Driver '{$firstName} {$lastName}' created and registered (inactive).");
                        } else {
                            $db->commit();
                            rotateCSRFToken();
                            redirectWithMessage(APP_URL . '/admin/people.php?id=' . $personId, 'success', "Driver '{$firstName} {$lastName}' created. Register them for a season below.");
                        }
                    } catch (\PDOException $e) {
                        $db->rollBack();
                        $errors[] = 'Could not create driver. ' . $e->getMessage();
                    }
                }
            }
        }
    }

    $csrfToken = generateCSRFToken();
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? 'Edit Driver' : 'Add Driver' ?></h1>
        <?php if ($isEdit && $person): ?><p class="page-subtitle"><?= h($person['first_name'] . ' ' . $person['last_name']) ?> — #<?= h((string)$person['racing_number']) ?></p><?php endif; ?>
    </div>
    <div style="display:flex;gap:0.5rem">
        <?php if ($isEdit): ?>
        <a href="<?= APP_URL ?>/admin/people.php?id=<?= $personId ?>" class="btn btn-outline">View Detail</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/people.php" class="btn btn-outline">&larr; Back</a>
    </div>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" class="form-control" value="<?= h($_POST['first_name'] ?? ($person['first_name'] ?? '')) ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-control" value="<?= h($_POST['last_name'] ?? ($person['last_name'] ?? '')) ?>" required maxlength="50">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control" value="<?= h($_POST['nationality'] ?? ($person['nationality'] ?? '')) ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="date_of_birth">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?= h($_POST['date_of_birth'] ?? ($person['date_of_birth'] ?? '')) ?>" required>
                <div class="form-hint">Driver must be 18–60 years old</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="racing_number">Racing Number (1–99)</label>
                <input type="number" id="racing_number" name="racing_number" class="form-control" value="<?= h((string)($_POST['racing_number'] ?? ($person['racing_number'] ?? ''))) ?>" required min="1" max="99">
            </div>
            <?php if ($isEdit): ?>
            <div class="form-group">
                <label class="form-label">Status</label>
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-top:0.5rem">
                    <input type="checkbox" name="is_active" value="1"<?= ($person['is_active'] ?? 1) ? ' checked' : '' ?>>
                    <span>Active driver</span>
                </label>
            </div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label" for="bio">Bio<?= !$isEdit ? ' (optional)' : '' ?></label>
            <textarea id="bio" name="bio" class="form-control" rows="3" maxlength="5000"><?= h($_POST['bio'] ?? ($person['bio'] ?? '')) ?></textarea>
        </div>
        <?php if (!$isEdit && $seasons && $teamSeasonsBySeason): ?>
        <hr style="margin:1.5rem 0;border-color:var(--border)">
        <div class="card-title" style="margin-bottom:1rem">Season Registration (Optional)</div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="reg_season_id">Season</label>
                <select id="reg_season_id" name="reg_season_id" class="form-control" onchange="updateTeamSeasonList(this.value)">
                    <option value="">— None —</option>
                    <?php foreach ($seasons as $s): ?>
                    <option value="<?= h((string)$s['id']) ?>"<?= (isset($_POST['reg_season_id']) && $_POST['reg_season_id'] == $s['id']) || (!isset($_POST['reg_season_id']) && $activeSeason && $s['id'] == $activeSeason['id']) ? ' selected' : '' ?>>
                        <?= h((string)$s['year']) ?><?= $s['is_active'] ? ' ★' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="reg_team_season_id">Team</label>
                <select id="reg_team_season_id" name="reg_team_season_id" class="form-control">
                    <option value="">— Select Season First —</option>
                </select>
            </div>
        </div>
        <script>
        var teamSeasonsBySeason = <?= json_encode($teamSeasonsBySeason, JSON_HEX_TAG) ?>;
        function updateTeamSeasonList(seasonId) {
            var sel = document.getElementById('reg_team_season_id');
            sel.innerHTML = '<option value="">— None —</option>';
            var teams = seasonId && teamSeasonsBySeason[seasonId] ? teamSeasonsBySeason[seasonId] : [];
            teams.forEach(function(ts) {
                var opt = document.createElement('option');
                opt.value = ts.id;
                opt.textContent = ts.team_name;
                sel.appendChild(opt);
            });
        }
        (function() {
            var sel = document.getElementById('reg_season_id');
            if (sel.value) updateTeamSeasonList(sel.value);
        })();
        </script>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Driver' ?></button>
            <a href="<?= APP_URL ?>/admin/people.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── DETAIL VIEW ─────────────────────────────────────────────────────────────
if ($_isDetail) {
    $personId = intval($_GET['id']);
    $stmt = $db->prepare('SELECT * FROM people WHERE id = ?');
    $stmt->execute([$personId]);
    $person = $stmt->fetch();
    if (!$person) { include __DIR__ . '/../includes/404.php'; exit; }

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(rr.points_scored),0) AS total_points,
               SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS wins,
               SUM(CASE WHEN rr.finish_position<=3 AND rr.finish_position IS NOT NULL AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS podiums,
               SUM(CASE WHEN rr.status='DNF' AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS dnfs,
               SUM(CASE WHEN rr.fastest_lap_bonus=1 THEN 1 ELSE 0 END) AS fastest_laps,
               COUNT(DISTINCT CASE WHEN rr.is_sprint=0 THEN re.race_id END) AS total_races
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id AND re.person_id = ?
    ");
    $stmt->execute([$personId]);
    $career = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT ds.*, s.year, t.name AS team_name,
               COALESCE((
                   SELECT SUM(rr2.points_scored) FROM race_results rr2
                   JOIN race_entries re2 ON re2.id=rr2.race_entry_id JOIN races r2 ON r2.id=re2.race_id
                   WHERE r2.season_id=ds.season_id AND r2.status='completed' AND re2.person_id=ds.person_id
               ),0) AS points,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END) FROM race_results rr2
                   JOIN race_entries re2 ON re2.id=rr2.race_entry_id JOIN races r2 ON r2.id=re2.race_id
                   WHERE r2.season_id=ds.season_id AND r2.status='completed' AND re2.person_id=ds.person_id
               ),0) AS wins
        FROM driver_seasons ds
        JOIN seasons s ON s.id=ds.season_id
        JOIN team_seasons ts ON ts.id=ds.team_season_id
        JOIN teams t ON t.id=ts.team_id
        WHERE ds.person_id=? ORDER BY s.year DESC
    ");
    $stmt->execute([$personId]);
    $seasons = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT rr.finish_position, rr.points_scored, rr.status, rr.fastest_lap_bonus,
               r.name AS race_name, r.round_number, r.id AS race_id, s.year
        FROM race_results rr
        JOIN race_entries re ON re.id=rr.race_entry_id AND re.person_id=?
        JOIN races r ON r.id=re.race_id JOIN seasons s ON s.id=r.season_id
        WHERE rr.is_sprint=0 ORDER BY s.year DESC, r.round_number DESC LIMIT 20
    ");
    $stmt->execute([$personId]);
    $raceHistory = $stmt->fetchAll();

    $activeSeason = getActiveSeason();
    $licencePoints = 0;
    if ($activeSeason) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(licence_points_awarded),0) FROM penalties pen JOIN races r ON r.id=pen.race_id WHERE pen.person_id=? AND r.season_id=?");
        $stmt->execute([$personId, $activeSeason['id']]);
        $licencePoints = (int)$stmt->fetchColumn();
    }

    $stmt = $db->prepare("
        SELECT pen.*, r.name AS race_name, r.round_number, s.year
        FROM penalties pen JOIN races r ON r.id=pen.race_id JOIN seasons s ON s.id=r.season_id
        WHERE pen.person_id=? ORDER BY pen.issued_at DESC
    ");
    $stmt->execute([$personId]);
    $penalties = $stmt->fetchAll();

    renderFlash();
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title">#<?= h((string)$person['racing_number']) ?> <?= h($person['first_name'] . ' ' . $person['last_name']) ?></h1>
        <p class="page-subtitle"><?= h($person['nationality']) ?> &bull; Born <?= h(date('d M Y', strtotime($person['date_of_birth']))) ?></p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <a href="<?= APP_URL ?>/admin/people.php?action=edit&id=<?= $personId ?>" class="btn btn-outline">Edit</a>
        <a href="<?= APP_URL ?>/admin/people.php" class="btn btn-outline">&larr; Drivers</a>
    </div>
</div>
<?php if ($licencePoints >= 10): ?>
<div class="alert alert-danger">&#9888; <strong><?= $licencePoints ?> licence points</strong> in the current season — at or above the penalty threshold.</div>
<?php endif; ?>
<div class="stats-grid" style="grid-template-columns:repeat(6,1fr);margin-bottom:1.5rem">
    <div class="stat-card"><div class="stat-value text-accent"><?= h(number_format((float)$career['total_points'],1)) ?></div><div class="stat-label">Career Points</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$career['wins']) ?></div><div class="stat-label">Wins</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$career['podiums']) ?></div><div class="stat-label">Podiums</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$career['fastest_laps']) ?></div><div class="stat-label">Fastest Laps</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$career['dnfs']) ?></div><div class="stat-label">DNFs</div></div>
    <div class="stat-card">
        <?php $licColor = $licencePoints>=10?'var(--danger)':($licencePoints>=6?'var(--warning)':'var(--success)'); ?>
        <div class="stat-value" style="color:<?= $licColor ?>"><?= h((string)$licencePoints) ?></div>
        <div class="stat-label">Licence Pts (<?= $activeSeason ? h((string)$activeSeason['year']) : 'Season' ?>)</div>
    </div>
</div>
<div class="grid-2">
<div class="card">
    <div class="card-title">Profile</div>
    <div class="info-grid">
        <div class="info-item"><div class="info-label">Racing Number</div><div class="info-value">#<?= h((string)$person['racing_number']) ?></div></div>
        <div class="info-item"><div class="info-label">Nationality</div><div class="info-value"><?= h($person['nationality']) ?></div></div>
        <div class="info-item"><div class="info-label">Date of Birth</div><div class="info-value"><?= h(date('d M Y', strtotime($person['date_of_birth']))) ?></div></div>
        <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="status-badge <?= $person['is_active']?'status-active':'status-inactive' ?>"><?= $person['is_active']?'Active':'Inactive' ?></span></div></div>
    </div>
    <?php if ($person['bio']): ?><p style="margin-top:0.75rem;font-size:0.9rem;color:var(--text-secondary)"><?= h($person['bio']) ?></p><?php endif; ?>
</div>
<div class="card">
    <div class="card-title">Season Registrations</div>
    <?php if (empty($seasons)): ?>
    <div class="empty-state"><p>No season registrations.</p></div>
    <?php else: ?>
    <table><thead><tr><th>Season</th><th>Team</th><th>Status</th><th>Points</th><th>Wins</th></tr></thead><tbody>
    <?php foreach ($seasons as $s): ?>
    <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/seasons.php?id=<?= (int)$s['season_id'] ?>">
        <td><strong><?= h((string)$s['year']) ?></strong></td>
        <td><?= h($s['team_name']) ?></td>
        <td><span class="status-badge status-<?= h($s['status']) ?>"><?= h($s['status']) ?></span></td>
        <td class="text-accent"><?= h(number_format((float)$s['points'],1)) ?></td>
        <td><?= h((string)$s['wins']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
</div>
</div>
<?php if (!empty($raceHistory)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Race History (last 20)</div>
    <table><thead><tr><th>Season</th><th>Rd</th><th>Race</th><th>Finish</th><th>Status</th><th>Points</th><th>FL</th></tr></thead><tbody>
    <?php foreach ($raceHistory as $r): ?>
    <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $r['race_id'] ?>">
        <td><?= h((string)$r['year']) ?></td>
        <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
        <td><?= h($r['race_name']) ?></td>
        <td><?= $r['finish_position']?'<span class="position-badge pos-'.($r['finish_position']<=3?$r['finish_position']:'other').'">'.h((string)$r['finish_position']).'</span>':'—' ?></td>
        <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
        <td class="text-accent fw-bold"><?= h((string)$r['points_scored']) ?></td>
        <td><?= $r['fastest_lap_bonus']?'<span class="fl-indicator">&#9889;</span>':'' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($penalties)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Penalty History</div>
    <table><thead><tr><th>Season</th><th>Race</th><th>Type</th><th>Reason</th><th>Lic. Pts</th></tr></thead><tbody>
    <?php foreach ($penalties as $pen): ?>
    <tr>
        <td><?= h((string)$pen['year']) ?></td>
        <td>Rd <?= h((string)$pen['round_number']) ?></td>
        <td><span class="status-badge status-<?= $pen['is_dsq']?'dsq':'warning' ?>"><?= h(str_replace('_',' ',$pen['penalty_type'])) ?></span></td>
        <td style="font-size:0.85rem"><?= h($pen['reason']) ?></td>
        <td><?= $pen['licence_points_awarded']?h((string)$pen['licence_points_awarded']):'—' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
</div>
<?php endif; ?>
    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── PENDING DRIVER APPROVAL / REJECTION ─────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $personId = intval($_POST['person_id'] ?? 0);

        if (isset($_POST['approve_driver']) && $personId) {
            $tempPassword = trim($_POST['temp_password'] ?? '');
            $driverEmail  = trim($_POST['driver_email'] ?? '');

            if (!$driverEmail)    $errors[] = 'Driver email is required.';
            if (!filter_var($driverEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
            $passErr = validatePassword($tempPassword);
            if ($passErr) $errors[] = $passErr;

            if (empty($errors)) {
                $stmt = $db->prepare('SELECT * FROM people WHERE id = ? AND requested_by_team_id IS NOT NULL AND is_active = 0');
                $stmt->execute([$personId]);
                $person = $stmt->fetch();
                if (!$person) {
                    $errors[] = 'Pending driver not found.';
                } else {
                    $eChk = $db->prepare('SELECT id FROM users WHERE email = ?');
                    $eChk->execute([$driverEmail]);
                    if ($eChk->fetch()) $errors[] = 'That email address is already in use.';
                }
                if (empty($errors)) {
                    $db->beginTransaction();
                    try {
                        $requestingTeamId = (int)$person['requested_by_team_id'];
                        $db->prepare('UPDATE people SET is_active = 1, requested_by_team_id = NULL WHERE id = ?')->execute([$personId]);

                        $hash     = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                        $fullName = $person['first_name'] . ' ' . $person['last_name'];
                        $db->prepare("INSERT INTO users (name, email, password_hash, role, linked_id, is_active, must_change_password, created_by) VALUES (?,?,?,'driver',?,1,1,?)")
                           ->execute([$fullName, $driverEmail, $hash, $personId, $_SESSION['user_id']]);

                        $activeSeason = getActiveSeason();
                        if ($activeSeason && $requestingTeamId) {
                            $tsStmt = $db->prepare('SELECT id FROM team_seasons WHERE team_id = ? AND season_id = ?');
                            $tsStmt->execute([$requestingTeamId, $activeSeason['id']]);
                            $ts = $tsStmt->fetch();
                            if ($ts) {
                                $db->prepare("INSERT IGNORE INTO driver_seasons (person_id, team_season_id, season_id, status) VALUES (?,?,?,'active')")
                                   ->execute([$personId, $ts['id'], $activeSeason['id']]);
                            }
                        }
                        $db->commit();
                        logAudit($_SESSION['user_id'], 'approve', 'people', $personId, "Approved pending driver: $fullName");
                        rotateCSRFToken();
                        redirectWithMessage(APP_URL . '/admin/people.php', 'success', "Driver '$fullName' approved. Account created (must change password on first login).");
                    } catch (\PDOException $e) {
                        $db->rollBack();
                        $errors[] = 'Approval failed: ' . $e->getMessage();
                    }
                }
            }
        }

        if (isset($_POST['reject_driver']) && $personId) {
            $stmt = $db->prepare('SELECT first_name, last_name FROM people WHERE id = ? AND requested_by_team_id IS NOT NULL');
            $stmt->execute([$personId]);
            $person = $stmt->fetch();
            if ($person) {
                $db->prepare('DELETE FROM people WHERE id = ?')->execute([$personId]);
                logAudit($_SESSION['user_id'], 'delete', 'people', $personId, "Rejected pending driver: {$person['first_name']} {$person['last_name']}");
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/people.php', 'success', "Driver request for '{$person['first_name']} {$person['last_name']}' rejected and removed.");
            } else {
                $errors[] = 'Pending driver not found.';
            }
        }
    }
}

// ─── LIST VIEW ────────────────────────────────────────────────────────────────
$pendingDrivers = [];
if ($isAdmin) {
    $stmt = $db->prepare("
        SELECT p.*, t.name AS requesting_team
        FROM people p
        LEFT JOIN teams t ON t.id = p.requested_by_team_id
        WHERE p.requested_by_team_id IS NOT NULL AND p.is_active = 0
        ORDER BY p.created_at ASC
    ");
    $stmt->execute();
    $pendingDrivers = $stmt->fetchAll();
}

$stmt = $db->query("
    SELECT p.*,
           COUNT(DISTINCT ds.season_id) AS season_count,
           COUNT(DISTINCT rr.id) AS result_count
    FROM people p
    LEFT JOIN driver_seasons ds ON ds.person_id = p.id
    LEFT JOIN race_entries re ON re.person_id = p.id
    LEFT JOIN race_results rr ON rr.race_entry_id = re.id
    WHERE p.requested_by_team_id IS NULL
    GROUP BY p.id
    ORDER BY p.last_name, p.first_name
");
$people = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<?php if ($isAdmin && !empty($pendingDrivers)): ?>
<div class="card" style="margin-bottom:1.5rem;border:1px solid var(--warning)">
    <div class="card-title" style="color:var(--warning)">&#9203; Pending Driver Requests (<?= count($pendingDrivers) ?>)</div>
    <?php foreach ($pendingDrivers as $pr): ?>
    <div style="border:1px solid var(--border);border-radius:var(--radius);padding:1rem;margin-bottom:0.75rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem">
            <div>
                <strong>#<?= h((string)$pr['racing_number']) ?> <?= h($pr['first_name'] . ' ' . $pr['last_name']) ?></strong>
                <span class="status-badge status-warning" style="margin-left:0.5rem">Pending</span>
                <div class="text-muted" style="font-size:0.8rem;margin-top:0.2rem">
                    <?= h($pr['nationality']) ?> &bull; Born <?= h(date('d M Y', strtotime($pr['date_of_birth']))) ?>
                    &bull; Requested by <strong><?= h($pr['requesting_team'] ?? 'Unknown') ?></strong>
                    &bull; <?= h(date('d M Y', strtotime($pr['created_at']))) ?>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-top:0.75rem;align-items:flex-end">
            <form method="post" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="person_id" value="<?= (int)$pr['id'] ?>">
                <div class="form-group" style="margin:0">
                    <label class="form-label" style="font-size:0.75rem">Driver Email</label>
                    <input type="email" name="driver_email" class="form-control" style="padding:0.3rem 0.5rem;width:200px" required placeholder="driver@example.com">
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label" style="font-size:0.75rem">Password</label>
                    <input type="text" name="temp_password" class="form-control" id="dpw<?= (int)$pr['id'] ?>"
                           data-pw-validate="dpwfb<?= (int)$pr['id'] ?>"
                           style="padding:0.3rem 0.5rem;width:180px" required
                           placeholder="Min 8, A-Z, a-z, 0-9, !@#$%^&*">
                    <div id="dpwfb<?= (int)$pr['id'] ?>" class="pw-feedback"></div>
                </div>
                <button type="submit" name="approve_driver" class="btn btn-primary btn-sm">Approve</button>
            </form>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="person_id" value="<?= (int)$pr['id'] ?>">
                <button type="submit" name="reject_driver" class="btn btn-danger btn-sm"
                        data-confirm="Reject and delete '<?= h($pr['first_name'] . ' ' . $pr['last_name']) ?>'?">Reject</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Driver Records</h1>
        <p class="page-subtitle"><?= count($people) ?> driver<?= count($people) != 1 ? 's' : '' ?></p>
    </div>
    <?php if ($isAdmin): ?><a href="<?= APP_URL ?>/admin/people.php?action=create" class="btn btn-primary">+ Add Driver</a><?php endif; ?>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="people-table" placeholder="Search drivers...">
        </div>
    </div>
    <table class="sortable" id="people-table">
        <thead><tr>
            <th>#</th><th>Name</th><th>Nationality</th><th>DOB</th><th>Seasons</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($people)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:2rem">No records found.</td></tr>
        <?php else: ?>
        <?php foreach ($people as $p): ?>
        <?php $delMsg = "Delete '{$p['first_name']} {$p['last_name']}'? This will remove all their race entries, results and telemetry ({$p['result_count']} results). This cannot be undone."; ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/people.php?id=<?= $p['id'] ?>">
            <td><strong class="text-accent"><?= h((string)$p['racing_number']) ?></strong></td>
            <td><strong><?= h($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
            <td><?= h($p['nationality']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($p['date_of_birth']))) ?></td>
            <td><?= h((string)$p['season_count']) ?></td>
            <td><span class="status-badge <?= $p['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <?php if ($isAdmin): ?>
                <a href="<?= APP_URL ?>/admin/people.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                <form method="post" action="<?= APP_URL ?>/admin/toggle.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="person">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="activate" value="<?= $p['is_active'] ? 0 : 1 ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/people.php') ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $p['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" action="<?= APP_URL ?>/admin/delete.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="person">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/people.php') ?>">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="<?= h($delMsg) ?>">Delete</button>
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

<?php
$pageTitle = 'Add Driver';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();
$errors = [];

// Load seasons and their team_seasons for optional registration
$seasons = getSeasonList();
$activeSeason = getActiveSeason();
// Team seasons keyed by season_id
$teamSeasonsBySeason = [];
$tsStmt = $db->prepare("
    SELECT ts.id, ts.season_id, ts.principal, t.name AS team_name
    FROM team_seasons ts
    JOIN teams t ON t.id = ts.team_id
    ORDER BY ts.season_id DESC, t.name ASC
");
$tsStmt->execute();
foreach ($tsStmt->fetchAll() as $row) {
    $teamSeasonsBySeason[$row['season_id']][] = $row;
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

        if (!$firstName)  $errors[] = 'First name is required.';
        if (!$lastName)   $errors[] = 'Last name is required.';
        if (!$natl)       $errors[] = 'Nationality is required.';
        if ($number < 1 || $number > 99) $errors[] = 'Racing number must be 1–99.';

        $dobError = validateDOB($dob);
        if ($dobError) $errors[] = $dobError;

        if (empty($errors)) {
            // Duplicate racing number
            $chk = $db->prepare('SELECT id FROM people WHERE racing_number = ?');
            $chk->execute([$number]);
            if ($chk->fetch()) {
                $errors[] = "Racing number #$number is already assigned to another driver.";
            }
            // Duplicate name
            $chk = $db->prepare('SELECT id FROM people WHERE first_name = ? AND last_name = ?');
            $chk->execute([$firstName, $lastName]);
            if ($chk->fetch()) {
                $errors[] = "A driver named '{$firstName} {$lastName}' already exists.";
            }
        }

        // Optional season registration
        $regSeasonId    = intval($_POST['reg_season_id'] ?? 0);
        $regTeamSeasonId = intval($_POST['reg_team_season_id'] ?? 0);

        if ($regTeamSeasonId && !$regSeasonId) {
            $errors[] = 'Select a season for team registration.';
        }
        if ($regSeasonId && !$regTeamSeasonId) {
            $errors[] = 'Select a team for season registration.';
        }

        if (empty($errors)) {
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO people (first_name, last_name, nationality, date_of_birth, racing_number, bio) VALUES (?,?,?,?,?,?)")
                   ->execute([$firstName, $lastName, $natl, $dob, $number, $bio ?: null]);
                $newId = (int)$db->lastInsertId();
                logAudit($_SESSION['user_id'], 'create', 'people', $newId, "$firstName $lastName #$number");

                if ($regSeasonId && $regTeamSeasonId) {
                    // Check driver limit (max 2 active per team per season)
                    $drChk = $db->prepare("SELECT COUNT(*) FROM driver_seasons WHERE team_season_id=? AND status='active'");
                    $drChk->execute([$regTeamSeasonId]);
                    if ((int)$drChk->fetchColumn() >= 2) {
                        $errors[] = 'This team already has 2 active drivers for that season.';
                        $db->rollBack();
                    } else {
                        $db->prepare("INSERT INTO driver_seasons (person_id, team_season_id, season_id, joined_round, status) VALUES (?,?,?,1,'active')")
                           ->execute([$newId, $regTeamSeasonId, $regSeasonId]);
                        logAudit($_SESSION['user_id'], 'create', 'driver_seasons', null, "Person $newId → TeamSeason $regTeamSeasonId Season $regSeasonId");
                        $db->commit();
                        rotateCSRFToken();
                        redirectWithMessage(APP_URL . '/admin/person_detail.php?id=' . $newId, 'success', "Driver '{$firstName} {$lastName}' created and registered.");
                    }
                } else {
                    $db->commit();
                    rotateCSRFToken();
                    redirectWithMessage(APP_URL . '/admin/person_detail.php?id=' . $newId, 'success', "Driver '{$firstName} {$lastName}' created. Register them for a season below.");
                }
            } catch (\PDOException $e) {
                $db->rollBack();
                $errors[] = 'Could not create driver. ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h1 class="page-title">Add Driver</h1>
    <a href="<?= APP_URL ?>/admin/people.php" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" class="form-control" value="<?= h($_POST['first_name'] ?? '') ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-control" value="<?= h($_POST['last_name'] ?? '') ?>" required maxlength="50">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control" value="<?= h($_POST['nationality'] ?? '') ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="date_of_birth">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?= h($_POST['date_of_birth'] ?? '') ?>" required>
                <div class="form-hint">Driver must be 18–60 years old</div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label required" for="racing_number">Racing Number (1–99)</label>
            <input type="number" id="racing_number" name="racing_number" class="form-control" value="<?= h($_POST['racing_number'] ?? '') ?>" required min="1" max="99" style="max-width:120px">
        </div>
        <div class="form-group">
            <label class="form-label" for="bio">Bio (optional)</label>
            <textarea id="bio" name="bio" class="form-control" rows="3" maxlength="5000"><?= h($_POST['bio'] ?? '') ?></textarea>
        </div>
        <?php if ($seasons && $teamSeasonsBySeason): ?>
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
        // Initialize for pre-selected season
        (function() {
            var sel = document.getElementById('reg_season_id');
            if (sel.value) updateTeamSeasonList(sel.value);
        })();
        </script>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Driver</button>
            <a href="<?= APP_URL ?>/admin/people.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

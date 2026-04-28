<?php
$pageTitle = 'Race Entries';
require_once __DIR__ . '/../includes/header.php';
requireRole('race_director', 'admin');

$db     = getDB();
$errors = [];
$raceId = intval($_GET['race_id'] ?? 0);

if (!$raceId) {
    $activeSeason = getActiveSeason();
    $seasonId = $activeSeason['id'] ?? null;
    $raceList = [];
    if ($seasonId) {
        $rlStmt = $db->prepare("SELECT r.id, r.name, r.round_number, r.race_date, r.status, (SELECT COUNT(*) FROM race_entries re WHERE re.race_id=r.id) AS entry_count FROM races r WHERE r.season_id = ? ORDER BY r.round_number ASC");
        $rlStmt->execute([$seasonId]);
        $raceList = $rlStmt->fetchAll();
    }
    $pageTitle = 'Race Entries — Select Race';
    renderFlash();
    echo '<div class="page-header"><div><h1 class="page-title">Race Entries</h1><p class="page-subtitle">Select a race to manage entries</p></div></div>';
    echo '<div class="card"><div class="card-title">&#128203; ' . h((string)($activeSeason['year'] ?? '')) . ' Races</div>';
    if (empty($raceList)) { echo '<div class="empty-state"><p>No races found for the active season.</p></div>'; }
    else {
        echo '<div class="table-container" style="border:0;margin:0"><table><thead><tr><th>Rd</th><th>Race</th><th>Date</th><th>Entries</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($raceList as $rl) {
            echo '<tr><td><span class="round-chip">' . h((string)$rl['round_number']) . '</span></td>'
               . '<td><strong>' . h($rl['name']) . '</strong></td>'
               . '<td class="text-muted">' . h(date('d M Y', strtotime($rl['race_date']))) . '</td>'
               . '<td>' . ($rl['entry_count'] > 0 ? '<span class="text-success">' . $rl['entry_count'] . '</span>' : '<span class="text-muted">0</span>') . '</td>'
               . '<td><span class="status-badge status-' . h($rl['status']) . '">' . h($rl['status']) . '</span></td>'
               . '<td><a href="' . APP_URL . '/race_director/race_entries.php?race_id=' . (int)$rl['id'] . '" class="btn btn-primary btn-sm">Manage Entries</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $db->prepare("SELECT r.*, c.name AS circuit, s.year AS season_year, r.season_id FROM races r JOIN circuits c ON c.id=r.circuit_id JOIN seasons s ON s.id=r.season_id WHERE r.id=?");
$stmt->execute([$raceId]);
$race = $stmt->fetch();
if (!$race) { include __DIR__ . '/../includes/404.php'; exit; }

// Existing entries
$stmt = $db->prepare("
    SELECT re.id, re.person_id, re.team_season_id,
           p.first_name, p.last_name, p.racing_number, t.name AS team_name
    FROM race_entries re
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE re.race_id = ?
    ORDER BY p.racing_number
");
$stmt->execute([$raceId]);
$entries = $stmt->fetchAll();

// Drivers registered for this season who aren't yet entered.
// ds.id = MAX(ds2.id) deduplicates drivers who transferred teams mid-season
// (they'd have two driver_seasons rows for the same season) — take their latest registration.
$stmt = $db->prepare("
    SELECT ds.person_id, ds.team_season_id,
           p.first_name, p.last_name, p.racing_number, t.name AS team_name
    FROM driver_seasons ds
    JOIN people p ON p.id = ds.person_id
    JOIN team_seasons ts ON ts.id = ds.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE ds.season_id = ?
      AND ds.status = 'active'
      AND ds.person_id NOT IN (SELECT person_id FROM race_entries WHERE race_id = ?)
      AND ds.id = (
          SELECT MAX(ds2.id) FROM driver_seasons ds2
          WHERE ds2.person_id = ds.person_id AND ds2.season_id = ? AND ds2.status = 'active'
      )
    ORDER BY p.racing_number
");
$stmt->execute([$race['season_id'], $raceId, $race['season_id']]);
$availableDrivers = $stmt->fetchAll();

// Handle add entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $personId    = intval($_POST['person_id'] ?? 0);
        $teamSeasonId= intval($_POST['team_season_id'] ?? 0);
        if (!$personId || !$teamSeasonId) {
            $errors[] = 'Driver and team are required.';
        } else {
            $chk = $db->prepare('SELECT id FROM race_entries WHERE race_id=? AND person_id=?');
            $chk->execute([$raceId, $personId]);
            if ($chk->fetch()) {
                $errors[] = 'This driver already has an entry for this race.';
            } else {
                $db->prepare("INSERT INTO race_entries (race_id,person_id,team_season_id) VALUES (?,?,?)")
                   ->execute([$raceId, $personId, $teamSeasonId]);
                logAudit($_SESSION['user_id'], 'create', 'race_entries', null, "Race $raceId, person $personId");
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/race_director/race_entries.php?race_id=' . $raceId, 'success', 'Driver entry added.');
            }
        }
    }
}

// Handle remove entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_entry'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $entryId = intval($_POST['entry_id'] ?? 0);
        $db->prepare('DELETE FROM race_entries WHERE id = ?')->execute([$entryId]);
        logAudit($_SESSION['user_id'], 'delete', 'race_entries', $entryId);
        rotateCSRFToken();
        redirectWithMessage(APP_URL . '/race_director/race_entries.php?race_id=' . $raceId, 'success', 'Entry removed.');
    }
}

// Handle bulk add all
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_all'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $added = 0;
        foreach ($availableDrivers as $d) {
            $chk = $db->prepare('SELECT id FROM race_entries WHERE race_id=? AND person_id=?');
            $chk->execute([$raceId, $d['person_id']]);
            if (!$chk->fetch()) {
                $db->prepare("INSERT INTO race_entries (race_id,person_id,team_season_id) VALUES (?,?,?)")
                   ->execute([$raceId, $d['person_id'], $d['team_season_id']]);
                $added++;
            }
        }
        logAudit($_SESSION['user_id'], 'create', 'race_entries', null, "Bulk added $added drivers for race $raceId");
        rotateCSRFToken();
        redirectWithMessage(APP_URL . '/race_director/race_entries.php?race_id=' . $raceId, 'success', "$added driver" . ($added != 1 ? 's' : '') . ' added.');
    }
}

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Race Entries</h1>
        <p class="page-subtitle"><?= h($race['name']) ?> — Round <?= h((string)$race['round_number']) ?> — <?= h(date('d M Y', strtotime($race['race_date']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/race_director/dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-title">Current Entries (<?= count($entries) ?>)</div>
        <?php if (empty($entries)): ?>
        <div class="empty-state"><p>No entries yet.</p></div>
        <?php else: ?>
        <?php foreach ($entries as $e): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--border)">
            <div>
                <strong>#<?= h((string)$e['racing_number']) ?> <?= h($e['first_name'] . ' ' . $e['last_name']) ?></strong>
                <div class="text-muted" style="font-size:0.8rem"><?= h($e['team_name']) ?></div>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="entry_id" value="<?= $e['id'] ?>">
                <input type="hidden" name="remove_entry" value="1">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove this entry? This will delete qualifying/race results too.">Remove</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div>
        <?php if ($availableDrivers): ?>
        <div class="card">
            <div class="card-title">Add Driver Entry</div>
            <form method="post" style="margin-bottom:1rem">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <div class="form-group">
                    <label class="form-label">Driver (Season Registered)</label>
                    <select name="person_id" class="form-control" onchange="updateTeam(this)">
                        <?php foreach ($availableDrivers as $d): ?>
                        <option value="<?= h((string)$d['person_id']) ?>" data-team="<?= h((string)$d['team_season_id']) ?>">
                            #<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?> — <?= h($d['team_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="team_season_id" id="team_season_id" value="<?= h((string)($availableDrivers[0]['team_season_id'] ?? '')) ?>">
                <button type="submit" name="add_entry" class="btn btn-primary">Add Entry</button>
            </form>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="add_all" value="1">
                <button type="submit" class="btn btn-secondary" data-confirm="Add all remaining season drivers to this race?">Add All Season Drivers</button>
            </form>
        </div>
        <?php else: ?>
        <div class="notice">All season-registered drivers have been entered for this race.</div>
        <?php endif; ?>

        <div class="card" style="margin-top:1rem">
            <div class="card-title">Next Steps</div>
            <div style="display:flex;flex-direction:column;gap:0.5rem">
                <a href="<?= APP_URL ?>/race_director/qualifying.php?race_id=<?= (int)$raceId ?>" class="btn btn-outline">Enter Qualifying Results &rarr;</a>
                <a href="<?= APP_URL ?>/race_director/results.php?race_id=<?= (int)$raceId ?>" class="btn btn-primary">Enter Race Results &rarr;</a>
                <?php if ($race['has_sprint']): ?>
                <a href="<?= APP_URL ?>/race_director/sprint.php?race_id=<?= (int)$raceId ?>" class="btn btn-outline">Enter Sprint Results &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateTeam(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('team_season_id').value = opt.dataset.team;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

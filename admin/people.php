<?php
$pageTitle = 'Driver Records';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin', 'race_director');
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$db = getDB();
$errors = [];

// Handle pending driver approval / rejection
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $personId = intval($_POST['person_id'] ?? 0);

        if (isset($_POST['approve_driver']) && $personId) {
            $tempPassword = trim($_POST['temp_password'] ?? '');
            $driverName   = trim($_POST['driver_name'] ?? '');
            $driverEmail  = trim($_POST['driver_email'] ?? '');

            if (!$tempPassword)   $errors[] = 'Temporary password is required.';
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
                    $db->beginTransaction();
                    try {
                        $requestingTeamId = (int)$person['requested_by_team_id'];

                        // Activate person and clear pending flag
                        $db->prepare('UPDATE people SET is_active = 1, requested_by_team_id = NULL WHERE id = ?')->execute([$personId]);

                        // Create user account
                        $hash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                        $fullName = $person['first_name'] . ' ' . $person['last_name'];
                        $db->prepare("INSERT INTO users (name, email, password_hash, role, linked_id, is_active, must_change_password, created_by) VALUES (?,?,?,'driver',?,1,1,?)")
                           ->execute([$fullName, $driverEmail, $hash, $personId, $_SESSION['user_id']]);

                        // Create driver_seasons entry for requesting team in the active season
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
                        logAudit($_SESSION['user_id'], 'approve', 'people', $personId, "Approved pending driver: $fullName, created user account");
                        rotateCSRFToken();
                        redirectWithMessage(APP_URL . '/admin/people.php', 'success', "Driver '$fullName' approved. User account created (must change password on first login).");
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
            }
        }
    }
}

// Pending driver requests
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
            <!-- Approve form -->
            <form method="post" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="person_id" value="<?= (int)$pr['id'] ?>">
                <input type="hidden" name="driver_name" value="<?= h($pr['first_name'] . ' ' . $pr['last_name']) ?>">
                <div class="form-group" style="margin:0">
                    <label class="form-label" style="font-size:0.75rem">Driver Email</label>
                    <input type="email" name="driver_email" class="form-control" style="padding:0.3rem 0.5rem;width:200px" required placeholder="driver@example.com">
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label" style="font-size:0.75rem">Temp Password</label>
                    <input type="text" name="temp_password" class="form-control" style="padding:0.3rem 0.5rem;width:160px" required placeholder="Min 8 chars">
                </div>
                <button type="submit" name="approve_driver" class="btn btn-primary btn-sm" data-confirm="Approve '<?= h($pr['first_name'] . ' ' . $pr['last_name']) ?>' and create driver account?">Approve</button>
            </form>
            <!-- Reject form -->
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="person_id" value="<?= (int)$pr['id'] ?>">
                <button type="submit" name="reject_driver" class="btn btn-danger btn-sm" data-confirm="Reject and delete '<?= h($pr['first_name'] . ' ' . $pr['last_name']) ?>'?">Reject</button>
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
    <?php if ($isAdmin): ?><a href="<?= APP_URL ?>/admin/people_create.php" class="btn btn-primary">+ Add Driver</a><?php endif; ?>
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
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/person_detail.php?id=<?= $p['id'] ?>">
            <td><strong class="text-accent"><?= h((string)$p['racing_number']) ?></strong></td>
            <td><strong><?= h($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
            <td><?= h($p['nationality']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($p['date_of_birth']))) ?></td>
            <td><?= h((string)$p['season_count']) ?></td>
            <td><span class="status-badge <?= $p['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <?php if ($isAdmin): ?>
                <a href="<?= APP_URL ?>/admin/people_edit.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
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

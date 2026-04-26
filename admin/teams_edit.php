<?php
$pageTitle = 'Edit Team';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$errors = [];
$teamId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM teams WHERE id = ?');
$stmt->execute([$teamId]);
$team = $stmt->fetch();
if (!$team) { include __DIR__ . '/../includes/404.php'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $name       = strip_tags(trim($_POST['name'] ?? ''));
        $shortName  = strtoupper(strip_tags(trim($_POST['short_name'] ?? '')));
        $nationality= strip_tags(trim($_POST['nationality'] ?? ''));
        $founded    = intval($_POST['founded_year'] ?? 0) ?: null;
        $isActive   = isset($_POST['is_active']) ? 1 : 0;

        if (!$name)        $errors[] = 'Team name is required.';
        if (!$shortName)   $errors[] = 'Short name is required.';
        elseif (!preg_match('/^[A-Z]{3,4}$/', $shortName)) $errors[] = 'Short name must be 3 or 4 uppercase letters only.';
        if (!$nationality) $errors[] = 'Nationality is required.';

        if (empty($errors)) {
            $chk = $db->prepare('SELECT id FROM teams WHERE name = ? AND id != ?');
            $chk->execute([$name, $teamId]);
            if ($chk->fetch()) {
                $errors[] = "Another team named '{$name}' already exists.";
            }
            if (empty($errors)) {
                $chkSn = $db->prepare('SELECT id FROM teams WHERE short_name = ? AND id != ?');
                $chkSn->execute([$shortName, $teamId]);
                if ($chkSn->fetch()) $errors[] = "Short name '{$shortName}' is already taken by another team.";
            }
            if (empty($errors)) {
                $db->prepare("UPDATE teams SET name=?,short_name=?,nationality=?,founded_year=?,is_active=? WHERE id=?")
                   ->execute([$name, $shortName, $nationality, $founded, $isActive, $teamId]);
                logAudit($_SESSION['user_id'], 'update', 'teams', $teamId, "Updated team: $name");
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/teams.php', 'success', "Team '{$name}' updated.");
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Team</h1>
        <p class="page-subtitle"><?= h($team['name']) ?></p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <a href="<?= APP_URL ?>/admin/team_detail.php?id=<?= $teamId ?>" class="btn btn-outline">View Detail</a>
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
                <input type="text" id="name" name="name" class="form-control" value="<?= h($_POST['name'] ?? $team['name']) ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label required" for="short_name">Short Name (3-4 letters)</label>
                <input type="text" id="short_name" name="short_name" class="form-control" value="<?= h($_POST['short_name'] ?? $team['short_name']) ?>" required maxlength="4" minlength="3" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z]/g,'')">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control" value="<?= h($_POST['nationality'] ?? $team['nationality']) ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label" for="founded_year">Founded Year</label>
                <input type="number" id="founded_year" name="founded_year" class="form-control" value="<?= h($_POST['founded_year'] ?? ($team['founded_year'] ?? '')) ?>" min="1950" max="<?= date('Y') ?>">
            </div>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
                <input type="checkbox" name="is_active" value="1"<?= $team['is_active'] ? ' checked' : '' ?>>
                <span>Active team</span>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/admin/teams.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

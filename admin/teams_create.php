<?php
$pageTitle = 'Add Team';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $name       = strip_tags(trim($_POST['name'] ?? ''));
        $shortName  = strtoupper(strip_tags(trim($_POST['short_name'] ?? '')));
        $nationality= strip_tags(trim($_POST['nationality'] ?? ''));
        $founded    = intval($_POST['founded_year'] ?? 0) ?: null;

        if (!$name)        $errors[] = 'Team name is required.';
        if (!$shortName)   $errors[] = 'Short name is required.';
        elseif (!preg_match('/^[A-Z]{3,4}$/', $shortName)) $errors[] = 'Short name must be 3 or 4 uppercase letters only.';
        if (!$nationality) $errors[] = 'Nationality is required.';

        if (empty($errors)) {
            $chk = $db->prepare('SELECT id FROM teams WHERE name = ?');
            $chk->execute([$name]);
            if ($chk->fetch()) {
                $errors[] = "A team named '{$name}' already exists.";
            }
            if (empty($errors)) {
                $chkSn = $db->prepare('SELECT id FROM teams WHERE short_name = ?');
                $chkSn->execute([$shortName]);
                if ($chkSn->fetch()) $errors[] = "Short name '{$shortName}' is already taken by another team.";
            }
            if (empty($errors)) {
                $db->prepare("INSERT INTO teams (name, short_name, nationality, founded_year) VALUES (?,?,?,?)")
                   ->execute([$name, $shortName, $nationality, $founded]);
                $newId = (int)$db->lastInsertId();
                logAudit($_SESSION['user_id'], 'create', 'teams', $newId, "Created team: $name");
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/team_detail.php?id=' . $newId . '&prompt_manager=1', 'success', "Team '{$name}' created. You can now add a team manager account below.");
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h1 class="page-title">Add Team</h1>
    <a href="<?= APP_URL ?>/admin/teams.php" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="name">Team Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label required" for="short_name">Short Name (3-4 letters)</label>
                <input type="text" id="short_name" name="short_name" class="form-control" value="<?= h($_POST['short_name'] ?? '') ?>" required maxlength="4" minlength="3" placeholder="e.g. RBR" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z]/g,'')">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control" value="<?= h($_POST['nationality'] ?? '') ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label" for="founded_year">Founded Year</label>
                <input type="number" id="founded_year" name="founded_year" class="form-control" value="<?= h($_POST['founded_year'] ?? '') ?>" min="1950" max="<?= date('Y') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Team</button>
            <a href="<?= APP_URL ?>/admin/teams.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

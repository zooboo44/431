<?php
$pageTitle = 'Add Season';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();
$errors = [];

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
        <div class="notice">
            After creating a season, use <strong>Season Registrations</strong> to assign teams and drivers.
            You can also set it as the active season from the Seasons list.
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Season</button>
            <a href="<?= APP_URL ?>/admin/seasons.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

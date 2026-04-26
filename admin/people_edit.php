<?php
$pageTitle = 'Edit Driver';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db       = getDB();
$errors   = [];
$personId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM people WHERE id = ?');
$stmt->execute([$personId]);
$person = $stmt->fetch();
if (!$person) { include __DIR__ . '/../includes/404.php'; exit; }

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
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if (!$firstName || !$lastName || !$natl) $errors[] = 'All required fields must be filled.';
        if ($number < 1 || $number > 99)          $errors[] = 'Racing number must be 1–99.';

        $dobError = validateDOB($dob);
        if ($dobError) $errors[] = $dobError;

        if (empty($errors)) {
            $chk = $db->prepare('SELECT id FROM people WHERE racing_number = ? AND id != ?');
            $chk->execute([$number, $personId]);
            if ($chk->fetch()) {
                $errors[] = "Racing number #$number is taken by another driver.";
            }
            $chk = $db->prepare('SELECT id FROM people WHERE first_name = ? AND last_name = ? AND id != ?');
            $chk->execute([$firstName, $lastName, $personId]);
            if ($chk->fetch()) {
                $errors[] = "Another driver named '{$firstName} {$lastName}' already exists.";
            }
        }

        if (empty($errors)) {
            $db->prepare("UPDATE people SET first_name=?,last_name=?,nationality=?,date_of_birth=?,racing_number=?,bio=?,is_active=? WHERE id=?")
               ->execute([$firstName, $lastName, $natl, $dob, $number, $bio ?: null, $isActive, $personId]);
            logAudit($_SESSION['user_id'], 'update', 'people', $personId, "$firstName $lastName");
            rotateCSRFToken();
            redirectWithMessage(APP_URL . '/admin/people.php', 'success', "Driver '{$firstName} {$lastName}' updated.");
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Driver</h1>
        <p class="page-subtitle"><?= h($person['first_name'] . ' ' . $person['last_name']) ?> — #<?= h((string)$person['racing_number']) ?></p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <a href="<?= APP_URL ?>/admin/person_detail.php?id=<?= $personId ?>" class="btn btn-outline">View Detail</a>
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
                <input type="text" id="first_name" name="first_name" class="form-control" value="<?= h($_POST['first_name'] ?? $person['first_name']) ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-control" value="<?= h($_POST['last_name'] ?? $person['last_name']) ?>" required maxlength="50">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control" value="<?= h($_POST['nationality'] ?? $person['nationality']) ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="date_of_birth">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?= h($_POST['date_of_birth'] ?? $person['date_of_birth']) ?>" required>
                <div class="form-hint">Driver must be 18–60 years old</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="racing_number">Racing Number</label>
                <input type="number" id="racing_number" name="racing_number" class="form-control" value="<?= h((string)($_POST['racing_number'] ?? $person['racing_number'])) ?>" required min="1" max="99">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-top:0.5rem">
                    <input type="checkbox" name="is_active" value="1"<?= $person['is_active'] ? ' checked' : '' ?>>
                    <span>Active driver</span>
                </label>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="bio">Bio</label>
            <textarea id="bio" name="bio" class="form-control" rows="3" maxlength="5000"><?= h($_POST['bio'] ?? ($person['bio'] ?? '')) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/admin/people.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

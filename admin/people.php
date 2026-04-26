<?php
$pageTitle = 'Driver Records';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin', 'race_director');
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$db = getDB();
$stmt = $db->query("
    SELECT p.*,
           COUNT(DISTINCT ds.season_id) AS season_count,
           COUNT(DISTINCT rr.id) AS result_count
    FROM people p
    LEFT JOIN driver_seasons ds ON ds.person_id = p.id
    LEFT JOIN race_entries re ON re.person_id = p.id
    LEFT JOIN race_results rr ON rr.race_entry_id = re.id
    GROUP BY p.id
    ORDER BY p.last_name, p.first_name
");
$people = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

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

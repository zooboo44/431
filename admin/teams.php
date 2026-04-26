<?php
$pageTitle = 'Teams';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin', 'race_director');
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$db = getDB();
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
    <?php if ($isAdmin): ?><a href="<?= APP_URL ?>/admin/teams_create.php" class="btn btn-primary">+ Add Team</a><?php endif; ?>
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
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/team_detail.php?id=<?= $t['id'] ?>">
            <td><strong><?= h($t['name']) ?></strong></td>
            <td><code><?= h($t['short_name']) ?></code></td>
            <td><?= h($t['nationality']) ?></td>
            <td class="text-muted"><?= $t['founded_year'] ? h((string)$t['founded_year']) : '—' ?></td>
            <td><?= h((string)$t['season_count']) ?></td>
            <td><span class="status-badge <?= $t['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <?php if ($isAdmin): ?>
                <a href="<?= APP_URL ?>/admin/teams_edit.php?id=<?= $t['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
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
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

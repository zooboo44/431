<?php
$pageTitle = 'Seasons';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_type'] = 'danger';
        $_SESSION['flash_message'] = 'Invalid token.';
        header('Location: ' . APP_URL . '/admin/seasons.php');
        exit;
    }

    if (isset($_POST['set_active'])) {
        $sid = intval($_POST['season_id'] ?? 0);
        if ($sid) {
            $db->query('UPDATE seasons SET is_active=0');
            $db->prepare('UPDATE seasons SET is_active=1 WHERE id=?')->execute([$sid]);
            logAudit($_SESSION['user_id'], 'update', 'seasons', $sid, 'Set as active season');
        }
        rotateCSRFToken();
        redirectWithMessage(APP_URL . '/admin/seasons.php', 'success', 'Active season updated.');
    }

    if (isset($_POST['delete_season'])) {
        $sid = intval($_POST['season_id'] ?? 0);
        $stmt = $db->prepare('SELECT year, is_active FROM seasons WHERE id = ?');
        $stmt->execute([$sid]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage(APP_URL . '/admin/seasons.php', 'danger', 'Season not found.'); }
        if ($row['is_active']) { redirectWithMessage(APP_URL . '/admin/seasons.php', 'danger', 'Cannot delete the active season.'); }
        $cmpStmt = $db->prepare("SELECT COUNT(*) FROM races WHERE season_id = ? AND status = 'completed'");
        $cmpStmt->execute([$sid]);
        if ((int)$cmpStmt->fetchColumn() > 0) {
            redirectWithMessage(APP_URL . '/admin/seasons.php', 'danger', 'Cannot delete a season that has completed races.');
        }
        $db->prepare('DELETE FROM seasons WHERE id = ?')->execute([$sid]);
        logAudit($_SESSION['user_id'], 'delete', 'seasons', $sid, "Year: {$row['year']}");
        rotateCSRFToken();
        redirectWithMessage(APP_URL . '/admin/seasons.php', 'success', "Season {$row['year']} deleted.");
    }
}

$stmt = $db->query("
    SELECT s.*,
           pd.first_name AS champ_d_first, pd.last_name AS champ_d_last,
           tc.name AS champ_team,
           COUNT(DISTINCT r.id) AS race_count
    FROM seasons s
    LEFT JOIN people pd ON pd.id = s.champion_person_id
    LEFT JOIN teams tc ON tc.id = s.champion_team_id
    LEFT JOIN races r ON r.season_id = s.id
    GROUP BY s.id
    ORDER BY s.year DESC
");
$seasons = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Seasons</h1>
        <p class="page-subtitle"><?= count($seasons) ?> season<?= count($seasons) != 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/seasons_create.php" class="btn btn-primary">+ Add Season</a>
</div>

<div class="table-container">
    <table class="sortable">
        <thead><tr>
            <th>Year</th><th>Races</th><th>Champion Driver</th><th>Champion Team</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($seasons)): ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:2rem">No seasons found.</td></tr>
        <?php else: ?>
        <?php foreach ($seasons as $s): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/season_detail.php?id=<?= $s['id'] ?>">
            <td><strong><?= h((string)$s['year']) ?></strong></td>
            <td><?= h((string)$s['race_count']) ?></td>
            <td><?= $s['champ_d_first'] ? h($s['champ_d_first'] . ' ' . $s['champ_d_last']) : '<span class="text-muted">TBD</span>' ?></td>
            <td><?= $s['champ_team'] ? h($s['champ_team']) : '<span class="text-muted">TBD</span>' ?></td>
            <td>
                <?php if ($s['is_active']): ?>
                <span class="status-badge status-active">&#9733; Active</span>
                <?php else: ?>
                <span class="status-badge status-inactive">Inactive</span>
                <?php endif; ?>
            </td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/season_registrations.php?season_id=<?= $s['id'] ?>" class="btn btn-outline btn-sm">Registrations</a>
                <?php if (!$s['is_active']): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="season_id" value="<?= $s['id'] ?>">
                    <button type="submit" name="set_active" class="btn btn-secondary btn-sm">Set Active</button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="season_id" value="<?= $s['id'] ?>">
                    <button type="submit" name="delete_season" class="btn btn-danger btn-sm"
                        data-confirm="Delete season <?= h((string)$s['year']) ?>? This will remove all <?= h((string)$s['race_count']) ?> races and their data.">Delete</button>
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

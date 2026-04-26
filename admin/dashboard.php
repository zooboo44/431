<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();

$stats = [];
$stats['users']    = $db->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn();
$stats['teams']    = $db->query('SELECT COUNT(*) FROM teams WHERE is_active=1')->fetchColumn();
$stats['drivers']  = $db->query('SELECT COUNT(*) FROM people WHERE is_active=1')->fetchColumn();
$stats['races']    = $db->query("SELECT COUNT(*) FROM races WHERE status='completed'")->fetchColumn();
$stats['circuits'] = $db->query('SELECT COUNT(*) FROM circuits WHERE is_active=1')->fetchColumn();
$stats['seasons']  = $db->query('SELECT COUNT(*) FROM seasons')->fetchColumn();
$stats['penalties']= $db->query('SELECT COUNT(*) FROM penalties')->fetchColumn();

// Championship leaders from active season
$activeSeason = getActiveSeason();
$leaderDriver = null;
$leaderTeam   = null;
if ($activeSeason) {
    $stmt = $db->prepare("
        SELECT p.first_name, p.last_name, p.id AS person_id, ds.points
        FROM driver_standings ds JOIN people p ON p.id = ds.person_id
        WHERE ds.season_id = ? AND ds.position = 1
        LIMIT 1
    ");
    $stmt->execute([$activeSeason['id']]);
    $leaderDriver = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT t.name, t.id AS team_id, cs.points
        FROM constructor_standings cs JOIN teams t ON t.id = cs.team_id
        WHERE cs.season_id = ? AND cs.position = 1
        LIMIT 1
    ");
    $stmt->execute([$activeSeason['id']]);
    $leaderTeam = $stmt->fetch();
}

// Recent audit log — last 10
$stmt = $db->prepare("
    SELECT al.*, u.name AS user_name, u.role AS user_role
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentAudit = $stmt->fetchAll();

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Admin Dashboard</h1>
        <p class="page-subtitle">System overview and recent activity</p>
    </div>
</div>

<div class="stats-grid">
    <a href="<?= APP_URL ?>/admin/users.php" class="stat-card stat-card-link">
        <div class="stat-icon">&#128101;</div>
        <div class="stat-value"><?= h((string)$stats['users']) ?></div>
        <div class="stat-label">Active Users</div>
    </a>
    <a href="<?= APP_URL ?>/admin/teams.php" class="stat-card stat-card-link">
        <div class="stat-icon">&#127937;</div>
        <div class="stat-value"><?= h((string)$stats['teams']) ?></div>
        <div class="stat-label">Active Teams</div>
    </a>
    <a href="<?= APP_URL ?>/admin/people.php" class="stat-card stat-card-link">
        <div class="stat-icon">&#128100;</div>
        <div class="stat-value"><?= h((string)$stats['drivers']) ?></div>
        <div class="stat-label">Driver Records</div>
    </a>
    <a href="<?= APP_URL ?>/admin/results_overview.php" class="stat-card stat-card-link">
        <div class="stat-icon">&#9989;</div>
        <div class="stat-value"><?= h((string)$stats['races']) ?></div>
        <div class="stat-label">Completed Races</div>
    </a>
    <a href="<?= APP_URL ?>/admin/circuits.php" class="stat-card stat-card-link">
        <div class="stat-icon">&#9940;</div>
        <div class="stat-value"><?= h((string)$stats['circuits']) ?></div>
        <div class="stat-label">Circuits</div>
    </a>
    <a href="<?= APP_URL ?>/admin/seasons.php" class="stat-card stat-card-link">
        <div class="stat-icon">&#128197;</div>
        <div class="stat-value"><?= h((string)$stats['seasons']) ?></div>
        <div class="stat-label">Seasons</div>
    </a>
    <a href="<?= APP_URL ?>/admin/penalties.php" class="stat-card stat-card-link">
        <div class="stat-icon">&#9888;</div>
        <div class="stat-value"><?= h((string)$stats['penalties']) ?></div>
        <div class="stat-label">Total Penalties</div>
    </a>
</div>

<?php if ($leaderDriver || $leaderTeam): ?>
<div class="grid-2" style="margin-bottom:1.5rem">
    <?php if ($leaderDriver): ?>
    <div class="card">
        <div class="card-title">&#127942; Championship Leader — Drivers</div>
        <a href="<?= APP_URL ?>/admin/person_detail.php?id=<?= $leaderDriver['person_id'] ?>" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:1rem">
            <div>
                <div style="font-size:1.5rem;font-weight:700"><?= h($leaderDriver['first_name'] . ' ' . $leaderDriver['last_name']) ?></div>
                <div style="color:var(--accent);font-size:1.1rem;font-weight:600"><?= h(number_format((float)$leaderDriver['points'], 1)) ?> pts</div>
            </div>
        </a>
    </div>
    <?php endif; ?>
    <?php if ($leaderTeam): ?>
    <div class="card">
        <div class="card-title">&#127942; Championship Leader — Constructors</div>
        <a href="<?= APP_URL ?>/admin/team_detail.php?id=<?= $leaderTeam['team_id'] ?>" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:1rem">
            <div>
                <div style="font-size:1.5rem;font-weight:700"><?= h($leaderTeam['name']) ?></div>
                <div style="color:var(--accent);font-size:1.1rem;font-weight:600"><?= h(number_format((float)$leaderTeam['points'], 1)) ?> pts</div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
        <span>Recent Audit Activity</span>
        <a href="<?= APP_URL ?>/admin/audit_log.php" class="btn btn-outline btn-sm">Full Log</a>
    </div>
    <?php if (empty($recentAudit)): ?>
    <div class="empty-state"><p>No audit entries yet.</p></div>
    <?php else: ?>
    <div class="table-container" style="border:0;margin-bottom:0">
        <table>
            <thead><tr>
                <th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Resource</th><th>IP</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentAudit as $log): ?>
            <tr>
                <td class="text-muted" style="white-space:nowrap;font-size:0.8rem"><?= h(date('d M H:i', strtotime($log['created_at']))) ?></td>
                <td><?= $log['user_name'] ? h($log['user_name']) : '<span class="text-muted">—</span>' ?></td>
                <td><?php if ($log['user_role']): ?>
                    <span class="role-badge" style="background:<?= h(ROLE_BADGE_COLORS[$log['user_role']] ?? '#555') ?>"><?= h($log['user_role']) ?></span>
                <?php else: ?>—<?php endif; ?></td>
                <td>
                    <code style="font-size:0.8rem;color:<?= str_contains($log['action'], 'fail') || str_contains($log['action'], 'denied') ? 'var(--danger)' : 'var(--success)' ?>"><?= h($log['action']) ?></code>
                </td>
                <td class="text-muted"><?= $log['resource'] ? h($log['resource'] . ($log['resource_id'] ? ' #' . $log['resource_id'] : '')) : '—' ?></td>
                <td class="text-muted" style="font-size:0.8rem"><?= h($log['ip_address']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) $publicPage = true;

$_view = $_GET['view'] ?? '';
if ($_view === 'driver')     $pageTitle = 'Driver Profile';
elseif ($_view === 'team')   $pageTitle = 'Team Profile';
else                         $pageTitle = 'Championship Standings';

require_once __DIR__ . '/../includes/header.php';
if (!($publicPage ?? false)) requireRole('admin', 'team_manager', 'driver');

$db = getDB();

// ─── DRIVER DETAIL ────────────────────────────────────────────────────────────
if ($_view === 'driver') {
    $personId = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM people WHERE id = ? AND is_active = 1");
    $stmt->execute([$personId]);
    $person = $stmt->fetch();
    if (!$person) { include __DIR__ . '/../includes/404.php'; exit; }

    $stmt = $db->prepare("
        SELECT s.year, s.id AS season_id, t.name AS team_name, t.id AS team_id, drs.status,
               NULL AS position,
               COALESCE((
                   SELECT SUM(rr2.points_scored)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
               ),0) AS points,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
               ),0) AS wins,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position<=3 AND rr2.finish_position IS NOT NULL AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
               ),0) AS podiums,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.fastest_lap_bonus=1 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
               ),0) AS fastest_laps,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.status='DNF' AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
               ),0) AS dnfs
        FROM driver_seasons drs
        JOIN seasons s ON s.id = drs.season_id
        JOIN team_seasons ts ON ts.id = drs.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE drs.person_id = ?
        ORDER BY s.year DESC
    ");
    $stmt->execute([$personId]);
    $careerBySeasons = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT r.id AS race_id, r.name AS race_name, r.round_number, s.year,
               rr.finish_position, rr.points_scored, rr.status, rr.fastest_lap_bonus,
               qr.grid_position, t.short_name
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id AND re.person_id = ?
        JOIN races r ON r.id = re.race_id
        JOIN seasons s ON s.id = r.season_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
        WHERE rr.is_sprint = 0
        ORDER BY r.race_date DESC
        LIMIT 20
    ");
    $stmt->execute([$personId]);
    $recentResults = $stmt->fetchAll();

    $totalPts  = array_sum(array_column($careerBySeasons, 'points'));
    $totalWins = array_sum(array_column($careerBySeasons, 'wins'));
    $totalPods = array_sum(array_column($careerBySeasons, 'podiums'));
    $totalDnfs = array_sum(array_column($careerBySeasons, 'dnfs'));
    ?>

<div class="page-header">
    <div>
        <h1 class="page-title">#<?= h((string)$person['racing_number']) ?> <?= h($person['first_name'] . ' ' . $person['last_name']) ?></h1>
        <p class="page-subtitle"><?= h($person['nationality']) ?><?= $person['date_of_birth'] ? ' &bull; Born ' . h(date('d M Y', strtotime($person['date_of_birth']))) : '' ?></p>
    </div>
    <a href="javascript:history.back()" class="btn btn-outline">&larr; Back</a>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card"><div class="stat-value"><?= h(number_format((float)$totalPts, 1)) ?></div><div class="stat-label">Career Points</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$totalWins) ?></div><div class="stat-label">Wins</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$totalPods) ?></div><div class="stat-label">Podiums</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$totalDnfs) ?></div><div class="stat-label">DNFs</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title">Season-by-Season</div>
        <?php if (empty($careerBySeasons)): ?>
        <div class="empty-state"><p>No season data.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Year</th><th>Team</th><th>Pos</th><th>Pts</th><th>Wins</th><th>Pods</th></tr></thead>
            <tbody>
            <?php foreach ($careerBySeasons as $cs): ?>
            <tr>
                <td><strong><?= h((string)$cs['year']) ?></strong></td>
                <td><a href="<?= APP_URL ?>/shared/standings.php?view=team&id=<?= (int)$cs['team_id'] ?>"><?= h($cs['team_name']) ?></a></td>
                <td><?= $cs['position'] ? 'P' . h((string)$cs['position']) : '—' ?></td>
                <td class="text-accent fw-bold"><?= h((string)($cs['points'] ?? 0)) ?></td>
                <td><?= h((string)($cs['wins'] ?? 0)) ?></td>
                <td><?= h((string)($cs['podiums'] ?? 0)) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Recent Race Results</div>
        <?php if (empty($recentResults)): ?>
        <div class="empty-state"><p>No results yet.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Year</th><th>Race</th><th>Grid</th><th>Finish</th><th>Pts</th></tr></thead>
            <tbody>
            <?php foreach ($recentResults as $r): ?>
            <tr class="clickable-row" data-href="<?= APP_URL ?>/shared/results.php?id=<?= (int)$r['race_id'] ?>">
                <td class="text-muted"><?= h((string)$r['year']) ?></td>
                <td><?= h($r['race_name']) ?></td>
                <td class="text-muted"><?= $r['grid_position'] ? 'P' . h((string)$r['grid_position']) : '—' ?></td>
                <td>
                    <?php if ($r['finish_position']): ?>
                    <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                    <?php else: ?>
                    <span class="status-badge status-dnf"><?= h($r['status']) ?></span>
                    <?php endif; ?>
                    <?= $r['fastest_lap_bonus'] ? ' <span class="fl-indicator">&#9889;</span>' : '' ?>
                </td>
                <td class="text-accent"><?= h((string)$r['points_scored']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── TEAM DETAIL ──────────────────────────────────────────────────────────────
if ($_view === 'team') {
    $teamId = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM teams WHERE id = ? AND is_active = 1");
    $stmt->execute([$teamId]);
    $team = $stmt->fetch();
    if (!$team) { include __DIR__ . '/../includes/404.php'; exit; }

    $stmt = $db->prepare("
        SELECT ts.id AS team_season_id, s.year, s.id AS season_id, ts.principal, ts.car_name, ts.power_unit,
               NULL AS championship_pos,
               COALESCE((
                   SELECT SUM(rr2.points_scored)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = ts.season_id AND r2.status='completed' AND re2.team_season_id = ts.id
               ),0) AS points,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = ts.season_id AND r2.status='completed' AND re2.team_season_id = ts.id
               ),0) AS wins
        FROM team_seasons ts
        JOIN seasons s ON s.id = ts.season_id
        WHERE ts.team_id = ?
        ORDER BY s.year DESC
    ");
    $stmt->execute([$teamId]);
    $seasonHistory = $stmt->fetchAll();

    $activeSeason = getActiveSeason();
    $currentDrivers = [];
    if ($activeSeason) {
        $stmt = $db->prepare("
            SELECT p.id AS person_id, p.first_name, p.last_name, p.racing_number, p.nationality,
                   drs.status, NULL AS position,
                   COALESCE((
                       SELECT SUM(rr2.points_scored)
                       FROM race_results rr2
                       JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                       JOIN races r2 ON r2.id = re2.race_id
                       WHERE r2.season_id = ? AND r2.status='completed' AND re2.person_id = p.id
                   ),0) AS points,
                   COALESCE((
                       SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                       FROM race_results rr2
                       JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                       JOIN races r2 ON r2.id = re2.race_id
                       WHERE r2.season_id = ? AND r2.status='completed' AND re2.person_id = p.id
                   ),0) AS wins,
                   COALESCE((
                       SELECT SUM(CASE WHEN rr2.finish_position<=3 AND rr2.finish_position IS NOT NULL AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                       FROM race_results rr2
                       JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                       JOIN races r2 ON r2.id = re2.race_id
                       WHERE r2.season_id = ? AND r2.status='completed' AND re2.person_id = p.id
                   ),0) AS podiums
            FROM driver_seasons drs
            JOIN people p ON p.id = drs.person_id
            JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
            WHERE drs.season_id = ? AND drs.status = 'active'
            ORDER BY p.racing_number
        ");
        $stmt->execute([$activeSeason['id'], $activeSeason['id'], $activeSeason['id'], $teamId, $activeSeason['id']]);
        $currentDrivers = $stmt->fetchAll();
    }
    ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= h($team['name']) ?></h1>
        <p class="page-subtitle"><?= h($team['short_name']) ?><?= $team['nationality'] ? ' &bull; ' . h($team['nationality']) : '' ?></p>
    </div>
    <a href="javascript:history.back()" class="btn btn-outline">&larr; Back</a>
</div>

<?php if ($activeSeason && !empty($currentDrivers)): ?>
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-title">&#128100; Current Drivers (<?= h((string)$activeSeason['year']) ?>)</div>
    <div class="stats-grid" style="grid-template-columns:repeat(<?= count($currentDrivers) ?>,1fr)">
        <?php foreach ($currentDrivers as $d): ?>
        <div class="stat-card" style="cursor:pointer" onclick="window.location='<?= APP_URL ?>/shared/standings.php?view=driver&id=<?= (int)$d['person_id'] ?>'">
            <div class="stat-value">#<?= h((string)$d['racing_number']) ?></div>
            <div class="stat-label"><a href="<?= APP_URL ?>/shared/standings.php?view=driver&id=<?= (int)$d['person_id'] ?>"><?= h($d['first_name'] . ' ' . $d['last_name']) ?></a></div>
            <?php if ($d['points'] !== null): ?>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.3rem"><?= h((string)$d['points']) ?> pts<?= $d['position'] ? ' &bull; P' . h((string)$d['position']) : '' ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Season History</div>
    <?php if (empty($seasonHistory)): ?>
    <div class="empty-state"><p>No season history.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Year</th><th>Principal</th><th>Car</th><th>Power Unit</th><th>Champ. Pos</th><th>Points</th><th>Wins</th></tr></thead>
        <tbody>
        <?php foreach ($seasonHistory as $ts): ?>
        <tr>
            <td><strong><?= h((string)$ts['year']) ?></strong></td>
            <td><?= h($ts['principal']) ?></td>
            <td class="text-muted"><?= h($ts['car_name']) ?></td>
            <td class="text-muted"><?= h($ts['power_unit']) ?></td>
            <td><?= $ts['championship_pos'] ? 'P' . h((string)$ts['championship_pos']) : '—' ?></td>
            <td class="text-accent fw-bold"><?= $ts['points'] !== null ? h((string)$ts['points']) : '—' ?></td>
            <td><?= $ts['wins'] !== null ? h((string)$ts['wins']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── STANDINGS LIST ───────────────────────────────────────────────────────────
$seasons = getSeasonList();

$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
}

$driverStandings = [];
$constructorStandings = [];

if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT RANK() OVER (ORDER BY COALESCE(SUM(rr.points_scored),0) DESC,
                            SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) DESC) AS position,
               COALESCE(SUM(rr.points_scored),0) AS points,
               SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS wins,
               SUM(CASE WHEN rr.finish_position<=3 AND rr.finish_position IS NOT NULL AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS podiums,
               SUM(CASE WHEN rr.status='DNF' AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS dnfs,
               SUM(CASE WHEN rr.fastest_lap_bonus=1 THEN 1 ELSE 0 END) AS fastest_laps,
               p.id AS person_id, p.first_name, p.last_name, p.racing_number, p.nationality,
               t.name AS team_name, t.short_name
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id
        JOIN races r ON r.id = re.race_id
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE r.season_id = ? AND r.status = 'completed'
        GROUP BY p.id, t.id
        ORDER BY points DESC, wins DESC
    ");
    $stmt->execute([$selectedSeasonId]);
    $driverStandings = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT RANK() OVER (ORDER BY COALESCE(SUM(rr.points_scored),0) DESC,
                            SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) DESC) AS position,
               COALESCE(SUM(rr.points_scored),0) AS points,
               SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS wins,
               t.id AS team_id, t.name, t.short_name, t.nationality
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id
        JOIN races r ON r.id = re.race_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE r.season_id = ? AND r.status = 'completed'
        GROUP BY t.id
        ORDER BY points DESC, wins DESC
    ");
    $stmt->execute([$selectedSeasonId]);
    $constructorStandings = $stmt->fetchAll();
}

$selectedYear = '';
foreach ($seasons as $s) {
    if ($s['id'] == $selectedSeasonId) { $selectedYear = $s['year']; break; }
}
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Championship Standings</h1>
        <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Formula 1 World Championship' : '' ?></p>
    </div>
    <form class="season-selector" method="get">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons as $s): ?>
            <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>>
                <?= h((string)$s['year']) ?><?= $s['is_active'] ? ' ★' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title">&#128100; Driver Championship</div>
        <?php if (empty($driverStandings)): ?>
        <div class="empty-state"><p>No standings data yet.</p></div>
        <?php else: ?>
        <div class="table-container" style="border:0;margin-bottom:0">
            <table class="sortable" id="driver-standings-table">
                <thead><tr>
                    <th>Pos</th><th>Driver</th><th>Team</th><th>Pts</th><th>Wins</th><th>Pods</th><th>FL</th>
                </tr></thead>
                <tbody>
                <?php foreach ($driverStandings as $d): ?>
                <tr>
                    <td><span class="position-badge pos-<?= $d['position'] <= 3 ? $d['position'] : 'other' ?>"><?= h((string)$d['position']) ?></span></td>
                    <td>
                        <a href="<?= APP_URL ?>/shared/standings.php?view=driver&id=<?= (int)$d['person_id'] ?>" style="font-weight:600"><?= h($d['first_name'] . ' ' . $d['last_name']) ?></a>
                        <div style="font-size:0.75rem;color:var(--text-muted)">#<?= h((string)$d['racing_number']) ?> &bull; <?= h($d['nationality']) ?></div>
                    </td>
                    <td class="text-muted"><?= h($d['short_name']) ?></td>
                    <td><strong class="text-accent"><?= h((string)$d['points']) ?></strong></td>
                    <td><?= h((string)$d['wins']) ?></td>
                    <td><?= h((string)$d['podiums']) ?></td>
                    <td><?= h((string)$d['fastest_laps']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:0.75rem">
            <button class="btn btn-outline btn-sm" data-export-csv="driver-standings-table" data-filename="driver_standings_<?= h((string)$selectedYear) ?>.csv">Export CSV</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">&#127937; Constructor Championship</div>
        <?php if (empty($constructorStandings)): ?>
        <div class="empty-state"><p>No standings data yet.</p></div>
        <?php else: ?>
        <div class="table-container" style="border:0;margin-bottom:0">
            <table class="sortable" id="constructor-standings-table">
                <thead><tr>
                    <th>Pos</th><th>Constructor</th><th>Pts</th><th>Wins</th>
                </tr></thead>
                <tbody>
                <?php foreach ($constructorStandings as $c): ?>
                <tr>
                    <td><span class="position-badge pos-<?= $c['position'] <= 3 ? $c['position'] : 'other' ?>"><?= h((string)$c['position']) ?></span></td>
                    <td>
                        <a href="<?= APP_URL ?>/shared/standings.php?view=team&id=<?= (int)$c['team_id'] ?>" style="font-weight:600"><?= h($c['name']) ?></a>
                        <div style="font-size:0.75rem;color:var(--text-muted)"><?= h($c['nationality']) ?></div>
                    </td>
                    <td><strong class="text-accent"><?= h((string)$c['points']) ?></strong></td>
                    <td><?= h((string)$c['wins']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:0.75rem">
            <button class="btn btn-outline btn-sm" data-export-csv="constructor-standings-table" data-filename="constructor_standings_<?= h((string)$selectedYear) ?>.csv">Export CSV</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

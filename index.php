<?php
$pageTitle = 'F1 Management System — Home';
$publicPage = true;
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$activeSeason = getActiveSeason();
$seasonId = $activeSeason['id'] ?? null;

// Championship leaders
$driverLeader = null;
$constructorLeader = null;
$latestRace = null;
$nextRace = null;
$topDrivers = [];
$topConstructors = [];

if ($seasonId) {
    // Driver leader
    $stmt = $db->prepare("
        SELECT ds.points, ds.wins, ds.position,
               p.first_name, p.last_name, p.racing_number,
               t.name AS team_name, t.short_name
        FROM driver_standings ds
        JOIN people p ON p.id = ds.person_id
        JOIN driver_seasons drs ON drs.person_id = p.id AND drs.season_id = ?
        JOIN team_seasons ts ON ts.id = drs.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE ds.season_id = ?
        ORDER BY ds.position ASC LIMIT 1
    ");
    $stmt->execute([$seasonId, $seasonId]);
    $driverLeader = $stmt->fetch();

    // Constructor leader
    $stmt = $db->prepare("
        SELECT cs.points, cs.wins, cs.position, t.name, t.short_name
        FROM constructor_standings cs
        JOIN teams t ON t.id = cs.team_id
        WHERE cs.season_id = ?
        ORDER BY cs.position ASC LIMIT 1
    ");
    $stmt->execute([$seasonId]);
    $constructorLeader = $stmt->fetch();

    // Latest completed race
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date,
               c.name AS circuit, c.country
        FROM races r JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ? AND r.status = 'completed'
        ORDER BY r.race_date DESC LIMIT 1
    ");
    $stmt->execute([$seasonId]);
    $latestRace = $stmt->fetch();

    // Next upcoming race
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date,
               c.name AS circuit, c.country, c.city
        FROM races r JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ? AND r.status = 'scheduled'
        ORDER BY r.race_date ASC LIMIT 1
    ");
    $stmt->execute([$seasonId]);
    $nextRace = $stmt->fetch();

    // Top 5 drivers
    $stmt = $db->prepare("
        SELECT ds.position, ds.points, ds.wins,
               p.first_name, p.last_name, p.racing_number,
               t.name AS team_name, t.short_name
        FROM driver_standings ds
        JOIN people p ON p.id = ds.person_id
        JOIN driver_seasons drs ON drs.person_id = p.id AND drs.season_id = ?
        JOIN team_seasons ts ON ts.id = drs.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE ds.season_id = ?
        ORDER BY ds.position ASC LIMIT 5
    ");
    $stmt->execute([$seasonId, $seasonId]);
    $topDrivers = $stmt->fetchAll();

    // Top 5 constructors
    $stmt = $db->prepare("
        SELECT cs.position, cs.points, cs.wins, t.name, t.short_name
        FROM constructor_standings cs
        JOIN teams t ON t.id = cs.team_id
        WHERE cs.season_id = ?
        ORDER BY cs.position ASC LIMIT 5
    ");
    $stmt->execute([$seasonId]);
    $topConstructors = $stmt->fetchAll();

    // Latest race top 3
    $raceTop3 = [];
    if ($latestRace) {
        $stmt = $db->prepare("
            SELECT rr.finish_position, rr.points_scored, rr.fastest_lap_bonus,
                   p.first_name, p.last_name, t.short_name
            FROM race_results rr
            JOIN race_entries re ON re.id = rr.race_entry_id
            JOIN people p ON p.id = re.person_id
            JOIN team_seasons ts ON ts.id = re.team_season_id
            JOIN teams t ON t.id = ts.team_id
            WHERE re.race_id = ? AND rr.finish_position IS NOT NULL
            ORDER BY rr.finish_position ASC LIMIT 3
        ");
        $stmt->execute([$latestRace['id']]);
        $raceTop3 = $stmt->fetchAll();
    }
}
?>
<section class="hero">
    <div class="container" style="padding-top:0;padding-bottom:0">
        <h1 class="hero-title"><span class="accent">F1</span> Management System</h1>
        <p class="hero-subtitle">
            <?= $activeSeason ? h((string)$activeSeason['year']) . ' Formula 1 World Championship' : 'Formula 1 Race Management Portal' ?>
        </p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin-top:1.5rem">
            <a href="<?= APP_URL ?>/public/standings.php" class="btn btn-primary">View Standings</a>
            <a href="<?= APP_URL ?>/public/results.php" class="btn btn-outline">Race Results</a>
        </div>
    </div>
</section>

<div class="container">
    <?php if ($driverLeader || $constructorLeader): ?>
    <div class="grid-2 mt-2">
        <?php if ($driverLeader): ?>
        <div class="leader-card">
            <div class="leader-label">&#127942; Driver Championship Leader</div>
            <div class="leader-name"><?= h($driverLeader['first_name'] . ' ' . $driverLeader['last_name']) ?></div>
            <div class="leader-sub"><?= h($driverLeader['team_name']) ?> &bull; #<?= h((string)$driverLeader['racing_number']) ?></div>
            <div class="leader-points"><?= h((string)$driverLeader['points']) ?> <small style="font-size:1rem;color:var(--text-secondary)">pts</small></div>
            <div class="leader-sub"><?= h((string)$driverLeader['wins']) ?> win<?= $driverLeader['wins'] != 1 ? 's' : '' ?></div>
        </div>
        <?php endif; ?>
        <?php if ($constructorLeader): ?>
        <div class="leader-card">
            <div class="leader-label">&#127937; Constructor Championship Leader</div>
            <div class="leader-name"><?= h($constructorLeader['name']) ?></div>
            <div class="leader-sub"><?= h($constructorLeader['short_name']) ?></div>
            <div class="leader-points"><?= h((string)$constructorLeader['points']) ?> <small style="font-size:1rem;color:var(--text-secondary)">pts</small></div>
            <div class="leader-sub"><?= h((string)$constructorLeader['wins']) ?> win<?= $constructorLeader['wins'] != 1 ? 's' : '' ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="grid-2">
        <?php if ($latestRace): ?>
        <div class="card">
            <div class="card-title">&#9989; Latest Race &mdash; <?= h($latestRace['name']) ?></div>
            <p class="text-muted mb-1" style="font-size:0.85rem">Round <?= h((string)$latestRace['round_number']) ?> &bull; <?= h($latestRace['circuit']) ?>, <?= h($latestRace['country']) ?> &bull; <?= h(date('d M Y', strtotime($latestRace['race_date']))) ?></p>
            <?php if (!empty($raceTop3)): ?>
            <table style="margin-top:0.75rem;width:100%">
                <?php foreach ($raceTop3 as $r): ?>
                <tr>
                    <td style="padding:0.3rem 0.5rem 0.3rem 0">
                        <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                    </td>
                    <td style="padding:0.3rem 0"><strong><?= h($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                    <td style="padding:0.3rem 0;color:var(--text-secondary);font-size:0.85rem"><?= h($r['short_name']) ?></td>
                    <td style="padding:0.3rem 0;text-align:right;color:var(--accent);font-weight:600"><?= h((string)$r['points_scored']) ?> pts</td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/public/race_detail.php?id=<?= $latestRace['id'] ?>" class="btn btn-outline btn-sm" style="margin-top:0.75rem">Full Results &rarr;</a>
        </div>
        <?php endif; ?>

        <?php if ($nextRace): ?>
        <div class="card">
            <div class="card-title">&#128197; Next Race &mdash; <?= h($nextRace['name']) ?></div>
            <p class="text-muted" style="font-size:0.85rem;margin-bottom:1rem">Round <?= h((string)$nextRace['round_number']) ?></p>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Circuit</div>
                    <div class="info-value"><?= h($nextRace['circuit']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Location</div>
                    <div class="info-value"><?= h($nextRace['city'] . ', ' . $nextRace['country']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Race Date</div>
                    <div class="info-value"><?= h(date('d M Y', strtotime($nextRace['race_date']))) ?></div>
                </div>
            </div>
            <a href="<?= APP_URL ?>/public/circuits.php" class="btn btn-outline btn-sm" style="margin-top:1rem">View Circuit &rarr;</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($topDrivers || $topConstructors): ?>
    <div class="grid-2">
        <?php if ($topDrivers): ?>
        <div class="card">
            <div class="card-title">Driver Standings — Top 5 <a href="<?= APP_URL ?>/public/standings.php" class="btn btn-outline btn-sm">Full Table</a></div>
            <table class="sortable">
                <thead><tr>
                    <th>Pos</th><th>Driver</th><th>Team</th><th>Pts</th>
                </tr></thead>
                <tbody>
                <?php foreach ($topDrivers as $d): ?>
                <tr>
                    <td><span class="position-badge pos-<?= $d['position'] <= 3 ? $d['position'] : 'other' ?>"><?= h((string)$d['position']) ?></span></td>
                    <td><strong><?= h($d['first_name'] . ' ' . $d['last_name']) ?></strong></td>
                    <td class="text-muted"><?= h($d['short_name']) ?></td>
                    <td><strong class="text-accent"><?= h((string)$d['points']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($topConstructors): ?>
        <div class="card">
            <div class="card-title">Constructor Standings — Top 5 <a href="<?= APP_URL ?>/public/standings.php" class="btn btn-outline btn-sm">Full Table</a></div>
            <table class="sortable">
                <thead><tr>
                    <th>Pos</th><th>Constructor</th><th>Pts</th>
                </tr></thead>
                <tbody>
                <?php foreach ($topConstructors as $c): ?>
                <tr>
                    <td><span class="position-badge pos-<?= $c['position'] <= 3 ? $c['position'] : 'other' ?>"><?= h((string)$c['position']) ?></span></td>
                    <td><strong><?= h($c['name']) ?></strong></td>
                    <td><strong class="text-accent"><?= h((string)$c['points']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<footer style="background:var(--bg-surface);border-top:1px solid var(--border);text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.8rem;margin-top:2rem">
    &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> &mdash; University Project
</footer>

</main>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>

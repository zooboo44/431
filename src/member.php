<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

$db = db_connect();
$role = current_role_internal_name();
$is_director = is_league_director();

function count_rows($db, $table) {
    $allowed_tables = ['accounts', 'teams', 'drivers', 'circuits', 'races', 'driver_statistics'];
    if (!in_array($table, $allowed_tables, true)) {
        return 0;
    }

    $result = $db->query("SELECT COUNT(*) AS total FROM " . $table);
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int) ($row['total'] ?? 0);
}

$counts = [
    'users' => count_rows($db, 'accounts'),
    'teams' => count_rows($db, 'teams'),
    'drivers' => count_rows($db, 'drivers'),
    'circuits' => count_rows($db, 'circuits'),
    'races' => count_rows($db, 'races'),
    'statistics' => count_rows($db, 'driver_statistics')
];

$recent_users = null;
if ($is_director) {
    $recent_users = $db->query("SELECT accounts.email, accounts.username, roles.display_name AS role_name
                                FROM accounts
                                JOIN roles ON roles.id = accounts.role_id
                                ORDER BY accounts.created_at DESC, accounts.id DESC
                                LIMIT 5");
}

$teams = $db->query("SELECT team_name, base_location FROM teams ORDER BY team_name LIMIT 8");
$recent_drivers = $db->query("SELECT drivers.first_name, drivers.last_name, teams.team_name
                              FROM drivers
                              JOIN teams ON teams.id = drivers.team_id
                              ORDER BY drivers.created_at DESC, drivers.id DESC
                              LIMIT 5");
$recent_races = $db->query("SELECT races.race_name, races.race_date, circuits.circuit_name
                            FROM races
                            JOIN circuits ON circuits.id = races.circuit_id
                            ORDER BY races.race_date DESC, races.id DESC
                            LIMIT 5");
$recent_statistics = $db->query("SELECT drivers.first_name, drivers.last_name, races.race_name,
                                        driver_statistics.finish_position, driver_statistics.points
                                 FROM driver_statistics
                                 JOIN drivers ON drivers.id = driver_statistics.driver_id
                                 JOIN races ON races.id = driver_statistics.race_id
                                 ORDER BY driver_statistics.created_at DESC, driver_statistics.id DESC
                                 LIMIT 5");
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Dashboard - F1 Statistics</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 24px; line-height: 1.4; }
            nav a { margin-right: 12px; }
            table { border-collapse: collapse; width: 100%; margin: 12px 0 24px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .counts { display: flex; flex-wrap: wrap; gap: 10px; margin: 16px 0; }
            .count-box { border: 1px solid #ddd; padding: 10px; min-width: 130px; }
            .count-box strong { display: block; font-size: 20px; }
        </style>
    </head>
    <body>
        <h1>F1 Statistics Dashboard</h1>

        <p>
            Logged in as <?php echo encode_var($_SESSION['username'] ?? ''); ?>
            / <?php echo encode_var($_SESSION['email'] ?? ''); ?>
            (<?php echo encode_var($_SESSION['role_display_name'] ?? ''); ?>)
        </p>

        <nav>
            <a href="teams.php"><?php echo $role === 'league_director' ? 'Manage Teams' : 'Teams'; ?></a>
            <a href="drivers.php"><?php echo $role === 'league_director' || $role === 'team_manager' ? 'Manage Drivers' : 'Drivers'; ?></a>
            <a href="statistic.php"><?php echo $role === 'league_director' || $role === 'team_manager' ? 'Manage Statistics' : 'Statistics'; ?></a>
            <a href="circuits.php"><?php echo $role === 'league_director' ? 'Manage Circuits' : 'Circuits'; ?></a>
            <a href="races.php"><?php echo $role === 'league_director' ? 'Manage Races' : 'Races'; ?></a>
            <?php if ($is_director): ?>
                <a href="users.php">Manage Users</a>
            <?php endif; ?>
            <a href="change_password_form.php">Change Password</a>
            <a href="logout.php">Logout</a>
        </nav>

        <h2>Overview</h2>
        <div class="counts">
            <div class="count-box"><strong><?php echo encode_var($counts['users']); ?></strong>Users</div>
            <div class="count-box"><strong><?php echo encode_var($counts['teams']); ?></strong>Teams</div>
            <div class="count-box"><strong><?php echo encode_var($counts['drivers']); ?></strong>Drivers</div>
            <div class="count-box"><strong><?php echo encode_var($counts['circuits']); ?></strong>Circuits</div>
            <div class="count-box"><strong><?php echo encode_var($counts['races']); ?></strong>Races</div>
            <div class="count-box"><strong><?php echo encode_var($counts['statistics']); ?></strong>Statistics Records</div>
        </div>

        <?php if ($is_director): ?>
            <h2>Recent Users</h2>
            <table>
                <tr><th>Username</th><th>Email</th><th>Role</th></tr>
                <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                    <?php while ($user = $recent_users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo encode_var($user['username']); ?></td>
                            <td><?php echo encode_var($user['email']); ?></td>
                            <td><?php echo encode_var($user['role_name']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3">No users found.</td></tr>
                <?php endif; ?>
            </table>
        <?php endif; ?>

        <h2>Teams</h2>
        <table>
            <tr><th>Team</th><th>Base Location</th></tr>
            <?php if ($teams && $teams->num_rows > 0): ?>
                <?php while ($team = $teams->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo encode_var($team['team_name']); ?></td>
                        <td><?php echo encode_var($team['base_location']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="2">No teams found.</td></tr>
            <?php endif; ?>
        </table>

        <h2>Recent Drivers</h2>
        <table>
            <tr><th>Driver</th><th>Team</th></tr>
            <?php if ($recent_drivers && $recent_drivers->num_rows > 0): ?>
                <?php while ($driver = $recent_drivers->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo encode_var($driver['first_name'] . ' ' . $driver['last_name']); ?></td>
                        <td><?php echo encode_var($driver['team_name']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="2">No drivers found.</td></tr>
            <?php endif; ?>
        </table>

        <h2>Recent Races</h2>
        <table>
            <tr><th>Race</th><th>Date</th><th>Circuit</th></tr>
            <?php if ($recent_races && $recent_races->num_rows > 0): ?>
                <?php while ($race = $recent_races->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo encode_var($race['race_name']); ?></td>
                        <td><?php echo encode_var($race['race_date']); ?></td>
                        <td><?php echo encode_var($race['circuit_name']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3">No races found.</td></tr>
            <?php endif; ?>
        </table>

        <h2>Recent Statistics</h2>
        <table>
            <tr><th>Driver</th><th>Race</th><th>Finish</th><th>Points</th></tr>
            <?php if ($recent_statistics && $recent_statistics->num_rows > 0): ?>
                <?php while ($stat = $recent_statistics->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo encode_var($stat['first_name'] . ' ' . $stat['last_name']); ?></td>
                        <td><?php echo encode_var($stat['race_name']); ?></td>
                        <td><?php echo encode_var($stat['finish_position'] ?? ''); ?></td>
                        <td><?php echo encode_var($stat['points']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No statistics found.</td></tr>
            <?php endif; ?>
        </table>
    </body>
</html>
<?php
$db->close();
?>

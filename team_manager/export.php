<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();
requireRole('team_manager');
checkSessionTimeout();
checkDisplacedSession();

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);
$type   = $_GET['type'] ?? '';
$seasonId = intval($_GET['season'] ?? 0);

if (!$teamId) { http_response_code(403); exit('Forbidden'); }

// Stream CSV directly — no disk writes
function csvRow(array $fields): string {
    $escaped = array_map(function($v) {
        $v = str_replace('"', '""', (string)$v);
        return '"' . $v . '"';
    }, $fields);
    return implode(',', $escaped) . "\r\n";
}

switch ($type) {

    case 'results':
        if (!$seasonId) { $a = getActiveSeason(); $seasonId = $a['id'] ?? 0; }

        $stmt = $db->prepare("
            SELECT r.round_number, r.name AS race_name, r.race_date, s.year,
                   p.racing_number, p.first_name, p.last_name,
                   qr.grid_position,
                   rr.start_position, rr.finish_position, rr.status,
                   rr.laps_completed, rr.points_scored, rr.fastest_lap_bonus
            FROM race_results rr
            JOIN race_entries re ON re.id = rr.race_entry_id
            JOIN team_seasons ts ON ts.id = re.team_season_id AND ts.team_id = ?
            JOIN races r ON r.id = re.race_id AND r.season_id = ?
            JOIN seasons s ON s.id = r.season_id
            JOIN people p ON p.id = re.person_id
            LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
            ORDER BY r.round_number ASC, rr.finish_position ASC
        ");
        $stmt->execute([$teamId, $seasonId]);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="results_' . intval($seasonId) . '.csv"');
        header('Cache-Control: no-cache');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        echo csvRow(['Round','Race','Date','Year','Number','First Name','Last Name',
                     'Grid','Start','Finish','Status','Laps','Points','Fastest Lap']);
        foreach ($rows as $row) {
            echo csvRow([
                $row['round_number'], $row['race_name'], $row['race_date'], $row['year'],
                $row['racing_number'], $row['first_name'], $row['last_name'],
                $row['grid_position'] ?? '', $row['start_position'], $row['finish_position'] ?? '',
                $row['status'], $row['laps_completed'], $row['points_scored'],
                $row['fastest_lap_bonus'] ? 'Yes' : 'No'
            ]);
        }
        exit;

    case 'drivers':
        $stmt = $db->prepare("
            SELECT DISTINCT p.racing_number, p.first_name, p.last_name, p.nationality,
                            p.date_of_birth,
                            COUNT(DISTINCT drs.season_id) AS seasons_count,
                            COALESCE(SUM(rr.points_scored), 0) AS career_points,
                            SUM(CASE WHEN rr.finish_position = 1 THEN 1 ELSE 0 END) AS career_wins,
                            SUM(CASE WHEN rr.finish_position <= 3 THEN 1 ELSE 0 END) AS career_podiums
            FROM people p
            JOIN driver_seasons drs ON drs.person_id = p.id
            JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
            LEFT JOIN race_entries re ON re.person_id = p.id
            LEFT JOIN race_results rr ON rr.race_entry_id = re.id
            GROUP BY p.id
            ORDER BY career_points DESC
        ");
        $stmt->execute([$teamId]);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="drivers.csv"');
        header('Cache-Control: no-cache');

        echo "\xEF\xBB\xBF";
        echo csvRow(['Number','First Name','Last Name','Nationality','Date of Birth',
                     'Seasons','Career Points','Career Wins','Career Podiums']);
        foreach ($rows as $row) {
            echo csvRow([
                $row['racing_number'], $row['first_name'], $row['last_name'],
                $row['nationality'], $row['date_of_birth'],
                $row['seasons_count'], $row['career_points'],
                $row['career_wins'], $row['career_podiums']
            ]);
        }
        exit;

    case 'standings':
        if (!$seasonId) { $a = getActiveSeason(); $seasonId = $a['id'] ?? 0; }

        $stmt = $db->prepare("
            SELECT ds.position, p.racing_number, p.first_name, p.last_name,
                   ds.points, ds.wins, ds.podiums, ds.fastest_laps, ds.dnfs
            FROM driver_standings ds
            JOIN people p ON p.id = ds.person_id
            JOIN driver_seasons drs ON drs.person_id = ds.person_id AND drs.season_id = ds.season_id
            JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
            WHERE ds.season_id = ?
            ORDER BY ds.position ASC
        ");
        $stmt->execute([$teamId, $seasonId]);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="standings_' . intval($seasonId) . '.csv"');
        header('Cache-Control: no-cache');

        echo "\xEF\xBB\xBF";
        echo csvRow(['Position','Number','First Name','Last Name','Points','Wins','Podiums','Fastest Laps','DNFs']);
        foreach ($rows as $row) {
            echo csvRow([
                $row['position'], $row['racing_number'], $row['first_name'], $row['last_name'],
                $row['points'], $row['wins'], $row['podiums'], $row['fastest_laps'], $row['dnfs']
            ]);
        }
        exit;

    default:
        // No type specified — redirect back to results page
        header('Location: ' . APP_URL . '/team_manager/results.php');
        exit;
}

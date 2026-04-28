<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

function formatLapTime(int $ms): string {
    $totalSeconds = intdiv($ms, 1000);
    $milliseconds = $ms % 1000;
    $minutes      = intdiv($totalSeconds, 60);
    $seconds      = $totalSeconds % 60;
    return sprintf('%d:%02d.%03d', $minutes, $seconds, $milliseconds);
}

function formatDuration(int $ms): string {
    $totalSeconds = intdiv($ms, 1000);
    $milliseconds = $ms % 1000;
    $hours        = intdiv($totalSeconds, 3600);
    $remainder    = $totalSeconds % 3600;
    $minutes      = intdiv($remainder, 60);
    $seconds      = $remainder % 60;
    return sprintf('%d:%02d:%02d.%03d', $hours, $minutes, $seconds, $milliseconds);
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function calculateRacePoints(int $position, bool $fastestLap, bool $inTopTen): float {
    $points = RACE_POINTS[$position] ?? 0;
    if ($fastestLap && $inTopTen) {
        $points += FASTEST_LAP_BONUS;
    }
    return (float)$points;
}

function calculateSprintPoints(int $position): float {
    return (float)(SPRINT_POINTS[$position] ?? 0);
}

function recalculateStandings(int $seasonId): void {
    $db = getDB();

    // Driver standings
    $stmt = $db->prepare("
        SELECT
            p.id AS person_id,
            COALESCE(SUM(rr.points_scored), 0) AS points,
            SUM(CASE WHEN rr.finish_position = 1 THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN rr.finish_position <= 3 AND rr.finish_position IS NOT NULL THEN 1 ELSE 0 END) AS podiums,
            SUM(CASE WHEN rr.status = 'DNF' THEN 1 ELSE 0 END) AS dnfs,
            SUM(CASE WHEN rr.fastest_lap_bonus = 1 THEN 1 ELSE 0 END) AS fastest_laps
        FROM people p
        JOIN driver_seasons ds ON ds.person_id = p.id AND ds.season_id = ?
        JOIN race_entries re ON re.person_id = p.id
        JOIN races r ON r.id = re.race_id AND r.season_id = ?
        LEFT JOIN race_results rr ON rr.race_entry_id = re.id
        GROUP BY p.id
    ");
    $stmt->execute([$seasonId, $seasonId]);
    $driverRows = $stmt->fetchAll();

    usort($driverRows, fn($a, $b) =>
        $b['points'] <=> $a['points'] ?: $b['wins'] <=> $a['wins']
    );

    foreach ($driverRows as $pos => $row) {
        $position = $pos + 1;
        $db->prepare("
            INSERT INTO driver_standings (season_id, person_id, points, wins, podiums, dnfs, fastest_laps, position)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                points=VALUES(points), wins=VALUES(wins), podiums=VALUES(podiums),
                dnfs=VALUES(dnfs), fastest_laps=VALUES(fastest_laps), position=VALUES(position)
        ")->execute([
            $seasonId, $row['person_id'], $row['points'], $row['wins'],
            $row['podiums'], $row['dnfs'], $row['fastest_laps'], $position
        ]);
    }

    // Constructor standings
    $stmt = $db->prepare("
        SELECT
            t.id AS team_id,
            COALESCE(SUM(rr.points_scored), 0) AS points,
            SUM(CASE WHEN rr.finish_position = 1 THEN 1 ELSE 0 END) AS wins
        FROM teams t
        JOIN team_seasons ts ON ts.team_id = t.id AND ts.season_id = ?
        JOIN race_entries re ON re.team_season_id = ts.id
        JOIN races r ON r.id = re.race_id AND r.season_id = ?
        LEFT JOIN race_results rr ON rr.race_entry_id = re.id
        GROUP BY t.id
    ");
    $stmt->execute([$seasonId, $seasonId]);
    $constructorRows = $stmt->fetchAll();

    usort($constructorRows, fn($a, $b) =>
        $b['points'] <=> $a['points'] ?: $b['wins'] <=> $a['wins']
    );

    foreach ($constructorRows as $pos => $row) {
        $position = $pos + 1;
        $db->prepare("
            INSERT INTO constructor_standings (season_id, team_id, points, wins, position)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE points=VALUES(points), wins=VALUES(wins), position=VALUES(position)
        ")->execute([
            $seasonId, $row['team_id'], $row['points'], $row['wins'], $position
        ]);
    }

    // Update team_seasons total_points
    $db->prepare("
        UPDATE team_seasons ts
        JOIN constructor_standings cs ON cs.team_id = ts.team_id AND cs.season_id = ts.season_id
        SET ts.total_points = cs.points
        WHERE ts.season_id = ?
    ")->execute([$seasonId]);
}

function logAudit(
    int|null $userId,
    string $action,
    ?string $resource = null,
    ?int $resourceId = null,
    ?string $details = null
): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db->prepare("
            INSERT INTO audit_log (user_id, action, resource, resource_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$userId, $action, $resource, $resourceId, $details, $ip]);
    } catch (Exception $e) {
        // Never let audit logging crash the application
    }
}

function checkRateLimit(string $ipAddress, string $action, int $maxAttempts, int $windowMinutes): bool {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) as cnt FROM audit_log
        WHERE ip_address = ? AND action = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$ipAddress, $action, $windowMinutes]);
    $row = $stmt->fetch();
    return (int)($row['cnt'] ?? 0) < $maxAttempts;
}

function getActiveSeason(): ?array {
    static $season = false;
    if ($season === false) {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM seasons WHERE is_active = 1 LIMIT 1');
        $stmt->execute();
        $season = $stmt->fetch() ?: null;
    }
    return $season;
}

function getSeasonList(): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, year, is_active FROM seasons ORDER BY year DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function redirectWithMessage(string $url, string $type, string $message): void {
    $_SESSION['flash_type']    = $type;
    $_SESSION['flash_message'] = $message;
    header('Location: ' . $url);
    exit;
}

function getFlashMessage(): ?array {
    if (!isset($_SESSION['flash_type'])) return null;
    $flash = [
        'type'    => $_SESSION['flash_type'],
        'message' => $_SESSION['flash_message'],
    ];
    unset($_SESSION['flash_type'], $_SESSION['flash_message']);
    return $flash;
}

function renderFlash(): void {
    $flash = getFlashMessage();
    if (!$flash) return;
    $type    = h($flash['type']);
    $message = h($flash['message']);
    echo "<div class=\"alert alert-{$type}\" id=\"flash-msg\">{$message}</div>";
}

function validateDOB(string $dob): ?string {
    if (!$dob || !strtotime($dob)) return 'Valid date of birth is required.';
    $dobDate = new DateTime($dob);
    $today   = new DateTime();
    if ($dobDate > $today) return 'Date of birth cannot be in the future.';
    $age = (int)$today->diff($dobDate)->y;
    if ($age < 18) return 'Driver must be at least 18 years old to compete in F1.';
    if ($age > 60) return 'Date of birth appears incorrect (driver would be over 60 years old).';
    return null;
}

function countRelated(PDO $db, string $table, string $col, int $id): int {
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$col} = ?");
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn();
}

function validatePassword(string $password): ?string {
    if (strlen($password) < 8)                          return 'Password must be at least 8 characters.';
    if (strlen($password) > 25)                         return 'Password must be no more than 25 characters.';
    if (!preg_match('/[A-Z]/', $password))              return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password))              return 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password))              return 'Password must contain at least one number.';
    if (!preg_match('/[!@#$%^&*]/', $password))         return 'Password must contain at least one special character (!@#$%^&*).';
    return null;
}

function passwordHint(): string {
    return '8–25 characters, with uppercase, lowercase, number, and one of: !@#$%^&*';
}

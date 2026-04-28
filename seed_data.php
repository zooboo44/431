<?php
/**
 * Comprehensive seed script for F1 Racing Management System
 * Safe to run multiple times (uses INSERT IGNORE / ON DUPLICATE KEY UPDATE)
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== F1 App Seed Script ===\n\n";

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function ins(PDO $db, string $sql, array $params = []): int {
    $db->prepare($sql)->execute($params);
    return (int)$db->lastInsertId();
}

$F1_PTS  = [1=>25,2=>18,3=>15,4=>12,5=>10,6=>8,7=>6,8=>4,9=>2,10=>1];
$SPR_PTS = [1=>8,2=>7,3=>6,4=>5,5=>4,6=>3,7=>2,8=>1];

// createUser is needed before Step 14 (penalties need issued_by FK)
function createUser(PDO $db, string $name, string $email, string $password, string $role, int $linkedId, int $createdBy = 0): void {
    global $adminId;
    $by = $createdBy ?: $adminId;
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $existing = $db->prepare("SELECT id FROM users WHERE email=?");
    $existing->execute([$email]);
    $row = $existing->fetch();
    if ($row) {
        $db->prepare("UPDATE users SET name=?, password_hash=?, role=?, linked_id=? WHERE id=?")
           ->execute([$name, $hash, $role, $linkedId, $row['id']]);
        return;
    }
    $db->prepare("INSERT INTO users (name, email, password_hash, role, linked_id, is_active, must_change_password, created_by) VALUES (?,?,?,?,?,1,0,?)")
       ->execute([$name, $email, $hash, $role, $linkedId, $by]);
}

// Admin must be created first so issued_by FK (penalties etc.) resolves on clean DB
$adminId = 0;
$_aHash = password_hash('Admin123', PASSWORD_BCRYPT, ['cost' => 12]);
$_aExist = $db->prepare("SELECT id FROM users WHERE email='admin@f1app.com'");
$_aExist->execute();
if ($_aExist->fetch()) {
    $db->prepare("UPDATE users SET name='Admin', password_hash=?, role='admin', linked_id=NULL WHERE email='admin@f1app.com'")->execute([$_aHash]);
} else {
    $db->prepare("INSERT INTO users (name,email,password_hash,role,linked_id,is_active,must_change_password,created_by) VALUES ('Admin','admin@f1app.com',?,'admin',NULL,1,0,NULL)")->execute([$_aHash]);
}
$adminId = (int)$db->query("SELECT id FROM users WHERE email='admin@f1app.com'")->fetchColumn();
echo "Admin user ensured (id=$adminId)\n";

// ─── STEP 0: SCHEMA PATCHES — add columns/indexes missing from base DDL ──────

// people.requested_by_team_id: tracks pending driver requests from team managers
$colCheck = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='people' AND column_name='requested_by_team_id'");
$colCheck->execute();
if (!(int)$colCheck->fetchColumn()) {
    $db->exec("ALTER TABLE people ADD COLUMN requested_by_team_id INT NULL DEFAULT NULL AFTER is_active, ADD KEY fk_ppl_req_team (requested_by_team_id), ADD CONSTRAINT fk_people_requested_team FOREIGN KEY (requested_by_team_id) REFERENCES teams(id) ON DELETE SET NULL");
}
// pit_stops unique key: prevents duplicate stop numbers per entry on re-run
$keyCheck = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='pit_stops' AND index_name='unique_entry_stop'");
$keyCheck->execute();
if (!(int)$keyCheck->fetchColumn()) {
    $db->exec("ALTER TABLE pit_stops ADD UNIQUE KEY unique_entry_stop (race_entry_id, stop_number)");
}
echo "Schema patches applied\n";

// ─── STEP 0: FOUNDATION — TEAMS + CIRCUITS ───────────────────────────────────

$db->exec("INSERT IGNORE INTO teams (id,name,short_name,nationality,founded_year,is_active) VALUES
    (1,'Red Bull Racing','RBR','Austrian',2005,1),
    (2,'Mercedes','MER','German',1954,1),
    (3,'Ferrari','FER','Italian',1950,1),
    (4,'McLaren','MCL','British',1966,1),
    (5,'Aston Martin','AMR','British',2021,1),
    (6,'Alpine','ALP','French',2021,1),
    (7,'Williams','WIL','British',1977,1),
    (8,'RB Formula One Team','RB','Italian',2024,1),
    (9,'Kick Sauber','SAU','Swiss',1993,1),
    (10,'Haas F1 Team','HAA','American',2016,1)");
echo "Teams ensured\n";

$db->exec("INSERT IGNORE INTO circuits (id,name,country,city,length_km,number_of_laps,circuit_type,lap_record_ms,lap_record_person_id,is_active) VALUES
    (1,'Bahrain International Circuit','Bahrain','Sakhir',5.412,57,'permanent',91447,NULL,1),
    (2,'Jeddah Corniche Circuit','Saudi Arabia','Jeddah',6.174,50,'street',90734,NULL,1),
    (3,'Albert Park Circuit','Australia','Melbourne',5.278,58,'permanent',79820,NULL,1),
    (4,'Suzuka International Racing','Japan','Suzuka',5.807,53,'permanent',90983,NULL,1),
    (5,'Shanghai International Circuit','China','Shanghai',5.451,56,'permanent',93558,NULL,1),
    (6,'Miami International Autodrome','USA','Miami',5.412,57,'street',90589,NULL,1),
    (7,'Autodromo Enzo e Dino Ferrari','Italy','Imola',4.909,63,'permanent',95701,NULL,1),
    (8,'Circuit de Monaco','Monaco','Monte Carlo',3.337,78,'street',74260,NULL,1),
    (9,'Circuit Gilles Villeneuve','Canada','Montreal',4.361,70,'permanent',73078,NULL,1),
    (10,'Circuit de Barcelona-Catalunya','Spain','Barcelona',4.675,66,'permanent',79981,NULL,1)");
echo "Circuits ensured\n";

// roles_permissions (static reference data — role, resource, action)
$rp = [];
$adminResources = ['users','teams','people','seasons','races','circuits','race_entries',
    'race_results','qualifying_results','sprint_results','pit_stops','lap_telemetry',
    'penalties','driver_standings','constructor_standings'];
foreach ($adminResources as $res) {
    foreach (['create','read','update','delete'] as $act) {
        $rp[] = "('admin','$res','$act')";
    }
}
$rdResources = ['races','race_entries','race_results','qualifying_results',
    'sprint_results','penalties','driver_standings','constructor_standings'];
foreach ($rdResources as $res) {
    foreach (['create','read','update','delete'] as $act) {
        $rp[] = "('race_director','$res','$act')";
    }
}
foreach (['teams','people','seasons','circuits','pit_stops','lap_telemetry'] as $res) {
    $rp[] = "('race_director','$res','read')";
}
foreach (['race_results','qualifying_results','sprint_results','pit_stops',
    'lap_telemetry','driver_standings','constructor_standings','penalties'] as $res) {
    $rp[] = "('team_manager','$res','read')";
}
foreach (['teams','people','seasons','circuits','race_entries'] as $res) {
    $rp[] = "('team_manager','$res','read')";
}
foreach (['people','race_entries'] as $res) {
    foreach (['create','update'] as $act) { $rp[] = "('team_manager','$res','$act')"; }
}
foreach (['race_results','qualifying_results','sprint_results','pit_stops',
    'lap_telemetry','driver_standings','constructor_standings'] as $res) {
    $rp[] = "('engineer','$res','read')";
}
foreach (['pit_stops','lap_telemetry'] as $res) {
    foreach (['create','update','delete'] as $act) { $rp[] = "('engineer','$res','$act')"; }
}
foreach (['race_results','qualifying_results','sprint_results','pit_stops',
    'lap_telemetry','driver_standings','constructor_standings','penalties',
    'teams','people','seasons','circuits','race_entries','races'] as $res) {
    $rp[] = "('driver','$res','read')";
    $rp[] = "('media','$res','read')";
    $rp[] = "('fan','$res','read')";
}
$db->exec("INSERT IGNORE INTO roles_permissions (role,resource,action) VALUES " . implode(',', $rp));
echo "Roles permissions ensured\n";

// ─── STEP 1: CLEAN UP ────────────────────────────────────────────────────────

// Remove the placeholder 2026 season (nothing depends on it)
$db->exec("DELETE FROM seasons WHERE year = 2026 AND is_active = 0");
echo "Removed placeholder 2026 season (if existed)\n";

// ─── STEP 2: SEASONS ─────────────────────────────────────────────────────────

$db->exec("INSERT IGNORE INTO seasons (year, is_active) VALUES (2023, 0), (2024, 0), (2025, 1)");

$seasons = [];
foreach ($db->query("SELECT id, year FROM seasons ORDER BY year") as $r) {
    $seasons[$r['year']] = (int)$r['id'];
}
$s23 = $seasons[2023];
$s24 = $seasons[2024];
$s25 = $seasons[2025];
echo "Seasons: 2023=$s23, 2024=$s24, 2025=$s25\n";

// ─── STEP 2b: 2025 TEAM SEASONS ──────────────────────────────────────────────

$db->exec("INSERT IGNORE INTO team_seasons (id,team_id,season_id,principal,car_name,power_unit,base_location,total_points) VALUES
    (1,1,$s25,'Christian Horner','RB21','Honda RBPT','Milton Keynes, UK',0),
    (2,2,$s25,'Toto Wolff','W16','Mercedes','Brackley, UK',0),
    (3,3,$s25,'Frederic Vasseur','SF-25','Ferrari','Maranello, Italy',0),
    (4,4,$s25,'Andrea Stella','MCL39','Mercedes','Woking, UK',0),
    (5,5,$s25,'Mike Krack','AMR25','Mercedes','Silverstone, UK',0),
    (6,6,$s25,'Oliver Oakes','A525','Renault','Enstone, UK',0),
    (7,7,$s25,'James Vowles','FW47','Mercedes','Grove, UK',0),
    (8,8,$s25,'Laurent Mekies','VCARB 02','Honda RBPT','Faenza, Italy',0),
    (9,9,$s25,'Mattia Binotto','C45','Ferrari','Hinwil, Switzerland',0),
    (10,10,$s25,'Ayao Komatsu','VF-25','Ferrari','Kannapolis, USA',0)");
echo "2025 team seasons ensured\n";

// ─── STEP 2c: ACTIVE 2025 DRIVERS (people) ───────────────────────────────────

$db->exec("INSERT IGNORE INTO people (id,first_name,last_name,nationality,date_of_birth,racing_number,is_active) VALUES
    (1,'Max','Verstappen','Dutch','1997-09-30',1,1),
    (2,'Liam','Lawson','New Zealand','2002-02-11',30,1),
    (3,'George','Russell','British','1998-02-15',63,1),
    (4,'Kimi','Antonelli','Italian','2006-08-25',12,1),
    (5,'Charles','Leclerc','Monegasque','1997-10-16',16,1),
    (6,'Lewis','Hamilton','British','1985-01-07',44,1),
    (7,'Lando','Norris','British','1999-11-13',4,1),
    (8,'Oscar','Piastri','Australian','2001-04-06',81,1),
    (9,'Fernando','Alonso','Spanish','1981-07-29',14,1),
    (10,'Lance','Stroll','Canadian','1998-10-29',18,1),
    (11,'Pierre','Gasly','French','1996-02-07',10,1),
    (12,'Jack','Doohan','Australian','2003-01-20',7,1),
    (13,'Alex','Albon','Thai','1996-03-23',23,1),
    (14,'Carlos','Sainz','Spanish','1994-09-01',55,1),
    (15,'Isack','Hadjar','French','2004-09-28',6,1),
    (16,'Yuki','Tsunoda','Japanese','2000-05-11',22,1),
    (17,'Nico','Hulkenberg','German','1987-08-19',27,1),
    (18,'Gabriel','Bortoleto','Brazilian','2004-10-14',5,1),
    (19,'Esteban','Ocon','French','1996-09-17',31,1),
    (20,'Oliver','Bearman','British','2005-05-08',87,1)");
echo "2025 drivers ensured\n";

// ─── STEP 2d: 2025 DRIVER SEASONS (active lineup) ────────────────────────────

// team_season_id → person_ids for 2025 active lineup
// ts1=RBR:1,2  ts2=MER:3,4  ts3=FER:5,6  ts4=MCL:7,8  ts5=AMR:9,10
// ts6=ALP:11,12  ts7=WIL:13,14  ts8=RB:15,16  ts9=SAU:17,18  ts10=HAA:19,20
$lineup2025 = [1=>[1,2],2=>[3,4],3=>[5,6],4=>[7,8],5=>[9,10],6=>[11,12],7=>[13,14],8=>[15,16],9=>[17,18],10=>[19,20]];
foreach ($lineup2025 as $tsId => $pids) {
    foreach ($pids as $pid) {
        $db->prepare("INSERT IGNORE INTO driver_seasons (person_id,team_season_id,season_id,status,joined_round) VALUES (?,?,?,'active',1)")
           ->execute([$pid, $tsId, $s25]);
    }
}
echo "2025 driver seasons ensured\n";

// ─── STEP 2e: 2025 RACES (rounds 1-5) ────────────────────────────────────────

$races2025base = [
    [1,'Bahrain Grand Prix',       1,1,'2025-03-02','2025-03-01',0,null,'completed'],
    [2,'Saudi Arabian Grand Prix', 1,2,'2025-03-09','2025-03-08',0,null,'completed'],
    [3,'Australian Grand Prix',    1,3,'2025-03-23','2025-03-22',0,null,'completed'],
    [4,'Japanese Grand Prix',      1,4,'2025-04-06','2025-04-05',0,null,'completed'],
    [5,'Chinese Grand Prix',       1,5,'2025-04-20','2025-04-19',1,'2025-04-19','scheduled'],
];
foreach ($races2025base as [$id,$name,$sid,$rnd,$date,$qdate,$spr,$sprdate,$status]) {
    $db->prepare("INSERT IGNORE INTO races (id,season_id,circuit_id,name,round_number,race_date,qualifying_date,has_sprint,sprint_date,status) VALUES (?,?,?,?,?,?,?,?,?,?)")
       ->execute([$id,$s25,$id,$name,$rnd,$date,$qdate,$spr,$sprdate,$status]);
}
echo "2025 base races ensured\n";

// ─── STEP 3: RESERVE DRIVERS (inactive people) ───────────────────────────────

// ALTER enum to include 'inactive' for reserves
$db->exec("ALTER TABLE driver_seasons MODIFY status ENUM('active','replaced','injured','inactive') DEFAULT 'active'");

// [racing_number, first_name, last_name, nationality, dob, is_active, team_id]
$reserves = [
    [36,  'Jake',    'Dennis',       'British',   '2002-02-13', 0, 1],  // Red Bull
    [40,  'Mick',    'Schumacher',   'German',    '1999-03-22', 0, 2],  // Mercedes
    [89,  'Robert',  'Shwartzman',   'Israeli',   '1999-06-25', 0, 3],  // Ferrari
    [50,  'Pato',    "O'Ward",       'Mexican',   '1999-03-06', 0, 4],  // McLaren
    [2,   'Felipe',  'Drugovich',    'Brazilian', '1999-05-25', 0, 5],  // Aston Martin
    [96,  'Oliver',  'Caldwell',     'British',   '2001-11-11', 0, 6],  // Alpine
    [45,  'Paul',    'Aron',         'Estonian',  '2000-10-22', 0, 7],  // Williams
    [60,  'Ayumu',   'Iwasa',        'Japanese',  '2001-03-10', 0, 8],  // RB
    [38,  'Theo',    'Pourchaire',   'French',    '2003-08-20', 0, 9],  // Sauber
    [75,  'Pietro',  'Fittipaldi',   'Brazilian', '1996-06-25', 0, 10], // Haas
];
foreach ($reserves as [$num, $fn, $ln, $nat, $dob, $active, $tid]) {
    $db->prepare("INSERT IGNORE INTO people (racing_number, first_name, last_name, nationality, date_of_birth, is_active) VALUES (?,?,?,?,?,?)")
       ->execute([$num, $fn, $ln, $nat, $dob, $active]);
}
echo "Reserve drivers inserted (IGNORE if exists)\n";

// Fetch all people by number for easy lookup (fetched after reserve insert so new rows are included)
$peopleByNum = [];
foreach ($db->query("SELECT id, racing_number FROM people WHERE requested_by_team_id IS NULL") as $r) {
    $peopleByNum[(int)$r['racing_number']] = (int)$r['id'];
}

// ─── STEP 4: TEAM SEASONS ────────────────────────────────────────────────────

// team_id => [car23, pu23, principal23, car24, pu24, principal24, base]
$teamCfg = [
    1 => ['RB19','Honda RBPT','Christian Horner','RB20','Honda RBPT','Christian Horner','Milton Keynes, UK'],
    2 => ['W14','Mercedes','Toto Wolff','W15','Mercedes','Toto Wolff','Brackley, UK'],
    3 => ['SF-23','Ferrari','Frédéric Vasseur','SF-24','Ferrari','Frédéric Vasseur','Maranello, Italy'],
    4 => ['MCL60','Mercedes','Andrea Stella','MCL38','Mercedes','Andrea Stella','Woking, UK'],
    5 => ['AMR23','Mercedes','Mike Krack','AMR24','Mercedes','Mike Krack','Silverstone, UK'],
    6 => ['A523','Renault','Otmar Szafnauer','A524','Renault','Bruno Famin','Enstone, UK'],
    7 => ['FW45','Mercedes','James Vowles','FW46','Mercedes','James Vowles','Grove, UK'],
    8 => ['AT04','Honda RBPT','Franz Tost','VCARB 01','Honda RBPT','Laurent Mekies','Faenza, Italy'],
    9 => ['C43','Ferrari','Alessandro Alunni Bravi','C44','Ferrari','Andreas Seidl','Hinwil, Switzerland'],
   10 => ['VF-23','Ferrari','Günther Steiner','VF-24','Ferrari','Ayao Komatsu','Kannapolis, USA'],
];

$tsIds = []; // [season_id][team_id] = team_season_id
// 2025 team_seasons already exist
foreach ($db->query("SELECT id, team_id FROM team_seasons WHERE season_id = $s25") as $r) {
    $tsIds[$s25][(int)$r['team_id']] = (int)$r['id'];
}

foreach ([2023=>$s23, 2024=>$s24] as $yr=>$sid) {
    foreach ($teamCfg as $tid=>$cfg) {
        $carIdx  = $yr===2023 ? 0 : 3;
        $puIdx   = $yr===2023 ? 1 : 4;
        $prinIdx = $yr===2023 ? 2 : 5;
        $base    = $cfg[6];
        $db->prepare("INSERT IGNORE INTO team_seasons (team_id, season_id, car_name, power_unit, principal, base_location)
            VALUES (?,?,?,?,?,?)")
           ->execute([$tid, $sid, $cfg[$carIdx], $cfg[$puIdx], $cfg[$prinIdx], $base]);
    }
    foreach ($db->query("SELECT id, team_id FROM team_seasons WHERE season_id = $sid") as $r) {
        $tsIds[$sid][(int)$r['team_id']] = (int)$r['id'];
    }
}
echo "Team seasons created for 2023, 2024\n";

// ─── STEP 5: DRIVER SEASONS ──────────────────────────────────────────────────

// [team_id => [num1, num2]] mapping per season
// person IDs come from peopleByNum lookup
$driverAssign = [
    2023 => [
        1=>[1,30], 2=>[44,63], 3=>[16,55], 4=>[4,81],
        5=>[14,18], 6=>[10,31], 7=>[23,12], 8=>[22,7],
        9=>[27,5], 10=>[87,6],
    ],
    2024 => [
        1=>[1,30], 2=>[44,63], 3=>[16,55], 4=>[4,81],
        5=>[14,18], 6=>[10,31], 7=>[23,87], 8=>[22,6],
        9=>[27,5], 10=>[7,12],
    ],
];

foreach ([2023=>$s23, 2024=>$s24] as $yr=>$sid) {
    foreach ($driverAssign[$yr] as $tid=>$nums) {
        $tsId = $tsIds[$sid][$tid];
        foreach ($nums as $num) {
            $pid = $peopleByNum[$num] ?? null;
            if (!$pid) { echo "WARNING: driver #$num not found\n"; continue; }
            $db->prepare("INSERT IGNORE INTO driver_seasons (person_id, team_season_id, season_id, joined_round, status)
                VALUES (?,?,?,1,'active')")
               ->execute([$pid, $tsId, $sid]);
        }
    }
}
// Insert reserve driver_seasons for all 3 seasons
foreach ($reserves as [$num, $fn, $ln, $nat, $dob, $active, $resTeamId]) {
    $resPid = $peopleByNum[$num] ?? null;
    if (!$resPid) {
        // Re-fetch in case they were just inserted
        $rs = $db->prepare("SELECT id FROM people WHERE racing_number=?");
        $rs->execute([$num]);
        $resPid = (int)($rs->fetchColumn() ?: 0);
    }
    if (!$resPid) { echo "WARNING: reserve #$num not found\n"; continue; }
    foreach ([$s23 => $s23, $s24 => $s24, $s25 => $s25] as $sid) {
        $tsId = $tsIds[$sid][$resTeamId] ?? null;
        if (!$tsId) continue;
        $db->prepare("INSERT IGNORE INTO driver_seasons (person_id, team_season_id, season_id, status) VALUES (?,?,?,'inactive')")
           ->execute([$resPid, $tsId, $sid]);
    }
}
echo "Driver seasons populated for 2023, 2024 (including reserves)\n";

// ─── STEP 6: CIRCUITS (add more if < 10 active) ──────────────────────────────

$circuitCount = (int)$db->query("SELECT COUNT(*) FROM circuits WHERE is_active=1")->fetchColumn();
if ($circuitCount < 8) {
    // Should have 10 from initial setup; just warn
    echo "WARNING: Only $circuitCount active circuits found\n";
}
// Get circuit IDs in order
$circuits = [];
foreach ($db->query("SELECT id FROM circuits WHERE is_active=1 ORDER BY id LIMIT 10") as $r) {
    $circuits[] = (int)$r['id'];
}
echo "Using " . count($circuits) . " circuits\n";

// ─── STEP 7: RACES ───────────────────────────────────────────────────────────

// Race calendar per season: [name, circuit_idx(0-based), date, has_sprint]
$raceCalendars = [
    2023 => [
        ['Bahrain Grand Prix',         0, '2023-03-05', 0],
        ['Saudi Arabian Grand Prix',   1, '2023-03-19', 0],
        ['Australian Grand Prix',      2, '2023-04-02', 0],
        ['Japanese Grand Prix',        3, '2023-04-09', 0],
        ['Chinese Grand Prix',         4, '2023-04-23', 1],
        ['Miami Grand Prix',           5, '2023-05-07', 1],
        ['Italian Grand Prix',         6, '2023-05-21', 0],
        ['Monaco Grand Prix',          7, '2023-05-28', 0],
    ],
    2024 => [
        ['Bahrain Grand Prix',         0, '2024-03-02', 0],
        ['Saudi Arabian Grand Prix',   1, '2024-03-09', 0],
        ['Australian Grand Prix',      2, '2024-03-24', 0],
        ['Japanese Grand Prix',        3, '2024-04-07', 0],
        ['Chinese Grand Prix',         4, '2024-04-21', 1],
        ['Miami Grand Prix',           5, '2024-05-05', 1],
        ['British Grand Prix',         6, '2024-07-07', 0],
        ['Hungarian Grand Prix',       7, '2024-07-21', 0],
    ],
];

$raceIds = []; // [season_year][round] = race_id
// Existing 2025 races
foreach ($db->query("SELECT id, round_number FROM races WHERE season_id=$s25 ORDER BY round_number") as $r) {
    $raceIds[2025][(int)$r['round_number']] = (int)$r['id'];
}

foreach ([2023=>$s23, 2024=>$s24] as $yr=>$sid) {
    foreach ($raceCalendars[$yr] as $rnd=>$rc) {
        [$name, $circIdx, $date, $hasSprint] = $rc;
        $roundNum = $rnd + 1;
        $circuitId = $circuits[$circIdx] ?? $circuits[0];
        $db->prepare("INSERT IGNORE INTO races (season_id, circuit_id, round_number, name, race_date, status, has_sprint)
            VALUES (?,?,?,?,?,'completed',?)")
           ->execute([$sid, $circuitId, $roundNum, $name, $date, $hasSprint]);
        // fetch id
        $stmt = $db->prepare("SELECT id FROM races WHERE season_id=? AND round_number=?");
        $stmt->execute([$sid, $roundNum]);
        $raceIds[$yr][$roundNum] = (int)$stmt->fetchColumn();
    }
}

// Add 2025 races 6-8 if they don't exist
$race2025additions = [
    [6, 'Miami Grand Prix',      5, '2025-05-04', 1],
    [7, 'Canadian Grand Prix',   8, '2025-06-15', 0],
    [8, 'British Grand Prix',    6, '2025-07-06', 0],
];
foreach ($race2025additions as [$rnd, $name, $circIdx, $date, $hasSprint]) {
    $circuitId = $circuits[$circIdx] ?? $circuits[0];
    $db->prepare("INSERT IGNORE INTO races (season_id, circuit_id, round_number, name, race_date, status, has_sprint)
        VALUES (?,?,?,?,?,'scheduled',?)")
       ->execute([$s25, $circuitId, $rnd, $name, $date, $hasSprint]);
    $stmt = $db->prepare("SELECT id FROM races WHERE season_id=? AND round_number=?");
    $stmt->execute([$s25, $rnd]);
    $raceIds[2025][$rnd] = (int)$stmt->fetchColumn();
}

echo "Races created\n";

// ─── STEP 8: RACE ENTRIES ────────────────────────────────────────────────────

// Build person → team_season map per season
function getDriverTeamMap(PDO $db, int $seasonId): array {
    // returns [person_id => team_season_id]
    $map = [];
    $stmt = $db->prepare("SELECT ds.person_id, ds.team_season_id FROM driver_seasons ds WHERE ds.season_id=? AND ds.status='active'");
    $stmt->execute([$seasonId]);
    foreach ($stmt->fetchAll() as $r) {
        $map[(int)$r['person_id']] = (int)$r['team_season_id'];
    }
    return $map;
}

$driverTeam = [
    2023 => getDriverTeamMap($db, $s23),
    2024 => getDriverTeamMap($db, $s24),
    2025 => getDriverTeamMap($db, $s25),
];

// Insert race entries for every race in every season
foreach ([2023=>$s23, 2024=>$s24] as $yr=>$sid) {
    foreach ($raceIds[$yr] as $rnd=>$rid) {
        foreach ($driverTeam[$yr] as $pid=>$tsId) {
            $db->prepare("INSERT IGNORE INTO race_entries (race_id, person_id, team_season_id) VALUES (?,?,?)")
               ->execute([$rid, $pid, $tsId]);
        }
    }
}

// Ensure ALL 2025 races have entries (including scheduled future rounds)
$allRace2025Ids = $db->query("SELECT id FROM races WHERE season_id=$s25")->fetchAll(PDO::FETCH_COLUMN);
foreach ($allRace2025Ids as $rid25) {
    foreach ($driverTeam[2025] as $pid=>$tsId) {
        $db->prepare("INSERT IGNORE INTO race_entries (race_id, person_id, team_season_id) VALUES (?,?,?)")
           ->execute([$rid25, $pid, $tsId]);
    }
}

echo "Race entries populated\n";

// ─── STEP 9: RACE ENTRY ID LOOKUP ────────────────────────────────────────────

// Returns [race_id][person_id] => entry_id
function getEntryIds(PDO $db, array $raceIds): array {
    $entryMap = [];
    $allRaceIds = [];
    foreach ($raceIds as $yrRaces) {
        foreach ($yrRaces as $rid) $allRaceIds[] = $rid;
    }
    if (empty($allRaceIds)) return [];
    $inList = implode(',', $allRaceIds);
    foreach ($db->query("SELECT id, race_id, person_id FROM race_entries WHERE race_id IN ($inList)") as $r) {
        $entryMap[(int)$r['race_id']][(int)$r['person_id']] = (int)$r['id'];
    }
    return $entryMap;
}

// 2023 and 2024 entries
$allRaceIds2324 = [];
foreach ([2023, 2024] as $yr) {
    foreach ($raceIds[$yr] as $rid) $allRaceIds2324[] = $rid;
}
// Also 2025 races with results (1-4)
foreach ([1,2,3,4] as $rnd) {
    if (isset($raceIds[2025][$rnd])) $allRaceIds2324[] = $raceIds[2025][$rnd];
}

$entryIds = getEntryIds($db, [0 => $allRaceIds2324]);

// ─── STEP 10: RACE RESULTS ───────────────────────────────────────────────────

// F1 points
$F1PTS = [1=>25,2=>18,3=>15,4=>12,5=>10,6=>8,7=>6,8=>4,9=>2,10=>1];

// Finishing orders per season per race [person numbers in finish order, DNF list]
// Format: [finish_order_by_number, fastest_lap_num, dnf_nums_from_back]
// 20 drivers — first 10 get points

// Driver numbers per season
$nums2023 = [1,30, 44,63, 16,55, 4,81, 14,18, 10,31, 23,12, 22,7, 27,5, 87,6];
$nums2024 = [1,30, 44,63, 16,55, 4,81, 14,18, 10,31, 23,87, 22,6, 27,5, 7,12];
$nums2025 = [1,30, 63,12, 16,44, 4,81, 14,18, 10,7,  23,55, 22,6, 27,5, 87,31];

// Race finishing orders [winner driver_num, 2nd, 3rd, ... 20th]
// For brevity, define by season-race, with DNFs at the back
// Define full 20-driver finishing orders by driver number
$raceFinish = [
    '2023-1' => ['order'=>[1,44,16,4,14,10,18,27,63,31,55,23,22,81,30,7,12,87,5,6],   'fl'=>16,'dnf'=>[]],
    '2023-2' => ['order'=>[1,16,44,4,14,10,81,27,63,18,55,23,22,30,7,12,87,31,5,6],   'fl'=>4, 'dnf'=>[]],
    '2023-3' => ['order'=>[14,1,44,16,4,81,10,18,27,63,55,23,22,30,7,12,87,31,5,6],   'fl'=>1, 'dnf'=>[]],
    '2023-4' => ['order'=>[1,16,14,4,81,44,10,18,27,63,55,23,22,30,7,12,87,31,5,6],   'fl'=>1, 'dnf'=>[5,6]],
    '2023-5' => ['order'=>[16,1,44,4,81,14,10,18,27,63,55,23,22,30,7,12,87,31,5,6],   'fl'=>4, 'dnf'=>[]],
    '2023-6' => ['order'=>[1,4,16,44,81,14,10,18,27,63,55,23,22,30,7,12,87,31,5,6],   'fl'=>1, 'dnf'=>[87]],
    '2023-7' => ['order'=>[4,1,16,44,81,14,10,18,27,63,55,23,22,30,7,12,87,31,5,6],   'fl'=>4, 'dnf'=>[]],
    '2023-8' => ['order'=>[1,44,4,16,81,14,10,18,27,63,55,23,22,30,7,12,87,31,5,6],   'fl'=>1, 'dnf'=>[5]],

    '2024-1' => ['order'=>[1,44,16,4,81,14,10,18,27,63,55,23,22,30,6,7,12,87,5,31],   'fl'=>1, 'dnf'=>[]],
    '2024-2' => ['order'=>[4,1,16,44,81,14,10,18,27,63,55,23,22,30,6,7,12,87,5,31],   'fl'=>4, 'dnf'=>[]],
    '2024-3' => ['order'=>[1,4,44,16,81,14,10,18,27,63,55,23,22,30,6,7,12,87,5,31],   'fl'=>1, 'dnf'=>[31]],
    '2024-4' => ['order'=>[4,81,1,44,16,14,10,18,27,63,55,23,22,30,6,7,12,87,5,31],   'fl'=>4, 'dnf'=>[]],
    '2024-5' => ['order'=>[1,4,81,44,16,14,10,18,27,63,55,23,22,30,6,7,12,87,5,31],   'fl'=>1, 'dnf'=>[5]],
    '2024-6' => ['order'=>[81,4,1,44,16,14,10,18,27,63,55,23,22,30,6,7,12,87,5,31],   'fl'=>4, 'dnf'=>[]],
    '2024-7' => ['order'=>[4,81,1,16,44,14,10,18,27,63,55,23,22,30,6,7,12,87,5,31],   'fl'=>4, 'dnf'=>[31,5]],
    '2024-8' => ['order'=>[1,4,81,16,44,14,10,18,27,63,55,23,22,30,6,7,12,87,5,31],   'fl'=>1, 'dnf'=>[]],

    '2025-1' => ['order'=>[1,4,81,16,63,44,14,10,18,27,55,23,22,30,6,7,12,87,5,31],   'fl'=>4, 'dnf'=>[]],
    '2025-2' => ['order'=>[4,1,81,16,63,44,14,10,18,27,55,23,22,30,6,7,12,87,5,31],   'fl'=>1, 'dnf'=>[]],
    '2025-3' => ['order'=>[1,81,4,16,63,44,14,10,18,27,55,23,22,30,6,7,12,87,5,31],   'fl'=>4, 'dnf'=>[]],
    '2025-4' => ['order'=>[4,1,81,16,44,63,14,10,18,27,55,23,22,30,6,7,12,87,5,31],   'fl'=>1, 'dnf'=>[31]],
];

// Build person_num → person_id cache for each season
$numToPid = [
    2023 => array_flip(array_map(fn($num) => $peopleByNum[$num] ?? 0, array_combine($nums2023, $nums2023))),
    2024 => array_flip(array_map(fn($num) => $peopleByNum[$num] ?? 0, array_combine($nums2024, $nums2024))),
    2025 => array_flip(array_map(fn($num) => $peopleByNum[$num] ?? 0, array_combine($nums2025, $nums2025))),
];
// Actually, just use peopleByNum directly - it's [number => person_id]

function insertRaceResults(PDO $db, array $entryIds, int $raceId, array $finish, array $F1PTS, array $peopleByNum): void {
    $order  = $finish['order'];
    $flNum  = $finish['fl'];
    $dnfNums = $finish['dnf'];

    $flPid = $peopleByNum[$flNum] ?? null;
    $flEntry = $flPid && isset($entryIds[$raceId][$flPid]) ? $entryIds[$raceId][$flPid] : null;

    $pos = 1;
    $laps = 58;
    foreach ($order as $dnum) {
        $pid = $peopleByNum[$dnum] ?? null;
        if (!$pid) continue;
        $entryId = $entryIds[$raceId][$pid] ?? null;
        if (!$entryId) continue;

        $isDnf   = in_array($dnum, $dnfNums, true);
        $status  = $isDnf ? 'DNF' : 'Finished';
        $finPos  = $isDnf ? null : $pos;
        $pts     = 0;
        $flBonus = 0;

        if (!$isDnf) {
            $pts = $F1PTS[$pos] ?? 0;
            if ($flEntry === $entryId && $pos <= 10) {
                $pts++;
                $flBonus = 1;
            }
            $pos++;
        }

        $lapsDone = $isDnf ? max(1, $laps - random_int(5, 40)) : $laps;
        $startPos = match($dnum) {
            $order[0] => 1, $order[1] => 2, $order[2] => 3,
            default   => array_search($dnum, $order) + 1
        };

        $db->prepare("INSERT INTO race_results
            (race_entry_id, finish_position, start_position, points_scored, status, fastest_lap_bonus, laps_completed)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            finish_position=VALUES(finish_position), start_position=VALUES(start_position),
            points_scored=VALUES(points_scored), status=VALUES(status),
            fastest_lap_bonus=VALUES(fastest_lap_bonus), laps_completed=VALUES(laps_completed)")
           ->execute([$entryId, $finPos, $startPos, $pts, $status, $flBonus, $lapsDone]);
    }
}

// Qualifying times in ms (base times per circuit)
$baseQual = [1=>88000, 2=>72000, 3=>75000, 4=>90000, 5=>93000, 6=>87000, 7=>75500, 8=>73000, 9=>74000, 10=>79000];

function insertQualResults(PDO $db, array $entryIds, int $raceId, array $finish, array $circuitTimes, int $circIdx, array $peopleByNum): void {
    $order = $finish['order'];
    $base  = $circuitTimes[$circIdx + 1] ?? 85000;
    $grid  = 1;
    foreach ($order as $i => $dnum) {
        $pid = $peopleByNum[$dnum] ?? null;
        if (!$pid) continue;
        $entryId = $entryIds[$raceId][$pid] ?? null;
        if (!$entryId) continue;

        $q3 = $grid <= 10 ? ($base + ($grid - 1) * random_int(150, 400)) : null;
        $q2 = $grid <= 15 ? ($base + 800 + ($grid - 11) * random_int(200, 500)) : null;
        $q1 = $base + 2000 + ($grid - 16) * random_int(200, 600);
        $elim = $grid > 15 ? 'Q1' : ($grid > 10 ? 'Q2' : null);

        if ($grid > 15) { $q3 = null; $q2 = null; }
        if ($grid > 10 && $grid <= 15) { $q3 = null; }
        if ($grid <= 10) { $q1 = null; $q2 = null; $elim = null; }

        $db->prepare("INSERT INTO qualifying_results
            (race_entry_id, q1_time_ms, q2_time_ms, q3_time_ms, grid_position, eliminated_in)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            q1_time_ms=VALUES(q1_time_ms), q2_time_ms=VALUES(q2_time_ms),
            q3_time_ms=VALUES(q3_time_ms), grid_position=VALUES(grid_position),
            eliminated_in=VALUES(eliminated_in)")
           ->execute([$entryId, $q1, $q2, $q3, $grid, $elim]);

        $grid++;
    }
}

// Insert results for 2023 races
$circuitIndex = [2023=>[0,1,2,3,4,5,6,7], 2024=>[0,1,2,3,4,5,6,7]];
foreach ([2023, 2024] as $yr) {
    foreach ($raceCalendars[$yr] as $rnd => $rc) {
        $roundNum = $rnd + 1;
        $rid = $raceIds[$yr][$roundNum];
        $key = "$yr-$roundNum";
        if (!isset($raceFinish[$key])) continue;
        insertQualResults($db, $entryIds, $rid, $raceFinish[$key], $baseQual, $rc[1], $peopleByNum);
        insertRaceResults($db, $entryIds, $rid, $raceFinish[$key], $F1PTS, $peopleByNum);
    }
}

// Insert results for 2025 races 1-4
foreach ([1,2,3,4] as $rnd) {
    $rid = $raceIds[2025][$rnd];
    $key = "2025-$rnd";
    if (!isset($raceFinish[$key]) || !isset($raceCalendars)) continue;
    insertQualResults($db, $entryIds, $rid, $raceFinish[$key], $baseQual, $rnd - 1, $peopleByNum);
    insertRaceResults($db, $entryIds, $rid, $raceFinish[$key], $F1PTS, $peopleByNum);
}

echo "Race results and qualifying inserted\n";

// ─── STEP 11: SPRINT RESULTS ─────────────────────────────────────────────────

$SPRPTS = [1=>8,2=>7,3=>6,4=>5,5=>4,6=>3,7=>2,8=>1];

// Sprint races: 2023 R5(China), R6(Miami); 2024 R5(China), R6(Miami)
$sprintFinish = [
    '2023-5' => [1,16,44,4,81,14,10,18,27,63,  55,23,22,30,7,12,87,31,5,6],
    '2023-6' => [1,4,81,16,44,14,10,18,27,63,  55,23,22,30,7,12,87,31,5,6],
    '2024-5' => [1,4,81,16,44,14,10,18,27,63,  55,23,22,30,6,7,12,87,5,31],
    '2024-6' => [81,4,1,44,16,14,10,18,27,63,  55,23,22,30,6,7,12,87,5,31],
];

foreach ($sprintFinish as $key=>$order) {
    [$yr,$rnd] = explode('-', $key);
    $yr = (int)$yr; $rnd = (int)$rnd;
    $rid = $raceIds[$yr][$rnd];
    $pos = 1;
    foreach ($order as $dnum) {
        $pid = $peopleByNum[$dnum] ?? null;
        if (!$pid) continue;
        $entryId = $entryIds[$rid][$pid] ?? null;
        if (!$entryId) continue;
        $pts = $SPRPTS[$pos] ?? 0;
        $db->prepare("INSERT INTO sprint_results (race_entry_id, finish_position, points_scored, status)
            VALUES (?,?,?,'Finished')
            ON DUPLICATE KEY UPDATE finish_position=VALUES(finish_position), points_scored=VALUES(points_scored)")
           ->execute([$entryId, $pos, $pts]);
        $pos++;
    }
}
echo "Sprint results inserted\n";

// ─── STEP 12: PIT STOPS ──────────────────────────────────────────────────────

$tyres = ['soft','medium','hard'];

// Insert pit stops for top 5 finishers in selected races
$pitRaces = [
    '2023-1', '2023-2', '2023-3',
    '2024-1', '2024-2', '2024-3',
    '2025-1', '2025-2', '2025-3',
];

foreach ($pitRaces as $key) {
    [$yr,$rnd] = explode('-', $key);
    $yr = (int)$yr; $rnd = (int)$rnd;
    if (!isset($raceIds[$yr][$rnd])) continue;
    $rid = $raceIds[$yr][$rnd];
    if (!isset($raceFinish[$key])) continue;
    $dnfNums = $raceFinish[$key]['dnf'];
    foreach ($raceFinish[$key]['order'] as $dnum) {
        if (in_array($dnum, $dnfNums, true)) continue; // DNF drivers get 0-1 stops
        $pid = $peopleByNum[$dnum] ?? null;
        if (!$pid) continue;
        $entryId = $entryIds[$rid][$pid] ?? null;
        if (!$entryId) continue;
        // 2 stops per finishing driver
        for ($s = 1; $s <= 2; $s++) {
            $lap = $s === 1 ? random_int(15,22) : random_int(38,45);
            $dur = random_int(2100, 3500);
            $tyreIn  = $tyres[($s-1) % 3];
            $tyreOut = $tyres[$s % 3];
            $db->prepare("INSERT IGNORE INTO pit_stops (race_entry_id, stop_number, lap_number, duration_ms, tyre_in, tyre_out)
                VALUES (?,?,?,?,?,?)")
               ->execute([$entryId, $s, $lap, $dur, $tyreIn, $tyreOut]);
        }
    }
    // DNF drivers get 1 stop
    foreach ($dnfNums as $dnum) {
        $pid = $peopleByNum[$dnum] ?? null;
        if (!$pid) continue;
        $entryId = $entryIds[$rid][$pid] ?? null;
        if (!$entryId) continue;
        $db->prepare("INSERT IGNORE INTO pit_stops (race_entry_id, stop_number, lap_number, duration_ms, tyre_in, tyre_out)
            VALUES (?,1,?,?,?,?)")
           ->execute([$entryId, random_int(8,15), random_int(2100,3500), $tyres[0], $tyres[1]]);
    }
}
echo "Pit stops inserted\n";

// ─── STEP 13: LAP TELEMETRY ──────────────────────────────────────────────────

// Add telemetry for ALL 20 drivers in first 2 races of each season (5+ laps each)
$telRaces = ['2023-1','2023-2','2024-1','2024-2','2025-1','2025-2'];

foreach ($telRaces as $key) {
    [$yr,$rnd] = explode('-', $key);
    $yr = (int)$yr; $rnd = (int)$rnd;
    if (!isset($raceIds[$yr][$rnd])) continue;
    $rid = $raceIds[$yr][$rnd];
    if (!isset($raceFinish[$key])) continue;
    $allDrivers = $raceFinish[$key]['order'];
    $circIdx = ($rnd - 1);
    $baseTime = array_values($baseQual)[$circIdx] ?? 85000;

    foreach ($allDrivers as $position => $dnum) {
        $pid = $peopleByNum[$dnum] ?? null;
        if (!$pid) continue;
        $entryId = $entryIds[$rid][$pid] ?? null;
        if (!$entryId) continue;

        // Each driver gets 8 laps of telemetry; faster drivers have lower base times
        $driverOffset = $position * 80;
        $tyre = 'medium';
        $tyreAge = 0;
        for ($lap = 1; $lap <= 8; $lap++) {
            $isPit = ($lap === 3 || $lap === 6) ? 1 : 0;
            if ($isPit) { $tyre = ($tyre === 'medium') ? 'hard' : 'soft'; $tyreAge = 0; }
            $lapMs  = $baseTime + $driverOffset + random_int(-100, 500) + ($tyreAge * 40);
            $s1     = intval($lapMs * 0.31);
            $s2     = intval($lapMs * 0.35);
            $s3     = $lapMs - $s1 - $s2;
            $speed  = round(290 - $position * 0.5 + random_int(-10, 15), 1);

            $db->prepare("INSERT INTO lap_telemetry
                (race_entry_id, lap_number, lap_time_ms, sector1_ms, sector2_ms, sector3_ms,
                 speed_trap_kmh, tyre_compound, tyre_age_laps, is_pit_lap)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                lap_time_ms=VALUES(lap_time_ms), sector1_ms=VALUES(sector1_ms),
                sector2_ms=VALUES(sector2_ms), sector3_ms=VALUES(sector3_ms),
                speed_trap_kmh=VALUES(speed_trap_kmh), tyre_compound=VALUES(tyre_compound),
                tyre_age_laps=VALUES(tyre_age_laps), is_pit_lap=VALUES(is_pit_lap)")
               ->execute([$entryId, $lap, $lapMs, $s1, $s2, $s3, $speed, $tyre, $tyreAge, $isPit]);
            $tyreAge++;
        }
    }
}
echo "Lap telemetry inserted\n";

// ─── STEP 14: PENALTIES ──────────────────────────────────────────────────────

// Get the race_director user id (fallback to admin)
$rdUser = (int)($db->query("SELECT id FROM users WHERE role='race_director' LIMIT 1")->fetchColumn() ?: $adminId);

$penaltyData = [
    ['2023-1', 18,  'time_penalty',  'Unsafe release from pit lane',              5, 0, 2, 0],
    ['2023-3', 14,  'grid_penalty',  'Impeding another driver in qualifying',      0, 3, 0, 0],
    ['2023-6', 31,  'time_penalty',  'Track limits violation (multiple)',           5, 0, 2, 0],
    ['2024-2', 30,  'time_penalty',  'Exceeding pit lane speed limit (20s)',       20, 0, 2, 0],
    ['2024-4', 44,  'dsq',           'Car found underweight after race',            0, 0, 0, 1],
    ['2024-7', 27,  'time_penalty',  'Collision with another driver',              10, 0, 3, 0],
    ['2025-1', 4,   'time_penalty',  'Track limits at final chicane (repeated)',    5, 0, 2, 0],
    ['2025-2', 18,  'grid_penalty',  'Gearbox change outside parc fermé',          0, 5, 2, 0],
];

foreach ($penaltyData as [$key,$dnum,$type,$reason,$timePen,$gridPen,$licPts,$isDsq]) {
    [$yr,$rnd] = explode('-', $key);
    $yr = (int)$yr; $rnd = (int)$rnd;
    if (!isset($raceIds[$yr][$rnd])) continue;
    $rid = $raceIds[$yr][$rnd];
    $pid = $peopleByNum[$dnum] ?? null;
    if (!$pid) continue;
    // Idempotent: delete existing seed penalty for this race+person+type, then reinsert
    $db->prepare("DELETE FROM penalties WHERE race_id=? AND person_id=? AND penalty_type=?")->execute([$rid,$pid,$type]);
    $db->prepare("INSERT INTO penalties
        (race_id, person_id, penalty_type, reason, time_penalty_s, grid_penalty_positions,
         licence_points_awarded, is_dsq, issued_by)
        VALUES (?,?,?,?,?,?,?,?,?)")
       ->execute([$rid, $pid, $type, $reason, $timePen ?: null, $gridPen ?: null, $licPts ?: null, $isDsq, $rdUser]);
}
echo "Penalties inserted\n";

// ─── STEP 15: RECALCULATE STANDINGS ─────────────────────────────────────────

foreach ([$s23, $s24, $s25] as $sid) {
    recalculateStandings($sid);
}
echo "Standings recalculated for all 3 seasons\n";

// Set champion_person_id and champion_team_id for 2023 and 2024
foreach ([$s23, $s24] as $sid) {
    $topDriver = $db->prepare("SELECT person_id FROM driver_standings WHERE season_id=? ORDER BY points DESC LIMIT 1");
    $topDriver->execute([$sid]);
    $champPersonId = (int)($topDriver->fetchColumn() ?: 0);

    $topTeam = $db->prepare("SELECT team_id FROM constructor_standings WHERE season_id=? ORDER BY points DESC LIMIT 1");
    $topTeam->execute([$sid]);
    $champTeamId = (int)($topTeam->fetchColumn() ?: 0);

    if ($champPersonId || $champTeamId) {
        $db->prepare("UPDATE seasons SET champion_person_id=?, champion_team_id=? WHERE id=?")
           ->execute([$champPersonId ?: null, $champTeamId ?: null, $sid]);
        echo "Set champions for season $sid: driver=$champPersonId, team=$champTeamId\n";
    }
}

// ─── STEP 16: USER ACCOUNTS ──────────────────────────────────────────────────

// Team manager assignments (one per team)
$teamManagers = [
    1  => ['John', 'Horner',     'john.horner@redbull.f1',       1],
    2  => ['Anna', 'Wolff',      'anna.wolff@mercedes.f1',       2],
    3  => ['Marco', 'Vasseur',   'marco.vasseur@ferrari.f1',     3],
    4  => ['Lisa', 'Stella',     'lisa.stella@mclaren.f1',       4],
    5  => ['Tom', 'Krack',       'tom.krack@astonmartin.f1',     5],
    6  => ['Eva', 'Famin',       'eva.famin@alpine.f1',          6],
    7  => ['James', 'Vowles',    'james.vowles@williams.f1',     7],
    8  => ['Franco', 'Mekies',   'franco.mekies@rb.f1',          8],
    9  => ['Karl', 'Seidl',      'karl.seidl@sauber.f1',         9],
   10  => ['Ayao', 'Komatsu',    'ayao.komatsu@haas.f1',        10],
];

// Engineers (one per team)
$engineers = [
    1  => ['Peter', 'Eng',       'peter.eng@redbull.f1',        1],
    2  => ['Mike', 'Elliott',    'mike.elliott@mercedes.f1',    2],
    3  => ['Luigi', 'Ferrari',   'luigi.ferrari@ferrari.f1',    3],
    4  => ['Tom', 'Anderson',    'tom.anderson@mclaren.f1',     4],
    5  => ['Eric', 'Blandin',    'eric.blandin@astonmartin.f1', 5],
    6  => ['Pierre', 'Hamelin',  'pierre.hamelin@alpine.f1',    6],
    7  => ['Dave', 'Redding',    'dave.redding@williams.f1',    7],
    8  => ['Ciaron', 'Pilbeam',   'ciaron.pilbeam@rb.f1',        8],
    9  => ['Jan', 'Monchaux',    'jan.monchaux@sauber.f1',      9],
   10  => ['Gary', 'Gannon',     'gary.gannon@haas.f1',        10],
];

// Race director
createUser($db, 'Race Director', 'rd@f1app.com', 'Director123', 'race_director', 0);

// Team Managers
foreach ($teamManagers as $tid => [$fn, $ln, $email, $linkedTeam]) {
    $pwd = $fn . '123';
    createUser($db, "$fn $ln", $email, $pwd, 'team_manager', $linkedTeam);
}

// Engineers
foreach ($engineers as $tid => [$fn, $ln, $email, $linkedTeam]) {
    $pwd = $fn . '123';
    createUser($db, "$fn $ln", $email, $pwd, 'engineer', $linkedTeam);
}

// Driver accounts — one per active 2025 driver
$driverData2025 = [
    1  => ['Max',      'Verstappen',  'max.verstappen@drivers.f1'],
    30 => ['Liam',     'Lawson',      'liam.lawson@drivers.f1'],
    63 => ['George',   'Russell',     'george.russell@drivers.f1'],
    12 => ['Kimi',     'Antonelli',   'kimi.antonelli@drivers.f1'],
    16 => ['Charles',  'Leclerc',     'charles.leclerc@drivers.f1'],
    44 => ['Lewis',    'Hamilton',    'lewis.hamilton@drivers.f1'],
    4  => ['Lando',    'Norris',      'lando.norris@drivers.f1'],
    81 => ['Oscar',    'Piastri',     'oscar.piastri@drivers.f1'],
    14 => ['Fernando', 'Alonso',      'fernando.alonso@drivers.f1'],
    18 => ['Lance',    'Stroll',      'lance.stroll@drivers.f1'],
    10 => ['Pierre',   'Gasly',       'pierre.gasly@drivers.f1'],
    7  => ['Jack',     'Doohan',      'jack.doohan@drivers.f1'],
    23 => ['Alex',     'Albon',       'alex.albon@drivers.f1'],
    55 => ['Carlos',   'Sainz',       'carlos.sainz@drivers.f1'],
    6  => ['Isack',    'Hadjar',      'isack.hadjar@drivers.f1'],
    22 => ['Yuki',     'Tsunoda',     'yuki.tsunoda@drivers.f1'],
    27 => ['Nico',     'Hulkenberg',  'nico.hulkenberg@drivers.f1'],
    5  => ['Gabriel',  'Bortoleto',   'gabriel.bortoleto@drivers.f1'],
    31 => ['Esteban',  'Ocon',        'esteban.ocon@drivers.f1'],
    87 => ['Oliver',   'Bearman',     'oliver.bearman@drivers.f1'],
];
foreach ($driverData2025 as $num => [$fn, $ln, $email]) {
    $pid = $peopleByNum[$num] ?? null;
    if (!$pid) continue;
    $pwd = $fn . '123';
    createUser($db, "$fn $ln", $email, $pwd, 'driver', $pid);
}

// Media accounts
createUser($db, 'Sky Sports F1',   'skysports@media.f1', 'Sky123',   'media', 0);
createUser($db, 'BBC Sport',       'bbc@media.f1',       'BBC123',   'media', 0);

// Fan accounts
createUser($db, 'Fan User One',    'fan1@f1fans.com', 'Fan123',  'fan', 0);
createUser($db, 'Fan User Two',    'fan2@f1fans.com', 'Fan123',  'fan', 0);

echo "User accounts created\n";

// ─── STEP 17: CREDENTIALS FILE ───────────────────────────────────────────────

$creds = [];
// Admin (default, created at install)
$creds[] = ['admin',        'Admin',         'admin@f1app.com',   'Admin123',      'N/A'];
$creds[] = ['race_director','Race Director',  'rd@f1app.com',      'Director123',   'N/A'];

$teamNames = [];
foreach ($db->query("SELECT id, name FROM teams WHERE id <= 10") as $r) {
    $teamNames[(int)$r['id']] = $r['name'];
}

foreach ($teamManagers as $tid => [$fn, $ln, $email, $lt]) {
    $creds[] = ['team_manager', "$fn $ln", $email, $fn . '123', $teamNames[$tid] ?? ''];
}
foreach ($engineers as $tid => [$fn, $ln, $email, $lt]) {
    $creds[] = ['engineer', "$fn $ln", $email, $fn . '123', $teamNames[$tid] ?? ''];
}
foreach ($driverData2025 as $num => [$fn, $ln, $email]) {
    // Find team
    $pid = $peopleByNum[$num] ?? null;
    $tsId = $driverTeam[2025][$pid] ?? 0;
    $teamId = 0;
    foreach ($tsIds[$s25] as $tid => $ts) { if ($ts === $tsId) { $teamId = $tid; break; } }
    $creds[] = ['driver', "$fn $ln", $email, $fn . '123', $teamNames[$teamId] ?? ''];
}
$creds[] = ['media', 'Sky Sports F1',   'skysports@media.f1', 'Sky123',  'N/A'];
$creds[] = ['media', 'BBC Sport',       'bbc@media.f1',       'BBC123',  'N/A'];
$creds[] = ['fan',   'Fan User One',    'fan1@f1fans.com',    'Fan123',  'N/A'];
$creds[] = ['fan',   'Fan User Two',    'fan2@f1fans.com',    'Fan123',  'N/A'];

$lines  = "F1 Racing Management System — User Credentials Reference\n";
$lines .= "Generated: " . date('Y-m-d H:i:s') . "\n";
$lines .= str_repeat('=', 100) . "\n";
$lines .= sprintf("%-15s %-25s %-40s %-16s %s\n", 'ROLE', 'NAME', 'EMAIL', 'PASSWORD', 'TEAM');
$lines .= str_repeat('-', 100) . "\n";

$roleOrder = ['admin','race_director','team_manager','engineer','driver','media','fan'];
usort($creds, function($a, $b) use ($roleOrder) {
    $ri = array_search($a[0], $roleOrder);
    $rj = array_search($b[0], $roleOrder);
    if ($ri !== $rj) return $ri - $rj;
    return strcmp($a[1], $b[1]);
});

foreach ($creds as $c) {
    $lines .= sprintf("%-15s %-25s %-40s %-16s %s\n", $c[0], $c[1], $c[2], $c[3], $c[4]);
}

file_put_contents(__DIR__ . '/credentials_reference.txt', $lines);
echo "credentials_reference.txt written\n";

// ─── STEP 18: VERIFICATION COUNTS ───────────────────────────────────────────

echo "\n=== VERIFICATION ===\n";

$counts = [
    'seasons'              => "SELECT COUNT(*) FROM seasons",
    'teams'                => "SELECT COUNT(*) FROM teams WHERE id <= 10",
    'people_active'        => "SELECT COUNT(*) FROM people WHERE is_active=1 AND requested_by_team_id IS NULL",
    'team_seasons'         => "SELECT COUNT(*) FROM team_seasons",
    'driver_seasons'       => "SELECT COUNT(*) FROM driver_seasons WHERE status='active'",
    'races_total'          => "SELECT COUNT(*) FROM races",
    'race_entries'         => "SELECT COUNT(*) FROM race_entries",
    'qualifying_results'   => "SELECT COUNT(*) FROM qualifying_results",
    'race_results'         => "SELECT COUNT(*) FROM race_results",
    'sprint_results'       => "SELECT COUNT(*) FROM sprint_results",
    'pit_stops'            => "SELECT COUNT(*) FROM pit_stops",
    'lap_telemetry'        => "SELECT COUNT(*) FROM lap_telemetry",
    'penalties'            => "SELECT COUNT(*) FROM penalties",
    'driver_standings'     => "SELECT COUNT(*) FROM driver_standings",
    'constructor_standings'=> "SELECT COUNT(*) FROM constructor_standings",
    'users'                => "SELECT COUNT(*) FROM users",
];

foreach ($counts as $label => $sql) {
    $n = (int)$db->query($sql)->fetchColumn();
    echo "  $label: $n\n";
}

echo "\nEntries per race:\n";
foreach ($db->query("SELECT race_id, COUNT(DISTINCT person_id) as d FROM race_entries GROUP BY race_id ORDER BY race_id") as $r) {
    $flag = ((int)$r['d'] !== 20) ? ' *** WRONG ***' : '';
    echo "  Race {$r['race_id']}: {$r['d']} drivers$flag\n";
}

echo "\n=== SEED COMPLETE ===\n";

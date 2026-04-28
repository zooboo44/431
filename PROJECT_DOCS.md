# F1 Racing Management System — Project Documentation

> **University project demonstrating Role-Based Access Control (RBAC) and full-stack web development**  
> Built with PHP 8.1 · MySQL 8.0 · Vanilla JavaScript · Custom CSS · Apache 2.4

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Folder and File Structure](#2-folder-and-file-structure)
3. [Database Schema](#3-database-schema)
4. [Domain Model and F1 Logic](#4-domain-model-and-f1-logic)
5. [Role-Based Access Control](#5-role-based-access-control)
6. [Security Implementation](#6-security-implementation)
7. [Key Features by Role](#7-key-features-by-role)
8. [Account Management System](#8-account-management-system)
9. [Data Flow](#9-data-flow)
10. [Setup and Deployment](#10-setup-and-deployment)
11. [Known Limitations and Future Improvements](#11-known-limitations-and-future-improvements)

---

## 1. Project Overview

### What It Is

The F1 Racing Management System is a multi-user web portal that simulates the digital infrastructure of a Formula 1 racing organisation. It provides role-specific portals for every stakeholder in an F1 team — from the central governing administrator down to individual fans — each with tightly scoped access to only the data and operations their role requires.

### Problem It Solves

Real-world sporting organisations manage sensitive data across multiple departments. A race engineer should not be able to see rival teams' telemetry. A driver should see their own results but not be able to edit them. A media organisation needs read-only access to public race data. This system demonstrates how a well-designed RBAC model solves that access-control problem, while also modelling the real complexity of F1 data: multi-season standings, qualifying sessions, sprint weekends, pit stop records, lap telemetry, and penalties.

### Academic Context

This is a university project demonstrating:
- **Full-stack PHP development** with structured MVC-like separation of concerns
- **Role-Based Access Control (RBAC)** enforced at both the middleware and SQL layer
- **Relational database design** with normalised tables, foreign keys, and computed standings
- **Security best practices**: bcrypt password hashing, CSRF tokens, prepared statements, session hardening, and audit logging

### Technology Stack

| Layer | Technology |
|-------|-----------|
| Server-side language | PHP 8.1 |
| Database | MySQL 8.0 |
| Frontend logic | Vanilla JavaScript (ES6+) |
| Styling | Custom CSS (dark theme, CSS variables) |
| Web server | Apache 2.4 with mod_rewrite |
| Password hashing | bcrypt via `password_hash()` (cost 12) |
| Session storage | PHP native sessions + MySQL session table for restricted roles |
| Charts | Chart.js (CDN, driver dashboard only) |

### Local Access

Once deployed, the application is accessible at:
- **Portal login**: `http://localhost/f1app/auth/login.php`
- **Public landing**: `http://localhost/f1app/`
- **Default admin**: `admin@f1app.com` / `Admin123!`

---

## 2. Folder and File Structure

```
f1app/
├── admin/                        # Admin-only pages
│   ├── audit_log.php             # Paginated view of the full audit trail
│   ├── audit_log_detail.php      # Single audit log entry detail
│   ├── circuit_detail.php        # Admin detail view of a circuit
│   ├── circuits.php              # List and manage circuits
│   ├── circuits_create.php       # Create a new circuit record
│   ├── circuits_edit.php         # Edit an existing circuit
│   ├── dashboard.php             # Admin overview: counts, recent activity
│   ├── delete.php                # Generic POST handler for entity deletion
│   ├── penalties.php             # View and manage all race penalties
│   ├── people.php                # Driver records + pending driver/engineer approval
│   ├── people_create.php         # Add a new driver person record
│   ├── people_edit.php           # Edit an existing driver record
│   ├── person_detail.php         # Full driver profile with season history
│   ├── pitstops.php              # Admin cross-team view of all pit stop data
│   ├── race_detail.php           # Admin detail view of a race
│   ├── races.php                 # List and manage all races
│   ├── races_create.php          # Create a new race in a season
│   ├── races_edit.php            # Edit race details and status
│   ├── reset_token.php           # Generate a password reset link for any user
│   ├── results_overview.php      # Summary of results across all races
│   ├── season_detail.php         # Season overview with team/driver registrations
│   ├── season_registrations.php  # Manage which teams compete in a season
│   ├── seasons.php               # List and manage seasons
│   ├── seasons_create.php        # Create a new season year
│   ├── standings.php             # Admin view and recalculate standings
│   ├── team_detail.php           # Full team profile with season history
│   ├── teams.php                 # List and manage all teams
│   ├── teams_create.php          # Create a new team
│   ├── teams_edit.php            # Edit team details
│   ├── telemetry.php             # Admin cross-team view of all lap telemetry
│   ├── toggle.php                # POST handler to activate/deactivate entities
│   ├── users.php                 # User account list with activate/deactivate/delete
│   ├── users_create.php          # Create a user with role-based person/team linking
│   └── users_edit.php            # Edit a user account
│
├── assets/
│   ├── css/
│   │   └── style.css             # Single dark-theme stylesheet with CSS variables
│   └── js/
│       └── main.js               # Sortable tables, search filter, CSRF confirm dialogs,
│                                 #   live password strength validation
│
├── auth/
│   ├── login.php                 # Login form: rate limiting, CSRF, session setup
│   ├── logout.php                # Destroy session, delete session row, redirect
│   └── reset_password.php        # Token-based password reset form
│
├── config/
│   ├── constants.php             # APP_URL, points tables, role colours, rate-limit config
│   └── database.php              # PDO singleton with utf8mb4, exception mode
│
├── driver/
│   ├── dashboard.php             # Personal stats, Chart.js points progression, recent results
│   ├── penalties.php             # Driver's own penalty history
│   ├── race.php                  # Driver's view of a single race (own data only)
│   └── season.php                # Driver's season breakdown
│
├── engineer/
│   ├── dashboard.php             # Team overview, last race summary, quick add buttons
│   ├── pitstops.php              # View pit stop data for own team only
│   ├── pitstops_add.php          # Form to add a pit stop record
│   ├── telemetry.php             # View lap telemetry for own team only
│   └── telemetry_add.php         # Form to add lap telemetry data
│
├── exports/                      # CSV export output directory (team_manager/export.php)
│
├── fan/
│   └── dashboard.php             # Standings, recent/upcoming races, circuit list
│
├── includes/
│   ├── 403.php                   # Access-denied error page
│   ├── footer.php                # HTML closing tags, deferred JS includes
│   ├── functions.php             # Shared helpers: formatting, validation, standings calc
│   └── header.php                # Session bootstrap, role nav, sidebar rendering
│
├── media/
│   ├── dashboard.php             # Media portal: recent results, standings summary
│   └── season_detail.php         # Full season summary for media use
│
├── middleware/
│   └── auth_check.php            # startSecureSession, requireLogin, requireRole,
│                                 #   CSRF, single-session enforcement, displaced-session check
│
├── public/                       # (Static public assets if any)
│
├── race_director/
│   ├── circuits.php              # Read-only circuit list for race director
│   ├── dashboard.php             # Upcoming races, recent completed races
│   ├── penalties.php             # Issue and manage race penalties
│   ├── qualifying.php            # Enter Q1/Q2/Q3 times and grid positions
│   ├── race_entries.php          # Manage driver entries for a specific race
│   ├── results.php               # Enter race results (triggers standings recalculation)
│   └── sprint.php                # Enter sprint race results
│
├── shared/
│   ├── change_password.php       # Password change form; handles forced first-login flow
│   ├── circuit_detail.php        # Circuit info, lap records, races held there
│   ├── circuits.php              # Circuit list accessible to all authenticated roles
│   ├── driver_detail.php         # Public driver profile: career stats, season history
│   ├── profile.php               # User's own profile page
│   ├── race_detail.php           # Full race result: qualifying, sprint, race, penalties
│   ├── results.php               # Season-level results overview
│   ├── standings.php             # Driver and constructor championship standings
│   └── team_detail.php           # Team profile: drivers, season stats, car info
│
├── team_manager/
│   ├── circuits.php              # Read-only circuits for team manager
│   ├── dashboard.php             # Team stats, constructor position, driver standings
│   ├── driver_profile.php        # View a driver's full profile
│   ├── drivers.php               # Request new drivers/engineers, view pending requests
│   ├── export.php                # Export team race data to CSV
│   ├── pitstops.php              # View pit stop data for own team
│   ├── race_data.php             # Combined race data view (telemetry + pit stops)
│   ├── results.php               # Race results filtered to own team
│   └── telemetry.php             # Lap telemetry for own team
│
├── credentials_reference.txt     # All seed account emails and passwords
├── f1app_ddl.sql                 # Full database DDL (mysqldump output)
├── index.php                     # Public landing page (no login required)
├── PROJECT_DOCS.md               # This documentation file
├── README.md                     # Quick-start guide
└── seed_data.php                 # Idempotent seed script (safe to re-run)
```

---

## 3. Database Schema

The database contains **23 tables** organised around the core F1 domain model.

### 3.1 Core Entity Tables

#### `users`
Stores all login accounts. One row per account.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | Auto-increment |
| `name` | VARCHAR(100) | Display name |
| `email` | VARCHAR(150) UNIQUE | Login identifier |
| `password_hash` | VARCHAR(255) | bcrypt hash (cost 12) |
| `role` | ENUM | `admin`, `race_director`, `team_manager`, `engineer`, `driver`, `media`, `fan` |
| `linked_id` | INT nullable | For `driver`: `people.id`; for `team_manager`/`engineer`: `teams.id` |
| `is_active` | TINYINT | 0 = disabled, 1 = active |
| `must_change_password` | TINYINT | 1 = forced change on next login |
| `last_login` | TIMESTAMP nullable | Updated on every successful login |
| `created_at` | TIMESTAMP | Account creation time |
| `created_by` | INT FK → `users.id` | Which admin created this account |

**Key design note:** `linked_id` is a polymorphic FK — it means different things depending on `role`. Drivers link to their person record; team managers and engineers link to their team. Media and fan accounts have `linked_id = NULL`.

---

#### `people`
Stores driver biographical records. Separate from `users` — a person can exist without an account.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `first_name` | VARCHAR(50) | |
| `last_name` | VARCHAR(50) | |
| `nationality` | VARCHAR(50) | |
| `date_of_birth` | DATE | Minimum age 18 validated server-side |
| `racing_number` | INT UNIQUE | Car number (e.g. 44 for Hamilton) |
| `bio` | TEXT nullable | Free-text biography |
| `is_active` | TINYINT | 0 while pending approval |
| `requested_by_team_id` | INT FK → `teams.id` nullable | Non-NULL indicates a pending driver request awaiting admin approval |
| `created_at` | TIMESTAMP | |

---

#### `teams`
One row per F1 constructor team.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `name` | VARCHAR(100) | Full team name |
| `short_name` | VARCHAR(10) | Abbreviation (e.g. `RBR`) |
| `nationality` | VARCHAR(50) | Team's country |
| `founded_year` | INT | |
| `is_active` | TINYINT | Inactive teams are hidden from dropdowns |

---

#### `circuits`
One row per racing circuit.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `name` | VARCHAR(100) | Full circuit name |
| `country` | VARCHAR(50) | |
| `city` | VARCHAR(50) | Host city |
| `length_km` | FLOAT | Track length in kilometres |
| `number_of_laps` | INT | Standard race lap count |
| `circuit_type` | ENUM | `permanent` or `street` |
| `lap_record_ms` | INT nullable | Fastest lap in milliseconds |
| `lap_record_person_id` | INT FK → `people.id` nullable | Who holds the lap record |
| `is_active` | TINYINT | |

---

### 3.2 Season Structure Tables

#### `seasons`
One row per championship year.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `year` | YEAR UNIQUE | 2023, 2024, 2025 |
| `champion_person_id` | INT FK → `people.id` nullable | Filled when season ends |
| `champion_team_id` | INT FK → `teams.id` nullable | Constructor champion |
| `is_active` | TINYINT | Only one season is active at a time |

---

#### `team_seasons`
Joins teams to seasons with that season's specific configuration.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `team_id` | INT FK → `teams.id` | |
| `season_id` | INT FK → `seasons.id` | |
| `principal` | VARCHAR(100) | Team principal that year |
| `car_name` | VARCHAR(100) | Car designation (e.g. `W16`) |
| `power_unit` | VARCHAR(100) | Engine supplier |
| `base_location` | VARCHAR(100) | Factory location |
| `total_points` | FLOAT | Denormalised from `constructor_standings`; updated on recalculation |

Each team participates in each season via one `team_seasons` row. Race entries and telemetry all trace back to this row, ensuring data is scoped to the correct team-season combination.

---

#### `driver_seasons`
Links drivers to specific team-seasons, tracking their status throughout the year.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `person_id` | INT FK → `people.id` | |
| `team_season_id` | INT FK → `team_seasons.id` | Which team-season slot |
| `season_id` | INT FK → `seasons.id` | Denormalised for efficient queries |
| `status` | ENUM | `active`, `replaced`, `injured`, `inactive` |
| `joined_round` | INT | Round number the driver joined |
| `left_round` | INT nullable | Round number the driver left |

---

### 3.3 Race Weekend Tables

#### `races`
One row per Grand Prix weekend.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `season_id` | INT FK → `seasons.id` | |
| `circuit_id` | INT FK → `circuits.id` | |
| `name` | VARCHAR(100) | e.g. `Bahrain Grand Prix` |
| `round_number` | INT | Position in the season calendar |
| `race_date` | DATE | |
| `qualifying_date` | DATE nullable | |
| `has_sprint` | TINYINT | Whether a sprint race is scheduled |
| `sprint_date` | DATE nullable | |
| `status` | ENUM | `scheduled`, `in_progress`, `completed`, `cancelled` |

---

#### `race_entries`
One row per driver per race — the central linking table for race weekend data.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `race_id` | INT FK → `races.id` | |
| `person_id` | INT FK → `people.id` | |
| `team_season_id` | INT FK → `team_seasons.id` | Determines which team-season this entry belongs to |

All results, telemetry, pit stops, and qualifying data join through `race_entries`. This ensures a single consistent path from any data point back to driver, team, race, and season.

---

### 3.4 Results Tables

#### `race_results`
One row per `race_entry` (one driver per race).

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `race_entry_id` | INT FK UNIQUE | One result per entry |
| `start_position` | INT | Grid position |
| `finish_position` | INT nullable | `NULL` for DNS |
| `points_scored` | FLOAT | Points after fastest-lap bonus |
| `total_race_time_ms` | INT nullable | Total elapsed race time |
| `fastest_lap_ms` | INT nullable | Fastest lap time set by this driver |
| `fastest_lap_bonus` | TINYINT | 1 if fastest lap bonus applied |
| `laps_completed` | INT | Number of laps completed |
| `status` | ENUM | `finished`, `DNF`, `DNS`, `DSQ` |

---

#### `qualifying_results`
One row per `race_entry` (one qualifying record per driver per race).

| Column | Type | Notes |
|--------|------|-------|
| `race_entry_id` | INT FK UNIQUE | |
| `q1_time_ms` | INT nullable | Q1 lap time in milliseconds |
| `q2_time_ms` | INT nullable | Q2 lap time (NULL if eliminated in Q1) |
| `q3_time_ms` | INT nullable | Q3 lap time (NULL if eliminated in Q1/Q2) |
| `grid_position` | INT | Final grid position after qualifying |
| `eliminated_in` | ENUM nullable | `Q1` or `Q2` if eliminated early |

---

#### `sprint_results`
One row per `race_entry` for sprint weekends only.

| Column | Type | Notes |
|--------|------|-------|
| `race_entry_id` | INT FK UNIQUE | |
| `finish_position` | INT nullable | |
| `points_scored` | FLOAT | Sprint points (1–8) |
| `status` | ENUM | `finished`, `DNF`, `DNS`, `DSQ` |

---

### 3.5 Telemetry and Pit Stop Tables

#### `lap_telemetry`
One row per lap per driver per race. Can be bulk-entered by engineers.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `race_entry_id` | INT FK | |
| `lap_number` | INT | |
| `lap_time_ms` | INT | Total lap time in milliseconds |
| `sector1_ms` | INT nullable | Sector 1 time |
| `sector2_ms` | INT nullable | Sector 2 time |
| `sector3_ms` | INT nullable | Sector 3 time |
| `speed_trap_kmh` | FLOAT nullable | Top speed in km/h |
| `is_pit_lap` | TINYINT | Whether this lap included a pit stop |
| `tyre_compound` | ENUM nullable | `soft`, `medium`, `hard`, `intermediate`, `wet` |
| `tyre_age_laps` | INT nullable | How many laps on this set of tyres |
| `tyre_condition` | ENUM | `good`, `worn`, `critical` |

---

#### `pit_stops`
One row per pit stop event. Has a UNIQUE constraint on `(race_entry_id, stop_number)`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `race_entry_id` | INT FK | |
| `stop_number` | INT | 1st stop, 2nd stop, etc. |
| `lap_number` | INT | Lap on which the pit stop occurred |
| `duration_ms` | INT nullable | Pit stop service time in milliseconds |
| `tyre_in` | ENUM nullable | Compound removed |
| `tyre_out` | ENUM nullable | Compound fitted |

---

### 3.6 Standing Tables

#### `driver_standings`
Recalculated by `recalculateStandings()` in `includes/functions.php` after every race result submission.

| Column | Type | Notes |
|--------|------|-------|
| `season_id` | INT FK | |
| `person_id` | INT FK | |
| `points` | FLOAT | Total championship points |
| `wins` | INT | Race wins |
| `podiums` | INT | Top 3 finishes |
| `dnfs` | INT | Did Not Finish count |
| `fastest_laps` | INT | Fastest lap bonuses earned |
| `position` | INT | Championship position (1 = leader) |

#### `constructor_standings`
Same pattern as driver standings but aggregated per team.

| Column | Type | Notes |
|--------|------|-------|
| `season_id` | INT FK | |
| `team_id` | INT FK | |
| `points` | FLOAT | Sum of both drivers' points |
| `wins` | INT | |
| `position` | INT | Championship position |

---

### 3.7 Governance and Security Tables

#### `penalties`
Race penalties issued by the race director.

| Column | Type | Notes |
|--------|------|-------|
| `race_id` | INT FK | |
| `person_id` | INT FK → `people.id` | The penalised driver |
| `issued_by` | INT FK → `users.id` NOT NULL | Who issued the penalty |
| `penalty_type` | ENUM | `time_penalty`, `grid_penalty`, `licence_points`, `dsq`, `warning` |
| `reason` | TEXT | Mandatory description |
| `time_penalty_s` | INT nullable | Seconds added to race time |
| `grid_penalty_positions` | INT nullable | Grid positions dropped for next race |
| `licence_points_awarded` | INT nullable | Superlicence points applied |
| `is_dsq` | TINYINT | Whether this resulted in disqualification |
| `issued_at` | TIMESTAMP | |

---

#### `audit_log`
Immutable append-only record of every significant action.

| Column | Type | Notes |
|--------|------|-------|
| `user_id` | INT FK nullable | NULL for unauthenticated actions |
| `action` | VARCHAR(100) | e.g. `login_success`, `delete`, `approve` |
| `resource` | VARCHAR(50) nullable | Table name affected |
| `resource_id` | INT nullable | PK of the affected row |
| `details` | TEXT nullable | Human-readable description |
| `ip_address` | VARCHAR(45) | Supports IPv6 |
| `created_at` | TIMESTAMP | |

---

#### `sessions`
Active session tracking for restricted roles (enforces single-session concurrency).

| Column | Type | Notes |
|--------|------|-------|
| `id` | VARCHAR(128) PK | PHP session ID |
| `user_id` | INT FK | |
| `ip_address` | VARCHAR(45) | |
| `user_agent` | TEXT | Browser/client string |
| `last_activity` | TIMESTAMP | Updated on each request for restricted roles |

---

#### `roles_permissions`
Reference table defining which resources each role may access.

| Column | Type | Notes |
|--------|------|-------|
| `role` | VARCHAR(30) | |
| `resource` | VARCHAR(50) | Table/resource name |
| `action` | ENUM | `create`, `read`, `update`, `delete` |

---

#### `engineer_requests`
Pending engineer access requests submitted by team managers.

| Column | Type | Notes |
|--------|------|-------|
| `first_name` | VARCHAR(50) | Requested engineer's name |
| `last_name` | VARCHAR(50) | |
| `requested_by_team_id` | INT FK → `teams.id` | Requesting team |
| `created_at` | TIMESTAMP | |

---

#### `password_reset_tokens`
One-use tokens for the forgot-password flow.

| Column | Type | Notes |
|--------|------|-------|
| `user_id` | INT FK | |
| `token_hash` | VARCHAR(64) UNIQUE | SHA-256 hash of the token |
| `expires_at` | TIMESTAMP | 1-hour TTL |
| `used_at` | TIMESTAMP nullable | Marked when consumed |

---

### 3.8 Entity Relationship Summary

```
seasons ─────────────── team_seasons ──────── teams
    │                        │
    │                    driver_seasons ────── people
    │
    └─── races ─────────── race_entries ──────┘
              │                  │
              │                  ├── race_results
              │                  ├── qualifying_results
              │                  ├── sprint_results
              │                  ├── lap_telemetry
              │                  └── pit_stops
              │
              └── penalties
                      │
                      └── users (issued_by)

users ──── linked_id ──── people (drivers)
       └── linked_id ──── teams (team_manager, engineer)

driver_standings ──── seasons + people
constructor_standings ──── seasons + teams
```

---

## 4. Domain Model and F1 Logic

### 4.1 Seasons and Teams

Formula 1 operates on annual championship seasons. Each year, teams enter with new car designations and sometimes different personnel. The `seasons` table tracks years; `team_seasons` captures the team's entry for that specific year — crucially with the car name, power unit supplier, and team principal that applied *that season*. A team's `teams` record holds timeless data (founding year, nationality); `team_seasons` holds the season-specific snapshot.

### 4.2 Drivers

Drivers are modelled as `people` records with biographical data and a unique racing number. A driver participates in a season through `driver_seasons`, which links them to a `team_seasons` entry. The `status` field tracks whether a driver is active, injured, replaced mid-season, or a reserve. A driver may have `driver_seasons` rows across multiple years, enabling multi-season career statistics.

### 4.3 Race Weekends

Each `races` row represents a Grand Prix weekend at a specific `circuit`. The race has a status lifecycle: `scheduled → in_progress → completed`. Weekends with `has_sprint = 1` include a Saturday sprint race with its own abbreviated points.

### 4.4 Race Entries

Before a race weekend, the race director creates `race_entries` — one per driver — confirming which drivers and teams are participating. These entries are the anchor point for all race weekend data.

### 4.5 Qualifying

Qualifying determines the starting grid. F1 uses an elimination format:
- **Q1**: All drivers set times; slowest 5 eliminated (grid positions 16–20)
- **Q2**: Remaining 15 set times; slowest 5 eliminated (grid positions 11–15)
- **Q3**: Top 10 set times; determines positions 1–10

The `qualifying_results` table stores the time set in each segment and the final `grid_position`. The `eliminated_in` field records whether a driver was knocked out in Q1 or Q2.

### 4.6 Race Results and Points

Points are awarded to the top 10 finishers:

| Position | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 |
|----------|---|---|---|---|---|---|---|---|---|----|
| Points   | 25 | 18 | 15 | 12 | 10 | 8 | 6 | 4 | 2 | 1 |

An additional **+1 bonus point** is awarded to the driver who sets the fastest lap, provided they finish in the top 10.

Sprint race points (top 8):

| Position | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 |
|----------|---|---|---|---|---|---|---|---|
| Points   | 8 | 7 | 6 | 5 | 4 | 3 | 2 | 1 |

### 4.7 Standings Recalculation

When the race director saves race results, `recalculateStandings(seasonId)` in `includes/functions.php` is called automatically. It:
1. Aggregates all `race_results.points_scored` for each driver across all races in the season
2. Sorts by points descending, breaking ties by wins
3. UPSERTs into `driver_standings` with the new position and accumulated stats
4. Does the same for teams in `constructor_standings`, summing both drivers' points
5. Updates `team_seasons.total_points` to match the new constructor total

### 4.8 Telemetry and Pit Stops

Engineers record lap-by-lap telemetry (`lap_telemetry`) and pit stop events (`pit_stops`) for their team's drivers. These are operational data — not used in standings — but visible to the team manager, engineer, and admin. Times are stored in milliseconds throughout for consistent precision.

### 4.9 Penalties

The race director may issue penalties of five types: time penalties (seconds added to race time), grid penalties (positions dropped from the next race's starting grid), licence points (towards race suspension), disqualification (removes all points for that race), and formal warnings. Penalties are recorded against a specific race and driver, with the issuing user tracked for audit purposes.

---

## 5. Role-Based Access Control

The system implements seven roles with strictly scoped permissions.

### 5.1 Role Summary Table

| Role | Primary Responsibility | Linked To |
|------|----------------------|-----------|
| `admin` | Full system administration | None |
| `race_director` | Race weekend management | None |
| `team_manager` | Own team operations | `teams.id` |
| `engineer` | Own team telemetry/pit data | `teams.id` |
| `driver` | Personal data view | `people.id` |
| `media` | Read-only public data | None |
| `fan` | Read-only public data | None |

---

### 5.2 Admin

**Can view:** Everything — all users, all teams, all drivers, all seasons, all races, all results, all penalties, all telemetry, all pit stops, all circuits, audit log.

**Can create:** Teams, circuits, seasons, races, driver records, user accounts for all roles.

**Can edit:** Any team, circuit, driver record, race, season, user account, standings.

**Can delete:** Users (except protected user #1), driver records (cascades results), races, circuits, teams, individual telemetry rows, pit stop rows.

**Special powers:** Approve or reject pending driver and engineer requests from team managers; activate/deactivate any user; reset any user's password; view the full audit log.

---

### 5.3 Race Director

**Can view:** Race calendar, race entries, qualifying results, race results, sprint results, penalties, driver standings, constructor standings, circuits, teams, seasons, drivers.

**Can create:** Race entries (add a driver to a race), qualifying results, race results, sprint results, race penalties.

**Can edit:** Race entries, qualifying results, race results, sprint results; mark races as completed.

**Cannot access:** User management, telemetry, pit stops, audit log. Cannot create or modify teams, circuits, or seasons.

---

### 5.4 Team Manager

**Can view:** Own team's results, own team's telemetry, own team's pit stops, own team's drivers, own team's race data, standings (all teams), circuits, upcoming races.

**Can create:** Driver requests (submits a new driver for admin approval), engineer requests.

**Can edit:** Own team's driver roster (request additions), own team's race entries (limited scope).

**Cannot access:** Other teams' data, user management, audit log, financial/admin data.

---

### 5.5 Engineer

**Can view:** Own team's telemetry, own team's pit stops, standings (all), race results (all).

**Can create:** Lap telemetry records for own team's drivers, pit stop records for own team's drivers.

**Can edit:** Existing telemetry and pit stop records for own team.

**Can delete:** Own team's telemetry and pit stop records.

**Cannot access:** Other teams' data, user management, race results entry, qualifying, audit log.

---

### 5.6 Driver

**Can view:** Own personal stats, own season history, own race-by-race results, own qualifying positions, own penalties, driver standings (all), constructor standings (all).

**Cannot view:** Other drivers' telemetry, other teams' pit data, user accounts.

**Cannot create or edit anything:** Drivers are entirely read-only users. They cannot edit their own results.

---

### 5.7 Media

**Can view:** All publicly available race data — results, standings, circuits, seasons, driver profiles, team profiles. Read-only access to all public-facing pages.

**Cannot create, edit, or delete** anything.

**Cannot view:** Individual telemetry data, user management, audit log.

---

### 5.8 Fan

Same as media — full read-only access to public data. The only functional difference from media in the current implementation is the dashboard layout and sidebar navigation grouping.

---

### 5.9 RBAC Enforcement in Code

**Middleware layer** (`middleware/auth_check.php`):

```php
function requireRole(string ...$roles): void {
    requireLogin();
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $roles, true)) {
        logAudit($_SESSION['user_id'], 'permission_denied', null, null,
            'Attempted to access ' . ($_SERVER['REQUEST_URI'] ?? ''));
        include __DIR__ . '/../includes/403.php';
        exit;
    }
}
```

Every protected page calls `requireRole('role_name')` immediately after including `header.php`, which itself calls `requireLogin()`. A user who navigates directly to `/admin/users.php` without the admin role gets a 403 page and an audit log entry.

**Data-layer isolation** for team-scoped roles: Every query for team manager, engineer, and driver data includes a `WHERE` clause filtering by `$_SESSION['linked_id']`:

```php
// Example: engineer only sees own team's telemetry
WHERE ts.team_id = ?    -- bound to $_SESSION['linked_id']
```

---

## 6. Security Implementation

### 6.1 Password Hashing

All passwords are hashed using bcrypt with cost factor 12:

```php
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
```

Verification uses `password_verify()`. Plaintext passwords are never stored or logged.

### 6.2 Password Constraints

Enforced server-side by `validatePassword()` in `includes/functions.php` and client-side in `main.js` via the `data-pw-validate` attribute pattern:

- Minimum 8 characters
- Maximum 25 characters
- At least one uppercase letter (A–Z)
- At least one lowercase letter (a–z)
- At least one digit (0–9)
- At least one special character from: `!@#$%^&*`

The client-side checker provides real-time feedback showing which requirements are still unmet.

### 6.3 First-Login Password Change

When an admin creates an account (for a new driver or engineer), `must_change_password = 1` is set. On next login, `enforcePasswordChange()` redirects every request to `/shared/change_password.php` until the password is changed. The new password must:
1. Meet all constraints above
2. Be **different** from the current stored password (preventing reuse of the temporary password):

```php
} elseif ($forced && password_verify($new, $row['password_hash'] ?? '')) {
    $errors[] = 'New password must be different from your temporary password.';
}
```

### 6.4 Session Security

Sessions are configured with hardened settings in `startSecureSession()`:

```php
ini_set('session.cookie_httponly', 1);     // Blocks JS access to cookie
ini_set('session.cookie_samesite', 'Lax'); // Prevents CSRF via cookie
ini_set('session.use_strict_mode', 1);     // Rejects unrecognised session IDs
```

Sessions expire after **2 hours of inactivity** (`SESSION_TIMEOUT = 7200`).

### 6.5 Single-Session Enforcement

For restricted roles (`admin`, `race_director`, `team_manager`, `engineer`, `driver`), a session row is written to the `sessions` table on login. A second login from another device or browser kills all previous sessions for that user. The displaced user receives a "signed in from another location" message on their next request.

### 6.6 CSRF Protection

Every form that writes data includes a CSRF token:

```php
// Generation (once per session, rotated after each successful mutation)
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Form field
<input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

// Verification
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { /* reject */ }

// Rotation after successful write
rotateCSRFToken();
```

### 6.7 SQL Injection Prevention

The application uses **only prepared statements** with bound parameters via PDO. There are no string-concatenated user inputs in SQL queries. Dynamic `WHERE` clauses (for filter pages) build the SQL structure separately and bind values through the parameterised execute call.

### 6.8 Output Encoding

All user-controlled data is HTML-escaped before output using the `h()` function:

```php
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
```

This prevents XSS on every page.

### 6.9 Rate Limiting

Login attempts are rate-limited via the audit log: if more than 5 `login_failed` events are recorded from the same IP within 15 minutes, further login attempts are blocked.

### 6.10 Security Headers

Set in `includes/header.php` before any output:

```php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
```

### 6.11 Audit Logging

Every significant action (login, logout, create, update, delete, approve, reject, permission denied) is written to `audit_log` with the user ID, action type, affected resource, and IP address. Audit logs cannot be deleted through the UI.

---

## 7. Key Features by Role

### 7.1 Admin User Journey

1. Log in → routed to `/admin/dashboard.php` showing counts of users, teams, races, and recent audit activity.
2. Navigate to **Users** → view all accounts sorted by role → create new accounts, edit existing, activate/deactivate, reset passwords.
3. Navigate to **Drivers** → see active drivers and pending approval requests from team managers → approve (entering email + temporary password) or reject.
4. Navigate to **Teams** → create teams → system prompts to create a team manager immediately after.
5. Navigate to **Seasons** → create a season year → navigate to **Races** to add Grand Prix events.
6. Navigate to **Standings** → manually trigger recalculation for any season if needed.
7. Navigate to **Telemetry** or **Pit Stops** → see all data across all teams with filters, delete erroneous records.
8. Navigate to **Audit Log** → review all user activity, investigate suspicious actions.

### 7.2 Race Director User Journey

1. Log in → routed to `/race_director/dashboard.php` showing upcoming and completed races.
2. **Before a race weekend**: Navigate to **Race Entries** → add all drivers to the race entry list.
3. **After qualifying**: Navigate to **Qualifying** → enter Q1/Q2/Q3 times and grid positions for all drivers.
4. **After sprint (if applicable)**: Navigate to **Sprint Results** → enter finish positions; system calculates and stores sprint points.
5. **After race**: Navigate to **Race Results** → enter finish positions, statuses (DNF/DNS/DSQ), total times, fastest lap; saving automatically triggers standings recalculation.
6. **After review**: Navigate to **Penalties** → issue time penalties, grid penalties, or disqualifications with mandatory reason text.
7. Browse **Standings** to verify the championship table reflects the latest results.

### 7.3 Team Manager User Journey

1. Log in → routed to `/team_manager/dashboard.php` showing team's constructor position, points, and driver standings.
2. Navigate to **Drivers** → view active drivers → submit a **new driver request** with biographical data (name, nationality, DOB, racing number). The driver appears as inactive until admin approves.
3. Navigate to **Drivers** → submit a **new engineer request** (first and last name only). Admin creates the account on approval.
4. Navigate to **Results** → view own team's race-by-race results with driver performance.
5. Navigate to **Race Data** → view combined telemetry and pit stop data for own team.
6. Navigate to **Telemetry** → browse own team's lap data with season/race/driver filters.
7. Navigate to **Pit Stops** → browse own team's pit stop history.
8. Use **Export** to download team data as CSV.

### 7.4 Engineer User Journey

1. Log in → routed to `/engineer/dashboard.php` showing team configuration, drivers' standings, and last race summary with quick-add links.
2. Navigate to **Telemetry** → view all lap data for own team → click **+ Telemetry** to enter a new lap record (lap number, lap time, sector times, speed trap, tyre data).
3. Navigate to **Pit Stops** → view all pit stop records for own team → add new stop records (stop number, lap, duration, tyre in/out).
4. The engineer can edit or delete their own team's telemetry and pit stop records to correct errors.

### 7.5 Driver User Journey

1. Log in → prompted to change temporary password (if first login).
2. Routed to `/driver/dashboard.php` → see career stats (total points, wins, podiums, fastest laps), a Chart.js line graph of cumulative points progression in the active season, and a table of season-by-season history.
3. Navigate to a specific season via the history table → see lap-by-lap performance.
4. Click a recent race → `/driver/race.php` → see own qualifying position, race result, any pit stops recorded.
5. Navigate to **Penalties** → see own penalty history.
6. Navigate to **Standings** → see the full championship table (not editable).

### 7.6 Media User Journey

1. Log in → routed to `/media/dashboard.php` showing latest results and standings summary.
2. Navigate to **Results** → browse all completed race results.
3. Navigate to **Standings** → view driver and constructor championships.
4. Navigate to **Seasons** → view a full season breakdown including all races.
5. Navigate to **Circuits** → browse circuit specifications.
6. Click any circuit, driver, or team to view detail pages.

### 7.7 Fan User Journey

1. Log in → routed to `/fan/dashboard.php` with season selector showing standings, recent results, upcoming races, and a circuit directory.
2. Use the season dropdown to switch between 2023, 2024, and 2025 data.
3. Navigate to **Standings** → full championship standings.
4. Navigate to **Results** → race-by-race results list.
5. Navigate to **Circuits** → circuit directory with detail pages.
6. Click driver/team names to view detail pages (career stats, team history).

---

## 8. Account Management System

### 8.1 The Person–User Separation

The system deliberately separates **person records** (`people` table) from **user accounts** (`users` table). A driver can exist as a person in the database — with biographical data, racing number, and race history — without having a portal login account. An account is only created when the admin approves a request. This mirrors reality: not every driver or team member needs digital portal access.

### 8.2 Driver Request Workflow

```
Team Manager                Admin                    System
     │                        │                        │
     ├── Fill out driver ─────►│                        │
     │   request form          │                        │
     │   (name, DOB,           │                        │
     │    nationality,         │                        │
     │    racing number)       │                        │
     │                         │                        │
     │   Person record created with is_active=0         │
     │   requested_by_team_id = team's ID               │
     │                         │                        │
     │                    Pending request               │
     │                    shown on admin/people.php     │
     │                         │                        │
     │                    Admin fills ─────────────────►│
     │                    email + temp                  │
     │                    password                      │
     │                         │                        │
     │                    Approve ──────────────────────►
     │                         │                   people: is_active=1
     │                         │                          requested_by_team_id=NULL
     │                         │                   users: new row, role=driver,
     │                         │                          must_change_password=1
     │                         │                   driver_seasons: active entry
     │                         │                          for current season
```

If the admin **rejects** the request, the person record is deleted entirely — no trace remains.

### 8.3 Engineer Request Workflow

```
Team Manager                Admin                    System
     │                        │                        │
     ├── Submit engineer ─────►│                        │
     │   request               │                        │
     │   (first + last name)   │                        │
     │                         │                        │
     │   engineer_requests row created                  │
     │                         │                        │
     │                    Admin fills ─────────────────►│
     │                    email + temp password         │
     │                         │                        │
     │                    Approve ──────────────────────►
     │                         │                   users: new row, role=engineer,
     │                         │                          linked_id=team_id,
     │                         │                          must_change_password=1
     │                         │                   engineer_requests: row deleted
```

Engineers are **not** in the `people` table — they link directly to a team via `users.linked_id`. Their access is team-scoped but they have no driver biographical record.

### 8.4 First-Login Password Change

When a user with `must_change_password = 1` logs in, `enforcePasswordChange()` intercepts every page request and redirects to `/shared/change_password.php`. The user cannot reach any other page until they set a new password. The new password must meet all constraints and must differ from the temporary password set by the admin. On successful change, `must_change_password` is set to `0` and the user is redirected to their role dashboard.

### 8.5 Manual Account Creation

Admins can also create accounts directly via `/admin/users_create.php` without the approval workflow. The process:
1. Select a role from the dropdown (triggers a page reload to show role-appropriate fields).
2. For `driver`: a dropdown of unlinked active people is shown (people who don't yet have a driver account).
3. For `team_manager` or `engineer`: a team dropdown is shown.
4. For `media` or `fan`: no linking field (account is standalone).
5. Admin sets the password directly; `must_change_password = 0` for admin-created accounts.

---

## 9. Data Flow

### 9.1 Race Result Submission → Standings

This is the most important data flow in the application.

```
Race Director enters results in race_director/results.php
    │
    ▼
POST request validated (CSRF token, requireRole('race_director'))
    │
    ▼
For each driver in the race:
    race_results row created/updated:
        start_position, finish_position, status, laps_completed,
        total_race_time_ms, fastest_lap_ms, fastest_lap_bonus, points_scored
    │
    ▼
recalculateStandings(seasonId) called:
    │
    ├── SELECT: all race_results for season → sum points, count wins/podiums/DNFs/FLs
    │   grouped by person_id
    ├── SORT: by points DESC, then wins DESC
    ├── UPSERT: driver_standings with new position, points, wins, podiums, dnfs, FLs
    │
    ├── SELECT: all race_results for season → sum points, count wins
    │   grouped by team_id (via team_seasons)
    ├── SORT: by points DESC, then wins DESC
    ├── UPSERT: constructor_standings with new position, points, wins
    │
    └── UPDATE: team_seasons.total_points from constructor_standings
```

After this flow completes, every standings page across all roles immediately reflects the new data — there is no caching layer.

### 9.2 Telemetry Submission → Engineer Dashboard

```
Engineer enters lap data in engineer/telemetry_add.php
    │
    ▼
Validates:
    - CSRF token
    - requireRole('engineer')
    - race_entry belongs to own team (re.team_season_id via ts.team_id = $_SESSION['linked_id'])
    │
    ▼
INSERT INTO lap_telemetry (race_entry_id, lap_number, lap_time_ms, ...)
    │
    ▼
Redirected to engineer/telemetry.php (own team filtered view)
    │
    ▼
Admin can see same data in admin/telemetry.php (all teams, no team filter)
Team manager sees same data in team_manager/telemetry.php
    (filtered: JOIN team_seasons WHERE team_id = $_SESSION['linked_id'])
```

### 9.3 Driver Request → Active Driver

```
Team manager fills form in team_manager/drivers.php
    │
    ▼
INSERT INTO people (first_name, last_name, ..., is_active=0,
                    requested_by_team_id = $_SESSION['linked_id'])
    │
    ▼
Admin sees pending request badge on admin/people.php
    │
    ▼
Admin fills email + temporary password, clicks Approve
    │
    ▼
Transaction:
    UPDATE people SET is_active=1, requested_by_team_id=NULL WHERE id=?
    INSERT INTO users (role='driver', linked_id=person_id, must_change_password=1, ...)
    INSERT INTO driver_seasons (person_id, team_season_id, season_id, status='active')
    │
    ▼
Driver logs in, redirected to change_password.php
Driver sets new password meeting constraints (different from temp)
    │
    ▼
Driver lands on driver/dashboard.php with full access to own data
```

---

## 10. Setup and Deployment

### 10.1 Prerequisites

- Ubuntu/Debian Linux (or equivalent)
- Apache 2.4 with `mod_rewrite` enabled
- PHP 8.1+ with extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`
- MySQL 8.0+

### 10.2 Step-by-Step Setup

**1. Clone/copy the project files:**
```bash
sudo cp -r f1app/ /var/www/html/f1app/
sudo chown -R www-data:www-data /var/www/html/f1app/
sudo chmod -R 755 /var/www/html/f1app/
```

**2. Create the MySQL database and import the schema:**
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS f1app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p f1app < /var/www/html/f1app/f1app_ddl.sql
```

**3. Configure database credentials** in `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'f1app');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

**4. Run the seed script** to populate all data:
```bash
php /var/www/html/f1app/seed_data.php
```

The seed script is idempotent — safe to run multiple times. It creates 3 seasons (2023, 2024, 2025), 10 teams, 20 active drivers, 24 races for the 2025 season with results, standings, telemetry, pit stops, and all user accounts. After running, refer to `credentials_reference.txt` for all login details.

**5. Configure Apache** — create a virtual host or use the default:
```apache
Alias /f1app /var/www/html/f1app
<Directory /var/www/html/f1app>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

**6. Enable Apache modules:**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**7. Set write permissions** on the exports directory:
```bash
sudo chmod 775 /var/www/html/f1app/exports/
```

**8. Access the application:**
- Open `http://localhost/f1app/` for the public landing page
- Open `http://localhost/f1app/auth/login.php` for the portal login
- Log in with `admin@f1app.com` / `Admin123!`

### 10.3 Default Admin Credentials

| Field | Value |
|-------|-------|
| Email | `admin@f1app.com` |
| Password | `Admin123!` |
| Role | admin |
| `must_change_password` | 0 (no forced change) |

All other seed account credentials are listed in `/var/www/html/f1app/credentials_reference.txt`.

### 10.4 Re-seeding a Clean Database

To reset all data:
```bash
mysql -u root -p -e "DROP DATABASE f1app; CREATE DATABASE f1app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p f1app < /var/www/html/f1app/f1app_ddl.sql
php /var/www/html/f1app/seed_data.php
```

---

## 11. Known Limitations and Future Improvements

### 11.1 Scope Simplifications (by design for the university project)

| Area | Current State | Production Equivalent |
|------|-------------|----------------------|
| Database credentials | Hardcoded in `config/database.php` | Environment variables or secrets manager |
| HTTPS | HTTP only (SSL cert warnings on localhost) | Full TLS with valid certificate |
| Email delivery | No email sending — reset tokens shown as links in UI | SMTP with secure transactional email |
| Password reset | Token link shown on screen | Token emailed to user's registered address |
| Session storage | MySQL `sessions` table | Redis or Memcached for scalability |
| File uploads | None — driver bios and team logos are text-only | S3-compatible object storage |
| API | None — server-rendered PHP only | REST API for mobile/third-party integration |
| Real-time data | No websockets — full page refreshes | WebSocket or server-sent events for live timing |
| Audit log | Admin-readable but not alerting | Automated anomaly detection and alerting |

### 11.2 Security Improvements Needed for Production

- **HTTPS enforcement**: All traffic should be over TLS; `session.cookie_secure` should be enabled.
- **Content Security Policy header**: Would prevent inline script execution and restrict resource loading.
- **Database user privileges**: The app currently runs as root. A production deployment would use a dedicated MySQL user with only the required table-level permissions.
- **Input length enforcement at the DB layer**: Some VARCHAR columns rely on PHP-side validation; database constraints would add defence in depth.
- **Two-factor authentication**: Admin and race director accounts especially would benefit from TOTP 2FA.
- **Secret rotation**: CSRF tokens, session secrets, and DB passwords should be rotatable without redeployment.

### 11.3 F1 Domain Simplifications

- **Race calendar**: The 2025 season has 24 races but only 5 circuits are configured (races 6–24 reuse earlier circuits). A real deployment would have a full 24-circuit calendar.
- **Tyre strategy logic**: Tyre data is recorded but no mandatory compound rules (each driver must use at least two different compounds) are enforced.
- **Safety car / virtual safety car**: Not modelled — the points system assumes clean race conditions.
- **DRS zones and sector splits**: Speed trap data is recorded as a single value rather than per-sector.
- **Superlicence points**: Licence points from penalties are recorded but no suspension threshold is enforced.
- **Champion tracking**: `seasons.champion_person_id` and `champion_team_id` are nullable and are not automatically populated when a driver clinches the title.

### 11.4 Scalability Constraints

- The telemetry table has no partitioning. At real F1 scale (1,400+ laps per race, 24 races, 20 drivers), the table would need partitioning by season and indexing strategy changes.
- `recalculateStandings()` recomputes all driver and constructor standings from scratch on every result save. For 24 races and 20 drivers this is fast; at scale it would need incremental updates.
- The `LIMIT 500` on the admin telemetry view prevents page timeouts but means the admin cannot see a full season's data in one query.

---

*Generated: 2026-04-28 — F1 Racing Management System*

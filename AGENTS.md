# AGENTS.md — Project Scope & Architecture Reference

This file describes the complete scope, architecture, and technical decisions
of the Gacha App project. It serves as a reference for any developer (or AI
agent) working on this codebase.

---

## Project Overview

A full-stack gacha pull simulator modeled after Genshin Impact's pull system.
Built from scratch as a learning project covering PHP, MySQL, Docker, Nginx,
Angular, and TypeScript.

**Live URLs (local dev):**
- `http://localhost:8080` — main app (Angular frontend via Nginx)
- `http://localhost:8081` — phpMyAdmin (database GUI)
- `http://localhost:4200` — Angular dev server (direct, bypasses Nginx)

---

## Architecture

```
Browser
  │
  ▼
Nginx (port 8080)
  ├── /api/*  → FastCGI → PHP-FPM container (port 9000)
  │                           └── MySQL container (port 3306 internal / 3307 host)
  └── /*      → Proxy → Angular dev server (port 4200)
```

All 5 services run in Docker containers on a shared internal network.
Containers communicate by service name (e.g. `db`, `app`, `frontend`).

---

## Tech Stack

| Layer          | Technology              | Version  |
|----------------|-------------------------|----------|
| Frontend       | Angular                 | 17       |
| Language       | TypeScript              | ~5.2     |
| Backend        | PHP-FPM                 | 8.5      |
| Database       | MySQL                   | 8.0      |
| Web server     | Nginx                   | Alpine   |
| Runtime        | Node.js                 | 20       |
| Containers     | Docker + Docker Compose | Latest   |
| Task runner    | GNU Make (via Scoop)    | Any      |
| OS (dev)       | Windows 11 + WSL2       | —        |

---

## Container Map

| Container     | Image              | Port (host→container) | Purpose                        |
|---------------|--------------------|-----------------------|--------------------------------|
| gacha-app     | custom (Dockerfile)| 9000 (internal only)  | PHP-FPM — runs API endpoints   |
| gacha-frontend| custom (frontend/) | 4200→4200             | Angular dev server             |
| gacha-nginx   | nginx:alpine       | 8080→80               | Reverse proxy + routing        |
| gacha-db      | mysql:8.0          | 3307→3306             | MySQL database                 |
| gacha-phpmyadmin | phpmyadmin      | 8081→80               | DB admin UI                    |

---

## File Structure

```
Gacha/
├── Dockerfile                     # PHP 8.5-FPM image with MySQL extensions
├── docker-compose.yml             # Orchestrates all 5 containers
├── Makefile                       # Shortcut commands (Windows/cmd.exe compatible)
├── COMMANDS.md                    # Raw PowerShell command reference
├── AGENTS.md                      # This file — project scope reference
├── README.md                      # GitHub readme
├── .env                           # DB credentials (gitignored)
├── .gitignore
│
├── nginx/
│   └── default.conf               # Routes /api/* to PHP, /* to Angular
│
├── api/                           # PHP REST API (JSON only, no HTML)
│   ├── db.php                     # Shared: DB connection + all gacha functions
│   ├── pull.php                   # POST /api/pull.php — run a pull
│   ├── history.php                # GET  /api/history.php — fetch pull history
│   └── stats.php                  # GET  /api/stats.php — fetch pity counts
│
├── src/                           # Legacy PHP frontend (pre-Angular)
│   ├── index.php                  # Original PHP-rendered gacha page (kept as reference)
│   ├── gacha.php                  # Older version of pull logic (kept as reference)
│   └── test.php                   # DB connection smoke test (delete before production)
│
├── sql/
│   ├── schema.sql                 # Creates items, pulls, user_stats tables
│   ├── seed.sql                   # Seeds 14 items across 3 rarity tiers
│   └── add_user_stats.sql         # Migration: adds user_stats table (one-time use)
│
└── frontend/                      # Angular 17 app
    ├── Dockerfile                 # Node 20 + Angular CLI dev server
    ├── package.json               # npm dependencies
    ├── tsconfig.json              # TypeScript config (types:[] prevents node conflicts)
    ├── angular.json               # Angular build config
    └── src/
        ├── index.html             # Shell HTML — only contains <app-root>
        ├── main.ts                # Entry point — bootstraps AppModule
        ├── styles.css             # Global styles (body, table, rarity colours)
        └── app/
            ├── app.module.ts      # Root module — registers all components
            ├── app.component.ts   # Root component — owns pityData + history state
            ├── app.component.html # Layout — assembles child components
            ├── app.component.css
            ├── services/
            │   └── gacha.service.ts    # All HTTP calls to PHP API
            └── components/
                ├── pull/               # Slot machine pull UI
                │   ├── pull.component.ts   # Spin logic, sound, flash, API call
                │   ├── pull.component.html # Three reels + pull button + result card
                │   └── pull.component.css  # All animation keyframes
                ├── pity-bar/           # 5-star and 4-star pity progress bars
                │   ├── pity-bar.component.ts
                │   ├── pity-bar.component.html
                │   └── pity-bar.component.css
                └── history/            # Recent pulls table + 100-pull popup
                    ├── history.component.ts
                    ├── history.component.html
                    └── history.component.css
```

---

## Database Schema

### `items`
Stores every pullable card.

| Column    | Type          | Notes                    |
|-----------|---------------|--------------------------|
| id        | INT PK AUTO   | unique identifier        |
| name      | VARCHAR(100)  | display name             |
| rarity    | TINYINT       | 3, 4, or 5               |
| image_url | VARCHAR(255)  | optional, for future art |

### `pulls`
One row per pull event. Never deleted — full audit trail.

| Column    | Type      | Notes                               |
|-----------|-----------|-------------------------------------|
| id        | INT PK AUTO | unique identifier                 |
| user_id   | INT       | hardcoded as 1 (no auth system yet) |
| item_id   | INT FK    | references items.id                 |
| pulled_at | TIMESTAMP | auto-set by MySQL DEFAULT           |

Indexes: `idx_user_id` on `user_id` for fast history queries.

### `user_stats`
One row per user. Stores pity counters that persist across sessions.

| Column          | Type | Notes                                    |
|-----------------|------|------------------------------------------|
| user_id         | INT PK | one row per user                       |
| pity_count      | INT  | pulls since last 5-star (resets on 5★)  |
| pity_count_4star| INT  | fixed 10-pull schedule counter           |

---

## Gacha System Rules

### 5-Star Pity
Staged rate that climbs every 10 pulls:

| Pull range | Rate   |
|------------|--------|
| 1–10       | 0.1%   |
| 11–20      | 1.0%   |
| 21–30      | 1.5%   |
| 31–40      | 2.0%   |
| 41–50      | 2.5%   |
| 51–60      | 5.0%   |
| 61–70      | 6.0%   |
| 71–80      | 7.0%   |
| 81–90      | 8.0%   |
| 91–99      | 20.0%  |
| 100        | 100%   |

Hard pity triggers at pull 100 (`pity_count >= 99`).
Soft pity begins at pull 11 (`pity_count >= 10`).

### 4-Star Pity
Fixed 10-pull schedule. `pity_count_4star` increments every pull regardless
of what dropped (natural 4-stars or 5-stars do NOT reset it). Resets to 0
only when the guaranteed 10th pull triggers.

### Roll Algorithm (`api/db.php → rollRarity()`)
```
1. pityCount >= 99  → force 5-star (hard pity)
2. pityCount4star >= 9 → force 4-star (4-star pity)
3. stage = intdiv(pityCount, 10) → look up rate in $rateTable
4. roll = mt_rand(1, 1000)
5. roll <= fiveStarThreshold  → 5-star
6. roll <= fourStarThreshold  → 4-star (base 5.1% on top of 5-star threshold)
7. else                        → 3-star
```

To change rates: update `$rateTable` in `rollRarity()` AND `$rateTable`
in `getCurrentFiveStarRate()`. Both must stay in sync.
Values in `rollRarity` are out of 1000 (multiply % by 10).
Values in `getCurrentFiveStarRate` are plain percentages for display.

---

## API Endpoints

All endpoints are in `api/`. All return `Content-Type: application/json`.
All include CORS headers (`Access-Control-Allow-Origin: *`).

### POST /api/pull.php
Runs one gacha pull for user_id = 1.

**Request body:** empty `{}`

**Response:**
```json
{
  "item": { "id": 4, "name": "Frost Knight", "rarity": 4 },
  "was_pity_5": false,
  "was_pity_4": false,
  "pity": {
    "count_5star": 24,
    "count_4star": 1,
    "current_rate": 1.5,
    "in_soft_pity": true,
    "in_hard_pity": false,
    "in_4star_pity": false
  }
}
```

### GET /api/history.php
Returns pull history for user_id = 1.

**Response:**
```json
{
  "recent": [ { "name": "...", "rarity": 4, "pulled_at": "..." }, ... ],
  "full":   [ ... ],
  "summary": { "5star": 2, "4star": 18, "3star": 80, "total": 100 }
}
```

### GET /api/stats.php
Returns current pity state for user_id = 1. Called on page load.

**Response:**
```json
{
  "pity": {
    "count_5star": 24,
    "count_4star": 3,
    "current_rate": 1.5,
    "in_soft_pity": true,
    "in_hard_pity": false,
    "in_4star_pity": false
  }
}
```

---

## Angular Component Tree

```
AppComponent (app.component.ts)
  Owns: pityData, history
  On load: calls getStats() + getHistory() via GachaService
  On pullComplete event: updates pityData + prepends to history locally
  │
  ├── <app-pity-bar [pityData]="pityData">
  │     Receives pityData via @Input()
  │     Renders 5-star bar (green→orange→red) + 4-star bar (purple)
  │     Bar widths computed as TypeScript getters, animated via CSS transition
  │
  ├── <app-pull (pullComplete)="onPullComplete($event)">
  │     Calls GachaService.pull() on button click
  │     Plays slot machine animation (3 reels, staggered stops)
  │     Plays Web Audio API sounds (spin, stop, reveal, 5-star arpeggio)
  │     Triggers gold flash overlay on 5-star
  │     Emits pullComplete event with full PHP response
  │
  └── <app-history [history]="history">
        Receives history via @Input()
        Shows last 10 pulls in table
        "View Last 100 Pulls" button opens modal popup
        Modal shows full history + rarity summary counts
```

---

## Data Flow — One Pull

```
1. User clicks PULL x1
2. PullComponent.pull() sets isLoading = true, starts reels spinning
3. GachaService.pull() sends POST /api/pull.php
4. Nginx receives on :8080, routes /api/ to PHP-FPM via FastCGI
5. pull.php loads db.php, runs rollRarity(), pickItem(), logPull(),
   updatePityCount(), returns JSON
6. Angular receives JSON, stores as pendingResult
7. Reels stop one by one (1.0s, 1.8s, 2.6s staggered)
8. revealResult() runs: sets pulledItem, triggers flash if 5-star,
   plays sound, emits pullComplete
9. AppComponent.onPullComplete() updates pityData + prepends to history
10. PityBarComponent re-renders bars (no API call needed)
11. HistoryComponent re-renders table (no API call needed)
Total time from click to reveal: ~2.6 seconds (animation-gated)
```

---

## Make Commands

```bash
make up           # Build and start all 5 containers
make down         # Stop all containers (data preserved)
make down-clean   # Stop + wipe database volume
make ps           # Check container status
make logs-app     # PHP-FPM logs
make logs-db      # MySQL logs
make logs-nginx   # Nginx logs
make db-schema    # Run schema.sql (create tables)
make db-seed      # Run seed.sql (populate items)
make db-shell     # Interactive MySQL shell
make db-items     # SELECT * FROM items
make db-pulls     # Recent pull history with item names
make db-tables    # SHOW TABLES
make shell        # bash inside PHP container
make setup        # Full first-time setup (up + wait + schema + seed)
```

---

## Known Limitations

- **No user authentication** — user_id is hardcoded as 1 everywhere
- **No rate limiting** — anyone can call /api/pull.php infinitely
- **Dev server only** — Angular runs as `ng serve`, not a production build
- **No HTTPS** — HTTP only, fine for local dev
- **Single banner** — all items are in one pool, no limited banners
- **No inventory** — pulled items are logged but not tracked per-user
- **No pull x10** — only single pulls implemented

---

## Potential Next Features

- [ ] User accounts + login (JWT or session-based)
- [ ] Pull x10 button
- [ ] Inventory page showing all collected items
- [ ] Multiple banners (standard + limited rate-up)
- [ ] Item images / card art
- [ ] Production Angular build (ng build) instead of dev server
- [ ] HTTPS via Let's Encrypt
- [ ] Rate limiting on API endpoints
- [ ] Pull statistics page (total pulls, 5-star rate, etc.)

---

## Development Notes

- **Windows file paths in Makefile:** use backslashes (`sql\schema.sql`),
  not forward slashes, because make runs through cmd.exe on Windows
- **node_modules volume:** the `/app/node_modules` anonymous volume in
  docker-compose.yml prevents Windows symlink issues with npm packages
- **TypeScript `types: []`** in tsconfig.json prevents @types/node from
  conflicting with Angular's browser-targeted TypeScript
- **CORS headers** in all API files allow Angular on :4200 to call PHP
  on :8080 during development
- **`make` via Scoop:** always use `make <command>` instead of raw
  PowerShell docker commands — credentials are handled securely via
  bash -c inside the container

# 🎰 Kitty Treat Gacha

A full-stack gacha pull simulator built from scratch, modeled after Genshin Impact's pull system — with a cat-themed item pool, an animated three-reel slot machine reveal, a dual pity engine, and persistent history, all backed by a hand-rolled PHP REST API. Ships two ways: local Docker Compose for development, and a single-container build for production deployment on Render.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | Angular 17 + TypeScript |
| Backend | PHP 8.5 (FPM) |
| Database | MySQL 8.0 (local) / Aiven MySQL (production) |
| Web Server | Nginx |
| Containerization | Docker + Docker Compose (local), single-container build via supervisord (Render) |
| Task Runner | GNU Make |

---

## Features

- **Gacha pull system** — weighted random pulls across 3-star, 4-star, and 5-star rarity tiers, decided server-side on every request
- **Staged 5-star soft pity** — rate climbs every 10 pulls from 0.1% up to 20%, with hard pity guaranteed at pull 100
- **4-star hard pity on a fixed schedule** — guaranteed every 10 pulls; natural 4-star or 5-star pulls do *not* reset this counter, only the guaranteed trigger does — the two pity counters track and reset independently of each other
- **Animated slot machine reveal** — three reels spin and stop in a staggered sequence (1000ms / 800ms / 800ms) with Web Audio API–generated sound effects (no audio files). The result is decided by PHP the instant you click; the spin is a purely cosmetic client-side animation layered on top of an already-determined outcome, with rarity-correct reel-matching logic (5★ always lands all reels on 💎, 4★ matches a shared symbol pool excluding 💎, 3★ deliberately avoids an accidental triple-match) plus a gold flash overlay on 5-star pulls
- **Live pity bars** — colour-coded progress bars (green → orange/soft-pity → red/hard-pity) showing current rate and pull count
- **Pull history** — last 10 pulls shown inline, full 100-pull history in a popup with a rarity summary, updated optimistically in-memory after each pull (no extra API round-trip or UI flicker)
- **Persistent state** — pity counters and pull history survive page refreshes and redeploys via MySQL
- **Cat-themed item pool** — 3 five-star "Chuupa" treats, 5 four-star affection actions, 8 three-star "kitty ignored you" flavor misses, all with emoji names requiring `utf8mb4` handling end-to-end

---

## Project Structure

```
Gacha/
├── Dockerfile                  # PHP 8.5-FPM image (local Docker Compose)
├── Dockerfile.render           # Single-container build for Render (Angular build → PHP+Nginx+supervisord)
├── docker-compose.yml          # Orchestrates local containers (Nginx, PHP-FPM, MySQL, Angular dev server, phpMyAdmin)
├── supervisord.conf            # Runs Nginx + PHP-FPM as sibling processes inside the Render container
├── start.sh                    # Injects Render's dynamic $PORT into Nginx config via envsubst, then launches supervisord
├── Makefile                    # Shortcut commands (make up, make db-schema, etc.)
├── COMMANDS.md                 # Full command reference
├── AGENTS.md                   # Conventions for AI coding agents working in this repo
├── .env                        # DB credentials (gitignored)
│
├── nginx/
│   ├── default.conf            # Local: routes /api/* to PHP-FPM, /* to Angular dev server
│   └── render.conf             # Render: single-container routing, listens on ${PORT}
│
├── api/                         # PHP REST API endpoints
│   ├── db.php                   # DB connection (env-driven, SSL-aware for Aiven) + all gacha/pity functions
│   ├── pull.php                 # POST /api/pull.php — rolls rarity, picks item, logs pull, updates pity
│   ├── history.php              # GET  /api/history.php — last 10 + last 100 pulls, rarity summary
│   └── stats.php                # GET  /api/stats.php — current pity state for initial page load
│
├── sql/
│   ├── schema.sql               # Creates items, pulls, user_stats tables (utf8mb4)
│   └── seed.sql                 # Cat-themed item pool (3/4/5-star)
│
└── frontend/                    # Angular 17 frontend
    ├── Dockerfile               # Node 20 + Angular CLI dev server (local)
    ├── package.json
    ├── tsconfig.json
    ├── angular.json
    └── src/
        └── app/
            ├── app.module.ts
            ├── app.component.ts          # Owns shared state (pityData, history), passes down via @Input()
            ├── services/
            │   └── gacha.service.ts      # All HTTP calls to the PHP API; resolves base URL by current port
            └── components/
                ├── pull/                 # Pull button, slot-machine reel animation, Web Audio sound effects
                ├── pity-bar/             # 5-star and 4-star pity bars, computed from @Input() pityData
                └── history/              # Recent pulls table + last-100 popup modal
```

---

## Getting Started

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop)
- [Scoop](https://scoop.sh) (Windows package manager)
- GNU Make via Scoop: `scoop install make`

### Installation

**1. Clone the repository**
```bash
git clone https://github.com/yourusername/gacha-app.git
cd gacha-app
```

**2. Create your `.env` file**
```env
MYSQL_ROOT_PASSWORD=your_root_password
MYSQL_DATABASE=myapp
MYSQL_USER=user
MYSQL_PASSWORD=your_password
```

**3. Start all containers**
```bash
make up
```

First run takes 5–10 minutes — Docker downloads images and Angular installs npm packages.

**4. Set up the database**
```bash
make db-schema
make db-seed
```

**5. Open the app**

| URL | What |
|---|---|
| http://localhost:8080 | Gacha app (Angular frontend) |
| http://localhost:8081 | phpMyAdmin (database GUI) |

---

## Production Deployment (Render)

Local development uses 5 separate containers via Docker Compose. Production collapses everything into a **single container** for simpler, cheaper hosting on Render, built from `Dockerfile.render`:

**Build process (multi-stage):**
1. **Stage 1** — `node:20-alpine` builds the Angular app for production (`ng build --configuration production`), output discarded after the static files are copied out
2. **Stage 2** — `php:8.5-fpm` base image, installs Nginx, supervisor, and PHP's `pdo_mysql`/`mysqli`/`zip` extensions; copies in the built Angular static files, the PHP API, and the Nginx/supervisor config

**Process management** — `supervisord` runs Nginx and PHP-FPM as two sibling processes inside the one container, restarting either if it crashes and forwarding both processes' logs to stdout/stderr so they show up in Render's log viewer. This replaces the multi-container split (separate Nginx + PHP-FPM containers) used locally.

**Dynamic port binding** — Render assigns a random `$PORT` at runtime rather than a fixed port. `start.sh` runs on container start, uses `envsubst` to inject the actual `$PORT` value into `nginx/render.conf`'s `listen ${PORT}` directive, writes out the real Nginx config, then hands off to `supervisord`.

**Database** — production points at an external **Aiven MySQL** instance rather than a local container. `api/db.php` reads `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_SSL`, and credentials entirely from environment variables, so the same file works against both the local Docker `db` container and Aiven without code changes — only the `.env` values differ. When `MYSQL_SSL=true`, the connection enables SSL verification, required by Aiven; PHP 8.5 renamed the relevant PDO constant (`Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT`), so `db.php` checks at runtime for whichever constant exists, keeping it compatible across PHP versions.

**Error handling in production** — PHP is configured to log errors to `stderr` (visible in Render's logs) while suppressing `display_errors`, so a PHP warning or notice never leaks into an HTTP response and silently breaks Angular's `JSON.parse()` on the other end.

---

## Make Commands

```bash
make up           # Build and start all containers
make down         # Stop all containers
make down-clean   # Stop and wipe the database
make ps           # Check container status
make logs-app     # PHP logs
make logs-db      # MySQL logs
make logs-nginx   # Nginx logs
make db-schema    # Create database tables
make db-seed      # Seed items table
make db-shell     # Open MySQL shell
make db-items     # View all items
make db-pulls     # View recent pull history
make db-tables    # Show all tables
make shell        # Open bash inside PHP container
```

---

## How the Pity System Works

### 5-Star Pity
The 5-star rate starts very low and climbs in stages every 10 pulls:

| Pulls since last 5★ | Rate |
|---|---|
| 1–10 | 0.1% |
| 11–20 | 1.0% |
| 21–30 | 1.5% |
| 31–40 | 2.0% |
| 41–50 | 2.5% |
| 51–60 | 5.0% |
| 61–70 | 6.0% |
| 71–80 | 7.0% |
| 81–90 | 8.0% |
| 91–99 | 20.0% |
| 100 | 100% (guaranteed) |

### 4-Star Pity
Every 10th pull is a guaranteed 4-star on a **fixed schedule** — pulling a natural 4-star or 5-star does not reset the counter. The two counters are tracked and reset completely independently: `pity_count` (5-star) resets only on an actual 5-star pull; `pity_count_4star` increments on *every* pull regardless of outcome, and resets to 0 only when the guaranteed-4-star trigger itself fires. A natural 4-star landing mid-cycle doesn't touch the 4-star counter at all.

---

## Architecture

**Local development** — 5 containers via Docker Compose, each service isolated:

```
Browser
  │
  ▼
Nginx (port 8080)
  ├── /api/*  → PHP-FPM container (gacha logic) ──→ MySQL container (port 3307)
  └── /*      → Angular dev server (port 4200)
```

**Production (Render)** — collapsed into a single container, external managed database:

```
Browser
  │
  ▼
Render container (listens on Render's dynamic $PORT)
  └── Nginx + PHP-FPM, run as sibling processes via supervisord
        ├── /api/*  → PHP-FPM (gacha logic, SSL connection)
        └── /*      → pre-built Angular static files (served directly, no dev server)
                            │
                            ▼
                    Aiven MySQL (external, managed)
```

In both environments, Angular never talks to MySQL directly — every request goes through the PHP API, which validates, processes, and persists everything server-side. The gacha outcome itself (`rollRarity()`) is decided entirely on the server before any response reaches the browser, so the client-side slot machine animation has no influence over — and no early knowledge of — the actual result.

---

## Database Schema

**`items`** — pullable cards with rarity tier (3/4/5)

**`pulls`** — log of every pull made (user_id, item_id, timestamp)

**`user_stats`** — pity counters per user (pity_count, pity_count_4star)

---

## Customising Pull Rates

Pull rates are defined in two matching tables inside `api/db.php` in the `rollRarity()` and `getCurrentFiveStarRate()` functions.

To change a rate, update both tables keeping them in sync:

```php
// rollRarity() — values out of 1000 (multiply % by 10)
0 => 1,    // 0.1% → change to e.g. 10 for 1%

// getCurrentFiveStarRate() — actual percentage for display
0 => 0.1,  // 0.1% → change to e.g. 1.0 for 1%
```

---

## Known Limitations / Next Steps

This is a single-user MVP by design — `user_id` is currently hardcoded to `1` in every endpoint, so there's no real authentication or multi-user support yet. The frontend and backend are already fully decoupled, so the natural next step would be JWT-based auth: an Angular `HttpInterceptor` attaching a token to outgoing requests, validated by PHP per-request with no server-side session state needed.

Other things deliberately left for later: rate limiting on `/api/pull.php`, input validation hardening beyond the basic method checks, and moving the hardcoded pull-rate tables in `db.php` into the database itself so rates could be tuned without a redeploy.

---

## What I Learned Building This

- PHP fundamentals — PDO, prepared statements, JSON APIs, environment-variable-driven config that works unmodified across local and production databases
- MySQL — schema design, foreign keys, indexes, JOIN queries, `utf8mb4` encoding for emoji data
- State machine design — two independent counters (5-star and 4-star pity) with different increment/reset rules, coordinated correctly across pulls
- Docker — multi-container local dev vs. a single-container multi-stage production build, supervisord for running multiple processes in one container
- Nginx — reverse proxying, FastCGI to PHP-FPM, dynamic port binding via `envsubst` for Render's runtime-assigned port
- Angular — components, services, `@Input`/`@Output`, RxJS Observables, change detection and why immutable state updates matter
- Client-side animation as a UX layer over server-authoritative results — the slot machine spin is cosmetic; the actual outcome is already decided and stored before the animation even starts
- Full-stack architecture — separating frontend from backend via REST API, and keeping that same API contract valid across two very different deployment topologies
- Windows dev tooling — Scoop, Make, Docker Desktop on WSL2

---

## License

MIT

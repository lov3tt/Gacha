# 🎰 Gacha App

A full-stack gacha pull simulator built from scratch as a learning project, modeled after Genshin Impact's pull system. Features a complete pity system, pull history, and a modern Angular frontend backed by a PHP REST API.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | Angular 17 + TypeScript |
| Backend | PHP 8.5 (FPM) |
| Database | MySQL 8.0 |
| Web Server | Nginx |
| Containerization | Docker + Docker Compose |
| Task Runner | GNU Make |

---

## Features

- **Gacha pull system** — weighted random pulls across 3-star, 4-star, and 5-star rarity tiers
- **Staged 5-star soft pity** — rate climbs every 10 pulls from 0.1% up to 20%, with hard pity guaranteed at pull 100
- **4-star hard pity** — guaranteed 4-star every 10 pulls on a fixed schedule (natural 4-star pulls don't reset it)
- **Live pity bars** — colour-coded progress bars showing your current rate and pull count
- **Pull history** — last 10 pulls shown inline, full 100-pull history in a popup with rarity summary
- **No page reloads** — Angular updates the UI instantly without re-rendering the whole page
- **Persistent state** — pity counters survive page refreshes via MySQL

---

## Project Structure

```
Gacha/
├── Dockerfile                  # PHP 8.5-FPM image
├── docker-compose.yml          # Orchestrates all 5 containers
├── Makefile                    # Shortcut commands (make up, make db-schema etc.)
├── COMMANDS.md                 # Full command reference
├── .env                        # DB credentials (gitignored)
│
├── nginx/
│   └── default.conf            # Routes /api/* to PHP, /* to Angular
│
├── api/                        # PHP REST API endpoints
│   ├── db.php                  # Shared DB connection + all gacha functions
│   ├── pull.php                # POST /api/pull.php
│   ├── history.php             # GET  /api/history.php
│   └── stats.php               # GET  /api/stats.php
│
├── src/                        # Legacy PHP frontend (kept for reference)
│   ├── index.php               # Original PHP-rendered gacha page
│   └── test.php                # DB connection smoke test
│
├── sql/
│   ├── schema.sql              # Creates items, pulls, user_stats tables
│   └── seed.sql                # Populates items with 3/4/5-star cards
│
└── frontend/                   # Angular 17 frontend
    ├── Dockerfile              # Node 20 + Angular CLI dev server
    ├── package.json
    ├── tsconfig.json
    ├── angular.json
    └── src/
        └── app/
            ├── app.module.ts
            ├── app.component.ts
            ├── services/
            │   └── gacha.service.ts        # All HTTP calls to PHP API
            └── components/
                ├── pull/                   # Pull button + result card
                ├── pity-bar/              # 5-star and 4-star pity bars
                └── history/               # Recent pulls + 100-pull popup
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
Every 10th pull is a guaranteed 4-star on a **fixed schedule** — pulling a natural 4-star or 5-star does not reset the counter.

---

## Architecture

```
Browser
  │
  ▼
Nginx (port 8080)
  ├── /api/*  → PHP-FPM container (gacha logic + MySQL)
  └── /*      → Angular dev server (port 4200)
                    │
                    ▼
                MySQL (port 3307)
```

Angular never talks to MySQL directly — all data goes through the PHP API, which validates, processes, and stores everything securely on the server side.

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

## What I Learned Building This

- PHP fundamentals — PDO, prepared statements, JSON APIs, environment variables
- MySQL — schema design, foreign keys, indexes, JOIN queries
- Docker — multi-container apps, volumes, networking, Dockerfile best practices
- Nginx — reverse proxying, FastCGI, routing
- Angular — components, services, @Input/@Output, RxJS Observables
- Full-stack architecture — separating frontend from backend via REST API
- Windows dev tooling — Scoop, Make, Docker Desktop on WSL2

---

## License

MIT

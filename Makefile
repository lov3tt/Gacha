# Makefile — shortcut commands for managing the gacha app.
# Run any of these with "make <command>" from the project root.
#
# IMPORTANT: make on Windows runs through cmd.exe, not PowerShell.
# All commands here use cmd.exe syntax:
#   - "type" instead of "Get-Content" for reading files
#   - bash -c inside the container handles credential expansion,
#     so passwords never appear in plain text on the host side.

# Tell make to use cmd.exe explicitly on Windows
SHELL = cmd.exe

# ── Container management ─────────────────────────────────────────

# Build images and start all containers in the background
up:
	docker compose up -d --build

# Stop all containers (data is kept in the named volume)
down:
	docker compose down

# Stop all containers AND wipe the database volume (fresh start)
down-clean:
	docker compose down -v

# Show status of all containers
ps:
	docker compose ps

# ── Logs ────────────────────────────────────────────────────────

# Tail PHP app logs (where your PHP errors appear)
logs-app:
	docker compose logs -f app

# Tail MySQL logs (startup issues, query errors)
logs-db:
	docker compose logs -f db

# Tail Nginx logs (routing issues, 502 errors)
logs-nginx:
	docker compose logs -f nginx

# ── Database ────────────────────────────────────────────────────

# Create the tables (run schema.sql inside the container).
# "type" is cmd.exe's equivalent of PowerShell's Get-Content.
# bash -c inside the container expands $MYSQL_* from the container's
# own environment — password never appears on the host command line.
db-schema:
	type sql\schema.sql | docker compose exec -T db bash -c "mysql -u \"$$MYSQL_USER\" -p\"$$MYSQL_PASSWORD\" \"$$MYSQL_DATABASE\""

# Seed the items table (run seed.sql)
db-seed:
	type sql\seed.sql | docker compose exec -T db bash -c "mysql -u \"$$MYSQL_USER\" -p\"$$MYSQL_PASSWORD\" \"$$MYSQL_DATABASE\""

# Open a live MySQL shell inside the container (interactive)
db-shell:
	docker compose exec db bash -c "mysql -u \"$$MYSQL_USER\" -p\"$$MYSQL_PASSWORD\" \"$$MYSQL_DATABASE\""

# Show all items in the database
db-items:
	docker compose exec db bash -c "mysql -u \"$$MYSQL_USER\" -p\"$$MYSQL_PASSWORD\" \"$$MYSQL_DATABASE\" -e \"SELECT * FROM items;\""

# Show recent pull history
db-pulls:
	docker compose exec db bash -c "mysql -u \"$$MYSQL_USER\" -p\"$$MYSQL_PASSWORD\" \"$$MYSQL_DATABASE\" -e \"SELECT pulls.id, items.name, items.rarity, pulls.pulled_at FROM pulls JOIN items ON pulls.item_id = items.id ORDER BY pulls.pulled_at DESC LIMIT 20;\""

# Show all tables in the database
db-tables:
	docker compose exec db bash -c "mysql -u \"$$MYSQL_USER\" -p\"$$MYSQL_PASSWORD\" \"$$MYSQL_DATABASE\" -e \"SHOW TABLES;\""

# ── App ──────────────────────────────────────────────────────────

# Open a bash shell inside the PHP container
shell:
	docker compose exec app bash

# ── Setup shortcut ───────────────────────────────────────────────

# Run the full first-time setup in one command:
# start containers, wait for MySQL, create tables, seed data
setup:
	docker compose up -d --build
	@echo Waiting 20 seconds for MySQL to initialize...
	timeout /t 20 /nobreak
	type sql\schema.sql | docker compose exec -T db bash -c "mysql -u \"$$MYSQL_USER\" -p\"$$MYSQL_PASSWORD\" \"$$MYSQL_DATABASE\""
	type sql\seed.sql | docker compose exec -T db bash -c "mysql -u \"$$MYSQL_USER\" -p\"$$MYSQL_PASSWORD\" \"$$MYSQL_DATABASE\""
	@echo Done! Visit http://localhost:8080
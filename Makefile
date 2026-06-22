# Makefile — shortcut commands for managing the gacha app.
# Run any of these with "make <command>" from the project root.
#
# SECURITY: None of these commands hardcode the password in plain text.
# Instead they use bash -c '...' to run inside the db container, where
# $MYSQL_USER, $MYSQL_PASSWORD, and $MYSQL_DATABASE are already injected
# as environment variables by docker-compose.yml. The shell expands them
# INSIDE the container, so they never appear in your local shell history.

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

# Create the tables (run schema.sql inside the container)
# bash -c lets the container expand $MYSQL_* from its own environment,
# so the password is never visible in your PowerShell history.
db-schema:
	Get-Content sql/schema.sql | docker compose exec -T db bash -c 'mysql -u "$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

# Seed the items table (run seed.sql)
db-seed:
	Get-Content sql/seed.sql | docker compose exec -T db bash -c 'mysql -u "$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

# Open a live MySQL shell inside the container (interactive)
db-shell:
	docker compose exec db bash -c 'mysql -u "$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

# Show all items in the database
db-items:
	docker compose exec db bash -c 'mysql -u "$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE" -e "SELECT * FROM items;"'

# Show recent pull history
db-pulls:
	docker compose exec db bash -c 'mysql -u "$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE" -e "SELECT pulls.id, items.name, items.rarity, pulls.pulled_at FROM pulls JOIN items ON pulls.item_id = items.id ORDER BY pulls.pulled_at DESC LIMIT 20;"'

# ── App ──────────────────────────────────────────────────────────

# Open a bash shell inside the PHP container
shell:
	docker compose exec app bash

# ── Setup shortcut ───────────────────────────────────────────────

# Run the full first-time setup in one command:
# start containers, wait, create tables, seed data
setup:
	docker compose up -d --build
	@echo "Waiting 20 seconds for MySQL to initialize..."
	sleep 20
	Get-Content sql/schema.sql | docker compose exec -T db bash -c 'mysql -u "$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'
	Get-Content sql/seed.sql | docker compose exec -T db bash -c 'mysql -u "$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'
	@echo "Done! Visit http://localhost:8080/gacha.php"

# COMMANDS.md — PowerShell reference for the gacha app
#
# All database commands use bash -c inside the db container so that
# $MYSQL_USER, $MYSQL_PASSWORD, and $MYSQL_DATABASE are expanded by
# the container's own shell — no password ever appears in plain text
# in your local PowerShell history or process list.

## Container management

# Start everything (build first)
docker compose up -d --build

# Stop everything (data kept)
docker compose down

# Stop everything AND wipe the database (fresh start)
docker compose down -v

# Check status of all 4 containers
docker compose ps


## Logs

# PHP app errors
docker compose logs -f app

# MySQL startup / query errors
docker compose logs -f db

# Nginx routing issues
docker compose logs -f nginx


## Database commands (secure — no hardcoded password)

# Run schema.sql (create tables)
Get-Content sql/schema.sql | docker compose exec -T db bash -c 'mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'

# Run seed.sql (populate items)
Get-Content sql/seed.sql | docker compose exec -T db bash -c 'mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'

# Open an interactive MySQL shell
docker compose exec db bash -c 'mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'

# Show all items
docker compose exec db bash -c 'mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT * FROM items;"'

# Show recent 20 pulls with item names
docker compose exec db bash -c 'mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT pulls.id, items.name, items.rarity, pulls.pulled_at FROM pulls JOIN items ON pulls.item_id = items.id ORDER BY pulls.pulled_at DESC LIMIT 20;"'

# Add the user_id index to pulls table (if not already done via schema.sql)
docker compose exec db bash -c 'mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "ALTER TABLE pulls ADD INDEX idx_user_id (user_id);"'


## App shell

# Open a bash shell inside the PHP container
docker compose exec app bash

<?php
// index.php — smoke-test script: confirms PHP can reach MySQL
// using credentials pulled from environment variables (never hardcoded).

// "db" is NOT "localhost" — it's the service name defined in docker-compose.yml.
// Docker Compose's internal DNS resolves "db" to the MySQL container's
// private IP address on the shared network.
$host = 'db';

// getenv() reads OS-level environment variables.
// These were injected into THIS container by docker-compose.yml's
// "env_file: .env" directive on the "app" service — so changing .env
// changes these values without touching this file at all.
$dbname = getenv('MYSQL_DATABASE');
$user   = getenv('MYSQL_USER');
$pass   = getenv('MYSQL_PASSWORD');

try {
    // PDO = PHP Data Objects, a generic database-access layer.
    // The DSN string ("mysql:host=...;dbname=...;charset=...") tells PDO
    // which driver to use (mysql) and which server/database to connect to.
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);

    // If new PDO(...) didn't throw an exception, the connection succeeded.
    echo "<h1>✅ Connected to MySQL successfully!</h1>";

    // Run a simple query to prove we can actually talk to the database,
    // not just open a socket.
    $stmt = $pdo->query("SELECT VERSION() as version");

    // fetch() pulls one row from the result set as an associative array.
    $row = $stmt->fetch();
    echo "<p>MySQL version: " . $row['version'] . "</p>";

    // phpversion() is a built-in PHP function — confirms which PHP
    // version this container is actually running (e.g. 8.5.x).
    $phpVersion = phpversion();
    echo "<p>PHP version: " . $phpVersion . "</p>";

} catch (PDOException $e) {
    // Runs if the connection or query failed — e.g. wrong password,
    // MySQL container not finished starting yet, wrong database name.
    echo "<h1>❌ Connection failed</h1><p>" . $e->getMessage() . "</p>";
}

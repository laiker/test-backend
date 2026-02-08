<?php

echo "\n🔧 Setting up database...\n";

$maxAttempts = 30;
$attempt = 0;

while ($attempt < $maxAttempts) {
    try {
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            getenv('DB_HOST') ?: 'postgres',
            getenv('DB_PORT') ?: '5432',
            getenv('DB_NAME') ?: 'bonus_db'
        );
        
        $pdo = new PDO(
            $dsn,
            getenv('DB_USER') ?: 'bonus_user',
            getenv('DB_PASSWORD') ?: 'bonus_pass'
        );
        
        echo "✓ PostgreSQL connection established\n";
        break;
    } catch (PDOException $e) {
        $attempt++;
        if ($attempt >= $maxAttempts) {
            echo "✗ Failed to connect to PostgreSQL: " . $e->getMessage() . "\n";
            exit(1);
        }
        echo "  Waiting for PostgreSQL... (attempt {$attempt}/{$maxAttempts})\n";
        sleep(1);
    }
}

$fixturesPath = __DIR__ . '/../fixtures/test_data.sql';

if (!file_exists($fixturesPath)) {
    echo "✗ Fixtures file not found: {$fixturesPath}\n";
    exit(1);
}

$sql = file_get_contents($fixturesPath);

try {
    $pdo->exec($sql);
    echo "✓ Database schema created\n";
    echo "✓ Test data loaded\n";
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM partners');
    $count = $stmt->fetchColumn();
    echo "✓ {$count} partners loaded\n";
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM sales');
    $count = $stmt->fetchColumn();
    echo "✓ {$count} sales records loaded\n";
    
    echo "\n";
    echo "✅ Database setup complete!\n";
    echo "\n";
    echo "🚀 Ready to start! Run:\n";
    echo "   php bin/console.php calculate-bonuses 2026-02\n";
    echo "\n";
    
} catch (PDOException $e) {
    echo "✗ Failed to load fixtures: " . $e->getMessage() . "\n";
    exit(1);
}

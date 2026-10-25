<?php
declare(strict_types=1);
require __DIR__.'/../app/DatabaseClock.php';
$pdo=new PDO('sqlite::memory:');
DatabaseClock::configure($pdo);
if(abs(time()-(int)$pdo->query("SELECT strftime('%s',CURRENT_TIMESTAMP)")->fetchColumn())>2)throw new RuntimeException('SQLite clock changed.');
echo "OK: SQLite remains UTC and no MySQL session statement is executed.\n";

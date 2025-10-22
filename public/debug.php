<?php
declare(strict_types=1);

// Debug info for Slim app
echo "<h2>Slim API Debug Info</h2>";

echo "<strong>Current dir:</strong> " . __DIR__ . "<br>";
echo "<strong>PHP version:</strong> " . PHP_VERSION . "<br>";

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "<strong>Autoload exists:</strong> YES<br>";
} else {
    echo "<strong>Autoload exists:</strong> NO<br>";
}

echo "<strong>Base path:</strong> /trouidees-fb-queue-api/public<br>";

echo "<strong>.htaccess exists:</strong> ";
echo file_exists(__DIR__ . '/.htaccess') ? "YES" : "NO";
echo "<br>";

echo "<strong>Test request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? '-') . "<br>";

echo "<hr><pre>";
print_r($_SERVER);
echo "</pre>";
?>

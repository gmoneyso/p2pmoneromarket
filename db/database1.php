<?php
// db/database.php

// Load environment variables
require_once __DIR__ . '/../env.php';

try {
    // Create PDO instance
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // Fetch associative arrays by default
            PDO::ATTR_EMULATE_PREPARES => false,              // Use real prepared statements
        ]
    );
} catch (PDOException $e) {
    // Stop execution if DB connection fails
    die("Database connection failed: " . $e->getMessage());
}

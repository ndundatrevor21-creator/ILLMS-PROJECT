<?php
/** Legacy schema migration that checks and adds columns/tables for older installs. */
require_once 'config/database.php';

// Check if users table has username column
$check_column = "SHOW COLUMNS FROM users LIKE 'username'";
$result = $conn->query($check_column);

if ($result->num_rows === 0) {
    echo "Adding missing columns to users table...\n";
    
    // Add username column
    $alter1 = "ALTER TABLE users ADD COLUMN username VARCHAR(120) NOT NULL UNIQUE AFTER id";
    $conn->query($alter1);
    echo "Added username column\n";
    
    // Add other missing columns
    $alter2 = "ALTER TABLE users 
        ADD COLUMN first_name VARCHAR(120) NULL AFTER name,
        ADD COLUMN last_name VARCHAR(120) NULL AFTER first_name,
        ADD COLUMN phone VARCHAR(50) NULL AFTER status,
        ADD COLUMN city VARCHAR(120) NULL AFTER phone,
        ADD COLUMN employment_status VARCHAR(120) NULL AFTER city,
        ADD COLUMN annual_income DECIMAL(12,2) NULL AFTER employment_status,
        ADD COLUMN company_name VARCHAR(160) NULL AFTER annual_income,
        ADD COLUMN lending_capacity DECIMAL(12,2) NULL AFTER company_name,
        ADD COLUMN preferred_loan_types VARCHAR(255) NULL AFTER lending_capacity";
    $conn->query($alter2);
    echo "Added other missing columns\n";
    
    echo "Database updated successfully!\n";
} else {
    echo "Users table already has the correct structure.\n";
}
?>
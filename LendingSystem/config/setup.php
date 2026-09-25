<?php
/** Initial schema installer; creates required lending tables and is not a normal page dependency. */
require_once 'database.php';

// Create users table with all required columns
$users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(120) NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer','lender','admin') NOT NULL DEFAULT 'customer',
    status ENUM('active','blocked') NOT NULL DEFAULT 'active',
    phone VARCHAR(50) NULL,
    city VARCHAR(120) NULL,
    employment_status VARCHAR(120) NULL,
    annual_income DECIMAL(12,2) NULL,
    company_name VARCHAR(160) NULL,
    lending_capacity DECIMAL(12,2) NULL,
    preferred_loan_types VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
executeQuery($users_table);

// Create loan_applications table
$loan_applications_table = "CREATE TABLE IF NOT EXISTS loan_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    term_months INT NOT NULL,
    status ENUM('pending','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id)
)";
executeQuery($loan_applications_table);

// Create loans table
$loans_table = "CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    customer_id INT NOT NULL,
    lender_id INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    term_months INT NOT NULL,
    status ENUM('pending','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES loan_applications(id),
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (lender_id) REFERENCES users(id)
)";
executeQuery($loans_table);

// Create payments table
$payments_table = "CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    customer_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id),
    FOREIGN KEY (customer_id) REFERENCES users(id)
)";
executeQuery($payments_table);

echo "Database setup completed successfully!";
?>
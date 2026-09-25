<?php
/** One-time migration adding information-request statuses and the loan_messages table. */
require_once 'config/database.php';

try {
    // 1. Update loan_applications status enum to add 'needs_information'
    // First, modify the status column without enum to remove the existing constraint
    $conn->query("ALTER TABLE loan_applications MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
    
    // Then, add the new status to the enum properly
    $conn->query("ALTER TABLE loan_applications MODIFY COLUMN status ENUM('pending','needs_information','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending'");
    
    // 2. Create loan_messages table
    $createMessagesTable = "CREATE TABLE IF NOT EXISTS loan_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_application_id INT NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (loan_application_id) REFERENCES loan_applications(id),
        FOREIGN KEY (sender_id) REFERENCES users(id)
    )";
    $conn->query($createMessagesTable);
    
    echo "Database updated successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<?php
/** Read-only schema diagnostic reporting required tables and columns. */
require_once __DIR__ . '/../config/database.php';

$tables = [
    'users', 'loan_applications', 'loans', 'payments', 'loan_offers', 'customers', 'lenders', 'loan_messages'
];

foreach ($tables as $t) {
    echo "--- TABLE: $t ---\n";
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    if (!$res) {
        echo "ERROR running SHOW TABLES: " . $conn->error . "\n\n";
        continue;
    }
    if ($res->num_rows === 0) {
        echo "MISSING\n\n";
        continue;
    }

    $row = $res->fetch_row();
    $name = $row[0];
    $createRes = $conn->query("SHOW CREATE TABLE `" . $conn->real_escape_string($name) . "`");
    if ($createRes && $createRes->num_rows > 0) {
        $createRow = $createRes->fetch_assoc();
        echo $createRow['Create Table'] . "\n\n";
    } else {
        echo "Unable to fetch CREATE statement: " . $conn->error . "\n\n";
    }
}

echo "Schema check complete.\n";

<?php
/** Shared MySQL connection, query/audit helpers, and lending calculation rules. */
// Load local database settings without committing credentials to source control.
$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
$settings = is_file($envFile) ? parse_ini_file($envFile, false, INI_SCANNER_RAW) : [];
$setting = static function (string $name, $default = null) use ($settings) {
    $value = $settings[$name] ?? getenv($name);
    return $value === false || $value === null ? $default : $value;
};

$host = (string)$setting('DB_HOST', '127.0.0.1');
$port = (int)$setting('DB_PORT', 3306);
$username = (string)$setting('DB_USERNAME', '');
$password = (string)$setting('DB_PASSWORD', '');
$database = (string)$setting('DB_DATABASE', 'lms_tr');

if ($username === '') {
    die('Database credentials are not configured. Copy .env.example to .env and set DB_USERNAME.');
}

// Create connection
$conn = new mysqli($host, $username, $password, "", $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `$database` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($database);

// Function to execute queries
function executeQuery($query) {
    global $conn;
    $result = $conn->query($query);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    return $result;
}

// Record important actions so administrators can audit system activity.
function logSystemActivity($conn, $actorId, $actorName, $action, $details = '', $entityType = 'system', $entityId = 0, $actorRole = 'system') {
    if (!$conn || !($conn instanceof mysqli)) {
        return false;
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'system_activity_logs'");
    if ($tableCheck && $tableCheck->num_rows === 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS system_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT NULL,
            actor_name VARCHAR(160) NULL,
            actor_role VARCHAR(50) NULL,
            action VARCHAR(150) NOT NULL,
            entity_type VARCHAR(80) NULL,
            entity_id INT NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    $stmt = $conn->prepare("INSERT INTO system_activity_logs (actor_id, actor_name, actor_role, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }

    $actorId = $actorId !== null ? (int)$actorId : null;
    $actorName = trim((string)$actorName);
    $action = trim((string)$action);
    $details = trim((string)$details);
    $entityType = trim((string)$entityType);
    $entityId = $entityId !== null ? (int)$entityId : 0;
    $actorRole = trim((string)$actorRole);

    $stmt->bind_param('issssis', $actorId, $actorName, $actorRole, $action, $entityType, $entityId, $details);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Calculate the amount owed, including interest and payments already made.
function calculateLoanPaymentSummary($loanId) {
    global $conn;

    $loanId = (int)$loanId;
    $loanStmt = $conn->prepare("SELECT id, amount, status, interest_rate, term_months FROM loans WHERE id = ?");
    $loanStmt->bind_param("i", $loanId);
    $loanStmt->execute();
    $loanResult = $loanStmt->get_result();

    if ($loanResult->num_rows === 0) {
        return [
            'loan_id' => $loanId,
            'loan_amount' => 0.0,
            'total_interest' => 0.0,
            'total_repayable' => 0.0,
            'total_paid' => 0.0,
            'remaining_balance' => 0.0,
            'status' => 'unknown'
        ];
    }

    $loan = $loanResult->fetch_assoc();
    $loanAmount = (float)($loan['amount'] ?? 0);
    $interestRate = (float)($loan['interest_rate'] ?? 0);
    $termMonths = (int)($loan['term_months'] ?? 0);

    $totalInterest = 0.0;
    if ($termMonths > 0 && $interestRate > 0) {
        $totalInterest = $loanAmount * ($interestRate / 100) * ($termMonths / 12);
    }
    $totalRepayable = $loanAmount + $totalInterest;

    $paidStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payments WHERE loan_id = ?");
    $paidStmt->bind_param("i", $loanId);
    $paidStmt->execute();
    $paidResult = $paidStmt->get_result();
    $paidData = $paidResult->fetch_assoc();
    $totalPaid = (float)($paidData['total_paid'] ?? 0);
    $remainingBalance = max(0.0, $totalRepayable - $totalPaid);

    if ($remainingBalance <= 0 && $loan['status'] !== 'paid') {
        $conn->query("UPDATE loans SET status = 'paid' WHERE id = $loanId");
        $loan['status'] = 'paid';
    }

    return [
        'loan_id' => (int)$loan['id'],
        'loan_amount' => $loanAmount,
        'total_interest' => $totalInterest,
        'total_repayable' => $totalRepayable,
        'total_paid' => $totalPaid,
        'remaining_balance' => $remainingBalance,
        'status' => $loan['status']
    ];
}

function syncLoanPaymentStatus($loanId) {
    return calculateLoanPaymentSummary((int)$loanId);
}

// Apply one transparent rate policy to every automatically funded loan.
function determineLoanInterestRate($creditScore) {
    $creditScore = (int)$creditScore;

    if ($creditScore < 34) {
        return null; // High-risk applications are not funded.
    }
    if ($creditScore >= 80) {
        return 8.0;  // Very strong profile
    }
    if ($creditScore >= 67) {
        return 10.0; // Strong profile
    }
    if ($creditScore >= 50) {
        return 13.0; // Fair profile
    }

    return 16.0; // Minimum eligible profile
}

function calculateCreditScore($customerId, $loanAmount = 0) {
    global $conn;

    $customerId = (int)$customerId;
    $loanAmount = (float)$loanAmount;

    // ── Fetch borrower profile ──────────────────────────────────────
    $profileStmt = $conn->prepare(
        "SELECT employment_status, annual_income, phone, city, created_at
         FROM users WHERE id = ?"
    );
    $profileStmt->bind_param("i", $customerId);
    $profileStmt->execute();
    $profile = $profileStmt->get_result()->fetch_assoc();

    $employment = strtolower($profile['employment_status'] ?? '');
    $income     = max(0.0, (float)($profile['annual_income'] ?? 0));

    // ── Fetch loan / payment history ────────────────────────────────
    $historyStmt = $conn->prepare(
        "SELECT
            SUM(CASE WHEN status = 'paid'      THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN status = 'defaulted'  THEN 1 ELSE 0 END) AS defaulted_count,
            SUM(CASE WHEN status = 'active'     THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN status = 'active'     THEN amount ELSE 0 END) AS active_debt
         FROM loans WHERE customer_id = ?"
    );
    $historyStmt->bind_param("i", $customerId);
    $historyStmt->execute();
    $history = $historyStmt->get_result()->fetch_assoc();

    $paidCount      = (int)($history['paid_count']      ?? 0);
    $defaultedCount = (int)($history['defaulted_count']  ?? 0);
    $activeCount    = (int)($history['active_count']     ?? 0);
    $activeDebt     = (float)($history['active_debt']    ?? 0);

    // ================================================================
    // FACTOR 1 — Employment Status  (weight: 25 points)
    // ================================================================
    switch ($employment) {
        case 'employed':      $employmentScore = 25; break;
        case 'self-employed':  $employmentScore = 20; break;
        case 'retired':        $employmentScore = 15; break;
        case 'student':        $employmentScore = 8;  break;
        default:               $employmentScore = 0;  break;   // unemployed / unknown
    }

    // ================================================================
    // FACTOR 2 — Annual Income  (weight: 25 points)
    // ================================================================
    if ($income >= 500000) {
        $incomeScore = 25;
    } elseif ($income >= 300000) {
        $incomeScore = 20;
    } elseif ($income >= 200000) {
        $incomeScore = 15;
    } elseif ($income >= 100000) {
        $incomeScore = 10;
    } elseif ($income >= 50000) {
        $incomeScore = 5;
    } else {
        $incomeScore = 0;
    }

    // ================================================================
    // FACTOR 3 — Debt-to-Income Ratio  (weight: 25 points)
    //   DTI = (existing active debt + requested loan) / annual income
    // ================================================================
    $totalDebtObligations = $activeDebt + $loanAmount;
    $dtiRatio = ($income > 0) ? ($totalDebtObligations / $income) : 1.0;
    $dtiPercent = round($dtiRatio * 100, 1);

    if ($dtiRatio <= 0.10) {
        $dtiScore = 25;
    } elseif ($dtiRatio <= 0.20) {
        $dtiScore = 20;
    } elseif ($dtiRatio <= 0.30) {
        $dtiScore = 15;
    } elseif ($dtiRatio <= 0.40) {
        $dtiScore = 10;
    } elseif ($dtiRatio <= 0.50) {
        $dtiScore = 5;
    } else {
        $dtiScore = 0;
    }

    // ================================================================
    // FACTOR 4 — Payment History  (weight: 25 points)
    //   Evaluates past loan outcomes: paid, defaulted, active
    // ================================================================
    $totalLoans = $paidCount + $defaultedCount + $activeCount;

    if ($totalLoans === 0) {
        // No history — neutral score (new borrower)
        $historyScore = 13;
    } elseif ($defaultedCount > 0) {
        // Any defaults → heavy penalty
        $defaultRatio = $defaultedCount / $totalLoans;
        if ($defaultRatio >= 0.5) {
            $historyScore = 0;
        } else {
            $historyScore = 5;
        }
    } else {
        // No defaults
        if ($paidCount >= 3 && $activeCount <= 1) {
            $historyScore = 25;   // Perfect — many paid, few active
        } elseif ($paidCount >= 1) {
            $historyScore = 20;   // Good — at least one paid
        } elseif ($activeCount >= 1 && $paidCount === 0) {
            $historyScore = 15;   // Fair — active but nothing paid yet
        } else {
            $historyScore = 13;   // Neutral
        }
    }

    // ================================================================
    // COMPOSITE WEIGHTED SCORE  (0 – 100)
    // ================================================================
    $score = $employmentScore + $incomeScore + $dtiScore + $historyScore;
    $score = max(0, min(100, $score));

    // ── Three-tier risk classification ──────────────────────────────
    if ($score >= 67) {
        $riskClass = 'Low Risk';
        $label     = 'Low Risk';
        $advice    = 'Strong credit profile. Eligible for funding with favorable terms.';
    } elseif ($score >= 34) {
        $riskClass = 'Medium Risk';
        $label     = 'Medium Risk';
        $advice    = 'Moderate credit profile. Eligible for funding with higher interest rate. Review borrower details carefully.';
    } else {
        $riskClass = 'High Risk';
        $label     = 'High Risk';
        $advice    = 'Weak credit profile. Funding is blocked — risk is too high.';
    }

    return [
        'score'              => $score,
        'label'              => $label,
        'risk_class'         => $riskClass,
        'advice'             => $advice,

        // Per-factor breakdown
        'employment_score'   => $employmentScore,
        'income_score'       => $incomeScore,
        'dti_score'          => $dtiScore,
        'history_score'      => $historyScore,

        // Raw data for display
        'employment_status'  => $employment,
        'annual_income'      => $income,
        'dti_percent'        => $dtiPercent,
        'active_debt'        => $activeDebt,
        'paid_count'         => $paidCount,
        'defaulted_count'    => $defaultedCount,
        'active_count'       => $activeCount,
    ];
}

function ensureDatabaseSchema() {
    global $conn;

    $createTables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(120) NULL,
            name VARCHAR(120) NULL,
            first_name VARCHAR(120) NULL,
            last_name VARCHAR(120) NULL,
            email VARCHAR(160) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('customer','lender','admin') NOT NULL DEFAULT 'customer',
            status ENUM('active','blocked') NOT NULL DEFAULT 'active',
            phone VARCHAR(50) NULL,
            city VARCHAR(120) NULL,
            street_address VARCHAR(255) NULL,
            address_line_2 VARCHAR(255) NULL,
            county VARCHAR(120) NULL,
            country VARCHAR(120) NULL,
            postal_code VARCHAR(40) NULL,
            employment_status VARCHAR(120) NULL,
            annual_income DECIMAL(12,2) NULL,
            company_name VARCHAR(160) NULL,
            lending_capacity DECIMAL(12,2) NULL,
            preferred_loan_types VARCHAR(255) NULL,
            account_age_months INT NULL,
            utility_payment_history VARCHAR(60) NULL,
            digital_activity VARCHAR(60) NULL,
            id_card_type VARCHAR(80) NULL,
            id_card_number VARCHAR(80) NULL,
            id_issuing_authority VARCHAR(160) NULL,
            id_issue_date DATE NULL,
            id_expiry_date DATE NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS loan_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            purpose VARCHAR(255) NOT NULL,
            term_months INT NOT NULL,
            status ENUM('pending','needs_information','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS customers (
            id INT NOT NULL PRIMARY KEY,
            profile TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS lenders (
            id INT NOT NULL PRIMARY KEY,
            organization VARCHAR(160) DEFAULT NULL,
            profile TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS loan_offers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lender_id INT NOT NULL,
            title VARCHAR(160) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            interest_rate DECIMAL(5,2) NOT NULL,
            term_months INT NOT NULL,
            description TEXT DEFAULT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lender_id) REFERENCES lenders(id)
        )",
        "CREATE TABLE IF NOT EXISTS loans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            customer_id INT NOT NULL,
            lender_id INT NULL,
            offer_id INT NOT NULL DEFAULT 0,
            amount DECIMAL(12,2) NOT NULL,
            interest_rate DECIMAL(5,2) NOT NULL,
            term_months INT NOT NULL,
            status ENUM('pending','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending',
            disbursed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES loan_applications(id),
            FOREIGN KEY (customer_id) REFERENCES users(id),
            FOREIGN KEY (lender_id) REFERENCES users(id),
            FOREIGN KEY (offer_id) REFERENCES loan_offers(id)
        )",
        "CREATE TABLE IF NOT EXISTS loan_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_application_id INT NOT NULL,
            sender_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (loan_application_id) REFERENCES loan_applications(id),
            FOREIGN KEY (sender_id) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            customer_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            method VARCHAR(60) DEFAULT 'manual',
            status ENUM('posted') NOT NULL DEFAULT 'posted',
            FOREIGN KEY (loan_id) REFERENCES loans(id),
            FOREIGN KEY (customer_id) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS system_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT NULL,
            actor_name VARCHAR(160) NULL,
            actor_role VARCHAR(50) NULL,
            action VARCHAR(150) NOT NULL,
            entity_type VARCHAR(80) NULL,
            entity_id INT NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($createTables as $sql) {
        $conn->query($sql);
    }

    $userColumnsResult = $conn->query("SHOW COLUMNS FROM users");
    $existingUserColumns = [];
    if ($userColumnsResult) {
        while ($row = $userColumnsResult->fetch_assoc()) {
            $existingUserColumns[] = $row['Field'];
        }
    }

    $userColumnStatements = [
        'username' => "ALTER TABLE users ADD COLUMN username VARCHAR(120) NULL",
        'name' => "ALTER TABLE users ADD COLUMN name VARCHAR(120) NULL",
        'first_name' => "ALTER TABLE users ADD COLUMN first_name VARCHAR(120) NULL",
        'last_name' => "ALTER TABLE users ADD COLUMN last_name VARCHAR(120) NULL",
        'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL",
        'city' => "ALTER TABLE users ADD COLUMN city VARCHAR(120) NULL",
        'street_address' => "ALTER TABLE users ADD COLUMN street_address VARCHAR(255) NULL",
        'address_line_2' => "ALTER TABLE users ADD COLUMN address_line_2 VARCHAR(255) NULL",
        'county' => "ALTER TABLE users ADD COLUMN county VARCHAR(120) NULL",
        'country' => "ALTER TABLE users ADD COLUMN country VARCHAR(120) NULL",
        'postal_code' => "ALTER TABLE users ADD COLUMN postal_code VARCHAR(40) NULL",
        'employment_status' => "ALTER TABLE users ADD COLUMN employment_status VARCHAR(120) NULL",
        'annual_income' => "ALTER TABLE users ADD COLUMN annual_income DECIMAL(12,2) NULL",
        'company_name' => "ALTER TABLE users ADD COLUMN company_name VARCHAR(160) NULL",
        'lending_capacity' => "ALTER TABLE users ADD COLUMN lending_capacity DECIMAL(12,2) NULL",
        'preferred_loan_types' => "ALTER TABLE users ADD COLUMN preferred_loan_types VARCHAR(255) NULL",
        'account_age_months' => "ALTER TABLE users ADD COLUMN account_age_months INT NULL",
        'utility_payment_history' => "ALTER TABLE users ADD COLUMN utility_payment_history VARCHAR(60) NULL",
        'digital_activity' => "ALTER TABLE users ADD COLUMN digital_activity VARCHAR(60) NULL",
        'id_card_type' => "ALTER TABLE users ADD COLUMN id_card_type VARCHAR(80) NULL",
        'id_card_number' => "ALTER TABLE users ADD COLUMN id_card_number VARCHAR(80) NULL",
        'id_issuing_authority' => "ALTER TABLE users ADD COLUMN id_issuing_authority VARCHAR(160) NULL",
        'id_issue_date' => "ALTER TABLE users ADD COLUMN id_issue_date DATE NULL",
        'id_expiry_date' => "ALTER TABLE users ADD COLUMN id_expiry_date DATE NULL",
        'created_at' => "ALTER TABLE users ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($userColumnStatements as $column => $sql) {
        if (!in_array($column, $existingUserColumns, true)) {
            $conn->query($sql);
        }
    }

    if (!in_array('username', $existingUserColumns, true)) {
        $conn->query("UPDATE users SET username = COALESCE(username, email) WHERE username IS NULL OR username = ''");
    }

    $loanTableColumnsResult = $conn->query("SHOW COLUMNS FROM loans");
    $existingLoanColumns = [];
    if ($loanTableColumnsResult) {
        while ($row = $loanTableColumnsResult->fetch_assoc()) {
            $existingLoanColumns[] = $row['Field'];
        }
    }

    $loanColumnStatements = [
        'application_id' => "ALTER TABLE loans ADD COLUMN application_id INT NOT NULL DEFAULT 0",
        'customer_id' => "ALTER TABLE loans ADD COLUMN customer_id INT NOT NULL DEFAULT 0",
        'lender_id' => "ALTER TABLE loans ADD COLUMN lender_id INT NULL",
        'offer_id' => "ALTER TABLE loans ADD COLUMN offer_id INT NOT NULL DEFAULT 0",
        'amount' => "ALTER TABLE loans ADD COLUMN amount DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        'interest_rate' => "ALTER TABLE loans ADD COLUMN interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00",
        'term_months' => "ALTER TABLE loans ADD COLUMN term_months INT NOT NULL DEFAULT 0",
        'status' => "ALTER TABLE loans ADD COLUMN status ENUM('pending','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending'",
        'disbursed_at' => "ALTER TABLE loans ADD COLUMN disbursed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'closed_at' => "ALTER TABLE loans ADD COLUMN closed_at DATETIME DEFAULT NULL",
        'created_at' => "ALTER TABLE loans ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($loanColumnStatements as $column => $sql) {
        if (!in_array($column, $existingLoanColumns, true)) {
            $conn->query($sql);
        }
    }

    // Repair incorrect loan application foreign key if it still points to the legacy applications table
    $fkResult = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loans' AND COLUMN_NAME = 'application_id' AND REFERENCED_TABLE_NAME = 'applications'");
    if ($fkResult && $fkResult->num_rows > 0) {
        $fkRow = $fkResult->fetch_assoc();
        $constraintName = $fkRow['CONSTRAINT_NAME'];
        $conn->query("ALTER TABLE loans DROP FOREIGN KEY `$constraintName`");
        $conn->query("ALTER TABLE loans ADD CONSTRAINT loans_ibfk_1 FOREIGN KEY (application_id) REFERENCES loan_applications(id)");
    }

    $applicationTableColumnsResult = $conn->query("SHOW COLUMNS FROM loan_applications");
    $existingApplicationColumns = [];
    if ($applicationTableColumnsResult) {
        while ($row = $applicationTableColumnsResult->fetch_assoc()) {
            $existingApplicationColumns[] = $row['Field'];
        }
    }

    $applicationColumnStatements = [
        'customer_id' => "ALTER TABLE loan_applications ADD COLUMN customer_id INT NOT NULL DEFAULT 0",
        'amount' => "ALTER TABLE loan_applications ADD COLUMN amount DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        'purpose' => "ALTER TABLE loan_applications ADD COLUMN purpose VARCHAR(255) NOT NULL DEFAULT ''",
        'term_months' => "ALTER TABLE loan_applications ADD COLUMN term_months INT NOT NULL DEFAULT 0",
        'status' => "ALTER TABLE loan_applications ADD COLUMN status ENUM('pending','needs_information','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending'",
        'created_at' => "ALTER TABLE loan_applications ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($applicationColumnStatements as $column => $sql) {
        if (!in_array($column, $existingApplicationColumns, true)) {
            $conn->query($sql);
        }
    }

    $applicationStatusResult = $conn->query("SHOW COLUMNS FROM loan_applications LIKE 'status'");
    if ($applicationStatusResult && $applicationStatusResult->num_rows > 0) {
        $applicationStatusRow = $applicationStatusResult->fetch_assoc();
        if (strpos($applicationStatusRow['Type'], 'needs_information') === false) {
            $conn->query("ALTER TABLE loan_applications MODIFY COLUMN status ENUM('pending','needs_information','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending'");
        }
    }

    $paymentTableColumnsResult = $conn->query("SHOW COLUMNS FROM payments");
    $existingPaymentColumns = [];
    if ($paymentTableColumnsResult) {
        while ($row = $paymentTableColumnsResult->fetch_assoc()) {
            $existingPaymentColumns[] = $row['Field'];
        }
    }

    $paymentColumnStatements = [
        'loan_id' => "ALTER TABLE payments ADD COLUMN loan_id INT NOT NULL DEFAULT 0",
        'customer_id' => "ALTER TABLE payments ADD COLUMN customer_id INT NOT NULL DEFAULT 0",
        'amount' => "ALTER TABLE payments ADD COLUMN amount DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        'method' => "ALTER TABLE payments ADD COLUMN method VARCHAR(60) DEFAULT 'manual'",
        'status' => "ALTER TABLE payments ADD COLUMN status ENUM('posted') NOT NULL DEFAULT 'posted'",
        'paid_at' => "ALTER TABLE payments ADD COLUMN paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'payment_date' => "ALTER TABLE payments ADD COLUMN payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($paymentColumnStatements as $column => $sql) {
        if (!in_array($column, $existingPaymentColumns, true)) {
            $conn->query($sql);
        }
    }

    if (!in_array('paid_at', $existingPaymentColumns, true) && in_array('payment_date', $existingPaymentColumns, true)) {
        $conn->query("UPDATE payments SET paid_at = payment_date WHERE paid_at IS NULL OR paid_at = '0000-00-00 00:00:00'");
    }
}

ensureDatabaseSchema();
?>
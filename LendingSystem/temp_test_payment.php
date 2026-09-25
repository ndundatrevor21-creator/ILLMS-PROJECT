<?php
/** Temporary local repayment diagnostic; never expose because it assumes a test identity. */
require 'config/database.php';
session_start();
$_SESSION['user_id'] = 5; // customer id
$_SESSION['role'] = 'customer';
// Simulate HTTP POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['loan_id'] = '10';
$_POST['amount'] = '500';
$_POST['method'] = 'paypal';
require 'makePayment.php';

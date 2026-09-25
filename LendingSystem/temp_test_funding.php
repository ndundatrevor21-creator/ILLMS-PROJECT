<?php
/** Temporary local funding diagnostic; never expose because it assumes a test identity. */
require 'config/database.php';
session_start();
$_SESSION['user_id'] = 4;
$_SESSION['role'] = 'lender';
$_GET = ['id' => '2'];
require 'fundLoan.php';

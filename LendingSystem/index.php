<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * Keep dashboard routing explicit. This avoids notices when "role" is missing
 * and prevents arbitrary session values from becoming redirect paths.
 */
if (!empty($_SESSION['user_id'])) {
    $dashboards = [ 
        'customer' => 'borrowerDashboard.php',
        'admin'    => 'adminDashboard.php',
        'staff'    => 'staffDashboard.php',
        'officer'  => 'officerDashboard.php',
    ];

    $role = strtolower((string) ($_SESSION['role'] ?? 'customer'));
    $dashboard = $dashboards[$role] ?? $dashboards['customer'];

    header('Location: ' . $dashboard);
    exit;
}

$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102f63">
    <meta name="description" content="Fast, secure and transparent lending solutions for individuals and businesses.">
    <title>Lending Management System | Your Financial Partner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index1.css">
</head>

<body>
<a class="skip-link" href="#main-content">Skip to main content</a>

<div class="top-bar">
    <div class="container top-bar-inner">
        <div class="top-contact">
            <a href="tel:+254700000000"><i class="fa-solid fa-phone"></i><span>+254 700 000 000</span></a>
            <a href="mailto:support@lendingsystem.com"><i class="fa-solid fa-envelope"></i><span>lendingsystem@mail.com</span></a>
        </div>
        <div class="top-links">
           <a href="#" class="top-cta">Open Account</a>
        </div>
    </div>
</div>

<header class="site-header" id="siteHeader">
    <div class="container header-inner">
        <a href="#" class="brand" aria-label="Lending Management System home">
            <span class="brand-logo">L</span>
            <span class="brand-copy">
                <strong>Lending Management System</strong>
                <small>Your Fin ancial Partner</small>
            </span>
        </a>

        <button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="mainNav">
            <i class="fa-solid fa-bars"></i>
        </button>

        <nav class="main-nav" id="mainNav" aria-label="Primary navigation">
            <a href="#" class="active">Home</a>
            <a href="#about">About Us</a>
            <a href="#vision">Vision</a>
            <a href="#mission">Mission</a>
            <div class="nav-dropdown">
                <button class="dropdown-trigger" type="button" aria-expanded="false">
                    Products <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="#products"><i class="fa-solid fa-user"></i> Personal Loans</a>
                    <a href="#products"><i class="fa-solid fa-briefcase"></i> Business Loans</a>
                    <a href="#products"><i class="fa-solid fa-piggy-bank"></i> Savings & Offers</a>
                    <a href="#products"><i class="fa-solid fa-house"></i> Mortgage</a>
                </div>
            </div>

            <a href="signin.php">Sign In</a>
            <a href="signup.php" class="nav-cta">Get Started</a>
        </nav>
    </div>
</header>

<main id="main-content">

<section class="hero-banner">
    <div class="hero-orb orb-one"></div>
    <div class="hero-orb orb-two"></div>

    <div class="container hero-grid">
        <div class="hero-content">
            <div class="hero-badge"><i class="fa-solid fa-star"></i> Trusted by borrowers nationwide</div>

            <h1>Own Your Financial<br>Future with Confidence</h1>

            <p>
                Fast, secure, and transparent lending solutions designed to help you
                move faster, borrow smarter, and repay with ease. From personal loans
                to business financing, we've got you covered.
            </p>

            <div class="hero-buttons">
                <a href="#" class="btn btn-light"><i class="fa-solid fa-user-plus"></i> Open an Account</a>
                <a href="#" class="btn btn-ghost"><i class="fa-solid fa-file-signature"></i> Apply for Loan</a>
            </div>

            <div class="hero-stats">
                <div class="hero-stat">
                    <span>Happy Borrowers</span>
                </div>
                
                <div class="hero-stat">
                    <span>Fast Approval Time</span>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="visual-glow"></div>
            <img src="Images/image4.jpeg" alt="Customers discussing financial services" loading="eager">

            <div class="visual-card satisfaction-card">
                <span>Customer Satisfaction</span>
                <strong>98%</strong>
                <small><i class="fa-solid fa-arrow-up"></i> +2.4% this month</small>
            </div>

    
        </div>
    </div>
</section>

<section class="quick-bar" aria-label="Quick links">
    <div class="container quick-inner">
        <span class="quick-label"><i class="fa-solid fa-magnifying-glass"></i> I'm looking for...</span>
        <div class="quick-links">
            <a href="#products"><i class="fa-solid fa-hand-holding-dollar"></i> Personal Loan</a>
            <a href="#products"><i class="fa-solid fa-briefcase"></i> Business Loan</a>
            <a href="#products"><i class="fa-solid fa-house"></i> Mortgage</a>
            <a href="#products"><i class="fa-solid fa-piggy-bank"></i> Savings Account</a>
        </div>
    </div>
</section>

<section class="section section-light" id="about">
    <div class="container about-grid">
        <div class="about-image-wrap">
            <img src="Images/image3.jpeg" alt="Lending Management System team" loading="lazy">
            <div class="about-float-card">
                <span>24/7 Client Service</span>
            </div>
        </div>

        <div class="about-text">
            <span class="section-badge">About Us</span>
            <h2>Building Trust, Delivering Results</h2>
            <p>At <strong>Lending Management System</strong>, we believe everyone deserves access to fair, transparent, and efficient financial services.</p>

            <div class="about-stats-row">
                <div class="about-stat-box"><strong>98%</strong><span>Satisfaction Rate</span></div>
                <div class="about-stat-box"><strong>24h</strong><span>Avg. Approval</span></div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="purpose">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">Our Purpose</span>
            <h2>Vision &amp; Mission</h2>
            <p>Driven by purpose, guided by values, and committed to transforming the lending landscape.</p>
        </div>

        <div class="vm-grid">
            <article class="vm-card" id="vision">
                <div class="vm-card-img"><img src="Images/image2.jpeg" alt="Our vision" loading="lazy"></div>
                <div class="vm-card-body">
                    <div class="vm-icon-wrap"><i class="fa-solid fa-eye"></i></div>
                    <h3>Our Vision</h3>
                    <p>To become the leading digital lending platform that empowers individuals and businesses nationwide.</p>
                </div>
            </article>

            <article class="vm-card" id="mission">
                <div class="vm-card-img"><img src="Images/image8.jpeg" alt="Our mission" loading="lazy"></div>
                <div class="vm-card-body">
                    <div class="vm-icon-wrap"><i class="fa-solid fa-bullseye"></i></div>
                    <h3>Our Mission</h3>
                    <p>To leverage technology and automation to simplify lendig processes, improve credit decisions,reduce risks and expand access to affordable financial services for underserved borrowers.</p>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="section section-soft" id="products">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">Discover Products</span>
            <h2>Products for You</h2>
            <p>Everything we do is designed to help you build a better financial future.</p>
        </div>

        <div class="products-grid">
            <article class="product-card">
                <div class="product-img"><img src="Images/image5.jpeg" alt="Personal loan" loading="lazy"></div>
                <div class="product-body">
                    <h3>Personal Loans</h3>
                    <p>Flexible personal loans with competitive rates and clear repayment terms.</p>
                    <a href="applyLoan.php">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </article>

            <article class="product-card">
                <div class="product-img"><img src="Images/image6.jpeg" alt="Business loan" loading="lazy"></div>
                <div class="product-body">
                    <h3>Business Loans</h3>
                    <p>Grow your business with financing designed around your goals.</p>
                    <a href="#">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </article>

            <article class="product-card">
                <div class="product-img"><img src="Images/image7.jpeg" alt="Home loan" loading="lazy"></div>
                <div class="product-body">
                    <h3>Home Loans</h3>
                    <p>Take the next step toward home ownership with confidence.</p>
                    <a href="#">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">Why Choose Us</span>
            <h2>Everything You Need to Fund Your Goals</h2>
            <p>Experience the difference with our modern, customer-focused lending platform.</p>
        </div>

        <div class="features-grid">
            <article class="feature-card"><div class="feature-icon"><i class="fa-solid fa-bolt"></i></div><h3>Fast Approval</h3><p>Get a decision in as little as 24 hours for eligible applications.</p></article>
            <article class="feature-card"><div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div><h3>Secure Platform</h3><p>Your account and application experience is designed with security in mind.</p></article>
            <article class="feature-card"><div class="feature-icon"><i class="fa-solid fa-percent"></i></div><h3>Competitive Rates</h3><p>Flexible options and transparent terms built around your needs.</p></article>
            <article class="feature-card"><div class="feature-icon"><i class="fa-solid fa-headset"></i></div><h3>Dedicated Support</h3><p>Get help from our team whenever you need guidance.</p></article>
        </div>
    </div>
</section>

<section class="how-section">
    <div class="container">
        <div class="section-header section-header-light">
            <span class="section-badge section-badge-light">How It Works</span>
            <h2>Simple Steps. Clear Decisions. Real Results.</h2>
            <p>Four straightforward steps to apply, get approved, and manage your repayments.</p>
        </div>

        <div class="steps-grid">
            <article class="step-card"><span>1</span><h3>Create an Account</h3><p>Sign up in minutes and complete your profile.</p></article>
            <article class="step-card"><span>2</span><h3>Apply for a Loan</h3><p>Fill out the application with the required details.</p></article>
            <article class="step-card"><span>3</span><h3>Get Approved</h3><p>Receive your decision and funding information.</p></article>
            <article class="step-card"><span>4</span><h3>Manage Repayments</h3><p>Track your loan and make convenient payments online.</p></article>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="container">
        <span class="section-badge section-badge-light">Ready When You Are</span>
        <h2>Ready to Get Started?</h2>
        <p>Join thousands of customers building a stronger financial future.</p>
        <a href="signup.php" class="btn btn-light btn-large"><i class="fa-solid fa-rocket"></i> Apply Now</a>
    </div>
</section>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a href="#" class="brand footer-brand-link">
                <span class="brand-logo">L</span>
                <span class="brand-copy"><strong>Lending Management System</strong><small>Your Financial Partner</small></span>
            </a>
            <p>Your trusted partner for personal and business lending solutions.</p>
            <div class="footer-social">
                <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" aria-label="Twitter"><i class="fa-brands fa-x-twitter"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
            </div>
        </div>

        <div class="footer-col"><h4>Products</h4><a href="applyLoan.php">Personal Loans</a><a href="#">Business Loans</a><a href="#">Mortgages</a><a href="#">Savings Accounts</a></div>
        <div class="footer-col"><h4>Company</h4><a href="#about">About Us</a><a href="#vision">Our Vision</a><a href="#mission">Our Mission</a><a href="#">Careers</a></div>
        <div class="footer-col"><h4>Support</h4><a href="#">Help Center</a><a href="#">FAQs</a><a href="#">Terms of Service</a><a href="#">Privacy Policy</a></div>

        <div class="footer-col footer-contact">
            <h4>Contact Us</h4>
            <p><i class="fa-solid fa-phone"></i> +254 700 000 000</p>
            </div>
    </div>

    <div class="footer-bottom">
        <div class="container">&copy; <?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?> Lending Management System. All rights reserved.</div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const header = document.getElementById('siteHeader');
    const menuButton = document.querySelector('.menu-toggle');
    const nav = document.getElementById('mainNav');
    const dropdownButton = document.querySelector('.dropdown-trigger');
    menuButton?.addEventListener('click', () => {
        const isOpen = nav.classList.toggle('is-open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
        menuButton.querySelector('i').className = isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
    });
    // JavaScript for toggling navigation and dropdown menus //
    dropdownButton?.addEventListener('click', () => {
        const isOpen = dropdownButton.parentElement.classList.toggle('is-open');
        dropdownButton.setAttribute('aria-expanded', String(isOpen));
    });

    document.querySelectorAll('.main-nav a').forEach(link => {
        link.addEventListener('click', () => {
            nav.classList.remove('is-open');
            menuButton?.setAttribute('aria-expanded', 'false');
            if (menuButton) menuButton.querySelector('i').className = 'fa-solid fa-bars';
        });
    });

    window.addEventListener('scroll', () => {
        header.classList.toggle('is-scrolled', window.scrollY > 20);
    }, { passive: true });
});
</script>
</body>
</html>

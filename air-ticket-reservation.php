<?php
session_start();

// Security: Check if ID exists and not empty
if (!isset($_GET['id']) || trim($_GET['id']) === '') {
    die("Invalid reservation session.");
}

$user_id = trim($_GET['id']);

// More flexible validation - allow alphanumeric and common separators
if (!preg_match('/^[a-zA-Z0-9\-_]{8,100}$/', $user_id)) {
    die("Invalid user ID format. Please use a valid session ID.");
}

// Regenerate session ID for security but preserve the user_id
session_regenerate_id(true);
$_SESSION['user_id'] = $user_id;

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set minimum dates for date pickers
$today = date('Y-m-d');
$maxDob = date('Y-m-d', strtotime('-1 day')); // Can't be born today
$minDob = date('Y-m-d', strtotime('-120 years')); // Max age 120
$maxPassportExpiry = date('Y-m-d', strtotime('+20 years')); // Passport valid 20 years max
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Air Ticket Reservation | ScholarSync Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

<style>
/* ============================================
   SCHOLARSYNC GLOBAL - PREMIUM AIR TICKET RESERVATION
   Ultra-Modern UI/UX Design
============================================ */
:root {
    --primary-navy: #427431;
    --secondary-blue: #3661B9;
    --dark-blue: #2f5a26;
    --accent-gold: #E21D1E;
    --accent-teal: #2DD4BF;
    --pure-white: #FFFFFF;
    
    --primary-light: rgba(1, 47, 107, 0.08);
    --accent-light: rgba(242, 166, 90, 0.12);
    --teal-light: rgba(45, 212, 191, 0.1);
    --gold-light: rgba(242, 166, 90, 0.15);
    
    --bg: #F8FAFC;
    --bg-light: #FFFFFF;
    --card: #FFFFFF;
    --text: #1E293B;
    --text-light: #64748B;
    --text-muted: #94A3B8;
    --border: #E2E8F0;
    --border-light: #F1F5F9;
    --border-focus: var(--accent-teal);
    --input-bg: #FFFFFF;
    --error: #EF4444;
    --success: #10B981;
    
    --shadow-sm: 0 2px 4px rgba(1, 47, 107, 0.04);
    --shadow-md: 0 4px 12px rgba(1, 47, 107, 0.08);
    --shadow-lg: 0 8px 20px rgba(1, 47, 107, 0.12);
    --shadow-xl: 0 20px 40px rgba(1, 47, 107, 0.15);
    
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
    color: var(--text);
    line-height: 1.6;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
    min-height: 100vh;
}

/* ===== PAGE HEADER ===== */
.form-wrap {
    max-width: 1200px;
    margin: 40px auto 90px;
    padding: 0 20px;
    position: relative;
}

.form-wrap::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, var(--gold-light), transparent 70%);
    border-radius: 50%;
    z-index: -1;
    opacity: 0.5;
}

.form-wrap::after {
    content: '';
    position: absolute;
    bottom: -50px;
    left: -50px;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, var(--teal-light), transparent 70%);
    border-radius: 50%;
    z-index: -1;
    opacity: 0.5;
}

.page-title {
    text-align: center;
    margin-bottom: 40px;
    position: relative;
}

.page-title h1 {
    font-size: 2.8rem;
    font-weight: 800;
    color: var(--primary-navy);
    margin-bottom: 12px;
    position: relative;
    display: inline-block;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-title h1::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
    border-radius: 4px;
}

.page-title p {
    font-size: 1.1rem;
    color: var(--text-light);
    max-width: 600px;
    margin: 25px auto 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: white;
    padding: 12px 24px;
    border-radius: 50px;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-light);
}

/* ===== FORM SECTIONS ===== */
.form-section {
    background: var(--card);
    border: 1px solid var(--border-light);
    border-radius: 24px;
    padding: 32px;
    margin-bottom: 30px;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
    transition: var(--transition);
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.form-section:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
    border-color: var(--accent-teal);
}

.form-section::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: linear-gradient(180deg, var(--accent-gold), var(--accent-teal));
    border-radius: 24px 0 0 24px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.section-header i {
    font-size: 1.6rem;
    color: var(--accent-teal);
    background: var(--teal-light);
    padding: 10px;
    border-radius: 14px;
    transition: var(--transition);
}

.form-section:hover .section-header i {
    transform: rotate(5deg) scale(1.1);
    color: var(--accent-gold);
    background: var(--gold-light);
}

.section-header h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-navy);
    margin: 0;
}

.section-help {
    font-size: 0.9rem;
    color: var(--text-light);
    margin-bottom: 24px;
    padding-left: 50px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ===== PREMIUM INPUT FIELDS ===== */
.form-control, .form-select {
    height: 52px;
    border-radius: 16px;
    font-size: 0.95rem;
    border: 2px solid var(--border);
    background: var(--input-bg);
    padding: 0 18px;
    color: var(--text);
    font-weight: 500;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
}

.form-control:focus, .form-select:focus {
    border-color: var(--accent-teal);
    box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.2);
    outline: none;
    transform: scale(1.02);
}

/* Flatpickr Customization */
.flatpickr-calendar {
    border-radius: 16px !important;
    border: 2px solid var(--border) !important;
    box-shadow: var(--shadow-xl) !important;
    font-family: 'Inter', sans-serif !important;
    margin-top: 8px;
}

.flatpickr-day.selected {
    background: var(--accent-teal) !important;
    border-color: var(--accent-teal) !important;
}

.flatpickr-day.today {
    border-color: var(--accent-gold) !important;
}

/* Input Groups with Icons */
.input-icon-group {
    position: relative;
    width: 100%;
}

.input-icon-group i {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1.1rem;
    z-index: 1;
    transition: var(--transition);
    pointer-events: none;
}

.input-icon-group .form-control,
.input-icon-group .form-select {
    padding-left: 48px;
}

.input-icon-group:focus-within i {
    color: var(--accent-teal);
}

/* ===== SELECT2 CUSTOMIZATION ===== */
.select2-container {
    width: 100% !important;
    font-size: 0.95rem;
}

.select2-container--bootstrap-5 .select2-selection {
    min-height: 52px !important;
    border-radius: 16px !important;
    border: 2px solid var(--border) !important;
    background: var(--input-bg) !important;
    box-shadow: var(--shadow-sm) !important;
}

.select2-container--bootstrap-5.select2-container--focus .select2-selection {
    border-color: var(--accent-teal) !important;
    box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.2) !important;
}

/* ===== INT TEL INPUT ===== */
.iti {
    width: 100%;
    --iti-border-radius: 16px;
}

.iti__flag-container {
    padding-left: 12px;
}

.iti--separate-dial-code .iti__selected-flag {
    background: linear-gradient(135deg, var(--primary-light), var(--teal-light)) !important;
    border-radius: 16px 0 0 16px !important;
    border-right: 2px solid var(--border) !important;
}

/* ===== PAYMENT METHOD BOX ===== */
.payment-box {
    display: none;
    background: linear-gradient(135deg, var(--gold-light), var(--teal-light));
    border: 2px solid var(--accent-teal);
    padding: 24px;
    border-radius: 20px;
    margin-top: 24px;
    animation: slideDown 0.4s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ===== SUBMIT BUTTON ===== */
.btn-submit {
    background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
    color: white;
    border: none;
    padding: 18px 60px;
    font-weight: 700;
    font-size: 1.2rem;
    border-radius: 50px;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    box-shadow: 0 8px 25px rgba(1, 47, 107, 0.3);
    position: relative;
    overflow: hidden;
}

.btn-submit:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 15px 35px rgba(1, 47, 107, 0.4);
}

.btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* ===== LOADING OVERLAY ===== */
#uploadOverlay {
    position: fixed;
    inset: 0;
    background: rgba(1, 47, 107, 0.95);
    backdrop-filter: blur(8px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.upload-card {
    background: white;
    padding: 50px 60px;
    border-radius: 30px;
    text-align: center;
    box-shadow: var(--shadow-xl);
    animation: popIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    max-width: 400px;
    width: 90%;
}

@keyframes popIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* ===== VALIDATION STYLES ===== */
.invalid-feedback {
    display: none;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--error);
}

.form-control.is-invalid,
.form-select.is-invalid,
.iti.is-invalid input {
    border-color: var(--error) !important;
}

.form-control.is-invalid ~ .invalid-feedback,
.form-select.is-invalid ~ .invalid-feedback,
.iti.is-invalid ~ .invalid-feedback {
    display: block;
}

/* ===== TOAST NOTIFICATIONS ===== */
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    color: var(--text);
    padding: 16px 24px;
    border-radius: 12px;
    box-shadow: var(--shadow-xl);
    border-left: 4px solid var(--accent-teal);
    z-index: 10000;
    transform: translateX(400px);
    transition: transform 0.3s ease;
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: 350px;
}

.toast-notification.show {
    transform: translateX(0);
}

.toast-notification.toast-error {
    border-left-color: var(--error);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .form-wrap {
        padding: 20px 15px 60px;
    }
    
    .page-title h1 {
        font-size: 2rem;
    }
    
    .form-section {
        padding: 25px 20px;
    }
    
    .btn-submit {
        padding: 16px 40px;
        width: 100%;
    }
}
</style>
</head>

<body>
<?php include 'header.php'; ?>

<div class="form-wrap">
    <div class="page-title">
        <h1><i class="fas fa-plane-departure"></i> Air Ticket Reservation</h1>
        <p>
            <i class="fas fa-search"></i> 
            Search by <strong>city name</strong>, select airports, and submit your request
            <span class="badge-premium"><i class="fas fa-star"></i> Premium Service</span>
        </p>
    </div>

    <form id="airForm" novalidate>
        <!-- Security Tokens -->
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($_SESSION['user_id']) ?>">
        <input type="hidden" name="form_version" value="1.0">
        <input type="hidden" name="submission_time" value="<?= time() ?>">
        
        <!-- Dynamic Fields -->
        <input type="hidden" name="departure_city" id="departure_city">
        <input type="hidden" name="destination_city" id="destination_city">
        <input type="hidden" name="departure_city_text" id="departure_city_text">
        <input type="hidden" name="destination_city_text" id="destination_city_text">
        <input type="hidden" name="airline_preferences" id="airline_preferences">
        <input type="hidden" name="phone_area_code" id="phone_area_code">
        <input type="hidden" name="phone_number" id="phone_number">

        <!-- PASSENGER INFORMATION -->
        <section class="form-section">
            <div class="section-header">
                <i class="fas fa-user-circle"></i>
                <h3>Passenger Information</h3>
            </div>
            <p class="section-help">
                <i class="fas fa-info-circle"></i> Details must match passport exactly
            </p>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="input-icon-group">
                        <i class="fas fa-user"></i>
                        <input class="form-control" name="full_name" 
                               placeholder="Full name (as on passport)" 
                               pattern="[A-Za-z\s\-']{2,100}" 
                               title="Please enter a valid name (letters, spaces, hyphens only)"
                               required>
                        <div class="invalid-feedback">Please enter your full name as it appears on your passport</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="input-icon-group">
                        <i class="fas fa-venus-mars"></i>
                        <select class="form-select" name="gender" required>
                            <option value="" disabled selected>Gender</option>
                            <option value="Male">👨 Male</option>
                            <option value="Female">👩 Female</option>
                        </select>
                        <div class="invalid-feedback">Please select your gender</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="input-icon-group">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="text" class="form-control datepicker-birth" 
                               name="date_of_birth" placeholder="Date of birth" 
                               required readonly>
                        <div class="invalid-feedback">Please select your date of birth</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-globe"></i>
                        <input class="form-control" name="nationality" 
                               placeholder="Nationality" list="countries" required>
                        <datalist id="countries"></datalist>
                        <div class="invalid-feedback">Please enter your nationality</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-passport"></i>
                        <input class="form-control" name="passport_number" 
                               placeholder="Passport number" 
                               pattern="[A-Z0-9]{6,20}" 
                               title="Passport number should be 6-20 alphanumeric characters"
                               required>
                        <div class="invalid-feedback">Please enter a valid passport number (6-20 characters)</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-calendar-times"></i>
                        <input type="text" class="form-control datepicker-passport" 
                               name="passport_expiry" placeholder="Passport expiry date" 
                               required readonly>
                        <div class="invalid-feedback">Please select passport expiry date</div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="input-icon-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" class="form-control" name="email" 
                               placeholder="Email address" required>
                        <div class="invalid-feedback">Please enter a valid email address</div>
                    </div>
                </div>

                <div class="col-md-6">
                    <input type="tel" id="phone" class="form-control" 
                           placeholder="Phone number" required>
                    <div class="invalid-feedback" id="phone-error">Please enter a valid phone number</div>
                    <small class="text-muted mt-1 d-block">
                        <i class="fas fa-globe-americas"></i> Select country code from dropdown
                    </small>
                </div>
            </div>
        </section>

        <!-- TRIP ROUTE -->
        <section class="form-section">
            <div class="section-header">
                <i class="fas fa-route"></i>
                <h3>Trip Route</h3>
            </div>
            <p class="section-help">
                <i class="fas fa-info-circle"></i> Type city name and select the correct airport
            </p>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-map-signs"></i>
                        <select class="form-select" name="trip_type" id="trip_type" required>
                            <option value="" disabled selected>Trip type</option>
                            <option value="one_way">➡️ One Way</option>
                            <option value="round_trip">🔄 Round Trip</option>
                            <option value="multi_city">📍 Multi City</option>
                        </select>
                        <div class="invalid-feedback">Please select trip type</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-plane-departure"></i>
                        <select id="departure_airport" class="form-control" required></select>
                        <div class="invalid-feedback">Please select departure airport</div>
                    </div>
                    <small class="text-muted mt-1"><i class="fas fa-info-circle"></i> Departure city</small>
                </div>

                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-plane-arrival"></i>
                        <select id="destination_airport" class="form-control" required></select>
                        <div class="invalid-feedback">Please select destination airport</div>
                    </div>
                    <small class="text-muted mt-1"><i class="fas fa-info-circle"></i> Destination city</small>
                </div>
            </div>
        </section>

        <!-- TRAVEL DATES & CABIN -->
        <section class="form-section">
            <div class="section-header">
                <i class="fas fa-clock"></i>
                <h3>Travel Dates & Cabin</h3>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-calendar-check"></i>
                        <input type="text" class="form-control datepicker-departure" 
                               name="departure_date" placeholder="Departure date" 
                               required readonly>
                        <div class="invalid-feedback">Please select departure date</div>
                    </div>
                    <small class="text-muted mt-1"><i class="fas fa-plane-departure"></i> Departure date</small>
                </div>

                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-calendar-plus"></i>
                        <input type="text" class="form-control datepicker-return" 
                               name="return_date" placeholder="Return date" readonly>
                        <div class="invalid-feedback">Please select return date for round trips</div>
                    </div>
                    <small class="text-muted mt-1"><i class="fas fa-plane-arrival"></i> Return date (if round trip)</small>
                </div>

                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-couch"></i>
                        <select class="form-select" name="cabin_class" required>
                            <option value="" disabled selected>Cabin class</option>
                            <option value="economy">💺 Economy</option>
                            <option value="business">✨ Business</option>
                            <option value="first">👑 First Class</option>
                        </select>
                        <div class="invalid-feedback">Please select cabin class</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- PASSENGERS COUNT -->
        <section class="form-section">
            <div class="section-header">
                <i class="fas fa-users"></i>
                <h3>Passengers</h3>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="input-icon-group">
                        <i class="fas fa-user-plus"></i>
                        <input type="number" min="1" max="9" value="1" 
                               class="form-control" name="passengers" 
                               placeholder="Total number of passengers" required>
                        <div class="invalid-feedback">Please enter a valid number of passengers (1-9)</div>
                    </div>
                    <small class="text-muted mt-1"><i class="fas fa-info-circle"></i> Including infants and children</small>
                </div>
            </div>
        </section>

        <!-- AIRLINE PREFERENCES -->
        <section class="form-section">
            <div class="section-header">
                <i class="fas fa-plane"></i>
                <h3>Airline Preferences</h3>
            </div>
            <p class="section-help">
                <i class="fas fa-info-circle"></i> Optional – select preferred airlines
            </p>
            <select id="airlines" class="form-control" multiple></select>
            <small class="text-muted mt-2 d-block">
                <i class="fas fa-check-circle"></i> You can select multiple airlines
            </small>
        </section>

        <!-- PAYMENT METHOD -->
        <section class="form-section">
            <div class="section-header">
                <i class="fas fa-credit-card"></i>
                <h3>Payment Method</h3>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="input-icon-group">
                        <i class="fas fa-money-bill-wave"></i>
                        <select class="form-select" name="payment_method" id="paymentMethod" required>
                            <option value="" disabled selected>Choose payment method</option>
                            <option value="mobile_money">📱 Mobile Money</option>
                            <option value="bank_transfer">🏦 Bank Transfer</option>
                            <option value="cash">💵 Cash</option>
                        </select>
                        <div class="invalid-feedback">Please select payment method</div>
                    </div>
                </div>
            </div>

            <div class="payment-box" id="mobileBox">
                <strong><i class="fas fa-mobile-alt"></i> Mobile Money Details</strong>
                <p><i class="fas fa-building"></i> <strong>MTN:</strong> +250 788 000 000</p>
                <p><i class="fas fa-building"></i> <strong>Airtel:</strong> +250 730 000 000</p>
                <p><i class="fas fa-clock"></i> Available 24/7</p>
            </div>
        </section>

        <!-- CONSENT CHECKBOX -->
        <section class="form-section">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" required id="consent" name="consent">
                <label class="form-check-label" for="consent">
                    <i class="fas fa-check-circle"></i>
                    I confirm the information is accurate and consent to be contacted.
                </label>
                <div class="invalid-feedback">You must consent to continue</div>
            </div>
        </section>

        <!-- SUBMIT BUTTON -->
        <div class="text-center">
            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Submit Reservation
            </button>
            <p class="text-muted mt-3"><i class="fas fa-lock"></i> Your information is secure and encrypted</p>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>

<!-- LOADING OVERLAY -->
<div id="uploadOverlay">
    <div class="upload-card">
        <i class="fas fa-spinner fa-pulse"></i>
        <strong>Processing…</strong>
        <p>Please wait while we submit your reservation</p>
        <div class="spinner"></div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
$(function() {
    // ============================================
    // FLATPICKR DATE PICKERS
    // ============================================
    
    // Date of Birth Picker
    $(".datepicker-birth").flatpickr({
        dateFormat: "Y-m-d",
        maxDate: "<?= $maxDob ?>",
        minDate: "<?= $minDob ?>",
        altInput: true,
        altFormat: "F j, Y",
        placeholder: "Select date of birth"
    });
    
    // Passport Expiry Picker
    $(".datepicker-passport").flatpickr({
        dateFormat: "Y-m-d",
        minDate: "today",
        maxDate: "<?= $maxPassportExpiry ?>",
        altInput: true,
        altFormat: "F j, Y",
        placeholder: "Select passport expiry date"
    });
    
    // Departure Date Picker
    const departurePicker = $(".datepicker-departure").flatpickr({
        dateFormat: "Y-m-d",
        minDate: "today",
        altInput: true,
        altFormat: "F j, Y",
        placeholder: "Select departure date",
        onChange: function(selectedDates, dateStr) {
            if (dateStr) {
                const nextDay = new Date(dateStr);
                nextDay.setDate(nextDay.getDate() + 1);
                returnPicker.set('minDate', nextDay);
            }
        }
    });
    
    // Return Date Picker
    const returnPicker = $(".datepicker-return").flatpickr({
        dateFormat: "Y-m-d",
        minDate: "today",
        altInput: true,
        altFormat: "F j, Y",
        placeholder: "Select return date"
    });

    // ============================================
    // PHONE INPUT
    // ============================================
    const phoneInput = document.getElementById('phone');
    const iti = intlTelInput(phoneInput, {
        separateDialCode: true,
        preferredCountries: ['us', 'gb', 'ca', 'rw', 'ke', 'ug', 'za', 'ng'],
        initialCountry: 'us',
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
        nationalMode: false,
        autoPlaceholder: 'polite'
    });
    
    // Sync phone data
    const syncPhone = () => {
        const countryData = iti.getSelectedCountryData();
        $("#phone_area_code").val("+" + countryData.dialCode);
        $("#phone_number").val(phoneInput.value.trim());
    };
    
    syncPhone();
    phoneInput.addEventListener("countrychange", syncPhone);
    phoneInput.addEventListener("input", syncPhone);

    // ============================================
    // SELECT2 INITIALIZATION
    // ============================================
    
    // Departure Airport
    $("#departure_airport").select2({
        theme: "bootstrap-5",
        placeholder: "🔍 Type departure city name...",
        minimumInputLength: 2,
        allowClear: true,
        ajax: {
            url: "getAirports.php",
            dataType: "json",
            delay: 300,
            data: params => ({ q: params.term }),
            processResults: data => ({ results: data })
        }
    }).on("select2:select", e => {
        $("#departure_city").val(e.params.data.id);
        $("#departure_city_text").val(e.params.data.text);
    });

    // Destination Airport
    $("#destination_airport").select2({
        theme: "bootstrap-5",
        placeholder: "🔍 Type destination city name...",
        minimumInputLength: 2,
        allowClear: true,
        ajax: {
            url: "getAirports.php",
            dataType: "json",
            delay: 300,
            data: params => ({ q: params.term }),
            processResults: data => ({ results: data })
        }
    }).on("select2:select", e => {
        $("#destination_city").val(e.params.data.id);
        $("#destination_city_text").val(e.params.data.text);
    });

    // Airlines
    $("#airlines").select2({
        theme: "bootstrap-5",
        placeholder: "🔍 Search for airlines...",
        minimumInputLength: 1,
        allowClear: true,
        multiple: true,
        ajax: {
            url: "getAirlines.php",
            dataType: "json",
            delay: 300,
            data: params => ({ q: params.term }),
            processResults: data => ({ results: data })
        }
    }).on("change", function() {
        const selected = $(this).val();
        $("#airline_preferences").val(selected ? JSON.stringify(selected) : "");
    });

    // ============================================
    // POPULATE COUNTRIES
    // ============================================
    $.getJSON("getCountries.php", function(countries) {
        const datalist = $("#countries");
        countries.forEach(country => {
            datalist.append(`<option value="${country.name}">`);
        });
    });

    // ============================================
    // TRIP TYPE HANDLER
    // ============================================
    $("#trip_type").on("change", function() {
        if ($(this).val() === "round_trip") {
            $(".datepicker-return").prop("required", true);
        } else {
            $(".datepicker-return").prop("required", false).val("");
        }
    });

    // ============================================
    // PAYMENT METHOD TOGGLE
    // ============================================
    $("#paymentMethod").on("change", function() {
        if (this.value === "mobile_money") {
            $("#mobileBox").slideDown(300);
        } else {
            $("#mobileBox").slideUp(200);
        }
    });

    // ============================================
    // FORM SUBMISSION
    // ============================================
    $("#airForm").on("submit", async function(e) {
        e.preventDefault();
        
        // Reset validation states
        $('.is-invalid').removeClass('is-invalid');
        
        // Validate phone
        if (!iti.isValidNumber()) {
            phoneInput.classList.add('is-invalid');
            $("#phone-error").show();
            return;
        }
        
        // Validate airports
        if (!$("#departure_city").val()) {
            showToast("Please select a departure airport", "error");
            return;
        }
        
        if (!$("#destination_city").val()) {
            showToast("Please select a destination airport", "error");
            return;
        }
        
        // HTML5 validation
        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }
        
        // Disable button and show loading
        const $submitBtn = $("#submitBtn");
        const originalText = $submitBtn.html();
        $submitBtn.prop("disabled", true).html('<i class="fas fa-spinner fa-pulse"></i> Processing...');
        $("#uploadOverlay").fadeIn(300);
        
        try {
            const formData = new FormData(this);
            
            const response = await fetch("submitAirReservation.php", {
                method: "POST",
                body: formData
            });
            
            const data = await response.json();
            
            if (data.status === "success") {
                sessionStorage.setItem('reservation_success', JSON.stringify({
                    id: data.reservation_id,
                    name: $("input[name='full_name']").val()
                }));
                
                window.location.href = "thank-you.php?id=" + encodeURIComponent(data.user_id) + 
                                      "&reservation=" + encodeURIComponent(data.reservation_id);
            } else {
                showToast(data.message || "Submission failed", "error");
                $("#uploadOverlay").fadeOut(200);
                $submitBtn.prop("disabled", false).html(originalText);
            }
        } catch (error) {
            console.error("Submission error:", error);
            showToast("Network error. Please try again.", "error");
            $("#uploadOverlay").fadeOut(200);
            $submitBtn.prop("disabled", false).html(originalText);
        }
    });

    // ============================================
    // TOAST FUNCTION
    // ============================================
    function showToast(message, type = "info") {
        const toast = $(`
            <div class="toast-notification toast-${type}">
                <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
            </div>
        `);
        
        $("body").append(toast);
        
        setTimeout(() => toast.addClass("show"), 100);
        setTimeout(() => {
            toast.removeClass("show");
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // ============================================
    // AUTO-SAVE DRAFT
    // ============================================
    let saveTimeout;
    $("#airForm").on("input change", "input, select", function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            const formData = {};
            $("#airForm").serializeArray().forEach(field => {
                if (!field.name.includes('csrf') && field.name !== 'submission_time') {
                    formData[field.name] = field.value;
                }
            });
            localStorage.setItem("airReservationDraft", JSON.stringify(formData));
        }, 1000);
    });

    // Load draft
    const draft = localStorage.getItem("airReservationDraft");
    if (draft) {
        const formData = JSON.parse(draft);
        Object.keys(formData).forEach(name => {
            const $field = $(`[name="${name}"]`);
            if ($field.length && !$field.val() && name !== 'csrf_token' && name !== 'submission_time') {
                $field.val(formData[name]);
            }
        });
    }
});
</script>

</body>
</html>
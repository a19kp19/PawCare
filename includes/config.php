<?php
/**
 * PawCare — Veterinary Clinic Management & Appointment System
 * ------------------------------------------------------------
 * Edit this file to match your client's clinic and your local database.
 */

// ---------------------------------------------------------------------------
// Database (XAMPP defaults: user "root" with an empty password)
// Environment variables are optional overrides used by hosting/CI.
// ---------------------------------------------------------------------------
define('DB_HOST', getenv('PAWCARE_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('PAWCARE_DB_PORT') ?: '3306');
define('DB_NAME', getenv('PAWCARE_DB_NAME') ?: 'pawcare');
define('DB_USER', getenv('PAWCARE_DB_USER') ?: 'root');
define('DB_PASS', getenv('PAWCARE_DB_PASS') ?: '');

// Show detailed PHP errors (turn off when the site goes live).
define('APP_DEBUG', true);

// ---------------------------------------------------------------------------
// Clinic details — replace with your client's real information
// ---------------------------------------------------------------------------
define('CLINIC_NAME', 'PawCare Veterinary Clinic');
define('CLINIC_SHORT_NAME', 'PawCare');
define('CLINIC_TAGLINE', 'Gentle, expert care for every paw');
define('CLINIC_SINCE', 2012);
define('CLINIC_PHONE', '(02) 8123 4567');
define('CLINIC_MOBILE', '0917 123 4567');
define('CLINIC_EMERGENCY', '0998 765 4321');
define('CLINIC_EMAIL', 'hello@pawcare.test');
define('CLINIC_ADDRESS', '123 Rizal Avenue, Brgy. San Roque, Quezon City');
define('CLINIC_MAP_QUERY', 'Quezon City Memorial Circle, Quezon City');
define('CLINIC_FACEBOOK', '#');
define('CLINIC_INSTAGRAM', '#');

define('CURRENCY', '₱');
define('TIMEZONE', 'Asia/Manila');

// ---------------------------------------------------------------------------
// Scheduling rules
// ---------------------------------------------------------------------------
// Opening hours per ISO weekday (1 = Monday ... 7 = Sunday). null = closed.
const CLINIC_HOURS = [
    1 => ['08:00', '18:00'],
    2 => ['08:00', '18:00'],
    3 => ['08:00', '18:00'],
    4 => ['08:00', '18:00'],
    5 => ['08:00', '18:00'],
    6 => ['08:00', '17:00'],
    7 => null,
];
const LUNCH_BREAK = ['12:00', '13:00'];   // set to null if the clinic has no lunch break
const SLOT_MINUTES = 30;                  // time-slot interval shown to clients
const BOOKING_WINDOW_DAYS = 60;           // how far ahead clients can book
const MIN_NOTICE_MINUTES = 60;            // clients must book at least this far ahead
const CANCEL_NOTICE_MINUTES = 120;        // clients can cancel up to this long before the visit

// ---------------------------------------------------------------------------
// Misc
// ---------------------------------------------------------------------------
const MAX_UPLOAD_MB = 4;
const REMINDER_WINDOW_DAYS = 30;          // vaccines due within this many days count as "due soon"
const SHOW_DEMO_LOGINS = true;            // shows one-click demo accounts on the login page

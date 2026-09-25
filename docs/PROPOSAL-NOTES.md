# Proposal notes: PawCare Veterinary Clinic System

This is a draft for each row of the WebDev2 proposal template, based on what the system does.
Replace the clinic details with your real client's name, location and needs from your interview.

---

## Proposed Website Overview
*General explanation of the website, core concept and functionality*

**PawCare** is a website and clinic management system for a small veterinary clinic.
Today, many small clinics take bookings by phone or Facebook Messenger and keep paper records. This leads to double bookings, lost records and forgotten vaccine boosters.

PawCare fixes this with three connected parts:
1. **A public website** that presents the clinic's services, prices, veterinarians and contact details.
2. **A pet-owner portal** where clients register their pets, book appointments online (real-time open slots), and see their pets' medical history and vaccine schedule.
3. **A staff/admin panel** where the clinic manages the daily schedule, records visits (vitals, diagnosis, treatment, prescriptions, vaccines), sends reminders and views business reports.

It is built with PHP, MySQL and Apache (XAMPP), HTML, CSS and JavaScript.

---

## Key Features and Modules

| Module | Features |
|---|---|
| **User registration & login** | Owner self-registration, secure login with hashed passwords, three roles (Admin, Staff, Pet Owner), account settings and password change, lockout after repeated failed logins |
| **Online appointment booking** | 4-step wizard, live calendar of open slots per vet, "first available vet" option, double-booking prevention, booking reference numbers, cancel and reschedule, calendar (.ics) export |
| **Pet & medical records (data management)** | Pet profiles with photos, medical history timeline, weight-trend chart, vaccine card with due dates, printable health record |
| **Clinic operations** | Staff dashboard, day schedule per vet, confirm / complete / no-show workflow, walk-in bookings, patient and client management, services and price list, vet schedules, staff accounts |
| **Notifications** | In-app notifications for booking requests, confirmations, reschedules, cancellations and vaccine due dates, plus one-click reminder sending by staff |
| **Reporting & analytics** | Revenue and visit charts, appointment outcomes, revenue by service, vet performance, patients by species, date filters, CSV export, printable reports |
| **Public website** | Services with category filters, team page, testimonials, FAQ, pet age calculator, vaccine guide, contact form |

---

## Target Users

**Primary users: pet owners (clinic clients)**
- *Needs:* book a visit without calling, know the price beforehand, remember vaccine dates, keep their pets' records in one place
- *Expectations:* simple and mobile-friendly, quick confirmation, reminders before due dates

**Secondary users: clinic staff (receptionist / front desk and veterinarians)**
- *Needs:* see the day's schedule at a glance, avoid double bookings, find a patient's history fast, record visits quickly
- *Expectations:* fast, clear dashboard; fewer phone calls and paper forms

**Clinic owner / administrator**
- *Needs:* manage services, prices, vets and staff accounts; see revenue and clinic performance
- *Expectations:* reliable reports and control over who can access what

---

## Scope
*Features per module included in the project*

- **Public site:** home page, services and price list, about, team, pet tools (age calculator, vaccine guide), testimonials, FAQ, contact form, opening hours and live open/closed status
- **Authentication:** register, login, logout, role-based access, account and password settings
- **Owner portal:** dashboard, add / edit / delete pets with photo, pet health record (visits, vaccines, weight chart), book appointment, view / cancel appointments, notifications
- **Staff panel:** dashboard, appointment list and details, confirm / decline / complete / no-show, reschedule, walk-in booking, day schedule, patients, clients, record visit and vaccine, reminders, contact-message inbox, CSV export
- **Admin-only:** reports and analytics, services and prices, veterinarians, staff accounts
- **System:** one-click installer with demo data, responsive design, dark mode (portals), print layouts

---

## Limitations
*Features not included; technical or resource constraints*

- **No online payment.** Payment is made at the clinic (cash, GCash, Maya or card); the system only shows prices.
- **No SMS or email sending.** Notifications and reminders appear inside the website. SMS/email would need a paid gateway.
- **No inventory or pharmacy stock** management and **no point-of-sale or billing** module.
- **No telemedicine** (video consultations) and **no native mobile app.** The website is mobile-friendly instead.
- **Single branch only.** Multi-branch clinics are not supported.
- **Requires an internet or network connection** (no offline mode) and a server with PHP 8 and MySQL/MariaDB (e.g. XAMPP or shared hosting).
- The Google Maps embed needs internet access. Stock photos are placeholders to be replaced with the clinic's own photos.

---

## Attachments (you prepare these)
The template asks for an **interview**, a **screenshot of an online meeting** with the client and an **actual photo with the client**.
Suggested interview questions:
1. How do clients book appointments today, and what problems happen (double bookings, no-shows)?
2. How are pet records and vaccine histories stored now?
3. Which services do you offer, and how much do they cost?
4. What are your opening hours, and how many vets work each day?
5. What information would you like to see every day or month (revenue, visits, popular services)?
6. Who on your staff would use the system, and what should each person be allowed to do?

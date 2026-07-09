# UBook Booking Conflict Manager

A comprehensive web-based booking administration system for the **UBook Campus Venue Booking System**. This module enables booking administrators and super administrators to manage venue reservations, detect scheduling conflicts, communicate with users via email, and receive AI-powered assistance.

---

## Features

### Booking Management
- View all bookings
- Create new bookings
- Edit existing bookings
- Delete bookings
- Confirm or reject booking requests
- Search bookings
- Filter by booking status
- Pagination support
- Export bookings to CSV

---

### Conflict Detection

Automatically detects:

- Time overlaps
- Public holiday bookings
- Past date bookings
- Outside operating hours
- Maximum booking duration exceeded
- User daily booking limit exceeded

Conflict bookings are highlighted throughout the system.

---

### Calendar View

Interactive FullCalendar integration:

- Pending bookings
- Confirmed bookings
- Rejected bookings
- Conflict bookings
- Booking details popup

---

### Automatic Booking Rules

#### Auto Reject

Automatically rejects:

- Expired pending bookings
- Bookings with unresolved conflicts

---

### Email Notifications

Uses PHPMailer with Gmail SMTP.

Automatically sends emails when:

- Booking confirmed
- Booking rejected

Supports:

- Custom emails
- HTML emails
- BCC logging

---

### AI Assistant (DeepSeek)

Integrated AI assistant capable of:

- Answering booking questions
- Detecting conflicts
- Reviewing venue usage
- Reading booking comments
- Sending custom emails
- Confirming/rejecting bookings
- Multi-turn conversation history

---

### Audit Log

Every important action is logged:

- Booking creation
- Booking updates
- Booking deletion
- Status changes
- Auto rejections

---

### Statistics Dashboard

Displays:

- Total bookings
- Pending bookings
- Confirmed bookings
- Rejected bookings

---

## Technologies Used

Backend

- PHP
- MySQL
- PHPMailer
- cURL

Frontend

- HTML5
- CSS3
- JavaScript
- Font Awesome
- FullCalendar
- Marked.js
- DOMPurify

AI

- DeepSeek Chat API

---

## Database Tables

Expected tables include:

- users
- venues
- bookings
- audit_log
- venue_comments

---

## Booking Rules

| Rule | Value |
|------|-------|
| Opening Hour | 8:00 AM |
| Closing Hour | 10:00 PM |
| Maximum Duration | 4 hours |
| Maximum Daily Bookings | 3 per user |

---

## User Roles

Only the following roles may access this page:

- booking_admin
- super_admin

Unauthorized users are redirected to the login page.

---

## CSV Export

Exports:

- Booking ID
- Student
- Email
- Venue
- Date
- Time
- Duration
- Comment
- Status

---

## Public Holiday Support

Built-in Malaysian public holidays are included for:

- 2026
- 2027
- 2028
- 2029
- 2030

Bookings on these dates are automatically flagged as conflicts.

---

## Email Configuration

The project currently uses Gmail SMTP through PHPMailer.

SMTP settings are configured directly inside the PHP file.

For production deployment, move credentials into environment variables instead of hardcoding them.

---

## AI Features

The AI assistant can:

- Summarize bookings
- Detect scheduling conflicts
- Analyze venue usage
- Read venue comments
- Send custom emails
- Update booking status
- Maintain conversation history

---

## Security

Current protections include:

- Session authentication
- Role-based authorization
- Prepared SQL statements
- UTF-8 support
- Audit logging

Recommended improvements:

- Store API keys in environment variables
- Store SMTP credentials securely
- Enable CSRF protection
- Rate-limit AJAX endpoints
- Validate all user inputs
- Enable HTTPS in production

---

## Installation

1. Clone the project.

2. Import the MySQL database.

3. Install dependencies:

```bash
composer install

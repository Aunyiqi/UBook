# MMU VENUE BOOKING SYSTEM FOR MELAKA CAMPUS

<p align="center">

![PHP](https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?style=for-the-badge&logo=javascript)
![PHPMailer](https://img.shields.io/badge/PHPMailer-Email_Service-blue?style=for-the-badge)
![DeepSeek AI](https://img.shields.io/badge/AI-DeepSeek-orange?style=for-the-badge)

</p>

---

## Project Overview

**UBook Booking Conflict Manager** is a comprehensive web-based administration module developed for the **UBook Campus Venue Booking System**.

The system provides an efficient platform for booking administrators and super administrators to manage campus venue reservations, identify scheduling conflicts, communicate with users through email notifications, and utilize artificial intelligence assistance for improved decision-making.

By integrating automated conflict detection, calendar visualization, email communication, and AI-powered analysis, the system improves the efficiency, reliability, and accuracy of venue booking management.

---

# Features

## Booking Management

The system provides complete booking administration capabilities:

- View all booking records
- Create new bookings
- Update existing bookings
- Delete bookings
- Approve or reject booking requests
- Search booking records
- Filter bookings based on status
- Pagination support for large datasets
- Export booking records into CSV format

---

## Conflict Detection System

The system automatically analyzes booking conditions and identifies potential conflicts.

Supported conflict detection includes:

| Conflict Type | Description |
|---------------|-------------|
| Time Overlap | Detects overlapping bookings within the same venue |
| Public Holiday | Prevents bookings during registered public holidays |
| Past Date | Rejects booking requests with previous dates |
| Operating Hours | Ensures bookings follow venue operating hours |
| Duration Limit | Prevents bookings exceeding the maximum allowed duration |
| User Quota | Controls the maximum number of daily bookings per user |

Detected conflicts are displayed clearly throughout the booking management interface.

---

## Calendar Management

The system integrates **FullCalendar** to provide an interactive scheduling interface.

Administrators can view:

- Pending bookings
- Confirmed bookings
- Rejected bookings
- Conflict bookings
- Detailed booking information

The calendar view provides better visibility of venue availability and scheduling issues.

---

## Automated Booking Rules

The system applies automated validation rules to maintain booking accuracy.

### Automatic Rejection

Pending bookings may be automatically rejected when:

- The booking time has expired
- Scheduling conflicts cannot be resolved
- Booking conditions violate system rules

---

## Email Notification System

The system uses **PHPMailer with Gmail SMTP** to provide automated communication.

Email notifications are triggered when:

- A booking is confirmed
- A booking is rejected

Additional email features include:

- Custom email communication
- HTML email formatting
- Email tracking through BCC logging

---

## Artificial Intelligence Assistant

The system integrates the **DeepSeek Chat API** to provide AI-powered administrative support.

The AI assistant is capable of:

- Answering booking-related queries
- Identifying booking conflicts
- Analysing venue utilization
- Reviewing venue comments
- Sending customized emails
- Updating booking status
- Maintaining multi-turn conversation history

---

## Audit Logging

The system records important administrative activities to improve transparency and accountability.

Logged activities include:

- Booking creation
- Booking modification
- Booking deletion
- Booking status changes
- Automatic rejection actions

---

## Statistics Dashboard

The administration dashboard provides real-time booking statistics:

| Statistic | Description |
|-----------|-------------|
| Total Bookings | Displays all booking records |
| Pending Bookings | Shows waiting approval requests |
| Confirmed Bookings | Displays approved reservations |
| Rejected Bookings | Displays declined reservations |

---

# Technologies Used

## Backend Technologies

| Technology | Purpose |
|-----------|---------|
| PHP | Server-side application development |
| MySQL | Database management |
| PHPMailer | Email communication |
| cURL | API communication |

---

## Frontend Technologies

| Technology | Purpose |
|-----------|---------|
| HTML5 | Web page structure |
| CSS3 | Interface styling |
| JavaScript | Client-side interaction |
| Font Awesome | User interface resources |
| FullCalendar | Calendar visualization |
| Marked.js | Markdown rendering |
| DOMPurify | HTML sanitization |

---

## Artificial Intelligence

| Technology | Purpose |
|-----------|---------|
| DeepSeek Chat API | AI-powered booking analysis and assistance |

---

# Database Structure

The system requires the following database tables:

| Table | Description |
|-------|-------------|
| users | Stores user information |
| venues | Stores venue information |
| bookings | Stores booking records |
| audit_log | Stores administrator activities |
| venue_comments | Stores venue feedback |

---

# Booking Configuration

| Rule | Value |
|------|-------|
| Opening Hour | 8:00 AM |
| Closing Hour | 10:00 PM |
| Maximum Booking Duration | 4 Hours |
| Maximum Daily Booking Limit | 3 Bookings Per User |

---

# User Authorization

The booking administration module is restricted to authorized users only.

Supported roles:

| Role | Permission |
|------|------------|
| booking_admin | Booking management access |
| super_admin | Full administrative access |

Unauthorized users will be redirected to the login page.

---

# CSV Export Functionality

Administrators can export booking records containing:

- Booking ID
- Student Name
- Email Address
- Venue Name
- Booking Date
- Booking Time
- Duration
- Comment
- Booking Status

---

# Public Holiday Support

The system includes Malaysian public holiday validation from:

| Year |
|------|
| 2026 |
| 2027 |
| 2028 |
| 2029 |
| 2030 |

Bookings created on public holidays will automatically be identified as conflicts.

---

# Email Configuration

The system currently uses Gmail SMTP through PHPMailer.

SMTP configuration includes:

- SMTP server settings
- Authentication credentials
- Email sender information
- Secure email transmission

For production deployment, sensitive information such as SMTP credentials should be stored using environment variables.

---

# Security Implementation

Current security mechanisms include:

- Session authentication
- Role-based authorization
- Prepared SQL statements
- UTF-8 character support
- Administrative audit logging

Recommended production improvements:

- Store API keys securely using environment variables
- Protect SMTP credentials
- Implement CSRF protection
- Apply AJAX request rate limiting
- Strengthen input validation
- Enable HTTPS encryption

---

# Installation Guide

## Step 1: Clone the Repository

```bash
git clone <repository-url>
```

## Step 2: Import Database

Import the provided MySQL database file into your database server.

## Step 3: Install Dependencies

```bash
composer install
```

## Step 4: Configure System Settings

Update:

- Database configuration
- SMTP settings
- DeepSeek API configuration

## Step 5: Run Application

Deploy the project using:

- XAMPP
- Apache Server
- PHP Development Server

---

# Project Information

| Information | Details |
|------------|---------|
| Project Name | MMU Venue Booking System in Melaka Campus |
| Developer | Aun Yi Qi |
| Student ID | 1221103276 |
| System Type | Web-based Booking Administration System |

---

# Future Enhancements

Future improvements may include:

- Real-time notification system
- Mobile application support
- Google Calendar integration
- Advanced analytics dashboard
- Multi-language support
- QR code attendance verification
- REST API development
- Enhanced permission management

---

# License

This project was developed as part of an academic project for educational purposes.

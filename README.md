# UBook – AI-Enhanced Campus Venue Booking System

## Overview

UBook is an AI-enhanced web-based campus venue booking system developed as a Final Year Project for Multimedia University (MMU) Melaka Campus.

The system is designed to replace traditional email-based venue reservation processes with a centralized intelligent platform that improves booking efficiency, reduces administrative workload, prevents scheduling conflicts, and enhances communication between students and administrators.

UBook integrates Artificial Intelligence, automated booking validation, community collaboration, and notification services into a single campus venue management solution.

---

# Project Objectives

The objectives of UBook are:

1. To develop an AI assistant capable of understanding natural language venue booking requests.

2. To provide an intelligent venue reservation platform with automated conflict detection and validation.

3. To reduce manual administrative workload through automated booking workflows.

4. To improve booking accuracy, transparency, and communication efficiency.

5. To support student collaboration through community-based features.

6. To provide administrators with monitoring, analytics, and audit capabilities.

---

# Technology Stack

## Backend Technologies

| Technology | Purpose |
|---|---|
| PHP 8+ | Server-side application development |
| MySQL | Database management |
| PHPMailer | Email notification service |
| cURL | API communication |

## Frontend Technologies

| Technology | Purpose |
|---|---|
| HTML5 | Web interface structure |
| CSS3 | Interface styling |
| JavaScript | Client-side interaction |
| AJAX | Asynchronous communication |
| FullCalendar | Calendar visualization |
| Font Awesome | User interface icons |
| Marked.js | Markdown rendering |
| DOMPurify | Frontend content sanitization |

## Artificial Intelligence

| Technology | Purpose |
|---|---|
| DeepSeek Chat API | AI conversational assistant and natural language processing |

## Development Environment

| Technology | Purpose |
|---|---|
| XAMPP | Local development server |
| phpMyAdmin | Database administration |
| Composer | PHP dependency management |

---

# System Architecture


              User
                |
                |
          Web Browser
                |
                |
        PHP Application Layer
                |
    --------------------------------
    |              |               |
    |              |               |

Booking Module AI Assistant Community Module
| | |
--------------------------------
|
|
Conflict Detection Engine
|
|
MySQL Database
|
---------------------
| |
DeepSeek Chat API Gmail SMTP Server



---

# Core System Modules

## 1. AI Booking Assistant Module

UBook integrates the DeepSeek Chat API to provide conversational booking assistance.

The AI assistant supports:

- Natural language booking requests
- Booking information extraction
- Venue recommendation
- Availability checking
- Conflict explanation
- Booking guidance
- Administrative assistance
- Email drafting support


### Example Booking Process

User Request:


Book the Main Hall tomorrow from 2 PM to 5 PM for a club event.



AI Extraction:


Venue:
Main Hall

Date:
Tomorrow

Time:
2:00 PM - 5:00 PM

Purpose:
Club Event



After extracting the required information, the system performs server-side validation before creating the booking request.

---

# 2. Venue Booking Management Module

The booking management module provides complete reservation management functionality.

Features:

- Create bookings
- Edit bookings
- Delete bookings
- Booking approval workflow
- Booking rejection workflow
- Booking search
- Booking filtering
- CSV export
- Pagination
- Calendar management


---

# 3. Intelligent Conflict Detection Module

The system automatically validates every booking request before submission.

## Validation Rules

| Validation | Description |
|---|---|
| Time Overlap Detection | Prevents multiple bookings for the same venue and time |
| Public Holiday Checking | Blocks bookings on unavailable dates |
| Operating Hours Validation | Ensures bookings are within allowed hours |
| Past Date Validation | Prevents booking previous dates |
| Duration Restriction | Limits maximum booking duration |
| Daily Booking Quota | Controls excessive booking requests |


## Booking Constraints

| Rule | Value |
|---|---|
| Opening Hours | 8:00 AM |
| Closing Hours | 10:00 PM |
| Maximum Booking Duration | 4 Hours |
| Daily Booking Limit | 3 Bookings |

---

# 4. Community Collaboration Module

The community module enables students to collaborate and communicate regarding campus activities.

Features:

- Community creation
- Community joining
- Group discussions
- Event collaboration
- Activity management
- AI-assisted discussions


---

# 5. Email Notification Module

The email notification system is implemented using PHPMailer with Gmail SMTP.

The system automatically sends:

- Booking confirmation emails
- Booking rejection notifications
- Password reset emails
- Administrator notifications


---

# Calendar Management

The FullCalendar integration provides an interactive booking schedule.

Features:

- Pending booking display
- Approved booking display
- Rejected booking display
- Conflict visualization
- Booking details
- Schedule overview


---

# Administrator Dashboard

The administrator dashboard provides system monitoring and management capabilities.

## Dashboard Information

Displays:

- Total bookings
- Pending bookings
- Approved bookings
- Rejected bookings
- Recent activities


## Audit Logging

The system records:

- Booking creation
- Booking updates
- Booking deletion
- Booking approval
- Booking rejection
- Automatic system actions


---

# Database Structure

| Table | Description |
|---|---|
| users | User account information and authentication data |
| venues | Venue information and availability details |
| bookings | Venue reservation records |
| venue_comments | Venue reviews and comments |
| comment_likes | User interactions with comments |
| chat_private_messages | Private user conversations |
| chat_groups | Community group information |
| chat_group_messages | Group conversation records |
| chat_group_participants | Group membership records |
| audit_log | System activity tracking |


---

# User Roles

| Role | Permission |
|---|---|
| Student | Search venues, create bookings, use AI assistant, participate in communities |
| Booking Administrator | Manage booking requests and approval processes |
| Super Administrator | Perform full system administration |


---

# Security Implementation

## Authentication Security

Implemented:

- Session-based authentication
- Role-based access control


## Database Security

Implemented:

- Prepared SQL statements
- Input validation


## Frontend Security

Implemented:

- HTML sanitization
- DOMPurify protection


## Monitoring Security

Implemented:

- Audit logging


## Recommended Security Improvements

Future improvements:

- HTTPS deployment
- CSRF protection
- Environment variable configuration
- API rate limiting


---

# Testing

## Unit Testing

Tested components:

- Time conversion functions
- Conflict detection algorithms
- Booking validation logic


Testing Result:


13 Tests Passed
0 Failures



## User Acceptance Testing

The evaluation focused on:

- System usability
- AI assistant effectiveness
- Booking experience
- Overall user satisfaction


---

# Folder Structure


UBook/

├── admin/
├── booking/
├── chatbot/
├── community/
├── assets/
├── uploads/
├── config/
├── database/
├── vendor/
└── README.md



---

# Installation Guide

## 1. Clone Repository

```bash
git clone https://github.com/yourusername/UBook.git
2. Install Dependencies
composer install
3. Database Setup

Import the database file:

database/ubook.sql
4. System Configuration

Configure:

Database credentials
SMTP settings
DeepSeek API key
5. Run Application

Supported environments:

XAMPP
WAMP
Apache Server
PHP Development Server
Screenshots

Recommended screenshots:

Homepage
AI Booking Assistant Interface
Venue Search Page
Booking Form
Calendar Management
Community Platform
Conflict Detection Result
Administrator Dashboard
Future Enhancements

Planned improvements:

Mobile application development
Google Calendar integration
Push notification support
QR attendance verification
REST API development
Advanced booking analytics
AI-based booking prediction
University authentication integration
Project Information
Item	Details
Project Name	UBook – AI-Enhanced Campus Venue Booking System
Developer	Aun Yi Qi
Student ID	1221103276
Institution	Multimedia University (MMU), Malaysia
Project Type	Final Year Project

UBook – AI-Enhanced Campus Venue Booking System
<p align="center">
![PHP](https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?style=for-the-badge&logo=javascript)
![PHPMailer](https://img.shields.io/badge/PHPMailer-Email_Service-blue?style=for-the-badge)
![DeepSeek AI](https://img.shields.io/badge/AI-DeepSeek-orange?style=for-the-badge)
</p>
Overview
UBook is an AI-enhanced web-based venue booking system developed as a Final Year Project for Multimedia University (MMU) Melaka Campus. The system modernizes the traditional email-based venue reservation process by providing an integrated platform for venue discovery, AI-assisted booking, automated conflict detection, community collaboration, real-time administration, and email notifications.
The platform is built using PHP, MySQL, HTML, CSS, JavaScript, AJAX, PHPMailer, and the DeepSeek Chat API.
---
Table of Contents
Project Objectives
System Modules
Features
Technologies
System Architecture
Database Structure
User Roles
Booking Rules
Security
Installation
Configuration
Folder Structure
Future Enhancements
License
---
Project Objectives
Develop an AI-powered chatbot capable of understanding natural language booking requests.
Provide a smart multi-venue booking platform with automated conflict detection.
Support community collaboration and activity management.
Reduce manual administrative workload.
Improve booking accuracy and communication.
---
System Modules
1. AI Booking Assistant
Natural language booking
Venue search
Availability checking
Booking assistance
Multi-turn conversations
Booking recommendations
2. Venue Booking Management
Venue reservation
Booking approval
Calendar management
Conflict detection
Email notifications
Audit logging
3. Community Platform
Community creation
Activity management
AI-assisted discussions
Event collaboration
---
Key Features
Booking Management
Create bookings
Edit bookings
Delete bookings
Approve or reject requests
Search bookings
Filter bookings
CSV export
Pagination
Intelligent Conflict Detection
Validation	Description
Time Overlap	Prevents double booking
Public Holidays	Rejects bookings on configured holidays
Operating Hours	Validates booking time
Past Date	Prevents outdated bookings
Duration Limit	Enforces maximum duration
Daily Booking Quota	Limits bookings per user
Calendar
Integrated FullCalendar provides:
Pending bookings
Approved bookings
Rejected bookings
Conflict visualization
Booking details
AI Assistant
DeepSeek Chat API supports:
Natural language interaction
Booking guidance
Conflict explanation
Venue recommendations
Booking updates
Email drafting
Community assistance
Email Notification
PHPMailer with Gmail SMTP automatically sends:
Booking confirmation
Booking rejection
Custom administrator emails
Dashboard
Displays:
Total bookings
Pending bookings
Approved bookings
Rejected bookings
Recent booking activity
Audit Log
Tracks:
Booking creation
Booking updates
Booking deletion
Approval actions
Automatic rejection
---
Technologies
Backend
Technology	Purpose
PHP	Server-side development
MySQL	Database
PHPMailer	Email
cURL	API communication
Frontend
Technology	Purpose
HTML5	Structure
CSS3	Styling
JavaScript	Interaction
AJAX	Asynchronous requests
FullCalendar	Calendar
Font Awesome	Icons
DOMPurify	Sanitization
Marked.js	Markdown rendering
AI
DeepSeek Chat API
---
System Architecture
Client Browser
↓
PHP Application
↓
Business Logic
Booking Management
Conflict Detection
AI Assistant
Community Module
Email Service
↓
MySQL Database
↓
External Services
DeepSeek Chat API
Gmail SMTP
---
Database Tables
users
venues
bookings
venue_comments
communities
community_members
audit_log
---
User Roles
Role	Permission
Student	Book venues, join communities, AI assistant
Booking Administrator	Manage bookings
Super Administrator	Full system administration
---
Booking Rules
Rule	Value
Opening Hours	8:00 AM
Closing Hours	10:00 PM
Maximum Duration	4 Hours

Daily Limit	3 Bookings
---
Security Features
Session authentication
Role-based access control
Prepared SQL statements
Input validation
HTML sanitization
Audit logging
Recommended improvements:
HTTPS
Environment variables
CSRF protection
Rate limiting
---
Installation
Clone Repository
```bash
git clone https://github.com/yourusername/UBook.git
```
Install Dependencies
```bash
composer install
```
Import Database
Import the provided SQL file into MySQL.
Configure
Update:
Database credentials
SMTP credentials
DeepSeek API Key
Run
Deploy using:
XAMPP
WAMP
Apache
PHP Development Server
---
Folder Structure
```text
UBook/
├── admin/
├── community/
├── booking/
├── chatbot/
├── assets/
├── uploads/
├── vendor/
├── config/
├── database/
└── README.md
```
---
Screenshots
Add screenshots for:
Home Page
AI Chatbot
Venue Search
Booking Form
Calendar
Community
Admin Dashboard
Conflict Detection
---
Future Enhancements
Mobile application
Google Calendar integration
Push notifications
QR attendance
REST API
Advanced analytics
AI recommendations
University authentication integration
---
Project Information
Item	Details
Project	UBook – AI-Enhanced Campus Venue Booking System
Developer	Aun Yi Qi
Student ID	1221103276
Institution	Multimedia University (MMU), Malaysia
Type	Final Year Project
---
License
This project was developed for academic and educational purposes.

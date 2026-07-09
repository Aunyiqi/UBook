# 🚀 UBook – AI-Enhanced Campus Venue Booking System

<p align="center">

![PHP](https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?style=for-the-badge&logo=javascript)
![DeepSeek AI](https://img.shields.io/badge/AI-DeepSeek-orange?style=for-the-badge)
![PHPMailer](https://img.shields.io/badge/Email-PHPMailer-blue?style=for-the-badge)
![XAMPP](https://img.shields.io/badge/Server-XAMPP-orange?style=for-the-badge)

</p>

---

# 📌 Overview

UBook is an **AI-enhanced web-based campus venue booking system** developed as a Final Year Project for **Multimedia University (MMU) Melaka Campus**.

The system aims to replace traditional email-based venue reservation processes with an intelligent centralized platform that improves booking efficiency, reduces administrative workload, prevents scheduling conflicts, and enhances communication between students and administrators.

UBook integrates Artificial Intelligence, automated validation, community collaboration, and notification services into a single campus venue management platform.

## Key Capabilities

- 🤖 AI-powered natural language booking assistant
- 🏢 Venue search and reservation management
- ⚠️ Automated booking conflict detection
- 📅 Interactive booking calendar
- 👥 Community collaboration platform
- 📧 Automated email notification system
- 📊 Administrative dashboard and audit tracking


## Technology Stack

- PHP 8+
- MySQL
- HTML5
- CSS3
- JavaScript
- AJAX
- DeepSeek Chat API
- PHPMailer
- FullCalendar


---

# 🎯 Project Objectives

The objectives of UBook are:

1. Develop an AI assistant capable of understanding natural language venue booking requests.

2. Provide an intelligent venue reservation platform with automated conflict detection.

3. Reduce manual administrative workload through automated workflows.

4. Improve booking accuracy and communication efficiency.

5. Support student collaboration through community features.

6. Provide administrators with monitoring, analytics, and audit capabilities.


---

# 🏗️ System Architecture


                User
                  |
                  ↓
           Web Browser
                  |
                  ↓
          PHP Application

| | |
Booking AI Assistant Community
Module Module Module

                  |
                  ↓

      Conflict Detection Engine

                  |
                  ↓

          MySQL Database

                  |
    -----------------------------
    |                           |

DeepSeek Chat API Gmail SMTP



---

# 🤖 AI Booking Assistant


UBook integrates the **DeepSeek Chat API** to provide intelligent conversational booking assistance.

The AI assistant supports:

- Natural language understanding
- Booking information extraction
- Venue recommendation
- Availability checking
- Conflict explanation
- Booking guidance
- Administrative assistance
- Email drafting


## Example AI Booking Request



User:

Book the Main Hall tomorrow from 2 PM to 5 PM for a club event.

AI Processing:

Venue:
Main Hall

Date:
Tomorrow

Time:
2:00 PM - 5:00 PM

Purpose:
Club Event



After extracting booking information, the system performs server-side validation before creating the reservation.


---

# 📦 System Modules


# 1. AI Booking Module

Features:

- Natural language booking
- Multi-turn conversation
- AI-assisted reservation
- Smart venue recommendation
- Booking explanation


---

# 2. Venue Booking Management


Features:

- Create bookings
- Edit bookings
- Delete bookings
- Booking approval
- Booking rejection
- Search bookings
- Filter bookings
- CSV export
- Pagination
- Calendar management


---

# 3. Intelligent Conflict Detection


The system automatically validates booking requests before submission.


| Validation | Description |
|---|---|
| Time Overlap Detection | Prevents duplicate reservations |
| Public Holiday Checking | Blocks unavailable dates |
| Operating Hours Validation | Ensures valid booking time |
| Past Date Validation | Prevents outdated requests |
| Duration Restriction | Limits booking duration |
| Daily Booking Quota | Controls excessive booking requests |


## Booking Rules


| Rule | Value |
|---|---|
| Opening Hours | 8:00 AM |
| Closing Hours | 10:00 PM |
| Maximum Duration | 4 Hours |
| Daily Booking Limit | 3 Bookings |


---

# 4. Community Collaboration Platform


The community module enables students to organize campus activities and communicate with other users.


Features:

- Community creation
- Community joining
- Group discussions
- Event collaboration
- Activity management
- AI-assisted discussions


---

# 5. Email Notification System


Implemented using **PHPMailer with Gmail SMTP**.


Automatically sends:

- Booking confirmation emails
- Booking rejection notifications
- Password reset emails
- Administrator notifications


---

# 📅 Calendar Management


The integrated FullCalendar provides:

- Pending booking display
- Approved booking display
- Rejected booking display
- Conflict visualization
- Booking details
- Schedule overview


---

# 📊 Administrator Dashboard


The dashboard provides system monitoring and analytics.


## Dashboard Information

Displays:

- Total bookings
- Pending bookings
- Approved bookings
- Rejected bookings
- Recent activities


## Audit Log


Tracks:


Booking Created

Booking Updated

Booking Deleted

Booking Approved

Booking Rejected

Automatic System Actions



---

# 🗄️ Database Structure


| Table | Description |
|---|---|
| users | User accounts and authentication |
| venues | Venue information |
| bookings | Booking records |
| venue_comments | Venue reviews |
| comment_likes | Comment interactions |
| chat_private_messages | Private conversations |
| chat_groups | Group conversations |
| chat_group_messages | Group messages |
| chat_group_participants | Group membership |
| audit_log | System activity tracking |


---

# 👤 User Roles


| Role | Permission |
|---|---|
| Student | Search venues, create bookings, use AI assistant, join communities |
| Booking Administrator | Manage bookings and approval workflow |
| Super Administrator | Full system administration |


---

# 🔐 Security Features


Implemented security mechanisms:


## Authentication

- Session-based authentication
- Role-based access control


## Database Security

- Prepared SQL statements
- Input validation


## Frontend Security

- HTML sanitization
- DOMPurify protection


## Monitoring

- Audit logging


## Recommended Improvements

- HTTPS deployment
- CSRF protection
- Environment variable configuration
- API rate limiting


---

# 🧪 Testing


## Unit Testing


Tested components:

- Time conversion functions
- Conflict detection logic
- Booking validation


Result:


13 Tests Passed
0 Failures



## User Acceptance Testing


Evaluation focused on:

- System usability
- AI assistant effectiveness
- Booking experience
- Overall satisfaction


---

# 🛠️ Technologies


## Backend


| Technology | Purpose |
|---|---|
| PHP 8+ | Server-side development |
| MySQL | Database management |
| PHPMailer | Email communication |
| cURL | API communication |


## Frontend


| Technology | Purpose |
|---|---|
| HTML5 | Interface structure |
| CSS3 | Styling |
| JavaScript | Client interaction |
| AJAX | Asynchronous communication |
| FullCalendar | Calendar visualization |
| Font Awesome | Icons |
| Marked.js | Markdown rendering |
| DOMPurify | Security sanitization |


## Artificial Intelligence


| Technology | Purpose |
|---|---|
| DeepSeek Chat API | AI conversational assistant |


---

# 📂 Folder Structure



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

# ⚙️ Installation


## Clone Repository


```bash
git clone https://github.com/yourusername/UBook.git
Install Dependencies
composer install
Database Setup

Import:

database/ubook.sql
Configure System

Update:

Database credentials

SMTP configuration

DeepSeek API Key

Run Application

Supported environments:

XAMPP
WAMP
Apache
PHP Development Server
📸 Screenshots

Recommended screenshots:

Homepage
AI Booking Assistant
Venue Search
Booking Form
Calendar View
Community Platform
Conflict Detection
Administrator Dashboard
🚀 Future Enhancements

Planned improvements:

Mobile application
Google Calendar integration
Push notifications
QR attendance verification
REST API development
Advanced analytics
AI booking prediction
University authentication integration
👨‍💻 Project Information
Item	Details
Project	UBook – AI-Enhanced Campus Venue Booking System
Developer	Aun Yi Qi
Student ID	1221103276
Institution	Multimedia University (MMU), Malaysia
Project Type	Final Year Project

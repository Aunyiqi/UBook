# 🎓 UBook – AI-Enhanced Campus Venue Booking System

<p align="center">

![PHP](https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?style=for-the-badge&logo=javascript)
![DeepSeek AI](https://img.shields.io/badge/AI-DeepSeek_API-412991?style=for-the-badge)
![XAMPP](https://img.shields.io/badge/Server-XAMPP-FB7A24?style=for-the-badge)

</p>

> An intelligent centralized campus venue management platform that combines Artificial Intelligence, automated scheduling validation, and collaborative communication features to improve university facility management.

---

# 📑 Table of Contents

- [Overview](#-overview)
- [Project Objectives](#-project-objectives)
- [System Architecture](#️-system-architecture)
- [Core Features](#-core-features)
- [Technology Stack](#️-technology-stack)
- [Database Structure](#️-database-structure)
- [User Roles](#-user-roles)
- [AI Integration Workflow](#-ai-integration-workflow)
- [Security Implementation](#-security-implementation)
- [Testing](#-testing)
- [Project Structure](#-project-structure)
- [Installation Guide](#️-installation-guide)
- [Screenshots](#-screenshots)
- [Future Enhancements](#-future-enhancements)
- [Project Information](#-project-information)

---

# 📖 Overview

**UBook** is an AI-enhanced web-based campus venue booking system developed as a Final Year Project for **Multimedia University (MMU) Melaka Campus**.

Traditional campus venue reservation methods usually rely on emails, manual approval processes, and spreadsheets. These approaches may cause:

- Slow booking responses
- Scheduling conflicts
- Difficult reservation tracking
- Increased administrative workload

UBook provides a centralized intelligent platform that improves campus facility management through:

- AI-powered booking assistance
- Automated conflict detection
- Real-time reservation validation
- Email notification services
- Student collaboration features
- Administrative analytics

Users can communicate with the AI assistant naturally:


@AI Book FCM Seminar Room tomorrow from 2 PM to 4 PM


The AI extracts booking information, validates availability, detects conflicts, and assists users throughout the reservation process.

---

# 🎯 Project Objectives

The objectives of UBook are:

### 1. AI Natural Language Booking Assistant

Develop an AI assistant capable of understanding human booking requests and converting them into structured reservation information.

---

### 2. Automated Conflict Detection

Prevent double booking by validating:

- Date and time conflicts
- Venue availability
- Venue capacity
- Maintenance schedules
- Booking restrictions

---

### 3. Reduce Administrative Workload

Automate:

- Booking approval processes
- Reservation validation
- Notification delivery
- Booking monitoring

---

### 4. Improve Communication

Provide a centralized communication platform through:

- AI chatbot
- Private messaging
- Group discussion

---

### 5. Provide Analytics and Monitoring

Enable administrators to monitor:

- Booking statistics
- Venue utilization
- User activities
- System reports

---

# 🏗️ System Architecture

                     User
                       |
                Web Browser
                       |
             PHP Application Layer
                       |
  ┌────────────────────┼────────────────────┐
  |                    |                    |

Booking Module AI Assistant Community Module
| | |
| DeepSeek API |
| | |
└────────────────────┼────────────────────┘
|
Booking Conflict Detection Engine
|
MySQL Database
|
┌────────────────┴────────────────┐
| |
DeepSeek Chat API Gmail SMTP Server
(AI Processing) (Notifications)


---

# 🚀 Core Features

## 🤖 AI Booking Assistant

The AI assistant provides a conversational booking experience.

### Functions:

✅ Natural language understanding  
✅ Booking information extraction  
✅ Venue availability checking  
✅ Booking preview generation  
✅ Confirmation workflow  

Example:


User:
@AI Need a meeting room next Monday at 3 PM for 2 hours

AI Response:

Venue:
Meeting Room

Date:
Monday

Time:
3:00 PM - 5:00 PM

Availability:
Available

Confirm booking?


---

# 📅 Venue Booking Module

Users can:

- Search available venues
- Create booking requests
- View booking history
- Cancel reservations
- Track booking status


### Booking Rules

- Maximum booking duration: 4 hours
- Cancellation allowed before 24 hours
- Venue operating hour validation
- Public holiday restriction checking

---

# ⚠️ Conflict Detection Engine

The system automatically checks booking conflicts.

Example:


Existing Booking:

10:00 AM - 12:00 PM

New Booking Request:

11:00 AM - 1:00 PM

Result:

❌ Conflict Detected


Validation includes:

- Time overlap detection
- Venue availability
- Capacity checking
- Maintenance status
- Booking quota

---

# 💬 Community Collaboration Module

Allows students to communicate and collaborate.

Features:

- Group creation
- Group chat
- Private messaging
- Venue discussion
- Comments
- Likes
- Community interaction

---

# 📊 Administrator Dashboard

Administrators can:

- Manage bookings
- Approve/reject reservations
- Manage venues
- Monitor activities
- View analytics
- Perform AI-assisted queries

---

# 🛠️ Technology Stack

## Frontend

| Technology | Usage |
|---|---|
| HTML5 | Interface structure |
| CSS3 | User interface design |
| JavaScript | Client-side interaction |
| AJAX | Asynchronous requests |
| Chart.js | Analytics visualization |

---

## Backend

| Technology | Usage |
|---|---|
| PHP 8+ | Server-side processing |
| MySQL | Database management |
| PHPMailer | Email notification |

---

## Artificial Intelligence

| Technology | Usage |
|---|---|
| DeepSeek API | Natural Language Processing |
| AI Chat Assistant | Booking support |

---

## Development Tools

| Tool | Usage |
|---|---|
| XAMPP | Local server |
| phpMyAdmin | Database management |
| Visual Studio Code | Development |
| Git | Version control |

---

# 🗄️ Database Structure

Database Name:


ubook


## Tables

| Table | Description |
|---|---|
| users | User accounts |
| venues | Venue information |
| bookings | Booking records |
| venue_comments | Venue reviews |
| comment_likes | Comment reactions |
| chat_private_messages | Private messages |
| chat_groups | Group information |
| chat_group_messages | Group messages |
| chat_group_participants | Group members |

---

# 👥 User Roles

## Student

Functions:

- Register/login
- Search venues
- Request bookings
- Use AI assistant
- Join communities
- Manage reservations

---

## Booking Administrator

Functions:

- Review bookings
- Approve/reject requests
- Manage venues
- Monitor conflicts
- View reports

---

## Super Administrator

Functions:

- Manage administrators
- Manage users
- Full system access
- System monitoring

---

# 🤖 AI Integration Workflow


User Input

  |
  v

AI Chat Assistant

  |
  v

DeepSeek API

  |
  v

Extract Booking Information

  |
  v

Validate Booking Rules

  |
  v

Check Database Conflicts

  |
  v

Booking Confirmation

  |
  v

Email Notification


---

# 🔐 Security Implementation

## Authentication Security

Implemented:

- Password hashing
- Session authentication
- Role-based access control

---

## Database Security

Implemented:

- Prepared SQL statements
- Input validation
- SQL injection prevention

---

## API Security

Implemented:

- API key protection
- Server-side validation
- Secure communication

---

# 🧪 Testing

## Unit Testing

Tested modules:

- Time conversion
- Booking validation
- Conflict detection


Result:


Total Test Cases: 13

Passed: 13

Failed: 0


---

## Manual Testing

Tested:

✅ Login system  
✅ Venue booking  
✅ AI assistant  
✅ Conflict detection  
✅ Email notification  
✅ Community module  

All major functions passed.

---

## User Acceptance Testing

A user survey was conducted to evaluate:

- System usability
- AI effectiveness
- Booking experience
- Overall satisfaction

---

# 📂 Project Structure


UBook/

│
├── admin/
│ ├── dashboard.php
│ ├── bookings.php
│ └── analytics.php
│
├── user/
│ ├── booking.php
│ ├── chat.php
│ └── profile.php
│
├── ai/
│ └── deepseek_api.php
│
├── email/
│ └── mail_handler.php
│
├── database/
│ └── ubook.sql
│
├── assets/
│ ├── css/
│ ├── js/
│ └── images/
│
└── index.php


---

# ⚙️ Installation Guide

## Requirements

Install:

- XAMPP
- PHP 8+
- MySQL
- Web Browser

---

## Step 1: Clone Repository

```bash
git clone https://github.com/yourusername/UBook.git
Step 2: Move Project

Copy into:

xampp/htdocs/
Step 3: Setup Database

Open:

http://localhost/phpmyadmin

Create database:

ubook

Import:

database/ubook.sql
Step 4: Configure Database

Update:

config/database.php

Example:

$db_host = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "ubook";
Step 5: Configure DeepSeek API

Add API key:

config/deepseek.php
Step 6: Run System

Open:

http://localhost/UBook
📸 Screenshots

(Add screenshots here)

Recommended screenshots:

Login Page
AI Booking Chat
Booking Dashboard
Conflict Detection Result
Community Chat
Analytics Dashboard
🔮 Future Enhancements

Possible improvements:

Mobile application
University Single Sign-On integration
AI-based venue recommendation
Calendar synchronization
QR attendance verification
IoT smart classroom integration
Predictive booking analytics
📌 Project Information
Item	Details
Project Name	UBook – AI-Enhanced Campus Venue Booking System
Project Type	Final Year Project
Institution	Multimedia University (MMU) Melaka Campus
Development Type	Web Application
Backend	PHP + MySQL
AI Technology	DeepSeek API
Email Service	PHPMailer + Gmail SMTP
👨‍💻 Developer

Aun Yi Qi

Final Year Project
Multimedia University (MMU) Melaka Campus


Save the entire content as:


README.md


This version is GitHub-ready and presents UBook as a professional software engineering 

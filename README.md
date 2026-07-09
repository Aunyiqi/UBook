# 🎓 UBook – AI-Enhanced Campus Venue Booking System

> An intelligent, centralized platform designed to modernize campus venue management by integrating AI assistance, automated conflict detection, and community collaboration.

---

## 📑 Table of Contents
- [Overview](#-overview)
- [Project Objectives](#-project-objectives)
- [System Architecture](#%EF%B8%8F-system-architecture)
- [Core System Modules](#-core-system-modules)
- [Technology Stack](#-technology-stack)
- [Database Structure](#%EF%B8%8F-database-structure)
- [User Roles](#-user-roles)
- [Security Implementation](#-security-implementation)
- [Testing](#-testing)
- [Folder Structure](#-folder-structure)
- [Installation Guide](#-installation-guide)
- [Screenshots](#-screenshots)
- [Future Enhancements](#-future-enhancements)
- [Project Information](#-project-information)

---

## 📖 Overview

**UBook** is an AI-enhanced web-based campus venue booking system developed as a Final Year Project for Multimedia University (MMU) Melaka Campus.

The system is designed to replace traditional email-based venue reservation processes with a centralized intelligent platform that improves booking efficiency, reduces administrative workload, prevents scheduling conflicts, and enhances communication between students and administrators. 

UBook seamlessly integrates Artificial Intelligence, automated booking validation, community collaboration, and notification services into a single campus venue management solution.

---

## 🎯 Project Objectives

1. To develop an AI assistant capable of understanding natural language venue booking requests.
2. To provide an intelligent venue reservation platform with automated conflict detection and validation.
3. To reduce manual administrative workload through automated booking workflows.
4. To improve booking accuracy, transparency, and communication efficiency.
5. To support student collaboration through community-based features.
6. To provide administrators with monitoring, analytics, and audit capabilities.

---

## 🏗️ System Architecture

```text
              User
                │
          Web Browser
                │
        PHP Application Layer
                │
    ┌───────────┴───────────┐
    │           │           │
 Booking    AI Assistant  Community
 Module         │         Module
    │           │           │
    └───────────┬───────────┘
                │
    Conflict Detection Engine
                │
         MySQL Database
                │
    ┌───────────┴───────────┐
    │                       │
DeepSeek Chat API     Gmail SMTP Server

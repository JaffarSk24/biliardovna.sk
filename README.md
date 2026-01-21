# 🎱 Biliardovna.sk - Club Management & Booking System

> **Portfolio Showcase**: This repository demonstrates a production-grade, full-stack web application built for a billiard club in Slovakia. It is presented here to showcase software architecture, backend logic, and frontend development skills.

![Project Status](https://img.shields.io/badge/Status-Production-brightgreen)
![PHP Version](https://img.shields.io/badge/PHP-8.2-blue)
![Database](https://img.shields.io/badge/Database-MySQL-orange)

## 📖 Project Overview

This project is a custom-built management solution designed to automate operations for **Biliardovna.sk**. Unlike generic booking plugins, this system addresses specific business needs: complex time-based pricing, real-time slot availability, multi-language support, and direct integration with Telegram for instant staff notifications.

The goal was to create a lightweight, high-performance system without the overhead of heavy frameworks, demonstrating a deep understanding of core web technologies and MVC architecture.

## 🚀 Key Features

### 📅 Advanced Booking Engine

- **Smart Availability Checking**: Real-time verification of slot availability to prevent double bookings.
- **Dynamic Pricing Models**: Calculation of prices based on complex curves (e.g., higher rates during evenings or weekends, specific holiday exceptions).
- **Time Slot Management**: Granular control over opening hours, including defining temporary blocks or holidays.

### 🛠️ Powerful Admin Dashboard

- **Analytics & Reporting**: Visual dashboard tracking revenue, booking popularity, and status breakdowns (Completed/Cancelled/Pending).
- **Coupon & Promo System**: Robust marketing tools including coupon generation with usage limits, expiration dates, and type-based logic (Standard/Multi-use).
- **Content Management**: Interface for managing pricing rules, services, and operational settings.

### 🔔 Integrations & Communication

- **Telegram Bot Integration**: Instant alerts to the administration team whenever a new booking is created or cancelled.
- **Email Notifications**: Automated confirmations sent to customers.
- **Multi-language Support**: Fully localized interface supporting SK, EN, RU, DE, and UK locales.

## 💻 Tech Stack

This project avoids "magic" solutions in favor of clean, maintainable, and explicit code.

- **Backend**:
  - **PHP 8.2** (Strict Typing, OOP features)
  - Custom **MVC Architecture** (Router, Controllers, Services, Models)
  - No bloated framework – pure, efficient execution.
- **Frontend**:
  - **Twig** Template Engine for modular and secure view rendering.
  - **Vanilla JavaScript (ES6+)** for reactive forms and booking logic.
  - **CSS3** (Custom Properties, Flexbox/Grid) for a responsive, premium design.
- **Database**:
  - **MySQL** with optimized schemas, foreign key constraints, and efficient indexing.
- **DevOps**:
  - **Composer** for dependency management.
  - **Git** version control.

## 📂 Architecture Highlights

The codebase is organized to separate concerns clearly:

```
src/
├── Controllers/   # Handle HTTP requests (Admin, Booking, Auth)
├── Models/        # Data validation and transformation
├── Services/      # Business logic (Pricing calculation, Notification dispatch)
└── Database/      # Singleton connection wrapping PDO
```

## 👨‍💻 Developer Notes

This project solved a critical business problem: **eliminating manual booking management via phone**. By automating the process, the club significantly increased operational efficiency and reduced lost leads.

---

_Developed by Kirill as a custom solution for Biliardovna.sk._

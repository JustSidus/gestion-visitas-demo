# Enterprise Visitor Management System

A full-stack Single Page Application (SPA) engineered to digitize, secure, and streamline facility access workflows. 

> **Disclaimer:** This repository contains a sanitized version of a production system built for a government agency. All sensitive data, institutional branding, real endpoints, and deployment pipelines have been anonymized or removed for public showcase.

### Tech Stack & Infrastructure
![PHP](https://img.shields.io/badge/PHP_8-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue_3-35495E?style=flat-square&logo=vue.js&logoColor=4FC08D)
![Microsoft Entra ID](https://img.shields.io/badge/Microsoft_Entra_ID-0078D4?style=flat-square&logo=microsoft&logoColor=white)
![Azure](https://img.shields.io/badge/Microsoft_Azure-0089D6?style=flat-square&logo=microsoft-azure&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=flat-square&logo=mysql&logoColor=white)

---

## Business Impact
This system was designed to replace vulnerable, legacy paper-based visitor logs. By digitizing the access lifecycle, the application enables real-time alert validation, automated reporting, and strict auditability, transforming a manual bottleneck into a highly secure and efficient process.

## Architecture & Engineering Decisions

### 1. Decoupled SPA & REST API
*   **Backend (Laravel):** Exposes a secure RESTful API, enforcing business logic, data validation, and authorization boundaries.
*   **Frontend (Vue 3 / Vite):** Component-based architecture utilizing Composables for reusable state logic and a centralized API service layer with Axios interceptors.

### 2. Enterprise Authentication (SSO & JWT)
Implemented a robust identity flow bridging Microsoft 365 and custom JWT authorization:
*   Frontend leverages MSAL to authenticate via **Microsoft Entra ID (Azure AD)** and retrieves an OAuth `access_token`.
*   The Laravel API validates the Microsoft token, provisions/syncs the internal user, and issues a proprietary, scoped JWT.
*   Route Guards on the frontend and middleware on the backend enforce strict **Role-Based Access Control (RBAC)** across 4 distinct organizational profiles.

### 3. Cloud Deployment (Azure)
Architected and provisioned the original cloud infrastructure for the agency:
*   **Azure App Service:** Hosted the Laravel REST API.
*   **Azure Static Web Apps:** Delivered the Vue 3 SPA.
*   **Azure Database for MySQL:** Managed the production persistence layer.
*   *(Note: Original CI/CD pipelines have been detached in this sanitized version).*

---

## System Preview

### Login

<img src="./assets/login-demo.png" alt="Captura de pantalla del Login" width="100%" style="max-width: 800px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">

### Dashboard

<img src="./assets/dashboard-demo.png" alt="Captura de pantalla del Dashboard" width="100%" style="max-width: 800px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">

### Statistics

<img src="./assets/statistics-demo.png" alt="Captura de pantalla de Estadísticas" width="100%" style="max-width: 800px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">

---

## Local Development Setup

**Prerequisites:** PHP 8.1+, Composer, Node.js 18+, MySQL.
```bash
# 1. Install Full-Stack Dependencies
npm run install:all

# 2. Configure Backend & Keys
copy backend\.env.example backend\.env
cd backend
php artisan key:generate
php artisan jwt:secret
cd ..

# 3. Run Migrations & Seed Demo Data
npm run migrate:fresh

# 4. Spin up Local Dev Servers
npm run dev

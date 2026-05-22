# Quaid-e-Azam Group of Colleges - ERP System

A comprehensive College Enterprise Resource Planning (ERP) solution designed for modern academic management. Built with a focus on speed, reliability, and a premium user experience.

## 🚀 Key Modules & Features

### 1. 📚 Library Management
*   **Inventory Control**: Manage a full catalog of books with auto-tracking of copies.
*   **Circulation**: Streamlined Issue and Return process.
*   **Fine System**: Automated overdue fine calculation based on return dates.
*   **Search**: Quick filtering for books and issued records.

### 2. 👨‍💼 HR Management
*   **Staff Directory**: Centralized database of all faculty and administrative staff.
*   **Leave Management**: Digital application and approval workflow for staff leaves.
*   **Payroll System**: Generate and track monthly salary disbursements.
*   **Stats**: High-level visibility into total staff, active teachers, and pending leaves.

### 3. 📝 Examination Management
*   **Scheduling**: Create detailed exam schedules with date, time, venue, and subject details.
*   **Marks Entry**: Efficient batch-entry for student scores with automatic grade calculation (A+ to F).
*   **Results & Reports**: Generate professional, printable student report cards with ranking and aggregate scores.

### 4. 🚌 Transport Management
*   **Fleet Management**: Track vehicles (Buses, Vans, Coasters) and their maintenance status.
*   **Route Planning**: Define routes with multiple stops and fixed monthly fee structures.
*   **Student Assignments**: Assign students to specific routes with pickup point tracking.
*   **Driver Directory**: Manage driver profiles and license information.

### 5. 💬 Communication System
*   **WhatsApp Integration**: Generate one-click message links to parents and students.
*   **Bulk Messaging**: Send announcements to entire classes or the whole college.
*   **Template Library**: Pre-made professional templates for Fee Reminders, Absence Alerts, and Exam Notices.
*   **Auditing**: Comprehensive logs of all outgoing communications.

## 🛠️ Technology Stack
*   **Backend**: PHP 8.x
*   **Database**: MySQL / MariaDB (PDO for secure transactions)
*   **Frontend**: Bootstrap 5, Vanilla CSS3, JavaScript (ES6)
*   **Icons**: FontAwesome 6 Pro
*   **Theme**: Custom Navy (`#0f2d48`) and Teal (`#4ec2b5`) palette.

## ⚙️ Installation & Setup

### Prerequisites
*   XAMPP / WAMP / MAMP installed.
*   PHP 8.0 or higher.
*   MySQL 5.7 or higher.

### Steps
1.  **Clone the Repository**:
    ```bash
    git clone https://github.com/your-repo/quaid_college.git
    ```
2.  **Database Setup**:
    *   Create a database named `quaid_college_db`.
    *   Import the latest SQL dump (if available) or simply navigate to the modules to let the **Auto-Migrate** system create the tables.
3.  **Configuration**:
    *   Edit `config/db.php` to match your local database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS).
4.  **Run**:
    *   Move the folder to `htdocs` (XAMPP) or `www` (WAMP).
    *   Access via `http://localhost/quaid_college`.

## 🎨 Design Philosophy
The system follows a "Premium First" design approach:
*   **Responsive**: Fully accessible on Desktops, Tablets, and Mobile devices.
*   **Clean UI**: Minimalist Navy backgrounds with Teal highlights for a professional look.
*   **UX Focused**: Modal-driven workflows to keep users on the same page and reduce navigation friction.

---
© 2026 Quaid-e-Azam Group of Colleges. Developed for Excellence.

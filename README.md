# ⚽ Football Leaders Academy

A fully dynamic football academy website built with PHP & MySQL.

## 🚀 Setup (XAMPP)

1. Copy `football-academy` → `C:\xampp\htdocs\football-academy\`
2. Start **Apache** and **MySQL** in XAMPP
3. Open **phpMyAdmin** → http://localhost/phpmyadmin
4. Click **Import** → select `database/football_academy.sql` → **Go**
5. Visit: **http://localhost/football-academy/**

## 🔐 Login Credentials (Single Login at /login.php)

| Role | User ID | Password |
|------|---------|----------|
| **Admin** | fla-001 | pass123 |
| Player | FLA-002 | player123 |
| Player | FLA-003 | player123 |
| Player | FLA-004 | player123 |
| Player | FLA-005 | player123 |
| Player | FLA-006 | player123 |
| Player | FLA-007 | player123 |

## 🔑 Login Flow

1. User enters `user_id` + `password` on `/login.php`
2. System checks **admins** table first → if match → admin dashboard
3. Else checks **players** table → if match → player dashboard
4. Else → error message

## 📊 Data Flow (Forms → DB → Admin)

| Frontend Form | → Database Table | → Admin View |
|---------------|------------------|--------------|
| "Join The Club" modal | `join_requests` | Admin > Join Requests (approve/reject) |
| Contact form | `contact_messages` | Admin > Messages |
| Events | `events` | Admin > Events (add/edit/delete) |

## 📁 Project Structure

```
football-academy/
├── assets/images/         (hero, about, programs, students, coaches, gallery, community, players)
├── css/style.css
├── js/app.js
├── includes/
│   ├── db.php
│   ├── header.php
│   ├── footer.php
│   └── logout.php
├── admin/
│   ├── dashboard.php      (stats overview)
│   ├── players.php        (CRUD)
│   ├── player-form.php    (add/edit)
│   ├── stats.php          (goals/assists/matches)
│   ├── teams.php          (CRUD)
│   ├── team-form.php      (add/edit)
│   ├── events.php         (CRUD)
│   ├── join_requests.php  (approve/reject)
│   ├── contacts.php       (view messages)
│   └── sidebar.php
├── player/
│   └── dashboard.php      (name, image, team, stats)
├── database/
│   └── football_academy.sql (7 tables)
├── index.php              (landing page + join modal + contact form)
├── login.php              (unified login)
└── README.md
```

## 🗄️ Database Tables (7)

| Table | Purpose |
|-------|---------|
| admins | Admin authentication |
| players | Player profiles |
| teams | Team management |
| player_stats | Goals, assists, matches |
| events | Dynamic events section |
| join_requests | "Join The Club" applications |
| contact_messages | Contact form submissions |

## 🛡️ Security
- `password_hash()` / `password_verify()`
- PDO prepared statements
- Input sanitization with `htmlspecialchars()`

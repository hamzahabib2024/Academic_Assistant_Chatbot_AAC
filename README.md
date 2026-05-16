# AAC — AI Academic Assistant Chatbot
## Full Project Setup Guide for XAMPP

---

## 📁 Project Structure

```
aac/
├── index.html          ← Main frontend (your original)
├── style.css           ← Styles (your original)
├── app.js              ← JavaScript (updated to use PHP backend)
├── uploads/            ← File uploads go here (auto-created)
├── aac_database.sql    ← Import this into phpMyAdmin
└── api/
    ├── config.php          ← DB config & settings
    ├── auth_helper.php     ← Token & response helpers
    ├── auth.php            ← Register / Login / Me
    ├── chat.php            ← Send message / History
    ├── knowledge.php       ← Knowledge Base CRUD
    ├── admin.php           ← Admin dashboard APIs
    └── upload.php          ← File upload & listing
```

---

## 🚀 Step-by-Step Setup

### Step 1 — Install & Start XAMPP
1. Download XAMPP from https://www.apachefriends.org
2. Open XAMPP Control Panel
3. Click **Start** next to **Apache** and **MySQL**

---

### Step 2 — Copy Project to htdocs
1. Copy the entire `aac` folder to:
   - **Windows:** `C:\xampp\htdocs\aac\`
   - **Mac:** `/Applications/XAMPP/htdocs/aac/`
   - **Linux:** `/opt/lampp/htdocs/aac/`

---

### Step 3 — Import the Database
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **"New"** on the left sidebar
3. Name the database: `aac_db` → Click **Create**
4. Click the `aac_db` database → Click **Import** tab
5. Click **Choose File** → select `aac_database.sql`
6. Click **Go** at the bottom

✅ This creates all tables and seeds demo data automatically.

---

### Step 4 — Set Upload Folder Permissions
The `uploads/` folder needs to be writable.

**Windows:** Usually works by default.

**Mac/Linux — run in Terminal:**
```bash
chmod 755 /Applications/XAMPP/htdocs/aac/uploads
```

---

### Step 5 — Open the App
Go to: **http://localhost/aac/**

---

## 🔑 Demo Accounts

| Role    | Email              | Password  |
|---------|--------------------|-----------|
| Student | student@demo.com   | demo123   |
| Admin   | admin@demo.com     | admin123  |

---

## ⚙️ Configuration

Edit `api/config.php` to change settings:

```php
define('DB_HOST', 'localhost');  // XAMPP default
define('DB_USER', 'root');       // XAMPP default
define('DB_PASS', '');           // XAMPP default (empty)
define('DB_NAME', 'aac_db');
```

If you set a MySQL root password in XAMPP, update `DB_PASS` accordingly.

---

## 🔌 API Endpoints Reference

### Auth (`api/auth.php`)
| Method | Action      | Description          |
|--------|-------------|----------------------|
| POST   | `?action=register` | Create account |
| POST   | `?action=login`    | Login          |
| GET    | `?action=me`       | Get current user (requires token) |

### Chat (`api/chat.php`)  *(requires auth token)*
| Method | Action           | Description              |
|--------|------------------|--------------------------|
| POST   | `?action=send`   | Send a message, get AI reply |
| GET    | `?action=history`| List all chat sessions   |
| GET    | `?action=session&id=N` | Get messages of a session |
| POST   | `?action=new_session`  | Create new session       |
| DELETE | `?action=session&id=N` | Delete a session         |

### Knowledge Base (`api/knowledge.php`)  *(write requires admin)*
| Method | Action            | Description        |
|--------|-------------------|--------------------|
| GET    | (none)            | List all entries   |
| POST   | `?action=add`     | Add new entry      |
| PUT    | `?action=edit&id=N` | Update entry     |
| DELETE | `?action=delete&id=N` | Delete entry  |

### Admin (`api/admin.php`)  *(admin only)*
| Method | Action             | Description          |
|--------|--------------------|----------------------|
| GET    | `?action=stats`    | Dashboard statistics |
| GET    | `?action=users`    | List all users       |
| POST   | `?action=add_user` | Add user             |
| PUT    | `?action=toggle_user&id=N` | Activate/Deactivate |
| DELETE | `?action=delete_user&id=N` | Delete user  |
| GET    | `?action=logs`     | Chat logs            |
| GET    | `?action=activity` | Recent activity      |

### File Upload (`api/upload.php`)  *(requires auth token)*
| Method | Description                |
|--------|----------------------------|
| POST   | Upload file (multipart)    |
| GET    | List user's uploaded files |
| DELETE | `?id=N` Delete file        |

---

## 🛠️ Troubleshooting

**"Network error. Is XAMPP running?"**
→ Make sure Apache and MySQL are both started in XAMPP Control Panel.

**"Database connection failed"**
→ Check that you imported `aac_database.sql` and credentials in `api/config.php` are correct.

**File upload not working**
→ Check that the `uploads/` folder exists and is writable.

**Page shows blank / CSS missing**
→ Make sure the folder is named `aac` (lowercase) inside `htdocs`.

---

## 📦 Technologies Used
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** PHP 8.x
- **Database:** MySQL (via PDO)
- **Server:** Apache (XAMPP)
- **Auth:** HMAC token-based (no external libraries)

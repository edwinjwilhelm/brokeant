# PHASE 2: USER REGISTRATION & LOGIN SYSTEM
**BrokeAnt Marketplace**  
**Timeline**: Jan 29 - Feb 2, 2026 (5-7 days)  
**Goal**: Users can register and log in  
**Revenue**: $0 (user system only, no payments yet)

---

## FOLDER STRUCTURE TO CREATE

```
c:\brokeant\
├── backend/
│   ├── api/
│   │   └── users.php
│   ├── config/
│   │   └── database.php
│   └── middleware/
│       └── auth.php
├── frontend/
│   ├── index.html (existing)
│   ├── register.html (NEW)
│   ├── login.html (NEW)
│   ├── melville/ (existing)
│   ├── yorkton/ (existing)
│   ├── regina/ (existing)
│   └── css/
│       └── styles.css
├── database/
│   └── schema.sql
└── README.md
```

---

## STEP 1: CREATE DATABASE SCHEMA

**File**: `database/schema.sql`

```sql
CREATE DATABASE brokeant;
USE brokeant;

CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20),
  city VARCHAR(50) NOT NULL,
  reputation_score FLOAT DEFAULT 5.0,
  total_reviews INT DEFAULT 0,
  verified BOOLEAN DEFAULT FALSE,
  suspended BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP
);
```

---

## STEP 2: DATABASE CONNECTION CONFIG

**File**: `backend/config/database.php`

```php
<?php
$host = 'localhost';
$user = 'brokeant_user';
$pass = 'your_strong_password_here';
$db = 'brokeant';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");
?>
```

---

## STEP 3: REGISTRATION PAGE

**File**: `frontend/register.html`

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register - BrokeAnt Marketplace</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, #133d60, #184c75);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .container {
      background: white;
      border-radius: 12px;
      padding: 40px;
      max-width: 500px;
      width: 100%;
      box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }
    
    h1 {
      color: #133d60;
      margin-bottom: 30px;
      text-align: center;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: bold;
    }
    
    input, select {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      font-family: Arial, sans-serif;
    }
    
    input:focus, select:focus {
      outline: none;
      border-color: #ffb34d;
      box-shadow: 0 0 5px rgba(255, 179, 77, 0.3);
    }
    
    button {
      width: 100%;
      background: #ffb34d;
      color: #402600;
      padding: 12px;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.3s;
    }
    
    button:hover {
      background: #ff9d1f;
    }
    
    .message {
      margin-top: 20px;
      padding: 12px;
      border-radius: 6px;
      text-align: center;
      display: none;
    }
    
    .message.success {
      background: #d4edda;
      color: #155724;
      display: block;
    }
    
    .message.error {
      background: #f8d7da;
      color: #721c24;
      display: block;
    }
    
    .login-link {
      text-align: center;
      margin-top: 20px;
      color: #666;
    }
    
    .login-link a {
      color: #ffb34d;
      text-decoration: none;
    }
    
    .login-link a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="container">
  <h1>Create Account</h1>
  
  <form id="registerForm">
    <div class="form-group">
      <label for="email">Email *</label>
      <input type="email" id="email" name="email" required>
    </div>
    
    <div class="form-group">
      <label for="password">Password *</label>
      <input type="password" id="password" name="password" required>
    </div>
    
    <div class="form-group">
      <label for="name">Full Name *</label>
      <input type="text" id="name" name="name" required>
    </div>
    
    <div class="form-group">
      <label for="city">City *</label>
      <select id="city" name="city" required>
        <option value="">Select your city</option>
        <option value="Melville">Melville</option>
        <option value="Yorkton">Yorkton</option>
        <option value="Regina">Regina</option>
      </select>
    </div>
    
    <div class="form-group">
      <label for="phone">Phone (Optional)</label>
      <input type="tel" id="phone" name="phone">
    </div>
    
    <button type="submit">Sign Up</button>
  </form>
  
  <div id="message" class="message"></div>
  
  <div class="login-link">
    Already have an account? <a href="login.html">Log in here</a>
  </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(document.getElementById('registerForm'));
  formData.append('action', 'register');
  
  try {
    const response = await fetch('/backend/api/users.php', {
      method: 'POST',
      body: formData
    });
    
    const data = await response.json();
    const messageEl = document.getElementById('message');
    
    if (data.success) {
      messageEl.className = 'message success';
      messageEl.textContent = data.message + ' Redirecting to login...';
      setTimeout(() => {
        window.location.href = '/login.html';
      }, 2000);
    } else {
      messageEl.className = 'message error';
      messageEl.textContent = 'Error: ' + data.message;
    }
  } catch (error) {
    console.error('Error:', error);
    document.getElementById('message').className = 'message error';
    document.getElementById('message').textContent = 'An error occurred. Please try again.';
  }
});
</script>

</body>
</html>
```

---

## STEP 4: LOGIN PAGE

**File**: `frontend/login.html`

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - BrokeAnt Marketplace</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, #133d60, #184c75);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .container {
      background: white;
      border-radius: 12px;
      padding: 40px;
      max-width: 500px;
      width: 100%;
      box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }
    
    h1 {
      color: #133d60;
      margin-bottom: 30px;
      text-align: center;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: bold;
    }
    
    input {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      font-family: Arial, sans-serif;
    }
    
    input:focus {
      outline: none;
      border-color: #ffb34d;
      box-shadow: 0 0 5px rgba(255, 179, 77, 0.3);
    }
    
    button {
      width: 100%;
      background: #ffb34d;
      color: #402600;
      padding: 12px;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.3s;
    }
    
    button:hover {
      background: #ff9d1f;
    }
    
    .message {
      margin-top: 20px;
      padding: 12px;
      border-radius: 6px;
      text-align: center;
      display: none;
    }
    
    .message.success {
      background: #d4edda;
      color: #155724;
      display: block;
    }
    
    .message.error {
      background: #f8d7da;
      color: #721c24;
      display: block;
    }
    
    .register-link {
      text-align: center;
      margin-top: 20px;
      color: #666;
    }
    
    .register-link a {
      color: #ffb34d;
      text-decoration: none;
    }
    
    .register-link a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="container">
  <h1>Log In</h1>
  
  <form id="loginForm">
    <div class="form-group">
      <label for="email">Email *</label>
      <input type="email" id="email" name="email" required>
    </div>
    
    <div class="form-group">
      <label for="password">Password *</label>
      <input type="password" id="password" name="password" required>
    </div>
    
    <button type="submit">Log In</button>
  </form>
  
  <div id="message" class="message"></div>
  
  <div class="register-link">
    Don't have an account? <a href="register.html">Sign up here</a>
  </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(document.getElementById('loginForm'));
  formData.append('action', 'login');
  
  try {
    const response = await fetch('/backend/api/users.php', {
      method: 'POST',
      body: formData
    });
    
    const data = await response.json();
    const messageEl = document.getElementById('message');
    
    if (data.success) {
      messageEl.className = 'message success';
      messageEl.textContent = 'Login successful! Redirecting...';
      setTimeout(() => {
        window.location.href = '/index.html';
      }, 2000);
    } else {
      messageEl.className = 'message error';
      messageEl.textContent = 'Error: ' + data.message;
    }
  } catch (error) {
    console.error('Error:', error);
    document.getElementById('message').className = 'message error';
    document.getElementById('message').textContent = 'An error occurred. Please try again.';
  }
});
</script>

</body>
</html>
```

---

## STEP 5: USERS API BACKEND

**File**: `backend/api/users.php`

```php
<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    register($conn);
} elseif ($action === 'login') {
    login($conn);
} elseif ($action === 'logout') {
    logout();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function register($conn) {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $city = $conn->real_escape_string($_POST['city'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    
    // Validate input
    if (empty($email) || empty($password) || empty($name) || empty($city)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        return;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        return;
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user
    $sql = "INSERT INTO users (email, password_hash, name, city, phone) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("sssss", $email, $password_hash, $name, $city, $phone);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
    } else {
        if ($conn->errno === 1062) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed']);
        }
    }
    
    $stmt->close();
}

function login($conn) {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        return;
    }
    
    $sql = "SELECT id, password_hash, name, city FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_city'] = $row['city'];
            
            // Update last login
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Login successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
    
    $stmt->close();
}

function logout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out']);
}
?>
```

---

## STEP 6: AUTHENTICATION MIDDLEWARE

**File**: `backend/middleware/auth.php`

```php
<?php
session_start();

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.html');
        exit;
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_name() {
    return $_SESSION['user_name'] ?? null;
}

function get_user_city() {
    return $_SESSION['user_city'] ?? null;
}
?>
```

---

## STEP 7: UPDATE MAIN PAGE

**File**: `frontend/index.html` (UPDATE)

Add this to the top of your existing index.html `<head>`:

```html
<script>
// Check if user is logged in
fetch('/backend/api/users.php?action=check_session')
  .then(r => r.json())
  .then(data => {
    if (data.logged_in) {
      document.body.classList.add('logged-in');
      document.getElementById('userGreeting').textContent = 'Welcome, ' + data.name + '!';
    }
  });
</script>
```

Add this after your header:

```html
<div id="userGreeting" style="color: white; text-align: right; padding: 10px 20px; background: rgba(0,0,0,0.2);">
  <a href="/login.html">Log In / Sign Up</a>
</div>
```

---

## STEP 8: DATABASE SETUP ON VPS

Run on VPS (via SSH):

```bash
ssh root@66.179.188.184

# Login to MySQL
mysql -u root -p

# Create database and user
CREATE DATABASE brokeant;
CREATE USER 'brokeant_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON brokeant.* TO 'brokeant_user'@'localhost';
FLUSH PRIVILEGES;
exit;

# Create tables
mysql -u brokeant_user -p brokeant < /var/www/brokeant/database/schema.sql
```

---

## STEP 9: GITHUB SETUP

```bash
# On your local machine
cd c:\brokeant
git init
git add .
git commit -m "Phase 2: User registration and login system"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/brokeant.git
git push -u origin main
```

---

## STEP 10: DEPLOY TO VPS

```bash
# SSH to VPS
ssh root@66.179.188.184
cd /var/www/brokeant
git pull origin main
sudo systemctl reload nginx
```

---

## STEP 11: TEST

Visit: **https://brokeant.com/register.html**

Test:
- [ ] Register new account
- [ ] Try duplicate email (should fail)
- [ ] Login with correct credentials
- [ ] Login with wrong password (should fail)
- [ ] Logout
- [ ] Session persists (refresh page = still logged in)

---

## TROUBLESHOOTING

**PHP errors?**
```bash
tail -f /var/log/nginx/error.log
tail -f /var/log/php8.3-fpm.log
```

**Database connection fails?**
- Check `database.php` password matches what you set
- Verify user exists: `mysql -u brokeant_user -p`

**Files not found?**
- Verify files in `/var/www/brokeant/backend/`
- Check nginx config: `sudo nginx -t`

---

## NEXT PHASE (Phase 3)

Once Phase 2 is complete, Phase 3 will add:
- Listings table
- Post item functionality
- Browse listings
- Edit/delete listings

---

**GOOD LUCK!** 🚀

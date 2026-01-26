# BrokeAnt Marketplace - Complete Development & Deployment Plan
**Status**: Architecture Defined | Ready for Implementation  
**Date**: January 25, 2026  
**Server**: VPS (217.154.52.209) | GitHub Integration | CI/CD Pipeline

---

## 📊 COMPLETE TECH STACK

### Frontend (What Users See)
- HTML/CSS/JavaScript (your current Melville/Yorkton/Regina pages)
- Responsive design for mobile
- Forms for posting, login, search
- Real-time listing updates

### Backend (Server-Side Logic)
- **PHP 8.0+** (simplest, most compatible)
- Alternative: Node.js or Python Flask
- Handles: user auth, database queries, payments, sessions

### Database (Permanent Storage)
- **MySQL 8.0** (free, reliable)
- Tables: users, listings, payments, reviews
- Backup strategy: daily automated backups

### Payment Processing
- **Stripe** (recommended) or PayPal
- Handles credit card security
- 2.9% + $0.30 per transaction fee
- Webhook integration for payment confirmations

### Hosting & Deployment
- **VPS**: 217.154.52.209 (your current server)
- **GitHub**: Version control & automated deployments
- **SSL**: Let's Encrypt (FREE HTTPS)
- **Domain**: bishopsthegame.tv

### DevOps/CI-CD
- GitHub Actions (automated testing & deployment)
- SSH key authentication (secure server access)
- Automated backups to GitHub or external storage

---

## 🗄️ DATABASE SCHEMA

### Users Table
```sql
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

### Listings Table
```sql
CREATE TABLE listings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(50),
  price DECIMAL(10, 2) NOT NULL,
  image_url VARCHAR(255),
  status ENUM('active', 'sold', 'removed') DEFAULT 'active',
  posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expiration_date DATE NOT NULL,
  city VARCHAR(50) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Payments Table
```sql
CREATE TABLE payments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  listing_id INT,
  amount DECIMAL(10, 2) NOT NULL,
  stripe_transaction_id VARCHAR(255),
  status ENUM('completed', 'failed', 'pending') DEFAULT 'pending',
  payment_method VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Reviews Table
```sql
CREATE TABLE reviews (
  id INT PRIMARY KEY AUTO_INCREMENT,
  reviewer_id INT NOT NULL,
  seller_id INT NOT NULL,
  listing_id INT,
  rating INT CHECK (rating >= 1 AND rating <= 5),
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reviewer_id) REFERENCES users(id),
  FOREIGN KEY (seller_id) REFERENCES users(id)
);
```

---

## 🔄 GITHUB INTEGRATION WORKFLOW

### Repository Structure
```
brokeant/
├── .github/
│   └── workflows/
│       ├── deploy-to-vps.yml        (auto-deploy on push)
│       ├── test.yml                 (run tests)
│       └── backup.yml               (daily backup)
├── backend/
│   ├── api/
│   │   ├── users.php               (registration, login)
│   │   ├── listings.php            (post, edit, delete)
│   │   ├── payments.php            (Stripe integration)
│   │   └── reviews.php             (rating system)
│   ├── config/
│   │   ├── database.php            (DB connection)
│   │   └── stripe.php              (payment config)
│   └── middleware/
│       ├── auth.php                (check logged-in user)
│       └── validation.php          (input validation)
├── frontend/
│   ├── index.html                  (main marketplace)
│   ├── melville/
│   ├── yorkton/
│   ├── regina/
│   ├── login.html
│   ├── register.html
│   ├── post-listing.html
│   ├── user-profile.html
│   ├── admin-dashboard.html
│   └── css/
│       └── styles.css
├── database/
│   ├── schema.sql                  (all table definitions)
│   └── seed-data.sql               (test data)
├── docs/
│   ├── API_DOCUMENTATION.md
│   ├── DEPLOYMENT.md
│   └── ARCHITECTURE.md
├── .gitignore                       (exclude sensitive files)
├── README.md
└── deploy.sh                        (deployment script)
```

### GitHub Actions - Auto Deployment Workflow
File: `.github/workflows/deploy-to-vps.yml`

```yaml
name: Deploy to VPS

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Deploy to VPS
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.VPS_IP }}
          username: ${{ secrets.VPS_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          script: |
            cd /var/www/brokeant
            git pull origin main
            chmod +x deploy.sh
            ./deploy.sh
```

### Sensitive Data (Use GitHub Secrets)
- `VPS_IP`: 217.154.52.209
- `VPS_USER`: deployment username
- `VPS_SSH_KEY`: private SSH key (for secure access)
- `STRIPE_SECRET_KEY`: from Stripe dashboard
- `DB_PASSWORD`: MySQL root password

---

## 🚀 COMPLETE DEVELOPMENT PHASES

### PHASE 1: Foundation (Week 1)
**Goal**: User system working

- [ ] Create GitHub repository for brokeant
- [ ] Set up folder structure (backend, frontend, database)
- [ ] Create `.gitignore` (exclude: config files, credentials, node_modules)
- [ ] Set up MySQL database on VPS
- [ ] Create users table + password hashing
- [ ] Build registration page & backend API
- [ ] Build login page & session management
- [ ] Test: register → login → logout works

**Files to Create**:
- `backend/api/users.php` - registration/login endpoints
- `backend/config/database.php` - MySQL connection
- `backend/middleware/auth.php` - session/login checking
- `frontend/register.html` - registration form
- `frontend/login.html` - login form
- `database/schema.sql` - database tables

**Deliverable**: Users can register and log in

---

### PHASE 2: Core Marketplace (Week 2-3)
**Goal**: Listings stored in database

- [ ] Create listings table
- [ ] Build "Post Listing" form (HTML)
- [ ] Build `listings.php` API (create, read, update, delete)
- [ ] Display all listings from database (with filters by city)
- [ ] Add "My Listings" page for logged-in users
- [ ] Edit listing (logged-in users only)
- [ ] Delete listing (logged-in users only)
- [ ] Test: post → appears in database → edit/delete works

**Files to Create**:
- `frontend/post-listing.html` - form to post item
- `frontend/listings.html` - display all listings
- `frontend/my-listings.html` - user's own listings
- `backend/api/listings.php` - CRUD operations
- `database/schema.sql` - update with listings table

**Deliverable**: Full listing management system (without payment)

---

### PHASE 3: Payment Integration (Week 4)
**Goal**: Charge $5 before posting

- [ ] Create Stripe account & get API keys
- [ ] Store API keys in GitHub Secrets
- [ ] Build Stripe payment form (checkout page)
- [ ] Create `payments.php` API
- [ ] Create payments table
- [ ] Modify "Post Listing" → require payment first
- [ ] Payment webhook: Stripe → your server confirms payment
- [ ] Only allow listing if payment succeeded
- [ ] Test: attempt post → redirect to Stripe → pay → listing created

**Files to Create**:
- `frontend/checkout.html` - Stripe payment form
- `backend/api/payments.php` - Stripe integration
- `backend/api/webhook.php` - handle Stripe webhooks
- `backend/config/stripe.php` - API key setup
- Database payment tracking

**Deliverable**: Payment system working, $5 charge per listing

---

### PHASE 4: User Features & Moderation (Week 5-6)
**Goal**: Reputation system & admin tools

- [ ] Create reviews table
- [ ] Build reviews API
- [ ] Add "Leave Review" functionality (post-sale)
- [ ] Calculate & display seller reputation (average rating)
- [ ] Build admin dashboard:
  - View all users
  - View all listings
  - View all payments
  - Suspend users
  - Delete listings
  - Ban users
- [ ] Listing expiration (30 days default)
- [ ] Email notifications (registration, payment, expiration)
- [ ] Flag inappropriate listings

**Files to Create**:
- `frontend/reviews.html` - leave review form
- `frontend/seller-profile.html` - seller reputation page
- `frontend/admin-dashboard.html` - admin controls
- `backend/api/reviews.php` - review API
- `backend/api/admin.php` - admin functions
- `backend/email/notifications.php` - email system

**Deliverable**: Full reputation system & admin control

---

### PHASE 5: Polish & Security (Week 7-8)
**Goal**: Production-ready, secure

- [ ] Add HTTPS/SSL (Let's Encrypt - FREE)
- [ ] Security audit:
  - Input validation (prevent injection attacks)
  - Rate limiting (prevent spam)
  - CORS headers (secure API calls)
  - CSRF tokens (prevent forgery)
- [ ] Database backups (automated daily)
- [ ] Add Terms of Service page
- [ ] Add Privacy Policy
- [ ] Add Contact/Support page
- [ ] Performance optimization:
  - Database indexing
  - Caching strategy
  - Image optimization
- [ ] Mobile responsiveness audit
- [ ] Error handling & logging

**Files to Create**:
- `frontend/terms.html` - terms of service
- `frontend/privacy.html` - privacy policy
- `backend/middleware/validation.php` - input checks
- `backend/middleware/security.php` - security headers
- `docs/SECURITY.md` - security documentation

**Deliverable**: Production-ready, secure marketplace

---

### PHASE 6: Launch! (Week 9)
**Goal**: BrokeAnt goes LIVE

- [ ] Final testing
- [ ] Set up automated backups
- [ ] Configure CI/CD (GitHub Actions auto-deploy)
- [ ] Set up monitoring/alerts
- [ ] Marketing: announce on social media
- [ ] Monitor for bugs/issues
- [ ] First users!

**Deliverable**: LIVE marketplace with users paying $5 per listing

---

## 💰 COST BREAKDOWN

| Item | Cost | Notes |
|------|------|-------|
| VPS (already have) | $5-10/mo | 217.154.52.209 |
| Domain (already have) | $12/year | bishopsthegame.tv |
| MySQL | FREE | Included in VPS |
| PHP | FREE | Included in VPS |
| SSL Certificate | FREE | Let's Encrypt |
| Stripe/PayPal | 2.9% + $0.30 per transaction | Only on revenue |
| Email Service | FREE → $10/mo | SendGrid/Mailgun (optional) |
| GitHub | FREE | Public/private repos |
| **TOTAL** | **~$5-15/mo** | Just hosting + optional email |

**Revenue Example**:
- 100 listings at $5 each = $500
- Stripe fee: $500 × 2.9% + (100 × $0.30) = $14.50 + $30 = $44.50
- **You keep**: $455.50

---

## 🔐 SECURITY CHECKLIST

- [ ] Passwords hashed (bcrypt, NOT plain text)
- [ ] SQL injection prevention (prepared statements)
- [ ] XSS prevention (htmlspecialchars on output)
- [ ] CSRF tokens on forms
- [ ] Rate limiting on login (prevent brute force)
- [ ] HTTPS only (SSL certificate)
- [ ] Secure Stripe integration (PCI compliance)
- [ ] Email verification (confirm user email)
- [ ] Session timeouts (auto-logout after 30 mins)
- [ ] Admin login (separate from user login, extra protection)
- [ ] Audit logging (track admin actions)

---

## 📋 GITHUB SECRETS TO SET UP

In your GitHub repo → Settings → Secrets → New repository secret:

```
VPS_IP = 217.154.52.209
VPS_USER = deployment_user
VPS_SSH_KEY = [paste your private SSH key]
VPS_HOST_KEY = [paste server host key]
STRIPE_PUBLIC_KEY = pk_live_...
STRIPE_SECRET_KEY = sk_live_...
DB_HOST = localhost
DB_USER = brokeant_user
DB_PASSWORD = [strong password]
SENDGRID_API_KEY = [optional, for emails]
```

---

## 🚀 QUICK START TOMORROW (When VPS is Ready)

### Step 1: VPS Setup (30 mins)
```bash
# SSH into VPS
ssh username@217.154.52.209

# Install required software
sudo apt update
sudo apt install php php-mysql mysql-server nginx git -y

# Start services
sudo systemctl start mysql nginx php-fpm
sudo systemctl enable mysql nginx php-fpm

# Create database
mysql -u root -p << EOF
CREATE DATABASE brokeant;
CREATE USER 'brokeant_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON brokeant.* TO 'brokeant_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### Step 2: GitHub Setup (15 mins)
```bash
# Clone your repo on VPS
cd /var/www
git clone https://github.com/YOUR_USERNAME/brokeant.git
cd brokeant

# Run database schema
mysql -u brokeant_user -p brokeant < database/schema.sql
```

### Step 3: Deploy Script (5 mins)
Create `deploy.sh`:
```bash
#!/bin/bash
cd /var/www/brokeant
git pull origin main
chmod 755 backend/
chmod 755 frontend/
echo "✅ Deployed successfully"
```

### Step 4: GitHub Actions Setup (10 mins)
Push `.github/workflows/deploy-to-vps.yml` → auto-deploys on every git push

---

## 📖 RECOMMENDED ORDER

1. **TODAY**: You have this plan
2. **TOMORROW (VPS arrives)**: Set up VPS + MySQL (Phase 1 prep)
3. **WEEK 1**: Build user registration/login (Phase 1)
4. **WEEK 2-3**: Build marketplace listing system (Phase 2)
5. **WEEK 4**: Add Stripe payments (Phase 3)
6. **WEEK 5-6**: Add reviews & admin panel (Phase 4)
7. **WEEK 7-8**: Security hardening (Phase 5)
8. **WEEK 9**: Launch! 🚀

---

## ✅ NEXT STEPS

1. **Create GitHub Repository**
   - Go to github.com/new
   - Name: `brokeant`
   - Description: "BrokeAnt Marketplace - Buy, Sell, Trade Locally"
   - Add README.md
   - Clone locally: `git clone https://github.com/YOU/brokeant.git`

2. **Start Phase 1 Tomorrow**
   - When VPS is available, set up MySQL
   - Build registration/login system
   - Test locally before deploying

3. **Set Up GitHub Secrets**
   - Add VPS IP, credentials, API keys to GitHub
   - Enable GitHub Actions for auto-deployment

4. **Follow the Weekly Phases**
   - Each phase = 1-2 weeks of work
   - Test before moving to next phase
   - Commit to GitHub regularly (git push)

---

## 💬 QUESTIONS TO CLARIFY

- [ ] Use PHP or Node.js/Python? (Recommend PHP for simplicity)
- [ ] Stripe or PayPal? (Recommend Stripe for better API)
- [ ] Base listing fee: $5 or different?
- [ ] City limit: Just Melville/Yorkton/Regina or add more?
- [ ] Admin approval needed for listings or auto-publish?
- [ ] Allow pictures upload or external image URLs?

---

**Status**: Ready to build! Your marketplace can be LIVE in 6-8 weeks. 🎯


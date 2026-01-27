# PHASE 4 IMPLEMENTATION GUIDE: Stripe & PayPal Integration

## PART 1: STRIPE ACCOUNT SETUP (15-20 mins)

### Step 1: Create Stripe Account
1. Go to https://dashboard.stripe.com/register
2. Email: `sales@brokeant.com` (ProtonMail)
3. Password: Create strong password (store in password manager)
4. Country: Canada
5. Business type: Sole proprietor
6. Business name: BrokeAnt

### Step 2: Verify Email & Complete Setup
1. Check ProtonMail inbox for verification link
2. Click verification link
3. Complete identity verification (name, address, phone)
4. Accept Stripe terms

### Step 3: Get API Keys
1. Dashboard → Developers (left sidebar)
2. Click "API keys" tab
3. Copy **Publishable Key** (starts with `pk_live_`)
4. Copy **Secret Key** (starts with `sk_live_`)
5. Save both to secure location

### Step 4: Configure Webhook
1. Dashboard → Developers → Webhooks
2. Click "Add endpoint"
3. Endpoint URL: `https://brokeant.com/backend/api/webhook-stripe.php`
4. Events to send: `charge.succeeded`, `charge.failed`
5. Copy **Signing Secret** (starts with `whsec_`)

---

## PART 2: PAYPAL ACCOUNT SETUP (20-30 mins)

### Step 1: Create PayPal Business Account
1. Go to https://www.paypal.com/business
2. Click "Sign Up"
3. Email: `sales@brokeant.com` (ProtonMail)
4. Account type: Business
5. Business name: BrokeAnt
6. Country: Canada
7. Business category: Marketplace/Classifieds

### Step 2: Verify Email & Phone
1. Check ProtonMail for verification email
2. Click verification link
3. Add phone number (can use Google Voice if staying anonymous)
4. Complete identity verification

### Step 3: Get API Credentials
1. Account Settings → API Signature (NOT certificate method)
2. Copy:
   - **API Username** (email format)
   - **API Password** (long random string)
   - **Signature** (long alphanumeric string)
3. Save all three to secure location

### Step 4: Create App for REST API (Alternative/Better)
1. Go to https://developer.paypal.com/dashboard/apps/live
2. Click "Create App"
3. App name: "BrokeAnt Marketplace"
4. Copy:
   - **Client ID** (starts with `AQEw...`)
   - **Secret** (long alphanumeric string)

---

## PART 3: CODE STRUCTURE (What We'll Build)

### New Files to Create:
- `backend/config/stripe.php` - Stripe configuration
- `backend/config/paypal.php` - PayPal configuration
- `backend/api/payment-stripe.php` - Stripe payment processing
- `backend/api/payment-paypal.php` - PayPal payment processing
- `backend/api/webhook-stripe.php` - Stripe webhook handler
- `database/payments_table.sql` - Payment tracking table
- `payment-checkout.html` - Checkout form (shown after registration)

### Database Changes:
- Add to `users` table: `payment_status` (pending/verified/failed), `payment_date`
- Create `payments` table: id, user_id, amount, currency, gateway, transaction_id, status, created_at

---

## PART 4: REGISTRATION FLOW (Updated)

### Current Flow:
```
User fills registration form 
  → POST to /backend/api/users.php 
  → Account created 
  → Redirect to login
```

### New Flow:
```
User fills registration form 
  → POST to /backend/api/users.php 
  → Account created (payment_status='pending') 
  → Redirect to /payment-checkout.html 
  → User chooses Stripe OR PayPal 
  → Pay $2 CAD 
  → Webhook receives confirmation 
  → Update payment_status='verified' 
  → Redirect to login
```

---

## PART 5: IMPLEMENTATION CHECKLIST

### Pre-Implementation:
- [ ] Stripe account created and API keys saved
- [ ] PayPal account created and credentials saved
- [ ] Store all keys in `backend/config/` (add to .gitignore)

### Database Updates:
- [ ] Run migration: Add columns to users table
- [ ] Run migration: Create payments table

### Backend APIs:
- [ ] Create `payment-stripe.php` - Handle Stripe token + charge
- [ ] Create `payment-paypal.php` - Handle PayPal token + charge
- [ ] Create `webhook-stripe.php` - Handle Stripe webhook confirmations
- [ ] Update `users.php` - Add payment_status parameter to registration

### Frontend:
- [ ] Create `payment-checkout.html` - Clean checkout form with Stripe/PayPal buttons
- [ ] Update `register.html` - Modify form submission to skip login redirect (let payment page handle it)
- [ ] Add Stripe.js library to payment page
- [ ] Add PayPal SDK to payment page

### Testing:
- [ ] Test Stripe with test card: `4242 4242 4242 4242` (any future date, any CVC)
- [ ] Test PayPal with sandbox account
- [ ] Verify webhook fires after payment
- [ ] Verify user can login after payment confirmed
- [ ] Check payment recorded in database

### Deployment:
- [ ] Commit all Phase 4 files to GitHub
- [ ] Git pull on VPS
- [ ] Run database migrations
- [ ] Test on live site with real cards (or test mode)

---

## PART 6: SECURITY NOTES

### Do NOT:
- ❌ Store API keys in PHP files (use environment variables or config files in .gitignore)
- ❌ Expose Secret keys to frontend
- ❌ Skip webhook verification
- ❌ Process payments without HTTPS (we're good here)

### Do:
- ✅ Keep Secret keys on backend only
- ✅ Verify webhook signatures before processing
- ✅ Store transaction IDs in database
- ✅ Add rate limiting on payment endpoints

---

## PART 7: TIMELINE

- **Account Setup (Stripe + PayPal):** 30-45 mins
- **Database Schema:** 5 mins
- **Backend APIs:** 90 mins
- **Frontend Checkout Page:** 45 mins
- **Testing & Debugging:** 30 mins
- **Total Phase 4:** ~3.5-4 hours

---

## QUICK REFERENCE: API Keys Format

| Service | Key Type | Format | Where to Find |
|---------|----------|--------|----------------|
| Stripe | Publishable | `pk_live_...` | Dashboard → Developers → API keys |
| Stripe | Secret | `sk_live_...` | Dashboard → Developers → API keys |
| Stripe | Webhook Secret | `whsec_...` | Dashboard → Developers → Webhooks |
| PayPal | Client ID | `AQEw...` | Developer Dashboard → Apps & Credentials |
| PayPal | Secret | `...` | Developer Dashboard → Apps & Credentials |

---

**Created:** January 27, 2026  
**Project:** BrokeAnt Marketplace - Phase 4 Implementation  
**Status:** Ready for Account Setup

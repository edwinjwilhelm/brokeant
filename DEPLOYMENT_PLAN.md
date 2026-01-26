# BrokeAnt Marketplace - Deployment Phase Plan

**Status**: Ready for VPS deployment  
**Created**: January 25, 2026  
**Target**: Go LIVE within 24 hours of VPS availability

---

## 📋 Pre-Deployment Information Needed (Tomorrow)

When you receive VPS access, provide:
1. **VPS IP Address** - e.g., `123.45.67.89`
2. **SSH Credentials** - username & port
3. **Root/Sudo Access** - confirm available
4. **Domain Name** - e.g., `brokeant.com` or IP-based access
5. **Web Server** - nginx or Apache (we'll help configure)
6. **SSL Certificate** - do you have one? (Let's Encrypt available)

---

## 🚀 Phase 1: Server Preparation (Day 1 - 15 mins)

### 1.1 Initial Server Setup
- [ ] SSH into VPS: `ssh username@vps_ip`
- [ ] Update system: `sudo apt update && sudo apt upgrade -y`
- [ ] Install nginx: `sudo apt install nginx -y`
- [ ] Start nginx: `sudo systemctl start nginx && sudo systemctl enable nginx`

### 1.2 Create Web Root Directory
```bash
sudo mkdir -p /var/www/brokeant
sudo chown -R $USER:$USER /var/www/brokeant
```

### 1.3 Verify Web Server
- [ ] Visit `http://VPS_IP` in browser → should see nginx welcome page

---

## 📦 Phase 2: File Transfer & Deployment (Day 1 - 10 mins)

### 2.1 Files to Deploy
**From your local machine:**
```
c:\brokeant\
├── index.html              (main landing page)
├── c8e9233c-b606-458b-a4e2-5c16b2696367.png  (Alfred image)
├── melville/
│   ├── index.html
│   ├── fur1.jpg
│   ├── fur2.jpg
│   ├── fur3.jpg
│   ├── fur4.jpg
│   ├── house_blue.jpg
│   └── ktmbike.jpg
├── regina/
│   ├── index.html
│   ├── fur1.jpg
│   ├── fur2.jpg
│   ├── fur3.jpg
│   ├── fur4.jpg
│   ├── house_blue.jpg
│   └── ktmbike.jpg
└── yorkton/
    ├── index.html
    ├── fur1.jpg
    ├── fur2.jpg
    ├── fur3.jpg
    ├── fur4.jpg
    ├── house_blue.jpg
    └── ktmbike.jpg
```

### 2.2 Transfer Using SFTP/SCP
**Option A: Use FileZilla (GUI)**
- Host: `sftp://VPS_IP`
- Port: `22`
- Username: `your_username`
- Local: `C:\brokeant\`
- Remote: `/var/www/brokeant`
- Drag & drop all files

**Option B: Use PowerShell SCP (if SSH key available)**
```powershell
scp -r C:\brokeant\* username@VPS_IP:/var/www/brokeant/
```

---

## ⚙️ Phase 3: Web Server Configuration (Day 1 - 10 mins)

### 3.1 Create Nginx Configuration
SSH into VPS and run:
```bash
sudo tee /etc/nginx/sites-available/brokeant > /dev/null <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    server_name _;
    root /var/www/brokeant;
    index index.html;
    
    # Enable gzip compression
    gzip on;
    gzip_types text/plain text/css text/javascript application/json;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    
    # Handle nested routes
    location / {
        try_files $uri $uri/ =404;
    }
    
    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }
}
EOF
```

### 3.2 Enable & Verify Config
```bash
sudo ln -sf /etc/nginx/sites-available/brokeant /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## 🔒 Phase 4: SSL/HTTPS Setup (Optional but Recommended - 15 mins)

### 4.1 Install Certbot
```bash
sudo apt install certbot python3-certbot-nginx -y
```

### 4.2 Get Free SSL Certificate
```bash
sudo certbot --nginx -d your-domain.com
```
*(If using domain name, not IP)*

### 4.3 Auto-Renewal
```bash
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
```

---

## ✅ Phase 5: Testing & Verification (Day 1 - 10 mins)

### 5.1 Test Main Page
- [ ] Open browser: `http://VPS_IP/` or `http://your-domain.com/`
- [ ] Verify Alfred image loads
- [ ] Verify "BrokeAnt Marketplace" title appears
- [ ] Check all 3 city cards visible (Melville, Yorkton, Regina)

### 5.2 Test City Pages
- [ ] Click "Melville" → should load `/melville/index.html`
- [ ] Click "Yorkton" → should load `/yorkton/index.html`
- [ ] Click "Regina" → should load `/regina/index.html`
- [ ] All images load (fur1-4, house_blue, ktmbike)

### 5.3 Mobile Responsiveness
- [ ] Test on mobile device (responsive design check)
- [ ] Verify city cards stack properly on small screens

### 5.4 Performance Check
- [ ] Page load time < 2 seconds
- [ ] No console errors (F12 → Console tab)
- [ ] All image files present (F12 → Network tab)

---

## 🔧 Phase 6: Post-Deployment Fixes (As Needed)

### 6.1 If Images Don't Load
**Problem**: Images showing broken
**Solution**: 
```bash
# SSH to VPS
ls -la /var/www/brokeant/melville/
# Verify all .jpg files exist
# Check permissions
sudo chmod -R 755 /var/www/brokeant
```

### 6.2 If City Links Don't Work
**Problem**: 404 errors on city pages
**Solution**: Check nginx config with `/try_files` directive (included in Phase 3.1)

### 6.3 If Page is Slow
**Solution**: Enable caching & compression (included in nginx config)

---

## 📊 Phase 7: Go LIVE Checklist

- [ ] VPS accessed & credentials confirmed
- [ ] Files uploaded to `/var/www/brokeant`
- [ ] Nginx configured & restarted
- [ ] Main page loads correctly
- [ ] All 3 city pages load correctly
- [ ] All images load properly
- [ ] Mobile responsive test passed
- [ ] SSL/HTTPS working (if configured)
- [ ] DNS/domain pointing to VPS IP (if using domain)
- [ ] Performance acceptable
- [ ] No browser console errors

---

## 🆘 Troubleshooting Commands

```bash
# Check nginx status
sudo systemctl status nginx

# View nginx error logs
sudo tail -f /var/logs/nginx/error.log

# Check file permissions
ls -la /var/www/brokeant/

# Verify nginx config syntax
sudo nginx -t

# Restart nginx (after any config change)
sudo systemctl restart nginx

# Test connectivity from your machine
ping VPS_IP
curl http://VPS_IP/

# Check disk space
df -h
```

---

## 📋 Next Steps (Tomorrow)

1. Provide VPS credentials when ready
2. Execute Phase 1 (Server Prep)
3. Execute Phase 2 (File Transfer)
4. Execute Phase 3 (Nginx Config)
5. Run Phase 5 (Testing)
6. Deploy Phase 4 (SSL - optional)
7. Complete Phase 7 checklist

**Estimated Total Time**: ~1 hour from VPS access to LIVE

---

## 📞 Quick Reference

- **Main Page**: `http://VPS_IP/` or `http://your-domain.com/`
- **Melville**: `http://VPS_IP/melville/`
- **Yorkton**: `http://VPS_IP/yorkton/`
- **Regina**: `http://VPS_IP/regina/`
- **Web Root**: `/var/www/brokeant`
- **Nginx Config**: `/etc/nginx/sites-available/brokeant`
- **Error Logs**: `/var/log/nginx/error.log`

---

**Ready to go LIVE when you provide VPS details!** 🚀

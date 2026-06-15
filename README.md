# ✉️ AeroMail
### Lightweight, Vanilla PHP MVC Webmail Client

**AeroMail** is a fast, lightweight, and secure webmail client built with pure PHP (Vanilla PHP) using the MVC architecture. It requires **zero heavy frameworks** (no Laravel, no Symfony) and connects directly to any standard IMAP/SMTP mail server. 

Perfect for developers who want a clean, dependency-free, and highly customizable mail interface.

---

## 🚀 Key Features

- ⚡ **Zero Dependencies:** Pure PHP 8+, no Composer or external libraries required.
- 🏗️ **True MVC Architecture:** Clean separation of concerns (Models, Views, Controllers, and a custom Router).
- 📥 **Full IMAP Support:** Read emails, manage folders (Inbox, Sent, Trash, Spam), and mark as read/unread.
- 📤 **Robust SMTP Sending:** STARTTLS support, multi-file attachments, Reply, and Forward functionality.
- 📎 **Secure Attachment Handling:** Safe upload/download with built-in size and type validation.
- 🔒 **Security First:** CSRF token protection, server-side session authentication, and no hardcoded credentials.
- 📱 **Responsive UI:** Modern, clean CSS that works flawlessly on desktop and mobile devices.
- 🗑️ **Smart Deletion:** Moves emails to the "Trash" folder instead of permanent deletion (standard mail client behavior).

---

## 🛠️ Prerequisites

- **PHP:** 8.0 or higher
- **PHP Extensions:** `imap`, `mbstring`, `openssl`
- **Web Server:** Apache (with `mod_rewrite` enabled) or Nginx
- **Mail Server:** Any server supporting IMAP (Port 993, SSL) and SMTP (Port 587, STARTTLS)

---

## 📂 Project Structure

```text
aeromail/
├── app/
│   ├── config/         # Application configuration (server settings, URLs, limits)
│   ├── core/           # Framework core (Router, base MVC classes)
│   ├── controllers/    # Business logic (AuthController, MailController)
│   ├── models/         # Server communication (ImapModel, SmtpModel)
│   └── views/          # HTML templates (layout, inbox, read, compose)
├── css/                # Stylesheets
├── tmp/                # Temporary directory (requires write permissions)
└── index.php           # Main entry point (Front Controller)
```

---

## ⚙️ Installation

1. **Clone the repository:**
   ```bash
   git clone git@github.com:marcin-filipiak/php_AeroMail.git
   cd php_AeroMail
   ```

2. **Configure the application:**
   Open `app/config/config.php` and update the settings to match your mail server:
   ```php
   define('MAIL_SERVER', 'mail.yourdomain.com');
   define('MAIL_IMAP_PORT', 993);
   define('MAIL_SMTP_PORT', 587);
   define('APP_URL', 'https://yourdomain.com/aeromail'); // IMPORTANT: No trailing slash!
   define('SMTP_DEBUG', false); // Set to true ONLY for troubleshooting
   ```

3. **Set directory permissions:**
   Ensure your web server has write access to the temporary directory:
   ```bash
   chmod 755 tmp/
   # Or, if your hosting requires it:
   chmod 777 tmp/
   ```

4. **Web Server Configuration:**
   - **Apache:** Ensure `mod_rewrite` is enabled and `AllowOverride All` is set in your VirtualHost config so the `.htaccess` file works.
   - **Nginx:** Configure your location block to route all non-file/directory requests to `index.php?url=$1`.

---

## 🔒 Security & Privacy

AeroMail is designed with data privacy in mind:
1. **No Local Mail Storage:** Emails and attachments are **never saved** to the web server's disk. The application acts strictly as a secure proxy between your browser and the mail server.
2. **Session-Based Auth:** Credentials are stored securely in PHP server-side sessions, never in browser cookies.
3. **Encrypted Transit:** All communication with the mail server is encrypted (IMAP over SSL, SMTP over STARTTLS).
4. **CSRF Protection:** All state-changing actions (sending, deleting) are protected by CSRF tokens.

> **⚠️ Production Note:** Always set `define('APP_DEBUG', false);` and `define('SMTP_DEBUG', false);` in `config.php` when deploying to production to prevent sensitive data leakage in logs.

---

## 🐛 Troubleshooting

- **White Screen of Death:** Ensure the `php-imap` extension is installed and enabled in your `php.ini`.
- **404 Errors on Navigation:** Verify that `mod_rewrite` is active and the `.htaccess` file is present in the root directory.
- **Attachments show as 0 KB:** Check your PHP `upload_max_filesize` and `post_max_size` limits, and ensure the `tmp/` directory is writable.

---

## 🤝 Contributing

Have an idea for a new feature or found a bug? Contributions are welcome!
1. Fork the repository.
2. Create your feature branch (`git checkout -b feature/AmazingFeature`).
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`).
0. Push to the branch (`git push origin feature/AmazingFeature`).
5. Open a Pull Request.

---

## 📜 License

This project is open-source and available under the **MIT License**. See the [LICENSE](LICENSE) file for more details.

---
*Built with ❤️ using pure, dependency-free PHP.*
```

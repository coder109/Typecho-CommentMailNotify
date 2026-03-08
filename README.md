[中文](README.zh-cn.md) | **English**

---

# CommentMailNotify

A Typecho plugin that sends email notifications via SMTP when a new comment is posted or when a comment receives a reply.

- **Compatible with**: Typecho 1.3.0
- **Features**: Notify site owner on new comments; notify the parent comment author on reply (optional: only after comment is approved)

---

## Features

- **Site owner notification**: When a new comment is posted, sends an email to configured recipient(s) with post title, commenter name, email, IP, comment link, and content.
- **Reply notification**: If the comment has a parent (is a reply), sends an email to the parent commenter’s address (“Your comment has a new reply”) with original and reply text; does not send when someone replies to their own comment.
- **Optional “Notify only approved comments”**: Recommended to avoid emails for pending or spam comments. If you moderate all comments, consider turning this off so you still receive notifications.
- **SMTP**: Supports SSL/TLS, custom From address/name, and debug mode (errors written to PHP error_log).

---

## Requirements

- Typecho 1.3.0
- PHP 5.5+ (same as PHPMailer)
- A working SMTP service (e.g. QQ Mail, Gmail, Office365)

---

## Installation

1. Put the whole `CommentMailNotify` folder under Typecho’s `usr/plugins/` so that you have:
   ```
   usr/plugins/CommentMailNotify/Plugin.php
   usr/plugins/CommentMailNotify/lib/PHPMailer/...
   ```
2. In the admin panel: **Dashboard** → **Plugins**, find **CommentMailNotify** and click **Enable**.
3. Click **Settings** and fill in SMTP and recipient options, then save.

---

## Configuration

| Option | Description |
|--------|--------------|
| Recipient email(s) | Who receives “new comment” notifications. Use comma or semicolon for multiple addresses. |
| SMTP host | e.g. `smtp.qq.com`, `smtp.gmail.com`, `smtp.office365.com`. |
| SMTP port | Usually 465 (SSL) or 587 (TLS). |
| Encryption | ssl / tls / none; should match your port. |
| SMTP username / password | Usually your sender email and SMTP auth code or app password. |
| From email / From name | Optional; defaults to SMTP username and a default name. |
| Notify only for approved comments | Recommended on. If you require approval for all comments, turn off so you still get notified. |
| Debug mode | When on, detailed SMTP errors are written to PHP error_log. |

---

## About PHPMailer (lib) and licensing

This plugin uses [PHPMailer](https://github.com/PHPMailer/PHPMailer) for sending email. The files under `lib/PHPMailer/` are PHPMailer source code under the **LGPL-2.1** license. The plugin only loads `Exception.php`, `PHPMailer.php`, and `SMTP.php`, does not modify them, and keeps their copyright and license headers.

- **Legal**: Distributing PHPMailer with the plugin while keeping its notices and stating its use (e.g. in this README) complies with LGPL-2.1 and does not constitute infringement.
- **Bundled vs submodule**:
  - **Bundled (current)**: Users can download or clone and use the plugin without `git submodule update`; common for end-user plugins.
  - **Submodule**: Clear dependency boundary and easier PHPMailer updates; users need `git clone --recursive` or `git submodule update --init`.

**Recommendation**: For end-user distribution, keep the current bundled form and mention PHPMailer (LGPL-2.1) in the README and release notes.

---

## License

The plugin’s own code (CommentMailNotify) may be released under the license you choose.  
Files under `lib/PHPMailer/` are under [PHPMailer’s LGPL-2.1 license](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html).

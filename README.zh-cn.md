**中文** | [English](README.md)

---

# CommentMailNotify

Typecho 插件：当有新评论或评论被回复时，通过 SMTP 发送邮件通知。

- **适用版本**：Typecho 1.3.0
- **功能**：评论提交后通知站长；评论被回复时通知被回复者（可选仅审核通过后通知）

---

## 功能说明

- **站长通知**：新评论产生时，向配置的收件人（可多个）发送邮件，包含文章标题、评论者、邮箱、IP、评论链接与内容。
- **回复通知**：若评论有父级（回复），向父级评论者邮箱发送“您的评论有了新回复”，包含原文与回复内容；自己回复自己不会发信。
- **可选“仅审核通过后通知”**：建议开启，避免待审核/垃圾评论也触发邮件。不过，如果开启了所有评论审核，建议关闭，否则会导致无法收到邮件通知。
- **SMTP 配置**：支持 SSL/TLS、自定义发件人、调试模式（错误输出到 PHP error_log）。

---

## 环境要求

- Typecho 1.3.0
- PHP 5.5+（与 PHPMailer 要求一致）
- 可用的 SMTP 服务（如 QQ 邮箱、Gmail、Office365 等）

---

## 安装

1. 将整个 `CommentMailNotify` 目录放到 Typecho 的 `usr/plugins/` 下，保证路径为：
   ```
   usr/plugins/CommentMailNotify/Plugin.php
   usr/plugins/CommentMailNotify/lib/PHPMailer/...
   ```
2. 后台 → **控制台** → **插件**，找到 **CommentMailNotify**，点击「启用」。
3. 点击「设置」，填写 SMTP 与收件人等信息并保存。

---

## 配置项

| 配置项 | 说明 |
|--------|------|
| 收件人邮箱 | 接收“新评论”通知的邮箱，多个用逗号或分号分隔。 |
| SMTP 主机 | 如 `smtp.qq.com`、`smtp.gmail.com`、`smtp.office365.com`。 |
| SMTP 端口 | 常见为 465（SSL）或 587（TLS）。 |
| 加密方式 | ssl / tls / 无，需与端口对应。 |
| SMTP 用户名 / 密码 | 一般为发信邮箱和授权码（或应用专用密码）。 |
| 发件人邮箱 / 发件人名称 | 可选；不填则使用 SMTP 用户名和默认名称。 |
| 仅审核通过的评论才通知 | 建议开启，避免垃圾评论触发邮件。不过，如果开启了所有评论审核，建议关闭，否则会导致无法收到邮件通知。 |
| 调试模式 | 开启后 SMTP 详细错误会写入 PHP error_log，便于排查。 |

---

## PHPMailer

本插件使用 [PHPMailer](https://github.com/PHPMailer/PHPMailer) 发送邮件，`lib/PHPMailer/` 下文件遵循 [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html)。

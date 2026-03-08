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

## 关于 PHPMailer（lib 目录）与版权

本插件使用 [PHPMailer](https://github.com/PHPMailer/PHPMailer) 发送邮件，`lib/PHPMailer/` 下为 PHPMailer 源码，采用 **LGPL-2.1** 许可证。插件仅按需加载 `Exception.php`、`PHPMailer.php`、`SMTP.php`，未修改其代码，并保留其文件头中的版权与许可声明。

- **是否侵权**：在保留 PHPMailer 的版权与许可声明、并注明使用 PHPMailer（如本 README）的前提下，将 PHPMailer 与插件一起分发是符合 LGPL-2.1 的，不构成侵权。
- **Submodule 还是当前形式**：
  - **当前形式（直接包含 lib/PHPMailer）**：用户下载/克隆后即可使用，无需执行 `git submodule update`，对插件使用者更友好，是常见做法。
  - **Submodule**：依赖关系清晰、便于跟踪 PHPMailer 上游更新，但用户需 `git clone --recursive` 或单独执行 `git submodule update --init`，对非开发者稍不友好。

**建议**：若主要面向最终用户分发插件，保持当前“直接包含 lib”的方式即可，并在 README 与发布说明中注明使用了 PHPMailer（LGPL-2.1）。若仓库主要给开发者协作、且希望严格区分“本仓库代码”与“第三方依赖”，可改为 submodule，并在 README 中说明克隆后需初始化 submodule。

---

## 许可证

本插件（CommentMailNotify 自身代码）可采用你选择的开源协议发布。  
`lib/PHPMailer/` 下的文件遵循 [PHPMailer 的 LGPL-2.1 许可证](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html)。

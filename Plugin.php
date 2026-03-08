<?php

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * CommentMailNotify
 * Send email notification when a new comment arrives or a comment gets a reply.
 */
class CommentMailNotify_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array(__CLASS__, 'onFinishComment');
        return _t('CommentMailNotify activated');
    }

    public static function deactivate()
    {
        return _t('CommentMailNotify deactivated');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $to = new Typecho_Widget_Helper_Form_Element_Text(
            'toEmail',
            null,
            '',
            _t('Recipient Email'),
            _t('Use comma or semicolon to separate multiple recipients.')
        );
        $form->addInput($to->addRule('required', _t('Recipient email is required')));

        $smtpHost = new Typecho_Widget_Helper_Form_Element_Text(
            'smtpHost',
            null,
            'smtp.qq.com',
            _t('SMTP Host'),
            _t('Example: smtp.qq.com / smtp.gmail.com / smtp.office365.com')
        );
        $form->addInput($smtpHost->addRule('required', _t('SMTP host is required')));

        $smtpPort = new Typecho_Widget_Helper_Form_Element_Text(
            'smtpPort',
            null,
            '465',
            _t('SMTP Port'),
            _t('Common values: 465 (SSL), 587 (TLS)')
        );
        $form->addInput($smtpPort->addRule('required', _t('SMTP port is required')));

        $smtpSecure = new Typecho_Widget_Helper_Form_Element_Select(
            'smtpSecure',
            array(
                'ssl' => 'ssl (usually 465)',
                'tls' => 'tls (usually 587)',
                '' => 'none'
            ),
            'ssl',
            _t('Encryption'),
            _t('Choose according to your SMTP provider settings')
        );
        $form->addInput($smtpSecure);

        $smtpUser = new Typecho_Widget_Helper_Form_Element_Text(
            'smtpUser',
            null,
            '',
            _t('SMTP Username'),
            _t('Usually your full sender email address')
        );
        $form->addInput($smtpUser->addRule('required', _t('SMTP username is required')));

        $smtpPass = new Typecho_Widget_Helper_Form_Element_Password(
            'smtpPass',
            null,
            '',
            _t('SMTP Password / App Password'),
            _t('Use SMTP authorization code / app password, not mailbox login password.')
        );
        $form->addInput($smtpPass->addRule('required', _t('SMTP password is required')));

        $fromEmail = new Typecho_Widget_Helper_Form_Element_Text(
            'fromEmail',
            null,
            '',
            _t('From Email (optional)'),
            _t('If empty, smtpUser will be used.')
        );
        $form->addInput($fromEmail);

        $fromName = new Typecho_Widget_Helper_Form_Element_Text(
            'fromName',
            null,
            'Typecho',
            _t('From Name'),
            _t('Display name shown in the email sender.')
        );
        $form->addInput($fromName);

        $onlyApproved = new Typecho_Widget_Helper_Form_Element_Radio(
            'onlyApproved',
            array(
                '1' => _t('Yes'),
                '0' => _t('No')
            ),
            '1',
            _t('Only notify approved comments'),
            _t('Recommended: enable this to avoid spam notifications.')
        );
        $form->addInput($onlyApproved);

        $debug = new Typecho_Widget_Helper_Form_Element_Radio(
            'debug',
            array(
                '0' => _t('Off'),
                '1' => _t('On')
            ),
            '0',
            _t('Debug Mode'),
            _t('When enabled, detailed SMTP errors will be written to PHP error_log.')
        );
        $form->addInput($debug);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * Triggered when a new comment is submitted.
     * For Widget_Feedback, Typecho passes the widget object itself.
     */
    public static function onFinishComment($feedback)
    {
        $comment = self::extractCommentData($feedback);
        if (!is_array($comment)) {
            self::log('Skip notify: cannot extract comment data.', null);
            return;
        }

        self::handleNotifications($comment);
    }

    /**
     * Legacy compatibility callback.
     * Old hook registrations may still call this method on comment approve.
     * Intentionally no-op: "comment approved" email has been disabled.
     */
    public static function onMarkComment($comment, $widget, $status)
    {
        return;
    }

    private static function handleNotifications(array $comment)
    {
        $opt = Typecho_Widget::widget('Widget_Options')->plugin('CommentMailNotify');

        // Keep this behavior: if enabled, only notify after approval.
        if (!empty($opt->onlyApproved) && ($comment['status'] ?? '') !== 'approved') {
            return;
        }

        $missing = self::validateSmtpConfig($opt);
        if (!empty($missing)) {
            self::log('Skip notify: missing config -> ' . implode(', ', $missing), $opt);
            return;
        }

        if (!self::loadPhpMailer($opt)) {
            return;
        }

        $cid = (int)($comment['cid'] ?? 0);
        $coid = (int)($comment['coid'] ?? 0);
        if ($cid <= 0) {
            self::log('Skip notify: invalid cid.', $opt);
            return;
        }

        $db = Typecho_Db::get();
        $post = $db->fetchRow($db->select()->from('table.contents')->where('cid = ?', $cid)->limit(1));
        $title = $post['title'] ?? '(Unknown Post)';

        $context = array(
            'cid' => $cid,
            'coid' => $coid,
            'parent' => (int)($comment['parent'] ?? 0),
            'title' => $title,
            'link' => self::buildCommentLink($cid, $coid),
            'author' => (string)($comment['author'] ?? '(Anonymous)'),
            'authorEmail' => trim((string)($comment['mail'] ?? '')),
            'ip' => (string)($comment['ip'] ?? ''),
            'text' => (string)($comment['text'] ?? ''),
            'siteTitle' => (string)Typecho_Widget::widget('Widget_Options')->title
        );

        self::notifySiteOwnerOnNewComment($opt, $context);
        self::notifyParentOnReply($opt, $context);
    }

    private static function notifySiteOwnerOnNewComment($opt, array $context)
    {
        $recipients = self::splitEmails((string)$opt->toEmail);
        if (empty($recipients)) {
            self::log('Skip owner notify: toEmail is empty.', $opt);
            return;
        }

        $subject = sprintf('[%s] New comment: %s', $context['siteTitle'], $context['title']);
        $body =
            "Post: {$context['title']}\n" .
            "Author: {$context['author']}\n" .
            "Email: {$context['authorEmail']}\n" .
            "IP: {$context['ip']}\n" .
            "Link: {$context['link']}\n\n" .
            "Content:\n{$context['text']}\n";

        self::sendMail(
            $opt,
            $recipients,
            $subject,
            $body,
            $context['authorEmail'],
            $context['author']
        );
    }

    private static function notifyParentOnReply($opt, array $context)
    {
        if ($context['parent'] <= 0) {
            return;
        }

        $db = Typecho_Db::get();
        $parent = $db->fetchRow(
            $db->select('coid', 'author', 'mail', 'text')
                ->from('table.comments')
                ->where('coid = ?', (int)$context['parent'])
                ->limit(1)
        );

        if (!is_array($parent)) {
            return;
        }

        $parentEmail = trim((string)($parent['mail'] ?? ''));
        if (!self::isValidEmail($parentEmail)) {
            return;
        }

        if (
            $context['authorEmail'] !== '' &&
            strcasecmp($parentEmail, $context['authorEmail']) === 0
        ) {
            // Do not notify users about replies to themselves.
            return;
        }

        $parentAuthor = (string)($parent['author'] ?? 'there');
        $parentText = trim((string)($parent['text'] ?? ''));
        $subject = sprintf('[%s] Your comment has a new reply: %s', $context['siteTitle'], $context['title']);

        $body =
            "Hi {$parentAuthor},\n\n" .
            "Someone replied to your comment.\n\n" .
            "Post: {$context['title']}\n" .
            "Replier: {$context['author']}\n" .
            "Reply Link: {$context['link']}\n\n" .
            "Your comment:\n{$parentText}\n\n" .
            "New reply:\n{$context['text']}\n";

        self::sendMail(
            $opt,
            array($parentEmail),
            $subject,
            $body,
            $context['authorEmail'],
            $context['author']
        );
    }

    private static function sendMail($opt, array $recipients, $subject, $body, $replyToEmail, $replyToName)
    {
        $targets = array();
        foreach ($recipients as $recipient) {
            $email = trim((string)$recipient);
            if ($email === '' || !self::isValidEmail($email)) {
                continue;
            }
            $targets[strtolower($email)] = $email;
        }

        if (empty($targets)) {
            self::log('Skip send: no valid recipient.', $opt);
            return false;
        }

        $mail = new PHPMailer(true);
        $smtpDebugLog = array();

        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = (string)$opt->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = (string)$opt->smtpUser;
            $mail->Password = (string)$opt->smtpPass;
            $mail->Port = (int)$opt->smtpPort;
            $mail->Timeout = 20;

            $secure = strtolower(trim((string)$opt->smtpSecure));
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
            }

            if (!empty($opt->debug)) {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = function ($str, $level) use (&$smtpDebugLog) {
                    $smtpDebugLog[] = '[SMTP:' . $level . '] ' . trim($str);
                };
            }

            $siteTitle = (string)Typecho_Widget::widget('Widget_Options')->title;
            $fromEmail = trim((string)$opt->fromEmail);
            if ($fromEmail === '') {
                $fromEmail = (string)$opt->smtpUser;
            }
            $fromName = trim((string)$opt->fromName);
            if ($fromName === '') {
                $fromName = $siteTitle !== '' ? $siteTitle : 'Typecho';
            }

            $mail->setFrom($fromEmail, $fromName);
            foreach ($targets as $target) {
                $mail->addAddress($target);
            }

            if (self::isValidEmail((string)$replyToEmail)) {
                $mail->addReplyTo((string)$replyToEmail, (string)$replyToName);
            }

            $mail->Subject = (string)$subject;
            $mail->Body = (string)$body;
            $mail->isHTML(false);

            $mail->send();
            self::log('Mail sent: ' . (string)$subject . ' => ' . implode(',', $targets), $opt);
            return true;
        } catch (PHPMailerException $e) {
            self::log('Send failed: ' . $e->getMessage(), $opt);
            if (!empty($smtpDebugLog)) {
                self::log(implode(' | ', $smtpDebugLog), $opt);
            }
        } catch (Throwable $e) {
            self::log('Unexpected error: ' . $e->getMessage(), $opt);
        }

        return false;
    }

    private static function validateSmtpConfig($opt)
    {
        $missing = array();

        if (trim((string)$opt->smtpHost) === '') {
            $missing[] = 'smtpHost';
        }
        if (trim((string)$opt->smtpUser) === '') {
            $missing[] = 'smtpUser';
        }
        if (trim((string)$opt->smtpPass) === '') {
            $missing[] = 'smtpPass';
        }
        if ((int)$opt->smtpPort <= 0) {
            $missing[] = 'smtpPort';
        }

        return $missing;
    }

    private static function isValidEmail($email)
    {
        return filter_var((string)$email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function extractCommentData($input)
    {
        if (is_array($input)) {
            return $input;
        }

        if (is_object($input)) {
            if (isset($input->comment)) {
                if (is_array($input->comment)) {
                    return $input->comment;
                }
                if (is_object($input->comment)) {
                    return (array)$input->comment;
                }
            }

            $fields = array('coid', 'cid', 'status', 'author', 'mail', 'ip', 'text', 'parent', 'created', 'url');
            $data = array();

            foreach ($fields as $field) {
                try {
                    $value = $input->$field;
                } catch (Throwable $e) {
                    $value = null;
                }

                if ($value !== null) {
                    $data[$field] = $value;
                }
            }

            if (!empty($data)) {
                return $data;
            }
        }

        return null;
    }

    private static function buildCommentLink($cid, $coid)
    {
        $link = '';

        try {
            $archive = Typecho_Widget::widget('Widget_Archive@cmn_' . (int)$cid, 'cid=' . (int)$cid);
            $link = (string)$archive->permalink;
        } catch (Throwable $e) {
            $link = '';
        }

        if ($link === '') {
            $link = (string)Typecho_Widget::widget('Widget_Options')->siteUrl;
        }

        if ($coid > 0) {
            $link .= '#comment-' . (int)$coid;
        }

        return $link;
    }

    private static function loadPhpMailer($opt)
    {
        $base = rtrim(__DIR__, '/\\') . '/lib/PHPMailer/';
        $files = array(
            'Exception.php',
            'PHPMailer.php',
            'SMTP.php'
        );

        foreach ($files as $file) {
            $fullPath = $base . $file;
            if (!is_file($fullPath)) {
                self::log('PHPMailer file not found: ' . $fullPath, $opt);
                return false;
            }
            require_once $fullPath;
        }

        return true;
    }

    private static function splitEmails($toEmail)
    {
        $items = preg_split('/[\s,;]+/', (string)$toEmail, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($items) ? $items : array();
    }

    private static function log($msg, $opt)
    {
        if ($opt !== null && empty($opt->debug)) {
            return;
        }

        error_log('[CommentMailNotify] ' . $msg);
    }
}


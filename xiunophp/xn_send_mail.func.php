<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * 发送邮件 (PHPMailer 6.x Wrapper)
 * 
 * @param array  $smtp      SMTP配置 array('host'=>'smtp.qq.com', 'port'=>465, 'user'=>'xxx', 'pass'=>'xxx', 'email'=>'xxx')
 * @param string $username  发件人名称
 * @param string $email     收件人邮箱
 * @param string $subject   邮件标题
 * @param string $message   邮件内容(HTML)
 * @param string $charset   字符集
 * @return bool|array       成功返回 TRUE，失败返回 array('code'=>-1, 'message'=>'错误信息')
 */
function xn_send_mail($smtp, $username, $email, $subject, $message, $charset = 'UTF-8') {
    // 确保 PHPMailer 类已加载 (通过 Composer)
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return xn_error(-1, 'PHPMailer class not found. Please run "composer install".');
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;   // Enable verbose debug output
        $mail->isSMTP();                            // Send using SMTP
        $mail->Host       = $smtp['host'];          // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                   // Enable SMTP authentication
        $mail->Username   = $smtp['user'];          // SMTP username
        $mail->Password   = $smtp['pass'];          // SMTP password
        
        // Port & Encryption Logic
        $mail->Port       = $smtp['port'];
        if ($smtp['port'] == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtp['port'] == 587) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Default fallback
            $mail->SMTPAutoTLS = false; // Disable AutoTLS for non-standard ports to avoid errors
        }

        $mail->CharSet = $charset;

        // Recipients
        // $smtp['email'] 是发件人邮箱地址
        $mail->setFrom($smtp['email'], $username);
        $mail->addAddress($email);     // Add a recipient
        $mail->addReplyTo($smtp['email'], $username);

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message); // Plain text version

        $mail->send();
        return TRUE;
    } catch (Exception $e) {
        return xn_error(-1, "Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

?>

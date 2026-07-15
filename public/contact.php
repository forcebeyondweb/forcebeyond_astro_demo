<?php
// 🚀 核心修改 1：关闭任何非致命的 PHP 提示，防止 HostGator 的 Warning 污染你的 JSON 输出
error_reporting(0);
ini_set('display_errors', 0);

// 🚀 核心修改 2：彻底放开握手限制，并在第一行声明我们返回的是干净的 JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit(0);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Load PHPMailer files Relatively
require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

// 2. Guard: Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // 如果是开发联调，不要返回死板的 HTML 页面，全部用标准的 JSON 回应前端
    echo json_encode(["success" => false, "error" => "Method Not Allowed. Must be POST."]);
    exit;
}

// 3. Sanitize and Collect All B2B Form Fields
$firstName   = htmlspecialchars(strip_tags(trim($_POST["first-name"] ?? '')));
$lastName    = htmlspecialchars(strip_tags(trim($_POST["last-name"] ?? '')));
$jobTitle    = htmlspecialchars(strip_tags(trim($_POST["job-title"] ?? '')));
$companyName = htmlspecialchars(strip_tags(trim($_POST["company-name"] ?? '')));

$address1    = htmlspecialchars(strip_tags(trim($_POST["address-line-1"] ?? '')));
$address2    = htmlspecialchars(strip_tags(trim($_POST["address-line-2"] ?? '')));
$city        = htmlspecialchars(strip_tags(trim($_POST["city"] ?? '')));
$state       = htmlspecialchars(strip_tags(trim($_POST["state-region"] ?? '')));
$postalCode  = htmlspecialchars(strip_tags(trim($_POST["postal-code"] ?? '')));
$country     = htmlspecialchars(strip_tags(trim($_POST["country"] ?? '')));

$email       = filter_var(trim($_POST["email"] ?? ''), FILTER_VALIDATE_EMAIL);
$phone       = htmlspecialchars(strip_tags(trim($_POST["phone"] ?? '')));
$leadSource  = htmlspecialchars(strip_tags(trim($_POST["marketing-source"] ?? '')));
$subject     = htmlspecialchars(strip_tags(trim($_POST["subject"] ?? 'Inquiry from Webform')));
$userMessage = htmlspecialchars(strip_tags(trim($_POST["message"] ?? '')));

// Validation check for core requirements
if (!$firstName || !$lastName || !$email || !$companyName || !$phone) {
    echo json_encode(["success" => false, "error" => "Missing required corporate fields."]);
    exit;
}

$fullName = $firstName . ' ' . $lastName;

// 4. Initialize PHPMailer
$mail = new PHPMailer(true);

try {
    // --- Load private SMTP config ---
    $configPath = __DIR__ . '/mail-config.php';

    if (!file_exists($configPath)) {
        throw new Exception('Mail configuration file is missing.');
    }

    $config = require $configPath;

    // --- Server Settings ---
    $mail->isSMTP();
    $mail->Host       = $config['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['SMTP_USERNAME'];
    $mail->Password   = $config['SMTP_PASSWORD'];
    $mail->Port       = (int) $config['SMTP_PORT'];

    if ($mail->Port === 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else if ($mail->Port === 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->CharSet = 'UTF-8';

    // --- EMAIL 1: Detailed Notification to Sales Team ---
    $mail->setFrom($mail->Username, 'www.forcebeyond.com');
    $mail->addAddress('oscar.wang@forcebeyond.com');            
    $mail->addReplyTo($email, $fullName);                 

    $mail->isHTML(true);
    $mail->Subject = "New Quote Request: " . $subject;
    
    $mail->Body    = "
        <h2 style='color: #ea580c;'>New RFQ / B2B Web Inquiry</h2>
        <hr style='border: 1px solid #e2e8f0;' />
        <h3>Primary Contact Info</h3>
        <p><b>Name:</b> {$fullName}</p>
        <p><b>Job Title:</b> {$jobTitle}</p>
        <p><b>Company:</b> {$companyName}</p>
        <p><b>Email:</b> {$email}</p>
        <p><b>Phone:</b> {$phone}</p>
        
        <h3>Company Address</h3>
        <p>
            {$address1}<br />
            " . ($address2 ? $address2 . "<br />" : "") . "
            {$city}, {$state} {$postalCode}<br />
            <b>Country:</b> {$country}
        </p>

        <h3>Marketing Metadata</h3>
        <p><b>How did they hear about us:</b> {$leadSource}</p>
        
        <hr style='border: 1px solid #e2e8f0;' />
        <h3>Message / Project Scope Details:</h3>
        <p style='white-space: pre-wrap; background-color: #f8fafc; padding: 15px; border-left: 4px solid #ea580c;'>{$userMessage}</p>
    ";
    
    $mail->send();

    // --- EMAIL 2: Professional Auto-Reply Confirmation to Customer ---
    $mail->clearAddresses();
    $mail->clearReplyTos();
    
    $mail->setFrom($mail->Username, 'www.forcebeyond.com');
    $mail->addAddress($email, $fullName);                        
    $mail->addReplyTo('oscar.wang@forcebeyond.com', 'ForceBeyond Sales'); 

    $mail->Subject = "Thank you for contacting ForceBeyond";
    
    // 🚀 完全对齐经典款样式
    $mail->Body    = "
        <p>Dear {$fullName},</p>
        <p>Thank you very much for contacting us. The following is what we received:</p>
        <p>------</p>
        
        <p>Your Name (required)<br /><b>{$fullName}</b></p>
        <p>Your Job Title (required)<br /><b>{$jobTitle}</b></p>
        <p>Your Company Name (required)<br /><b>{$companyName}</b></p>
        
        <p>Your Address (required)<br />
        <b>
            {$address1}<br />
            " . ($address2 ? $address2 . "<br />" : "") . "
            {$city}, {$state} {$postalCode}<br />
            {$country}
        </b></p>
        
        <p>Your Email (required)<br /><b>{$email}</b></p>
        <p>Your Phone (Required)<br /><b>{$phone}</b></p>
        <p>How Did You Hear About Us? (required)<br /><b>{$leadSource}</b></p>
        <p>Subject (required)<br /><b>{$subject}</b></p>
        
        <p>Your Message<br />
        <span style='white-space: pre-wrap;'><b>{$userMessage}</b></span></p>
        
        <p>------</p>
        <p>We greatly appreciate your opportunities. We will contact you back very soon.</p>
        <p>Looking forward to serving you!</p>
        <p>Best Regards,</p>
        <p><b>ForceBeyond</b><br /><a href='http://www.forcebeyond.com'>http://www.forcebeyond.com</a></p>
    ";

    $mail->send();

    // 🚀 确保没有多余干扰，只吐出完美的 JSON 成功标识
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    // 捕获邮件底层抛出的错误并化作标准 JSON，防止页面崩溃引发网络错误
    echo json_encode(["success" => false, "error" => "Mailer Error: {$mail->ErrorInfo}"]);
}
?>
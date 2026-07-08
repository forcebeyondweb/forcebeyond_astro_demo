<?php
// 1. 彻底允许浏览器跨越目录和域名的安全握手
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Credentials: true");

// 2. 拦截并秒回浏览器的 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit(0);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 3. 拦截非 POST 的直接访问
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 405 Method Not Allowed");
    exit("Method Not Allowed");
}

header("Content-Type: application/json; charset=UTF-8");

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';


// ==================================================================
// 📥 获取并清洗前端传来的所有 B2B RFQ 核心字段
// ==================================================================
$firstName   = htmlspecialchars(strip_tags(trim($_POST["first-name"] ?? '')));
$lastName    = htmlspecialchars(strip_tags(trim($_POST["last-name"] ?? '')));
$jobTitle    = htmlspecialchars(strip_tags(trim($_POST["job-title"] ?? '')));
$organization= htmlspecialchars(strip_tags(trim($_POST["organization"] ?? ''))); 

$address1    = htmlspecialchars(strip_tags(trim($_POST["address-line-1"] ?? 'Not provided')));
$address2    = htmlspecialchars(strip_tags(trim($_POST["address-line-2"] ?? '')));
$city        = htmlspecialchars(strip_tags(trim($_POST["city"] ?? 'Not provided')));
$state       = htmlspecialchars(strip_tags(trim($_POST["state"] ?? 'Not provided')));
$postalCode  = htmlspecialchars(strip_tags(trim($_POST["postal-code"] ?? 'Not provided')));
$country     = htmlspecialchars(strip_tags(trim($_POST["country"] ?? 'Not provided')));

$email       = filter_var(trim($_POST["email"] ?? ''), FILTER_VALIDATE_EMAIL);
$phone       = htmlspecialchars(strip_tags(trim($_POST["phone"] ?? '')));
$leadSource  = htmlspecialchars(strip_tags(trim($_POST["marketing-source"] ?? 'Unspecified')));

// 项目上下文高级核心字段映射
$interest    = htmlspecialchars(strip_tags(trim($_POST["project-interest"] ?? 'Not specified')));
$budget      = htmlspecialchars(strip_tags(trim($_POST["annual-budget"] ?? 'Not specified')));

// 核心数据完整性防护
if (!$firstName || !$lastName || !$email || !$organization || !$phone) {
    echo json_encode(["success" => false, "error" => "Required validation fields are empty."]);
    exit;
}

$fullName = $firstName . ' ' . $lastName;

// ==================================================================
// 🛡️ 后端文件拦截与深度过滤防线
// ==================================================================
$allowedExtensions = ['pdf', 'dwg', 'dxf', 'step', 'stp', 'iges', 'igs', 'sldprt', 'zip', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$maxFileSize = 25 * 1024 * 1024; // 25MB

if (isset($_FILES['engineering-assets'])) {
    $files = $_FILES['engineering-assets'];
    foreach ($files['name'] as $key => $name) {
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            $fileNameLower = strtolower($name);
            
            if (preg_match('/\.(php|js|sh|exe|pl|py)/', $fileNameLower)) {
                echo json_encode(["success" => false, "error" => "Security exception: Execution script pattern intercepted."]);
                exit;
            }

            $ext = pathinfo($fileNameLower, PATHINFO_EXTENSION);
            if (!in_array($ext, $allowedExtensions)) {
                echo json_encode(["success" => false, "error" => "Extension rejected: '." . $ext . "' is unauthorized."]);
                exit;
            }

            if ($files['size'][$key] > $maxFileSize) {
                echo json_encode(["success" => false, "error" => "Payload cap breached by file: " . $name]);
                exit;
            }
        }
    }
}

// ==================================================================
// 📧 PHPMailer 事务处理并开始构造邮件
// ==================================================================
$mail = new PHPMailer(true);

try {
    // --- Server Settings ---
    $mail->isSMTP();
    $mail->Host       = 'REMOVED_SMTP_HOST';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'REMOVED_SMTP_USERNAME';         
    $mail->Password   = 'REMOVED_SMTP_PASSWORD';      
    $mail->Port       = 465;                                  
    
    if ($mail->Port == 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else if ($mail->Port == 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    // --- 将选中的图纸及Word资产绑定至内存 ---
    if (isset($_FILES['engineering-assets'])) {
        $files = $_FILES['engineering-assets'];
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $mail->addAttachment($files['tmp_name'][$key], $name);
            }
        }
    }

    // --- EMAIL 1: Detailed Notification to Sales Team ---
    $mail->setFrom('REMOVED_SMTP_USERNAME', 'ForceBeyond Website');
    $mail->addAddress('oscar.wang@forcebeyond.com'); 
    $mail->addReplyTo($email, $fullName);                 
    
    $mail->Subject = "New RFQ Request from " . $organization . " (" . $fullName . ")";
    
    $mail->Body    = "
        <h2 style='color: #ea580c; font-family: sans-serif; margin-bottom: 20px;'>New RFQ Received (Internal Notification)</h2>
        <p>The following corporate inquiry details were submitted through the website:</p>
        <p style='color: #cbd5e1;'>--------------------------------------------------</p>
        
        <p><b>Lead Name:</b><br />{$fullName}</p>
        <p><b>Job Title:</b><br />{$jobTitle}</p>
        <p><b>Company Name:</b><br />{$organization}</p>
        
        <p><b>Project Interest:</b><br /><span style='color: #ea580c; font-weight: bold;'>{$interest}</span></p>
        <p><b>Annual Budget:</b><br />{$budget}</p>
        
        <p><b>Lead Address:</b><br />
        <b>
            {$address1}<br />
            " . ($address2 ? $address2 . "<br />" : "") . "
            {$city}, {$state} {$postalCode}<br />
            {$country}
        </b></p>
        
        <p><b>Lead Email:</b><br /><a href='mailto:{$email}'>{$email}</a></p>
        <p><b>Lead Phone:</b><br />{$phone}</p>
        <p><b>How Did They Hear About Us?</b><br />{$leadSource}</p>
        
        <p style='margin-top: 20px;'><b>Attached Engineering Assets:</b><br />
        <span style='color: #475569; font-size: 13px;'>The uploaded blueprint/drawing/word files have been attached directly to this email.</span></p>
        
        <p style='color: #cbd5e1;'>--------------------------------------------------</p>
        <p><i>This is an automated notification generated by the web server. Please click 'Reply' directly to contact the lead at {$email}.</i></p>
    ";
    
    $mail->send(); 

    // ==================================================================
    // --- EMAIL 2: Copy Back to Customer ---
    // ==================================================================
    $mail->clearAddresses();
    $mail->clearReplyTos();
    
    $mail->addAddress($email, $fullName);                        
    $mail->addReplyTo('oscar.wang@forcebeyond.com', 'www.forcebeyond.com'); 

    $mail->Subject = "Thank you for your RFQ - ForceBeyond";
    
    $mail->Body    = "
        <div style='font-family: sans-serif; color: #334155; line-height: 1.6;'>
            <p>Dear {$fullName},</p>
            <p>Thank you very much for contacting ForceBeyond. We have safely received your Request for Quote (RFQ) along with your engineering assets.</p>
            <p>Our technical engineering team will review your specifications and get back to you within 24 business hours.</p>
            
            <p>For your records, here is a summary of the details you submitted:</p>
            <p style='color: #cbd5e1;'>--------------------------------------------------</p>
            
            <p><b>Your Name:</b> {$fullName}</p>
            <p><b>Your Job Title:</b> {$jobTitle}</p>
            <p><b>Company Name:</b> {$organization}</p>
            <p><b>Interested In:</b> {$interest}</p>
            <p><b>Annual Budget:</b> {$budget}</p>
            <p><b>Your Phone:</b> {$phone}</p>
            
            <p style='margin-top: 15px;'><b>Attached Engineering Assets:</b><br />
            <span style='color: #64748b; font-size: 13px;'>A copy of your uploaded documents has been attached to this email confirmation.</span></p>
            
            <p style='color: #cbd5e1;'>--------------------------------------------------</p>
            <p>We greatly appreciate this project opportunity and look forward to partnering with you!</p>
            
            <p style='margin-top: 25px;'>Best Regards,</p>
            <p><b>ForceBeyond Team</b><br />
            <a href='https://www.forcebeyond.com' style='color: #ea580c; text-decoration: none;'>www.forcebeyond.com</a></p>
        </div>
    ";

    $mail->send(); 

    $mail->clearAttachments();
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "SMTP Mailer Error: {$mail->ErrorInfo}"]);
}
?>
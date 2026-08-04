<?php
// Allow browser requests and CORS preflight handling
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Credentials: true");

// Respond to OPTIONS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit(0);
}

require_once __DIR__ . '/turnstile-verify.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 405 Method Not Allowed");
    exit("Method Not Allowed");
}

verifyHoneypot();
verifyTurnstile('rfq-form');

header("Content-Type: application/json; charset=UTF-8");

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';


// Collect and sanitize RFQ form fields
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
$leadSource  = htmlspecialchars(strip_tags(trim($_POST["marketing-source"] ?? '')));

// Project context fields
$interest    = htmlspecialchars(strip_tags(trim($_POST["project-interest"] ?? '')));
$budget      = htmlspecialchars(strip_tags(trim($_POST["annual-budget"] ?? '')));

// Validate required fields
if (!$firstName || !$lastName || !$email || !$organization || !$leadSource || !$interest || !$budget || !$phone) {
    echo json_encode(["success" => false, "error" => "Required validation fields are empty."]);
    exit;
}

$fullName = $firstName . ' ' . $lastName;

// Validate uploaded engineering files
$allowedExtensions = ['pdf', 'dwg', 'dxf', 'step', 'stp', 'iges', 'igs', 'sldprt', 'zip', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$maxFileSize = 25 * 1024 * 1024; // 25 MB
$attachmentCount = 0;

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

            $attachmentCount++;
        }
    }
}


$attachmentMessageForSales = $attachmentCount > 0
    ? 'The uploaded document is attached to this email.'
    : 'There is no attachment.';

$attachmentMessageForCustomer = $attachmentCount > 0
    ? 'A copy of your uploaded document is attached to this email confirmation.'
    : 'No engineering document was attached to your submission.';

// Initialize PHPMailer and send messages
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
    $mail->isHTML(true);

    // Attach uploaded engineering files
    if (isset($_FILES['engineering-assets'])) {
        $files = $_FILES['engineering-assets'];
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $mail->addAttachment($files['tmp_name'][$key], $name);
            }
        }
    }

    // --- EMAIL 1: Detailed Notification to Sales Team ---
    $mail->setFrom('contact@forcebeyond.com', 'www.forcebeyond.com');
    $mail->addAddress('contact@forcebeyond.com'); 
    $mail->addReplyTo($email, $fullName);                 
    
    $mail->Subject = "www.forcebeyond.com - " . $organization;
    
    $mail->Body    = "
        <p>This person sent a RFQ from our website <a href='https://www.forcebeyond.com'>https://www.forcebeyond.com</a>.</p>
        <p>------</p>
        
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
        <span style='color: #475569; font-size: 13px;'>{$attachmentMessageForSales}</span></p>
        
        <p>------</p>
        <p>This e-mail was sent from page &quot;request for quote&quot; on <a href='http://www.forcebeyond.com'>http://www.forcebeyond.com</a></p>
    ";
    
    $mail->send(); 

    // --- EMAIL 2: Copy Back to Customer ---
    $mail->clearAddresses();
    $mail->clearReplyTos();
    
    $mail->setFrom('contact@forcebeyond.com', 'www.forcebeyond.com');
    $mail->addAddress($email, $fullName);
    $mail->addReplyTo('contact@forcebeyond.com', 'ForceBeyond Sales'); 

    $mail->Subject = "Thank you for your RFQ - ForceBeyond";
    
    $mail->Body    = "
        <div style='font-family: sans-serif; color: #334155; line-height: 1.6;'>
            <p>Dear {$fullName},</p>
            <p>Thank you very much for contacting ForceBeyond. We have safely received your Request for Quote (RFQ) </p>
            <p>For your record, here is a summary of the details you submitted:</p>
            <p style='color: #cbd5e1;'>--------------------------------------------------</p>
            
            <p><b>Your Name:</b> {$fullName}</p>
            <p><b>Your Job Title:</b> {$jobTitle}</p>
            <p><b>Company Name:</b> {$organization}</p>
            <p><b>Interested In:</b> {$interest}</p>
            <p><b>Annual Budget:</b> {$budget}</p>
            <p><b>Your Phone:</b> {$phone}</p>
            
            <p style='margin-top: 15px;'><b>Attached Engineering Assets:</b><br />
            <span style='color: #64748b; font-size: 13px;'>{$attachmentMessageForCustomer}</span></p>
            
            <p style='color: #cbd5e1;'>--------------------------------------------------</p>
            <p>We greatly appreciate your opportunity and we will get back to you soon!</p>
            
            <p style='margin-top: 25px;'>Best Regards,</p>
            <p><b>ForceBeyond Team</b><br />
            <a href='https://www.forcebeyond.com' style='color: #ea580c; text-decoration: none;'>www.forcebeyond.com</a></p>
        </div>
    ";

    $mail->send(); 

    $mail->clearAttachments();
    echo json_encode(["success" => true]);

} catch (Throwable $e) {
    http_response_code(500);

    error_log(
        basename(__FILE__) .
        ' error: ' .
        $e->getMessage() .
        ' in ' .
        $e->getFile() .
        ':' .
        $e->getLine()
    );

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);

    exit;
}
?>
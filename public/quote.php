<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/turnstile-verify.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 405 Method Not Allowed");
    exit("Method Not Allowed");
}


require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';


// Collect and sanitize RFQ form fields
$firstName   = htmlspecialchars(strip_tags(trim($_POST["first-name"] ?? '')));
$lastName    = htmlspecialchars(strip_tags(trim($_POST["last-name"] ?? '')));
$jobTitle    = htmlspecialchars(strip_tags(trim($_POST["job-title"] ?? '')));
$organization= htmlspecialchars(strip_tags(trim($_POST["organization"] ?? ''))); 

$address1    = htmlspecialchars(strip_tags(trim($_POST["address-line-1"] ?? '')));
$address2    = htmlspecialchars(strip_tags(trim($_POST["address-line-2"] ?? '')));
$city        = htmlspecialchars(strip_tags(trim($_POST["city"] ?? '')));
$state       = htmlspecialchars(strip_tags(trim($_POST["state"] ?? '')));
$postalCode  = htmlspecialchars(strip_tags(trim($_POST["postal-code"] ?? '')));
$country     = htmlspecialchars(strip_tags(trim($_POST["country"] ?? '')));

$rawEmail    = trim($_POST["email"] ?? '');
$confirmEmail = trim($_POST["confirm-email"] ?? '');
$email       = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
$phone       = htmlspecialchars(strip_tags(trim($_POST["phone"] ?? '')));
$leadSource  = htmlspecialchars(strip_tags(trim($_POST["marketing-source"] ?? '')));

// Project context fields
$interest    = htmlspecialchars(strip_tags(trim($_POST["project-interest"] ?? '')));
$budget      = htmlspecialchars(strip_tags(trim($_POST["annual-budget"] ?? '')));

// Every user-facing field is required except engineering attachments.
$requiredFields = [
    'First Name' => $firstName,
    'Last Name' => $lastName,
    'Job Title' => $jobTitle,
    'Business / Organization' => $organization,
    'Email' => $email,
    'Confirm Email' => $confirmEmail,
    'Address Line 1' => $address1,
    'City' => $city,
    'State' => $state,
    'Postal Code' => $postalCode,
    'Country' => $country,
    'How Did You Hear About ForceBeyond' => $leadSource,
    'Project Interest' => $interest,
    'Annual Budget' => $budget,
    'Phone Number' => $phone,
];

$missingFields = [];

foreach ($requiredFields as $label => $value) {
    if ($value === false || trim((string) $value) === '') {
        $missingFields[] = $label;
    }
}

if ($missingFields) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "error" => "Please complete: " . implode(", ", $missingFields) . "."
    ]);
    exit;
}

if (strcasecmp($rawEmail, $confirmEmail) !== 0) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "error" => "Email and confirm email must match."
    ]);
    exit;
}

// Run bot checks only after ordinary field validation succeeds.
// Turnstile tokens are single-use, so this avoids consuming a token for an incomplete form.
verifyHoneypot();
verifyTurnstile('rfq-form');

$fullName = $firstName . ' ' . $lastName;

// Validate uploaded engineering files
$allowedExtensions = ['pdf', 'dwg', 'dxf', 'step', 'stp', 'iges', 'igs', 'sldprt', 'zip', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$maxFileSize = 25 * 1024 * 1024; // 25 MB
$attachmentCount = 0;

if (isset($_FILES['engineering-assets'])) {
    $files = $_FILES['engineering-assets'];

    foreach ($files['name'] as $key => $name) {
        $uploadError = $files['error'][$key] ?? UPLOAD_ERR_NO_FILE;

        // Empty file slots are normal when the optional attachment field is unused.
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        // Do not silently turn a failed upload into "no attachment". Mobile files
        // are often larger, which can expose hosting-level PHP upload limits.
        if ($uploadError !== UPLOAD_ERR_OK) {
            $uploadErrorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form upload size limit.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload folder is missing.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
                UPLOAD_ERR_EXTENSION  => 'A server extension stopped the file upload.',
            ];

            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => ($uploadErrorMessages[$uploadError] ?? 'The attachment could not be uploaded.') .
                    ($name ? ' File: ' . $name : ''),
            ]);
            exit;
        }

        $fileNameLower = strtolower($name);

        if (preg_match('/\.(php|js|sh|exe|pl|py)/', $fileNameLower)) {
            http_response_code(422);
            echo json_encode(["success" => false, "error" => "Security exception: Execution script pattern intercepted."]);
            exit;
        }

        $ext = pathinfo($fileNameLower, PATHINFO_EXTENSION);
        if (!in_array($ext, $allowedExtensions, true)) {
            http_response_code(422);
            echo json_encode(["success" => false, "error" => "Extension rejected: '." . $ext . "' is unauthorized."]);
            exit;
        }

        if (($files['size'][$key] ?? 0) > $maxFileSize) {
            http_response_code(422);
            echo json_encode(["success" => false, "error" => "Payload cap breached by file: " . $name]);
            exit;
        }

        if (!is_uploaded_file($files['tmp_name'][$key])) {
            http_response_code(422);
            echo json_encode(["success" => false, "error" => "The server could not verify the uploaded file: " . $name]);
            exit;
        }

        $attachmentCount++;
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
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);

        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host = SMTP_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = SMTP_USERNAME;
        $this->mail->Password = SMTP_PASSWORD;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = SMTP_PORT;

        // Default sender
        $this->mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    }

    public function sendContactInquiry($inquiryData) {
        try {
            // Recipients
            $this->mail->addAddress(SMTP_FROM, SMTP_FROM_NAME); // Send to admin

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = 'New Contact Inquiry: ' . $inquiryData['subject'];
            $this->mail->Body = $this->getContactInquiryHTML($inquiryData);
            $this->mail->AltBody = $this->getContactInquiryText($inquiryData);

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    private function getContactInquiryHTML($data) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #374151; }
                .value { color: #6b7280; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Contact Inquiry</h2>
                </div>
                <div class='content'>
                    <div class='field'>
                        <div class='label'>Name:</div>
                        <div class='value'>{$data['name']}</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Email:</div>
                        <div class='value'>{$data['email']}</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Phone:</div>
                        <div class='value'>" . (!empty($data['phone']) ? $data['phone'] : 'Not provided') . "</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Subject:</div>
                        <div class='value'>{$data['subject']}</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Message:</div>
                        <div class='value'>" . nl2br(htmlspecialchars($data['message'])) . "</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Received:</div>
                        <div class='value'>{$data['created_at']}</div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getContactInquiryText($data) {
        return "New Contact Inquiry

Name: {$data['name']}
Email: {$data['email']}
Phone: " . (!empty($data['phone']) ? $data['phone'] : 'Not provided') . "
Subject: {$data['subject']}
Message:
{$data['message']}

Received: {$data['created_at']}";
    }
}
?>

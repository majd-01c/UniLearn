<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Service for sending user-related emails using direct SMTP transport
 * This bypasses Symfony Messenger queue for immediate delivery
 */
class UserMailerService
{
    public function __construct(
        private string $mailerDsn,
        private ?LoggerInterface $logger = null,
        private string $fromEmail = 'majdlabidi666@gmail.com',
        private string $fromName = 'UniLearn Platform'
    ) {
        // Debug logging to see what DSN we're getting
        $this->log('warning', '🔍 Constructor called with DSN: ' . $this->mailerDsn);
    }

    /**
     * Send welcome email with temporary password
     */
    public function sendWelcomeEmail(User $user, string $tempPassword, string $loginUrl): void
    {
        $roleLabel = $this->getRoleLabel($user->getRole());
        
        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('🎓 Welcome to UniLearn Platform - Your Account Details')
            ->html($this->getWelcomeEmailHtml($user, $roleLabel, $tempPassword, $loginUrl));

        $this->sendEmail($email, 'welcome', $user->getEmail());
    }

    /**
     * Send password reset email with new temporary password
     */
    public function sendPasswordResetEmail(User $user, string $tempPassword, string $loginUrl): void
    {
        $roleLabel = $this->getRoleLabel($user->getRole());
        
        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('🔐 UniLearn Platform - Password Reset')
            ->html($this->getPasswordResetEmailHtml($user, $roleLabel, $tempPassword, $loginUrl));

        $this->sendEmail($email, 'password-reset', $user->getEmail());
    }

    /**
     * Send a simple notification email
     */
    public function sendNotificationEmail(string $to, string $subject, string $message): void
    {
        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->html($this->getNotificationEmailHtml($subject, $message));

        $this->sendEmail($email, 'notification', $to);
    }

    /**
     * Send email using direct SMTP transport
     */
    private function sendEmail(Email $email, string $type, string $recipient): void
    {
        $this->log('info', "Sending {$type} email to: {$recipient}");
        $this->log('warning', '🔍 About to create transport with DSN: ' . $this->mailerDsn);

        if (empty($this->mailerDsn)) {
            $this->log('error', 'MAILER_DSN not configured');
            throw new \RuntimeException('MAILER_DSN is not configured');
        }

        try {
            $transport = Transport::fromDsn($this->mailerDsn);
            $sentMessage = $transport->send($email);
            
            $messageId = $sentMessage ? $sentMessage->getMessageId() : 'unknown';
            $this->log('info', "Email sent successfully. Message ID: {$messageId}");
        } catch (\Throwable $e) {
            $this->log('error', "Failed to send email: " . $e->getMessage());
            throw $e;
        }
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            $this->logger->$level('[UserMailerService] ' . $message);
        }
    }

    private function getRoleLabel(?string $role): string
    {
        return match($role) {
            'ADMIN' => 'Administrator',
            'STUDENT' => 'Student',
            'TEACHER' => 'Teacher',
            'BUSINESS_PARTNER' => 'Business Partner',
            default => 'User',
        };
    }

    private function getWelcomeEmailHtml(User $user, string $roleLabel, string $tempPassword, string $loginUrl): string
    {
        $userName = $user->getProfile()?->getFirstName() ?? 'User';
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; }
        .content { background: #ffffff; padding: 30px; }
        .credentials { background: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; }
        .credentials h3 { margin-top: 0; color: #667eea; }
        .btn { display: inline-block; background: #667eea; color: white !important; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Welcome to UniLearn!</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{$userName}</strong>,</p>
            <p>Your account has been successfully created as <strong>{$roleLabel}</strong>.</p>
            
            <div class="credentials">
                <h3>📧 Your Login Credentials</h3>
                <p><strong>Email:</strong> {$user->getEmail()}</p>
                <p><strong>Temporary Password:</strong> <code style="background:#eee;padding:2px 8px;border-radius:3px;">{$tempPassword}</code></p>
            </div>
            
            <p style="text-align: center;">
                <a href="{$loginUrl}" class="btn">Login to UniLearn</a>
            </p>
            
            <div class="warning">
                <strong>⚠️ Important:</strong> For security reasons, you must change your password on your first login.
            </div>
            
            <p>If you have any questions, please contact your administrator.</p>
            <p>Best regards,<br><strong>The UniLearn Team</strong></p>
        </div>
        <div class="footer">
            <p>UniLearn Platform - Education Management System</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getPasswordResetEmailHtml(User $user, string $roleLabel, string $tempPassword, string $loginUrl): string
    {
        $userName = $user->getProfile()?->getFirstName() ?? 'User';
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; }
        .content { background: #ffffff; padding: 30px; }
        .credentials { background: #f8f9fa; border-left: 4px solid #f5576c; padding: 20px; margin: 20px 0; }
        .credentials h3 { margin-top: 0; color: #f5576c; }
        .btn { display: inline-block; background: #f5576c; color: white !important; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Password Reset</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{$userName}</strong>,</p>
            <p>Your password has been reset. Here are your new login credentials:</p>
            
            <div class="credentials">
                <h3>📧 New Login Credentials</h3>
                <p><strong>Email:</strong> {$user->getEmail()}</p>
                <p><strong>New Temporary Password:</strong> <code style="background:#eee;padding:2px 8px;border-radius:3px;">{$tempPassword}</code></p>
            </div>
            
            <p style="text-align: center;">
                <a href="{$loginUrl}" class="btn">Login Now</a>
            </p>
            
            <div class="warning">
                <strong>⚠️ Important:</strong> Please change your password immediately after logging in.
            </div>
            
            <p>If you did not request this password reset, please contact your administrator immediately.</p>
            <p>Best regards,<br><strong>The UniLearn Team</strong></p>
        </div>
        <div class="footer">
            <p>UniLearn Platform - Education Management System</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getNotificationEmailHtml(string $subject, string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .content { background: #ffffff; padding: 30px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📢 {$subject}</h1>
        </div>
        <div class="content">
            {$message}
            <p>Best regards,<br><strong>The UniLearn Team</strong></p>
        </div>
        <div class="footer">
            <p>UniLearn Platform - Education Management System</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}

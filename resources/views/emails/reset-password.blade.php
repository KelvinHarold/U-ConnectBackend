{{-- resources/views/emails/reset-password.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #5C352C 0%, #8B5E4F 100%);
            padding: 32px 24px;
            text-align: center;
        }
        .title {
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        .content {
            padding: 40px 32px;
        }
        .button {
            display: inline-block;
            background-color: #5C352C;
            color: white;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            margin: 24px 0;
        }
        .warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            margin: 20px 0;
            font-size: 13px;
            color: #92400e;
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
        @media (max-width: 600px) {
            .content {
                padding: 24px 20px;
            }
            .button {
                display: block;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Reset Your Password</h1>
        </div>
        
        <div class="content">
            <p>Hello,</p>
            <p>We received a request to reset the password for your U-Connect account. Click the button below to create a new password:</p>
            
            <div style="text-align: center;">
                <a href="{!! $resetUrl !!}" class="button">Reset Password</a>
            </div>
            
            <div class="warning">
                <strong>⚠️ This link will expire in 60 minutes</strong><br>
                If you didn't request a password reset, please ignore this email.
            </div>
            
            <p>If the button doesn't work, copy and paste this link into your browser:</p>
            <p style="word-break: break-all; font-size: 12px; color: #64748b;">{!! $resetUrl !!}</p>
            
            <hr style="margin: 32px 0; border: none; border-top: 1px solid #e2e8f0;">
            
            <p style="font-size: 13px; color: #64748b;">
                For security reasons, this password reset request was made from IP: {{ request()->ip() }}<br>
                If this wasn't you, please contact our support team immediately.
            </p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} U-Connect. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
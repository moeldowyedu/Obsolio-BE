<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        body {
            background-color: #121212;
            color: #E0E0E0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #1E1E1E;
            padding: 40px;
            border-radius: 8px;
            margin-top: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #FFFFFF;
            font-weight: 800;
            letter-spacing: 2px;
            margin: 0;
        }

        .content {
            text-align: center;
        }

        h2 {
            color: #FFFFFF;
            border-bottom: 1px solid #333;
            padding-bottom: 20px;
        }

        p {
            line-height: 1.6;
            color: #CCCCCC;
        }

        .button {
            display: inline-block;
            background-color: #FFFFFF;
            color: #000000;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #E0E0E0;
        }

        .footer {
            margin-top: 40px;
            border-top: 1px solid #333;
            padding-top: 20px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }

        .link-break {
            word-break: break-all;
            color: #888;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <h1>OBSOLIO</h1>
        </div>
        <div class="content">
            <h2>Activate Your Workspace</h2>
            <p>Hello,</p>
            <p>You are just one step away from accessing your OBSOLIO workspace. Please click the button below to verify
                your email address and activate your account.</p>

            <a href="{{ $actionUrl }}" class="button">Activate My Workspace</a>

            <p>This verification link will expire in 60 minutes.</p>
            <p>If you did not create an account, no further action is required.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} OBSOLIO. All rights reserved.</p>
            <p style="margin-top: 10px;">
                If you're having trouble clicking the "Activate My Workspace" button, copy and paste the URL below into
                your web browser:
                <br>
                <span class="link-break">{{ $actionUrl }}</span>
            </p>
        </div>
    </div>
</body>

</html>
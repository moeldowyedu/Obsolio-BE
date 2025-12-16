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
            text-align: left;
        }

        h2 {
            color: #FFFFFF;
            border-bottom: 1px solid #333;
            padding-bottom: 20px;
            text-align: center;
        }

        .info-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #333;
        }

        .label {
            font-weight: bold;
            color: #888;
            width: 150px;
        }

        .value {
            color: #FFF;
        }

        .button {
            display: inline-block;
            background-color: #FFFFFF;
            color: #000000;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 30px;
            text-align: center;
            display: block;
            width: 200px;
            margin-left: auto;
            margin-right: auto;
        }

        .footer {
            margin-top: 40px;
            border-top: 1px solid #333;
            padding-top: 20px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <h1>OBSOLIO</h1>
        </div>
        <div class="content">
            <h2>New Tenant Verified</h2>
            <p>A new tenant has successfully verified their email and activated their workspace.</p>

            <table class="info-table">
                <tr>
                    <td class="label">Organization/Name:</td>
                    <td class="value">{{ $tenantName }}</td>
                </tr>
                <tr>
                    <td class="label">Subdomain:</td>
                    <td class="value">{{ $subdomain }}</td>
                </tr>
                <tr>
                    <td class="label">Contact Email:</td>
                    <td class="value">{{ $userEmail }}</td>
                </tr>
                <tr>
                    <td class="label">Type:</td>
                    <td class="value">{{ ucfirst($type) }}</td>
                </tr>
                <tr>
                    <td class="label">Verified At:</td>
                    <td class="value">{{ $verifiedAt }}</td>
                </tr>
            </table>

            <a href="https://console.obsolio.com" class="button">View in Admin Console</a>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} OBSOLIO System Notification.</p>
        </div>
    </div>
</body>

</html>
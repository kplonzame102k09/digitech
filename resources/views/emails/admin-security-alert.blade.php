<!doctype html>
<html lang="en">
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="padding:28px 32px;background-color:#065f46;color:#ffffff;">
                            <h1 style="margin:0;font-size:20px;font-weight:bold;">DIGITECH COLLEGE</h1>
                            <p style="margin:4px 0 0;font-size:13px;color:#a7f3d0;">Security Alert</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;color:#0f172a;">
                            <p style="margin:0 0 16px;font-size:15px;">Hello <strong>{{ $name }}</strong>,</p>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
                                The portal has blocked a possible brute-force sign-in attempt.
                                Sign-ins for the account below are locked until <strong>{{ $lockedUntil }}</strong>.
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;border-radius:8px;margin-bottom:20px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase;">Account Identifier Used</p>
                                        <p style="margin:0 0 16px;font-size:15px;font-family:Consolas,Menlo,monospace;color:#065f46;text-transform:uppercase;"><strong>{{ $identifier }}</strong></p>
                                        <p style="margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase;">Role Attempted</p>
                                        <p style="margin:0 0 16px;font-size:15px;color:#0f172a;text-transform:capitalize;">{{ $role }}</p>
                                        <p style="margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase;">Failed Attempts</p>
                                        <p style="margin:0 0 16px;font-size:15px;color:#0f172a;">{{ $attempts }}</p>
                                        <p style="margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase;">IP Address</p>
                                        <p style="margin:0;font-size:15px;font-family:Consolas,Menlo,monospace;color:#0f172a;">{{ $ip }}</p>
                                    </td>
                                </tr>
                            </table>
                            <a href="{{ $manageUrl }}" style="display:inline-block;background-color:#16a34a;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 28px;border-radius:8px;">Open admin dashboard</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background-color:#f8fafc;color:#64748b;font-size:12px;">
                            This is an automated notification from the Digitech College portal.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
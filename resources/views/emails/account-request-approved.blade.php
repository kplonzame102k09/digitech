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
                            <p style="margin:4px 0 0;font-size:13px;color:#a7f3d0;">Account Approved</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;color:#0f172a;">
                            <p style="margin:0 0 16px;font-size:15px;">Hello <strong>{{ $name }}</strong>,</p>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
                                Your request for an account has been <strong>approved</strong>. You can now sign in to the Digitech College portal using the credentials below.
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;border-radius:8px;margin-bottom:20px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase;">User ID</p>
                                        <p style="margin:0 0 16px;font-size:15px;font-family:Consolas,Menlo,monospace;color:#065f46;"><strong>{{ $userId }}</strong></p>
                                        <p style="margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase;">Username</p>
                                        <p style="margin:0 0 16px;font-size:15px;color:#0f172a;">{{ $username }}</p>
                                        <p style="margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase;">Initial Password</p>
                                        <p style="margin:0;font-size:15px;font-family:Consolas,Menlo,monospace;color:#0f172a;">{{ $password }}</p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 12px;font-size:14px;line-height:1.6;">
                                <strong>Heads up:</strong> for your security, you will be asked to change this password the first time you sign in.
                            </p>
                            <p style="margin:0 0 24px;font-size:14px;line-height:1.6;">
                                Sign in with your <strong>User ID</strong> or <strong>email address</strong>, then select the matching account type.
                            </p>
                            <a href="{{ $loginUrl }}" style="display:inline-block;background-color:#16a34a;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 28px;border-radius:8px;">Sign in to the portal</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background-color:#f8fafc;color:#64748b;font-size:12px;">
                            If you did not request an account, please disregard this email or contact the Digitech College administration.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
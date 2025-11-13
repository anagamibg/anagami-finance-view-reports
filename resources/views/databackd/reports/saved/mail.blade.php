<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->name }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#28313c;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:6px;overflow:hidden;">
                <tr>
                    <td style="background:#1d82f5;padding:18px 28px;color:#ffffff;font-size:16px;font-weight:bold;">
                        {{ $companyName ?: (trim((string)(global_setting()->global_app_name ?? '')) ?: 'databackd') }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 14px;font-size:15px;">@lang('databackd-reports-saved.mail.greeting')</p>
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.5;">
                            {{-- S12: {{ }} - @lang does not escape the replacements (report/company name typed by a user) --}}
                            {{ __('databackd-reports-saved.mail.intro', ['name' => $report->name, 'company' => $companyName]) }}
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8eef3;border-radius:4px;margin-bottom:20px;">
                            <tr>
                                <td style="padding:16px 18px;font-size:14px;line-height:1.6;">
                                    <div style="font-size:17px;font-weight:bold;color:#1d82f5;margin-bottom:4px;">{{ $report->name }}</div>
                                    @if ($report->description)
                                        <div style="color:#616e80;margin-bottom:10px;">{{ $report->description }}</div>
                                    @endif
                                    <div><span style="color:#616e80;">@lang('databackd-reports-saved.mail.type'):</span>
                                        {{ __('databackd-reports-saved.types.' . $report->type) }}</div>
                                    <div><span style="color:#616e80;">@lang('databackd-reports-saved.mail.schedule'):</span>
                                        {{ __('databackd-reports-saved.schedules.' . $report->schedule) }}</div>
                                    @foreach ($summary as $line)
                                        <div>{{ $line }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        </table>

                        @if ($openUrl)
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
                                <tr>
                                    <td style="background:#1d82f5;border-radius:4px;">
                                        <a href="{{ $openUrl }}" target="_blank"
                                           style="display:inline-block;padding:11px 22px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;">
                                            @lang('databackd-reports-saved.mail.open')
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <p style="margin:0;font-size:12px;color:#8a95a5;">
                            {{ __('databackd-reports-saved.mail.generated_at', ['date' => $generatedAt]) }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px;background:#f9fafb;font-size:12px;color:#8a95a5;line-height:1.5;">
                        {{ __('databackd-reports-saved.mail.footer', ['app' => (trim((string)(global_setting()->global_app_name ?? '')) ?: 'databackd')]) }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

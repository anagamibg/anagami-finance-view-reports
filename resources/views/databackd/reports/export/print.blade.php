<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @include('databackd.reports.export._css', ['pdf' => false])
</head>
<body>
<div class="rp-toolbar">
    <button type="button" onclick="window.print()">{{ __('databackd-reports-export.print') }}</button>
    <button type="button" class="secondary" onclick="window.close()">{{ __('databackd-reports-export.close') }}</button>
</div>

@include('databackd.reports.export._report')

<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 400);
    });
</script>
</body>
</html>

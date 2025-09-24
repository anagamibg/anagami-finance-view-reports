<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('databackd.reports.export._css', ['pdf' => true])
</head>
<body>
@include('databackd.reports.export._report')
</body>
</html>

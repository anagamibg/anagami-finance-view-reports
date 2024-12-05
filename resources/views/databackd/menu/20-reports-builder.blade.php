@if (databackd_module('databackd_reports')) {{-- AFV (S8): package module --}}
<x-sub-menu-item :link="route('databackd.reports.builder.index')" :text="__('databackd-reports-builder.menu')" />
@endif

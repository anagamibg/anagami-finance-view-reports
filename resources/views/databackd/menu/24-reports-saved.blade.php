@if (databackd_module('databackd_reports')) {{-- AFV (S8): package module --}}
<x-sub-menu-item :link="route('databackd.reports.saved.index')" :text="__('databackd-reports-saved.menu')" />
@endif

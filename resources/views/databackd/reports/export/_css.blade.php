<style>
    @page { size: A4 landscape; margin: {{ !empty($pdf) ? '28px 28px 40px 28px' : '12mm' }}; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; margin: 0; }
    .rp-head { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .rp-head td { vertical-align: top; padding: 0; }
    .rp-logo { max-height: 42px; max-width: 160px; }
    .rp-company { font-size: 12px; font-weight: bold; color: #1f3a5f; }
    .rp-title { font-size: 16px; font-weight: bold; color: #111827; margin: 0 0 4px 0; }
    .rp-meta { font-size: 8.5px; color: #4b5563; line-height: 1.5; }
    .rp-generated { font-size: 8.5px; color: #6b7280; text-align: right; }
    .rp-line { border-top: 2px solid #1f3a5f; margin: 6px 0 10px 0; }
    .rp-table-title { font-size: 11px; font-weight: bold; color: #1f3a5f; margin: 12px 0 4px 0; }
    .rp-part { font-weight: normal; color: #6b7280; font-size: 8px; }
    table.rp { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.rp th { background: #1f3a5f; color: #ffffff; font-weight: bold; text-align: right; padding: 4px 5px; border: 1px solid #1f3a5f; font-size: 8.5px; }
    table.rp td { text-align: right; padding: 3px 5px; border: 1px solid #d1d5db; white-space: nowrap; }
    table.rp th.l, table.rp td.l { text-align: left; white-space: normal; }
    table.rp tbody tr:nth-child(even) td { background: #f5f7fa; }
    table.rp tr.total td { font-weight: bold; background: #e8eef6 !important; border-top: 2px solid #1f3a5f; }
    table.rp td.sum { font-weight: bold; }
    table.rp thead { display: table-header-group; }
    table.rp tr { page-break-inside: avoid; }
    .rp-chunk { page-break-inside: avoid; }
    .rp-empty { padding: 24px; text-align: center; color: #6b7280; border: 1px dashed #d1d5db; }
    @if(empty($pdf))
    body { padding: 16px; max-width: 1400px; margin: 0 auto; font-size: 11px; }
    .rp-toolbar { position: sticky; top: 0; background: #fff; padding: 8px 0 10px 0; margin-bottom: 8px; border-bottom: 1px solid #e5e7eb; z-index: 5; }
    .rp-toolbar button { font-family: inherit; font-size: 13px; padding: 7px 16px; margin-right: 8px; border-radius: 4px; border: 1px solid #1f3a5f; cursor: pointer; background: #1f3a5f; color: #fff; }
    .rp-toolbar button.secondary { background: #fff; color: #1f3a5f; }
    table.rp th, table.rp td { font-size: 11px; }
    @media print {
        .rp-toolbar { display: none !important; }
        body { padding: 0; max-width: none; font-size: 9px; }
        table.rp th, table.rp td { font-size: 8.5px; }
        table.rp th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.rp tr.total td, table.rp tbody tr:nth-child(even) td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .rp-chunk { page-break-inside: avoid; }
    }
    @endif
</style>

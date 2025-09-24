<table class="rp-head">
    <tr>
        <td style="width: 68%;">
            @if(!empty($logo))
                <img src="{{ $logo }}" class="rp-logo" alt=""><br>
            @endif
            @if($company_name !== '')
                <div class="rp-company">{{ $company_name }}</div>
            @endif
        </td>
        <td class="rp-generated">{{ __('databackd-reports-export.generated_at') }}: {{ $generated_at }}</td>
    </tr>
</table>
<div class="rp-line"></div>
<h1 class="rp-title">{{ $title }}</h1>
<div class="rp-meta">
    {{ implode('   ·   ', $summary) }}<br>
    {{ implode('   ·   ', $config_lines) }}
</div>

@if($empty)
    <div class="rp-empty">{{ __('databackd-reports-export.no_data') }}</div>
@else
    @foreach($tables as $table)
        @foreach(\App\Exports\Databackd\Reports\PivotExport::chunk($table) as $chunk)
            <div class="rp-chunk">
                {{-- S12: one title line per block - the first block of a split table printed its title twice --}}
                @if($chunk['title'] && $chunk['parts'] === 1)
                    <div class="rp-table-title">{{ $chunk['title'] }}</div>
                @elseif($chunk['parts'] > 1)
                    <div class="rp-table-title" style="margin-top: 8px;">
                        @if($chunk['title']){{ $chunk['title'] }} @endif
                        <span class="rp-part">({{ __('databackd-reports-export.continued', ['part' => $chunk['part'], 'parts' => $chunk['parts']]) }})</span>
                    </div>
                @endif
                <table class="rp">
                    <thead>
                    <tr>
                        @foreach($chunk['headers'] as $i => $h)
                            <th class="{{ $i === 0 ? 'l' : '' }}">{{ $h }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($chunk['rows'] as $row)
                        <tr class="{{ $row['total'] ? 'total' : '' }}">
                            @foreach($row['cells'] as $i => $cell)
                                <td class="{{ $i === 0 ? 'l' : '' }} {{ ($chunk['trailing'] ?? 0) && $i === count($row['cells']) - 1 && $chunk['part'] === $chunk['parts'] ? 'sum' : '' }}">{{ \App\Exports\Databackd\Reports\PivotExport::format($cell, $chunk['formats'][$i]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endforeach
@endif

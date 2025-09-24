<?php

namespace App\Exports\Databackd\Reports;

use App\Services\Databackd\Reports\ReportFilters;

/**
 * PDF (A4 landscape, DejaVu Sans) of the pivot report. No session, no logged in user: the company is passed explicitly,
 * so it also works from the console (command databackd:send-reports).
 */
class PivotPdf
{

    /**
     * @return string binary PDF
     */
    public static function render(array $config, ReportFilters $f, int $companyId): string
    {
        // S12: a daily report over a quarter/year (90-365 columns x 3 tables) needs 150-300 MB in dompdf
        self::raiseMemoryLimit();

        $data = PivotExport::prepare($config, $f, $companyId);
        // S12: the data: URI of prepare() (fine for print) is not an allowed protocol below, dompdf drew a broken-image
        // box instead of the logo - give dompdf the local file (same as CounterpartyPdf), or no logo at all
        $data['logo'] = self::logoFile($companyId);
        $html = view('databackd.reports.export.pdf', $data)->render();

        $pdf = app('dompdf.wrapper');
        // SECURITY (S6/P4): do NOT enable_php here. The template's <script type="text/php"> footer block
        // interpolates $company_name (user-controlled, Settings -> Organisation, max 60 chars) via json_encode()
        // straight into PHP source that dompdf eval()s (PhpEvaluator::evaluate()). json_encode() does not escape
        // "$" or "{", so a company name containing PHP string-interpolation syntax (e.g. "${...}" / "{$...}")
        // would run as code in that eval() - remote code execution by any company admin. enable_php defaults to
        // false (config/dompdf.php), so simply never turning it on here closes this. The page-number footer is
        // instead drawn below with Canvas::page_text(), which never evaluates the text it draws.
        $pdf->setOption('isHtml5ParserEnabled', true);
        // S12: embed only the used glyphs of DejaVu Sans (a one-page report was ~860 KB with the whole font)
        $pdf->setOption('isFontSubsettingEnabled', true);
        // No remote (http/https) fetch is needed - the logo is always a local file path - so keep it disabled
        // even though config/dompdf.php's default is enabled, and only allow the local-file scheme actually used.
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('allowed_protocols', [
            'file://' => ['rules' => []],
        ]);
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHTML($html);
        $pdf->render();

        self::drawFooter($pdf, (string)($data['company_name'] ?? ''), (string)($data['title'] ?? ''));

        return $pdf->output();
    }

    /**
     * Page "<company> · <title>" / "Page X of Y" footer, drawn via the dompdf Canvas API (no eval of any text).
     * Replaces the former <script type="text/php"> block of the view (removed in S10: enable_php is never set),
     * so the same footer renders without ever treating report/company data as PHP source.
     */
    protected static function drawFooter($pdf, string $companyName, string $title): void
    {
        $canvas = $pdf->getCanvas();
        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $size = 8;
        $color = [0.42, 0.45, 0.50];
        $w = $canvas->get_width();
        $h = $canvas->get_height();

        $left = trim($companyName . ' · ' . $title);
        $right = __('databackd-reports-export.page', ['page' => '{PAGE_NUM}', 'total' => '{PAGE_COUNT}']);
        $rightWidth = $fontMetrics->getTextWidth(str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], ['00', '00'], $right), $font, $size);

        // page_text() itself replaces the {PAGE_NUM}/{PAGE_COUNT} placeholders per page (Dompdf\Adapter\CPDF::page_text()).
        $canvas->page_text(21, $h - 28, $left, $font, $size, $color);
        $canvas->page_text($w - 21 - $rightWidth, $h - 28, $right, $font, $size, $color);
    }

    /** Local path of the company logo (inside the dompdf chroot = base_path()), null when there is none. */
    protected static function logoFile(int $companyId): ?string
    {
        $company = \App\Models\Company::find($companyId);

        if (!$company || !$company->logo) {
            return null;
        }

        $path = public_path(\App\Helper\Files::UPLOAD_FOLDER . '/app-logo/' . basename((string)$company->logo));

        return is_file($path) ? $path : null;
    }

    /** Raises memory_limit to at least $min (never lowers it; -1 = unlimited stays). */
    public static function raiseMemoryLimit(string $min = '512M'): void
    {
        $bytes = function (string $v): int {
            $v = trim($v);

            if ($v === '' || $v === '-1') {
                return -1;
            }

            $n = (int)$v;

            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1024 ** 3,
                'm' => $n * 1024 ** 2,
                'k' => $n * 1024,
                default => $n,
            };
        };

        $current = $bytes((string)ini_get('memory_limit'));

        if ($current !== -1 && $current < $bytes($min)) {
            @ini_set('memory_limit', $min);
        }
    }

    public static function filename(array $config, ReportFilters $f, int $companyId): string
    {
        return PivotExport::baseName($config) . '.pdf';
    }

}

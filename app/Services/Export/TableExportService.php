<?php

namespace App\Services\Export;

use App\Models\CompanyProfile;
use App\Services\TenantContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one place CSV/XLSX/PDF list exports are actually built - Reports,
 * Invoices, Quotes, Payments and Clients each build their own $headings/
 * $rows (their own filters, their own fields) then hand them to this
 * service instead of each page re-implementing CSV escaping, workbook
 * styling, or the PDF shell.
 *
 * $rows is always a plain array of associative arrays keyed exactly like
 * $headings (same order) - callers are responsible for formatting values
 * (currency, dates) into display strings before calling in, since only
 * the caller knows the tenant's currency/locale for that field.
 */
class TableExportService
{
    /**
     * Guards against CSV/Excel "formula injection": a cell value starting
     * with =, +, -, @ (or tab/CR) is interpreted as a formula by Excel/
     * Sheets when the file is opened, regardless of format. Prefixing
     * with a plain quote defuses it while keeping the visible text intact
     * for every field we ever export (references, names, notes...).
     */
    public static function sanitizeCell(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    public function csv(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 (Arabic/French accents) correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);

            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'sanitizeCell'], $row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function excel(string $filename, string $sheetTitle, array $headings, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet->getActiveSheet(), $sheetTitle, $headings, $rows);

        return $this->streamSpreadsheet($spreadsheet, $filename);
    }

    /**
     * Adds one more populated sheet to an existing workbook (Reports'
     * multi-sheet export: Summary/Invoices/Payments/Clients) instead of
     * building four separate files. Pass $isFirst only for the very first
     * call on a freshly-constructed Spreadsheet, to reuse its default
     * blank sheet instead of leaving it behind as an empty extra tab.
     */
    public function addSheet(Spreadsheet $spreadsheet, string $title, array $headings, array $rows, bool $isFirst = false): void
    {
        $sheet = $isFirst ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();

        $this->writeSheet($sheet, $title, $headings, $rows);
    }

    public function streamSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function writeSheet($sheet, string $title, array $headings, array $rows): void
    {
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $title), 0, 31));

        $sheet->fromArray($headings, null, 'A1');
        $lastCol = $this->columnLetter(count($headings));

        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E91E63');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_map([self::class, 'sanitizeCell'], array_values($row)), null, "A{$rowIndex}");
            $rowIndex++;
        }

        foreach (range(1, count($headings)) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }

    private function columnLetter(int $count): string
    {
        return Coordinate::stringFromColumnIndex(max(1, $count));
    }

    /**
     * Raw PDF bytes for a simple "filtered list" export (Invoices/Quotes/
     * Payments/Clients) - same branding shell the richer Reports PDF uses,
     * just with a plain table instead of KPI/breakdown sections.
     */
    public function listPdf(string $title, ?string $subtitle, array $headings, array $rows): string
    {
        [$companyName, $logoSrc] = $this->branding();
        $locale = app(TenantContextService::class)->locale();

        return Pdf::loadView('pdf.exports.list', [
            'companyName' => $companyName,
            'logoSrc'     => $logoSrc,
            'title'       => $title,
            'subtitle'    => $subtitle,
            'headings'    => $headings,
            'rows'        => $rows,
            'generatedAt' => now(),
            'labels'      => $this->pdfLabels($locale),
            'isRtl'       => $locale === 'ar',
        ])->setPaper('a4', count($headings) > 5 ? 'landscape' : 'portrait')->output();
    }

    /**
     * Same "small inline label map, no lang files" approach already used
     * by Pdf\TemplateRendererService::resolveStatus() for PDF-only text.
     */
    public function pdfLabels(string $locale): array
    {
        return match ($locale) {
            'ar' => ['generated_on' => 'تاريخ الإصدار', 'page' => 'صفحة', 'of' => 'من', 'no_data' => 'لا توجد بيانات لهذا التصدير.'],
            'en' => ['generated_on' => 'Generated on', 'page' => 'Page', 'of' => 'of', 'no_data' => 'No data for this export.'],
            default => ['generated_on' => 'Généré le', 'page' => 'Page', 'of' => 'sur', 'no_data' => 'Aucune donnée pour cet export.'],
        };
    }

    /**
     * @return array{0: string, 1: ?string} [companyName, logoSrc]
     */
    public function branding(): array
    {
        $company = CompanyProfile::first();
        $companyName = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));

        return [$companyName, $this->resolveLogoSrc($company?->logo_path)];
    }

    // Same resolution strategy as Pdf\TemplateRendererService::resolveLogoSrc()
    // (DomPDF's enable_remote=false means only local file:// paths render).
    private function resolveLogoSrc(?string $logoPath): ?string
    {
        if (empty($logoPath)) {
            return null;
        }

        if (preg_match('~^https?://~i', $logoPath)) {
            $logoPath = parse_url($logoPath, PHP_URL_PATH) ?? '';
        }

        if (!empty($logoPath) && is_file($logoPath)) {
            return 'file://' . $logoPath;
        }

        $disk = Storage::disk('public');
        $candidate = ltrim($logoPath, '/');
        if (str_starts_with($candidate, 'storage/')) {
            $candidate = substr($candidate, strlen('storage/'));
        }

        return (!empty($candidate) && $disk->exists($candidate)) ? 'file://' . $disk->path($candidate) : null;
    }
}

<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportExportService
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     * @return array{content: string, mime: string, extension: string}
     */
    public function content(array $headers, array $rows, string $format): array
    {
        return match ($format) {
            'csv' => [
                'content' => $this->csvContent($headers, $rows),
                'mime' => 'text/csv; charset=UTF-8',
                'extension' => 'csv',
            ],
            'xlsx' => [
                'content' => $this->xlsxContent($headers, $rows),
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extension' => 'xlsx',
            ],
            'pdf' => [
                'content' => $this->pdfContent($headers, $rows),
                'mime' => 'application/pdf',
                'extension' => 'pdf',
            ],
            default => throw new \InvalidArgumentException('Format mora biti csv, xlsx ili pdf.'),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     */
    public function export(string $filename, array $headers, array $rows, string $format): SymfonyResponse
    {
        try {
            $export = $this->content($headers, $rows, $format);
        } catch (\InvalidArgumentException) {
            return response()->json([
                'message' => 'Format mora biti csv, xlsx ili pdf.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response($export['content'], Response::HTTP_OK, [
            'Content-Type' => $export['mime'],
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$export['extension'].'"',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     */
    private function csvContent(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return (string) $content;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     */
    private function xlsxContent(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $handle = fopen('php://temp', 'r+');
        (new Xlsx($spreadsheet))->save($handle);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        $spreadsheet->disconnectWorksheets();

        return (string) $content;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     */
    private function pdfContent(array $headers, array $rows): string
    {
        return Pdf::loadView('reports.table', [
            'headers' => $headers,
            'rows' => $rows,
        ])->output();
    }
}

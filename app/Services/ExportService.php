<?php

namespace App\Services;

use App\Jobs\ProcessDataExportJob;
use App\Models\DatabaseConnection;
use App\Models\DataExport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportService
{
    public function __construct(
        private readonly QueryValidationService $queryValidationService,
        private readonly ReadOnlyQueryExecutor $readOnlyQueryExecutor,
    ) {}

    public function queueExport(User $user, DatabaseConnection $connection, string $sql, string $format): DataExport
    {
        $export = DataExport::create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'format' => $format,
            'status' => 'pending',
            'sql' => $sql,
            'expires_at' => Carbon::now()->addMinutes((int) config('monitorsql.export_expiration_minutes', 60)),
        ]);

        ProcessDataExportJob::dispatch($export->id);

        return $export;
    }

    public function process(DataExport $export): DataExport
    {
        $connection = $export->connection;

        if (! $connection instanceof DatabaseConnection) {
            $export->update([
                'status' => 'failed',
                'meta' => ['error' => 'Connection not available.'],
            ]);

            return $export;
        }

        $validation = $this->queryValidationService->validate(
            $export->sql,
            min((int) config('monitorsql.export_max_rows', 10000), $connection->max_rows),
        );

        if (! $validation['is_valid']) {
            $export->update([
                'status' => 'failed',
                'meta' => ['errors' => $validation['errors']],
            ]);

            return $export;
        }

        $result = $this->readOnlyQueryExecutor->execute($connection, $validation['sql_with_limit'], true);
        $rows = collect($result['rows']);
        $path = $this->storeFile($export, $rows);

        $export->update([
            'status' => 'completed',
            'file_path' => $path,
            'row_count' => count($result['rows']),
            'meta' => [
                'columns' => $result['columns'],
                'duration_ms' => $result['meta']['duration_ms'],
            ],
        ]);

        return $export->fresh();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function storeFile(DataExport $export, Collection $rows): string
    {
        $extension = $this->resolveExtension($export->format);
        $fileName = sprintf('exports/%d/export_%d.%s', $export->user_id ?? 0, $export->id, $extension);

        if ($export->format === 'xlsx') {
            $this->storeXlsx($rows, $fileName);

            return $fileName;
        }

        $content = $this->renderTextContent($rows, $export->format);

        Storage::disk('local')->put($fileName, $content);

        return $fileName;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function storeXlsx(Collection $rows, string $fileName): void
    {
        $temporaryFilePath = storage_path('app/'.sprintf('tmp_%s.xlsx', uniqid('', true)));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if ($rows->isNotEmpty()) {
            $headers = array_keys($rows->first());
            $sheet->fromArray($headers, null, 'A1');

            $rowIndex = 2;

            foreach ($rows as $row) {
                $values = collect($headers)
                    ->map(fn (string $header): string => isset($row[$header]) ? (string) $row[$header] : '')
                    ->all();

                $sheet->fromArray($values, null, 'A'.$rowIndex);
                $rowIndex++;
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($temporaryFilePath);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        Storage::disk('local')->put($fileName, file_get_contents($temporaryFilePath) ?: '');

        @unlink($temporaryFilePath);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function renderTextContent(Collection $rows, string $format): string
    {
        if ($format === 'json') {
            return json_encode($rows->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        return $this->toCsv($rows, ',');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function toCsv(Collection $rows, string $delimiter): string
    {
        if ($rows->isEmpty()) {
            return '';
        }

        $headers = array_keys($rows->first());
        $lines = [implode($delimiter, $headers)];

        foreach ($rows as $row) {
            $cells = collect($headers)
                ->map(function (string $header) use ($row, $delimiter): string {
                    $value = isset($row[$header]) ? (string) $row[$header] : '';
                    $escaped = str_replace('"', '""', $value);

                    if (str_contains($escaped, $delimiter) || str_contains($escaped, '"') || str_contains($escaped, "\n")) {
                        return sprintf('"%s"', $escaped);
                    }

                    return $escaped;
                })
                ->all();

            $lines[] = implode($delimiter, $cells);
        }

        return implode("\n", $lines);
    }

    private function resolveExtension(string $format): string
    {
        return match ($format) {
            'json' => 'json',
            'xlsx' => 'xlsx',
            default => 'csv',
        };
    }
}

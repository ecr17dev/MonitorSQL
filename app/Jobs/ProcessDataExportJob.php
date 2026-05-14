<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Services\ExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDataExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $exportId) {}

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService): void
    {
        $export = DataExport::find($this->exportId);

        if (! $export instanceof DataExport) {
            return;
        }

        $exportService->process($export);
    }
}

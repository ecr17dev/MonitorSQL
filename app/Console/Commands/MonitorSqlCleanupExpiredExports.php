<?php

namespace App\Console\Commands;

use App\Models\DataExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MonitorSqlCleanupExpiredExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitorsql:clean-expired-exports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired MonitorSQL export files and mark records as expired';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredExports = DataExport::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereIn('status', ['pending', 'completed'])
            ->get();

        foreach ($expiredExports as $export) {
            if ($export->file_path !== null && Storage::disk('local')->exists($export->file_path)) {
                Storage::disk('local')->delete($export->file_path);
            }

            $export->update([
                'status' => 'expired',
                'file_path' => null,
            ]);
        }

        $this->info(sprintf('Processed %d expired exports.', $expiredExports->count()));

        return self::SUCCESS;
    }
}

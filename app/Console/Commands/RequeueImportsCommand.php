<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductImport;
use App\Jobs\ImportProductsJob;

class RequeueImportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imports:requeue {id?} {--status=pending,failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-dispatch pending or failed product imports. Optionally provide an ID or status.';

    public function handle()
    {
        $id = $this->argument('id');
        $statusOpt = $this->option('status');

        if ($id) {
            $import = ProductImport::find($id);
            if (!$import) {
                $this->error("Import with id {$id} not found.");
                return 1;
            }
            ImportProductsJob::dispatch($import->id);
            $this->info("Dispatched import {$import->id}");
            return 0;
        }

        $statuses = array_map('trim', explode(',', $statusOpt));
        $imports = ProductImport::whereIn('status', $statuses)->get();

        if ($imports->isEmpty()) {
            $this->info('No imports found for statuses: ' . implode(',', $statuses));
            return 0;
        }

        foreach ($imports as $imp) {
            ImportProductsJob::dispatch($imp->id);
            $this->line("Queued import id={$imp->id} path={$imp->path}");
        }

        $this->info('Dispatched ' . $imports->count() . ' imports.');
        return 0;
    }
}

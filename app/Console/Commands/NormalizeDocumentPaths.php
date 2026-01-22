<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class NormalizeDocumentPaths extends Command
{
    protected $signature = 'documents:normalize-paths';
    protected $description = 'Moves legacy stored documents into the private/documents tree and fixes their database paths.';

    public function handle(): int
    {
        $disk = Storage::disk(Document::STORAGE_DISK);
        $prefix = 'private/documents/';
        $updates = 0;
        $missing = 0;

        Document::withoutGlobalScope('company')
            ->where('path', 'not like', $prefix.'%')
            ->chunkById(100, function ($documents) use ($disk, $prefix, &$updates, &$missing) {
                foreach ($documents as $document) {
                    $oldPath = $document->path;
                    $target = sprintf(
                        '%s%s/%s/%s',
                        $prefix,
                        $document->company_id,
                        $document->user_id,
                        basename($oldPath)
                    );

                    if ($disk->exists($target)) {
                        $document->forceFill(['path' => $target])->saveQuietly();
                        $updates++;
                        $this->info("Updated path for {$document->id} to existing target");
                        continue;
                    }

                    if (! $disk->exists($oldPath)) {
                        $missing++;
                        $this->warn("File missing for document {$document->id}: {$oldPath}");
                        continue;
                    }

                    $disk->makeDirectory(dirname($target));
                    $disk->move($oldPath, $target);
                    $document->forceFill(['path' => $target])->saveQuietly();

                    $updates++;
                    $this->info("Moved {$oldPath} => {$target} for document {$document->id}");
                }
            });

        $this->info("Normalization finished. Updated: {$updates}, Missing files: {$missing}");

        return 0;
    }
}

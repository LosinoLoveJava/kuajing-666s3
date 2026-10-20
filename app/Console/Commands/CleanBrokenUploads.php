<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Upload;
use Illuminate\Console\Command;

class CleanBrokenUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uploads:clean-broken
                            {--dry-run : Only preview, do not delete}
                            {--missing-local : Also delete any local records whose file is missing}
                            {--force : Delete even if still referenced by products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete broken remote/orphan upload records (URL originals with missing local files)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $missingLocal = (bool) $this->option('missing-local');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('Dry-run mode: no records will be deleted.');
        }

        $usedIds = $force ? [] : $this->collectUsedUploadIds();
        if (!$force) {
            $this->info('Loaded ' . count($usedIds) . ' upload id(s) referenced by products (will be skipped).');
        }

        $deletedRemote = $this->cleanRemoteUploads($usedIds, $dryRun);
        $deletedOrphan = $this->cleanOrphanUrlUploads($usedIds, $dryRun);
        $deletedMissing = 0;
        if ($missingLocal) {
            $deletedMissing = $this->cleanMissingLocalUploads($usedIds, $dryRun);
        }

        $this->line('');
        $this->info(($dryRun ? 'Would delete' : 'Deleted') . " remote/external: {$deletedRemote}");
        $this->info(($dryRun ? 'Would delete' : 'Deleted') . " orphan url/download: {$deletedOrphan}");
        if ($missingLocal) {
            $this->info(($dryRun ? 'Would delete' : 'Deleted') . " missing local: {$deletedMissing}");
        }
        $this->info('Total: ' . ($deletedRemote + $deletedOrphan + $deletedMissing));

        return 0;
    }

    /**
     * Collect upload ids still referenced by products.
     *
     * @return array
     */
    protected function collectUsedUploadIds()
    {
        $ids = [];

        Product::query()
            ->select(['id', 'thumbnail_img', 'photos', 'meta_img'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$ids) {
                foreach ($products as $product) {
                    foreach ([$product->thumbnail_img, $product->meta_img] as $id) {
                        if ($id !== null && $id !== '' && is_numeric($id)) {
                            $ids[(int) $id] = true;
                        }
                    }
                    if (!empty($product->photos)) {
                        foreach (explode(',', $product->photos) as $photoId) {
                            $photoId = trim($photoId);
                            if ($photoId !== '' && is_numeric($photoId)) {
                                $ids[(int) $photoId] = true;
                            }
                        }
                    }
                }
            });

        return array_keys($ids);
    }

    /**
     * Delete uploads whose file_name is a remote URL or external_link is set.
     *
     * @param array $usedIds
     * @param bool $dryRun
     * @return int
     */
    protected function cleanRemoteUploads(array $usedIds, $dryRun)
    {
        $query = Upload::query()->where(function ($q) {
            $q->where('file_name', 'like', 'http%')
                ->orWhere('file_name', 'like', 'https%')
                ->orWhereNotNull('external_link');
        });

        if (!empty($usedIds)) {
            $query->whereNotIn('id', $usedIds);
        }

        return $this->deleteByQuery($query, $dryRun, 'Remote/external');
    }

    /**
     * Delete collect/import orphans: original name is a URL (or //cdn...),
     * but local file_name is missing / empty. These show as broken alicdn cards.
     *
     * @param array $usedIds
     * @param bool $dryRun
     * @return int
     */
    protected function cleanOrphanUrlUploads(array $usedIds, $dryRun)
    {
        $query = Upload::query()->where(function ($q) {
            $q->where('file_original_name', 'like', 'http%')
                ->orWhere('file_original_name', 'like', '//%')
                ->orWhere('file_name', 'like', 'download/%');
        })->where(function ($q) {
            // exclude pure remote file_name rows (handled by cleanRemoteUploads)
            $q->whereNull('file_name')
                ->orWhere('file_name', 'not like', 'http%');
        });

        if (!empty($usedIds)) {
            $query->whereNotIn('id', $usedIds);
        }

        $deleted = 0;
        $previewed = 0;
        $candidates = 0;

        $query->orderBy('id')->chunkById(200, function ($files) use (&$deleted, &$previewed, &$candidates, $dryRun) {
            $toDelete = [];
            foreach ($files as $file) {
                if (empty($file->file_name) || !file_exists(public_path($file->file_name))) {
                    $toDelete[] = $file->id;
                    $candidates++;
                    if ($dryRun && $previewed < 20) {
                        $label = $file->file_original_name ?: $file->file_name;
                        $this->line("  [dry-run] orphan #{$file->id} {$label}");
                        $previewed++;
                    }
                }
            }

            if (empty($toDelete)) {
                return;
            }

            if ($dryRun) {
                $deleted += count($toDelete);
                return;
            }

            $deleted += Upload::whereIn('id', $toDelete)->delete();
            $this->line('  deleted orphan batch: ' . count($toDelete));
        });

        $this->info("Orphan url/download candidates: {$candidates}");

        return $deleted;
    }

    /**
     * Delete uploads whose local file path no longer exists on disk.
     *
     * @param array $usedIds
     * @param bool $dryRun
     * @return int
     */
    protected function cleanMissingLocalUploads(array $usedIds, $dryRun)
    {
        $query = Upload::query()
            ->whereNotNull('file_name')
            ->where('file_name', 'not like', 'http%')
            ->whereNull('external_link');

        if (!empty($usedIds)) {
            $query->whereNotIn('id', $usedIds);
        }

        $deleted = 0;
        $previewed = 0;
        $candidates = 0;

        $query->orderBy('id')->chunkById(200, function ($files) use (&$deleted, &$previewed, &$candidates, $dryRun) {
            $toDelete = [];
            foreach ($files as $file) {
                $path = public_path($file->file_name);
                if (!file_exists($path)) {
                    $toDelete[] = $file->id;
                    $candidates++;
                    if ($dryRun && $previewed < 20) {
                        $this->line("  [dry-run] missing local #{$file->id} {$file->file_name}");
                        $previewed++;
                    }
                }
            }

            if (empty($toDelete)) {
                return;
            }

            if ($dryRun) {
                $deleted += count($toDelete);
                return;
            }

            $deleted += Upload::whereIn('id', $toDelete)->delete();
            $this->line('  deleted missing-local batch: ' . count($toDelete));
        });

        $this->info("Missing local upload candidates: {$candidates}");

        return $deleted;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $dryRun
     * @param string $label
     * @return int
     */
    protected function deleteByQuery($query, $dryRun, $label)
    {
        $count = (clone $query)->count();
        $this->info("{$label} upload candidates: {$count}");

        if ($count === 0) {
            return 0;
        }

        if ($dryRun) {
            (clone $query)->orderBy('id')->limit(20)->get(['id', 'file_original_name', 'file_name', 'external_link'])
                ->each(function ($file) {
                    $this->line("  [dry-run] #{$file->id} " . ($file->file_name ?: $file->external_link));
                });
            if ($count > 20) {
                $this->line('  ... and ' . ($count - 20) . ' more');
            }
            return $count;
        }

        $deleted = 0;
        $query->orderBy('id')->chunkById(200, function ($files) use (&$deleted) {
            $ids = $files->pluck('id')->all();
            $deleted += Upload::whereIn('id', $ids)->delete();
            $this->line('  deleted batch: ' . count($ids));
        });

        return $deleted;
    }
}

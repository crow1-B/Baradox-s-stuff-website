<?php

namespace App\Support\Http;

use Illuminate\Support\Facades\DB;
use Throwable;

// Rows that record a request rather than serve it, written after the response has gone out so
// a page never waits on them. LogVisit queues its visit here; anything else that only records
// what happened should queue the same way rather than write inline.
// Bound per request (AppServiceProvider) and flushed by FlushDeferredWrites::terminate(),
// one insert per table.
class DeferredWrites
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $rows = [];

    public function insert(string $table, array $row): void
    {
        $this->rows[$table][] = $row;
    }

    public function pending(): bool
    {
        return $this->rows !== [];
    }

    public function flush(): void
    {
        $rows = $this->rows;
        $this->rows = [];

        foreach ($rows as $table => $batch) {
            try {
                DB::table($table)->insert($batch);
            } catch (Throwable $e) {
                // The response is already sent and nobody is left to show an error to, so it
                // goes to the log rather than vanishing.
                report($e);
            }
        }
    }
}

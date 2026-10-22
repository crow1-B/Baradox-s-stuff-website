<?php

namespace Tests\Feature\Backups;

use App\Models\Backup;
use App\Support\Backups\BackupRetention;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackupRetentionTest extends TestCase
{
    private function backup(int $id, string $at, string $trigger = 'scheduled'): Backup
    {
        $backup = new Backup;
        $backup->id = $id;
        $backup->trigger = $trigger;
        $backup->created_at = Carbon::parse($at, 'Asia/Baghdad');

        return $backup;
    }

    private function deletedIds($backups, int $daily, int $weekly, int $manual = 10): array
    {
        return BackupRetention::select(collect($backups), $daily, $weekly, $manual, 'Asia/Baghdad')->pluck('id')->sort()->values()->all();
    }

    public function test_it_keeps_the_newest_of_each_day_and_week(): void
    {
        $backups = [
            $this->backup(1, '2026-10-05 03:00'),
            $this->backup(2, '2026-10-12 03:00'),
            $this->backup(3, '2026-10-19 03:00'),
            $this->backup(4, '2026-10-20 03:00'),
            $this->backup(5, '2026-10-21 03:00'),
            $this->backup(6, '2026-10-22 03:00'),
            $this->backup(7, '2026-10-22 18:00'),
        ];

        $this->assertSame([1, 2, 3, 4, 6], $this->deletedIds($backups, 2, 0));
        $this->assertSame([3, 4, 6], $this->deletedIds($backups, 2, 3));
    }

    public function test_days_without_a_backup_do_not_count(): void
    {
        $backups = [
            $this->backup(1, '2026-09-01 03:00'),
            $this->backup(2, '2026-09-02 03:00'),
            $this->backup(3, '2026-10-22 03:00'),
        ];

        $this->assertSame([], $this->deletedIds($backups, 3, 0));
    }

    public function test_days_are_local_baghdad_days(): void
    {
        $backups = [
            $this->backup(1, '2026-10-21 23:30'),
            $this->backup(2, '2026-10-22 00:30'),
        ];

        $this->assertSame([], $this->deletedIds($backups, 2, 0));
        $this->assertSame([1], $this->deletedIds($backups, 1, 0));
    }

    public function test_manual_backups_are_their_own_bucket(): void
    {
        $backups = [
            $this->backup(1, '2026-10-22 10:00', 'manual'),
            $this->backup(2, '2026-10-22 11:00', 'manual'),
            $this->backup(3, '2026-10-22 12:00', 'manual'),
            $this->backup(4, '2026-10-22 13:00'),
            $this->backup(5, '2026-10-22 14:00'),
        ];

        $this->assertSame([4], $this->deletedIds($backups, 7, 4, 10));
        $this->assertSame([1, 4], $this->deletedIds($backups, 7, 4, 2));
    }

    public function test_prerestore_backups_are_never_selected(): void
    {
        $backups = [
            $this->backup(1, '2026-01-01 10:00', 'prerestore'),
            $this->backup(2, '2026-10-22 10:00', 'manual'),
        ];

        $this->assertSame([], $this->deletedIds($backups, 1, 0, 1));
    }
}

<?php

namespace Tests\Feature\Repositories;

use App\Data\ImportResult;
use App\Models\ImportRun;
use App\Repositories\Contracts\ImportRunRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentImportRunRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var ImportRunRepositoryInterface
     */
    private ImportRunRepositoryInterface $repository;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(ImportRunRepositoryInterface::class);
    }

    /**
     * @return void
     */
    public function test_nothing_is_completed_before_any_import(): void
    {
        $this->assertFalse($this->repository->isCompleted($this->day('2026-10-01')));
        $this->assertNull($this->repository->lastCompletedDate());
    }

    /**
     * @return void
     */
    public function test_a_started_day_is_running_and_not_completed(): void
    {
        $this->repository->markStarted($this->day('2026-10-01'));

        $this->assertDatabaseHas('import_runs', ['status' => ImportRun::STATUS_RUNNING]);
        $this->assertFalse($this->repository->isCompleted($this->day('2026-10-01')));
    }

    /**
     * @return void
     */
    public function test_a_completed_day_stores_the_counters(): void
    {
        $this->repository->markStarted($this->day('2026-10-01'));
        $this->repository->markCompleted($this->importResult('2026-10-01', files: 480));

        $run = ImportRun::query()->sole();

        $this->assertSame(ImportRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(480, $run->files);
        $this->assertSame(1, $run->failed_files);
        $this->assertSame(270000, $run->readings);
        $this->assertSame(1200, $run->rejected_readings);
        $this->assertSame(470, $run->sensor_days);
        $this->assertNotNull($run->finished_at);
        $this->assertTrue($this->repository->isCompleted($this->day('2026-10-01')));
    }

    /**
     * @return void
     */
    public function test_importing_the_same_day_again_keeps_one_row(): void
    {
        $this->repository->markStarted($this->day('2026-10-01'));
        $this->repository->markCompleted($this->importResult('2026-10-01', files: 480));
        $this->repository->markStarted($this->day('2026-10-01'));
        $this->repository->markCompleted($this->importResult('2026-10-01', files: 500));

        $this->assertDatabaseCount('import_runs', 1);
        $this->assertSame(500, ImportRun::query()->value('files'));
    }

    /**
     * @return void
     */
    public function test_the_time_of_day_does_not_matter(): void
    {
        $this->repository->markCompleted($this->importResult('2026-10-01 18:45:00', files: 480));

        $this->assertTrue($this->repository->isCompleted($this->day('2026-10-01 06:00:00')));
        $this->assertFalse($this->repository->isCompleted($this->day('2026-10-02')));
    }

    /**
     * @return void
     */
    public function test_a_failed_day_stores_the_error_and_is_not_completed(): void
    {
        $this->repository->markStarted($this->day('2026-10-01'));
        $this->repository->markFailed($this->day('2026-10-01'), 'Archive unavailable');

        $this->assertDatabaseHas('import_runs', [
            'status' => ImportRun::STATUS_FAILED,
            'error' => 'Archive unavailable',
        ]);
        $this->assertFalse($this->repository->isCompleted($this->day('2026-10-01')));
    }

    /**
     * @return void
     */
    public function test_restarting_a_failed_day_clears_the_error(): void
    {
        $this->repository->markFailed($this->day('2026-10-01'), 'Archive unavailable');
        $this->repository->markStarted($this->day('2026-10-01'));

        $run = ImportRun::query()->sole();

        $this->assertSame(ImportRun::STATUS_RUNNING, $run->status);
        $this->assertNull($run->error);
    }

    /**
     * @return void
     */
    public function test_last_completed_date_ignores_failed_days(): void
    {
        $this->repository->markCompleted($this->importResult('2026-10-01', files: 480));
        $this->repository->markCompleted($this->importResult('2026-10-03', files: 480));
        $this->repository->markFailed($this->day('2026-10-05'), 'Archive unavailable');

        $this->assertSame('2026-10-03', $this->repository->lastCompletedDate()?->toDateString());
    }

    /**
     * @param  string  $date
     * @return CarbonImmutable
     */
    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    /**
     * @param  string  $date
     * @param  int  $files
     * @return ImportResult
     */
    private function importResult(string $date, int $files): ImportResult
    {
        return new ImportResult($this->day($date), $files, 1, 270000, 1200, 470, false);
    }
}

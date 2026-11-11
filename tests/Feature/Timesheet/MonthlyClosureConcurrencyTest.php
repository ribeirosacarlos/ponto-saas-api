<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\User;
use App\Services\Timesheet\MonthlyClosureService;
use App\Services\Timesheet\TimesheetSignatureService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Opt in with TEST_CLOSURE_LOCKS=1 and DB_CONNECTION=pgsql on a disposable database
 * whose name ends in _test. This suite rebuilds that database before each test.
 */
class MonthlyClosureConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('TEST_CLOSURE_LOCKS')) {
            $this->markTestSkipped('Requires an isolated PostgreSQL database and TEST_CLOSURE_LOCKS=1.');
        }

        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql'
            || ! str_ends_with($connection->getDatabaseName(), '_test')
            || ! function_exists('pcntl_fork')) {
            $this->fail('Concurrency tests require PostgreSQL, a disposable database ending in _test, and pcntl.');
        }

        // migrate:fresh only wipes the configured public schema.
        DB::statement('DROP SCHEMA IF EXISTS commercial CASCADE');
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
    }

    public function test_deletion_wins_and_waiting_signature_cannot_sign_deleted_timesheet(): void
    {
        [$admin, $employee, $closure, $timesheet] = $this->fixture();
        $result = $this->race(
            $closure,
            fn () => app(MonthlyClosureService::class)->delete($admin, $closure),
            function () use ($timesheet, $employee) {
                app(TimesheetSignatureService::class)->signAsEmployee($timesheet, $employee, $this->signRequest());
            },
        );

        $this->assertSame(404, $result['status'], json_encode($result));
        $this->assertDatabaseMissing('monthly_closures', ['id' => $closure->id]);
        $this->assertDatabaseMissing('employee_timesheets', ['id' => $timesheet->id]);
        $this->assertDatabaseCount('timesheet_signatures', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_signature_wins_and_waiting_deletion_preserves_signed_timesheet(): void
    {
        [$admin, $employee, $closure, $timesheet] = $this->fixture();
        $result = $this->race(
            $closure,
            fn () => app(TimesheetSignatureService::class)->signAsEmployee($timesheet, $employee, $this->signRequest()),
            fn () => app(MonthlyClosureService::class)->delete($admin, $closure),
        );

        $this->assertSame(422, $result['status'], json_encode($result));
        $this->assertDatabaseHas('monthly_closures', ['id' => $closure->id]);
        $this->assertDatabaseHas('employee_timesheets', [
            'id' => $timesheet->id, 'status' => TimesheetStatus::PENDING_MANAGER->value,
        ]);
        $this->assertDatabaseHas('timesheet_signatures', ['employee_timesheet_id' => $timesheet->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'monthly_closure.deleted']);
    }

    private function race(MonthlyClosure $closure, callable $winner, callable $waiter): array
    {
        // Disconnect before forking: each process must own a separate PostgreSQL session.
        DB::disconnect();
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('Could not fork the competing request.');
        }

        if ($pid === 0) {
            fclose($parentSocket);
            stream_set_timeout($childSocket, 10);
            try {
                DB::statement("SET application_name TO 'monthly_closure_lock_test'");
                DB::statement("SET statement_timeout TO '10s'");
                fwrite($childSocket, "ready\n");
                fgets($childSocket);
                $waiter();
                $result = ['status' => 200];
            } catch (ModelNotFoundException $exception) {
                $result = ['status' => 404];
            } catch (ValidationException $exception) {
                $result = ['status' => 422, 'errors' => $exception->errors()];
            } catch (\Throwable $exception) {
                $result = ['status' => 500, 'error' => $exception->getMessage()];
            }
            fwrite($childSocket, json_encode($result)."\n");
            fclose($childSocket);
            DB::disconnect();
            exit(0);
        }

        fclose($childSocket);
        stream_set_timeout($parentSocket, 12);
        try {
            $this->assertSame("ready\n", fgets($parentSocket));
            DB::beginTransaction();
            MonthlyClosure::lockForUpdate()->findOrFail($closure->id);
            fwrite($parentSocket, "go\n");

            $blocked = false;
            $deadline = microtime(true) + 5;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $blocked = (bool) DB::selectOne("SELECT EXISTS (
                    SELECT 1 FROM pg_stat_activity
                    WHERE application_name = 'monthly_closure_lock_test' AND wait_event_type = 'Lock'
                ) AS blocked")->blocked;
                if (! $blocked) {
                    usleep(20_000);
                }
            } while (! $blocked && microtime(true) < $deadline);
            $this->assertTrue($blocked, 'The competing operation must actually wait on the closure lock.');

            $winner();
            DB::commit();

            $result = json_decode(fgets($parentSocket) ?: '{}', true);
            $this->assertArrayHasKey('status', $result);

            return $result;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            fclose($parentSocket);
            $deadline = microtime(true) + 2;
            do {
                $finished = pcntl_waitpid($pid, $status, WNOHANG);
                if ($finished === $pid || $finished === -1) {
                    break;
                }
                usleep(20_000);
            } while (microtime(true) < $deadline);
            if ($finished === 0) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }
        }
    }

    private function signRequest(): Request
    {
        return Request::create('/sign', 'POST', [
            'password' => 'password', 'accepted_terms' => true,
            'signature_image' => 'data:image/png;base64,'.base64_encode('test-signature'),
        ]);
    }

    private function fixture(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = User::factory()->create(['company_id' => $admin->company_id]);
        $closure = MonthlyClosure::create([
            'company_id' => $admin->company_id, 'employee_id' => $employee->id,
            'closed_by' => $admin->id, 'reference_year' => 2026, 'reference_month' => 4,
            'status' => ClosureStatus::OPEN, 'closed_at' => now(),
        ]);
        $timesheet = EmployeeTimesheet::create([
            'company_id' => $closure->company_id, 'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id, 'status' => TimesheetStatus::PENDING_EMPLOYEE,
            'snapshot' => ['totals' => []], 'snapshot_generated_at' => now(),
        ]);

        return [$admin, $employee, $closure, $timesheet];
    }
}

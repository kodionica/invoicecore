<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase as BaseTestCase;

function requireDedicatedKpoMariaDb(): void
{
    $configuredDatabase = (string) config('database.connections.mariadb.database');
    $expectedDatabase = (string) getenv('KPO_MARIADB_TEST_DATABASE');
    $defaultConnection = DB::connection();
    $mariadbConnection = DB::connection('mariadb');
    $defaultActualDatabase = (string) ($defaultConnection->selectOne('SELECT DATABASE() AS database_name')->database_name ?? '');
    $mariadbActualDatabase = (string) ($mariadbConnection->selectOne('SELECT DATABASE() AS database_name')->database_name ?? '');

    if (config('database.default') !== 'mariadb'
        || $defaultConnection->getName() !== 'mariadb'
        || $mariadbConnection->getName() !== 'mariadb'
        || $expectedDatabase === ''
        || $configuredDatabase !== $expectedDatabase
        || $defaultActualDatabase !== $expectedDatabase
        || $mariadbActualDatabase !== $expectedDatabase
        || ! preg_match('/(?:test|testing|concurrency)/i', $configuredDatabase)
        || in_array(strtolower($configuredDatabase), ['db', 'invoicecore', 'laravel', 'production'], true)) {
        throw new RuntimeException('Refusing KPO MariaDB concurrency tests: dedicated test database is not unambiguously configured.');
    }
}

function createKpoConcurrencyContext(string $suffix, int $invoiceCount = 1): array
{
    $user = User::create([
        'first_name' => 'Concurrency',
        'last_name' => 'Test',
        'email' => "kpo-concurrency-{$suffix}@example.test",
        'username' => "kpo-concurrency-{$suffix}",
        'password' => 'password',
    ]);
    $company = Company::create([
        'name' => "KPO Concurrency {$suffix}",
        'user_id' => $user->id,
        'vat_enabled' => false,
    ]);

    $invoices = [];
    for ($index = 1; $index <= $invoiceCount; $index++) {
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $company->clients()->create(['name' => "Client {$index}"])->id,
            'invoice_number' => "CONC-{$suffix}-{$index}",
            'issue_date' => '2026-10-16',
            'due_date' => '2026-10-31',
            'currency' => 'RSD',
            'fx_rate_to_rsd' => 1,
            'total' => 300,
            'subtotal_original' => 300,
            'tax_original' => 0,
            'total_original' => 300,
            'subtotal_rsd' => 300,
            'tax_rsd' => 0,
            'total_rsd' => 300,
            'vat_enabled_at_issue' => false,
            'status' => 'draft',
            'include_in_kpo' => true,
        ]);
        $invoice->items()->createMany([
            ['name' => 'Product', 'quantity' => 1, 'price' => 100, 'sub_total' => 100, 'total' => 100, 'tax_amount' => 0, 'description' => 'Product', 'income_category' => 'product'],
            ['name' => 'Service', 'quantity' => 1, 'price' => 200, 'sub_total' => 200, 'total' => 200, 'tax_amount' => 0, 'description' => 'Service', 'income_category' => 'service'],
        ]);
        $invoices[] = $invoice;
    }

    return [$company, $invoices];
}

function createKpoConcurrencyTempDirectory(string $suffix): string
{
    $path = tempnam(sys_get_temp_dir(), 'invoicecore-kpo-'.$suffix.'-');

    if ($path === false || ! unlink($path) || ! mkdir($path, 0700)) {
        throw new RuntimeException('Unable to create an isolated KPO concurrency temporary directory.');
    }

    chmod($path, 0700);

    return $path;
}

function cleanupKpoConcurrencyTempDirectory(string $root, array $workerEntries): void
{
    $knownPaths = [$root.'/release'];

    foreach (array_keys($workerEntries) as $index) {
        $knownPaths[] = $root.'/worker-'.$index;
        $knownPaths[] = $root.'/worker-'.$index.'.ready';
    }

    foreach ($knownPaths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    if (is_dir($root)) {
        rmdir($root);
    }
}

function runConcurrentKpoWorkers(
    int $companyId,
    array $workerEntries,
    string $suffix,
    ?string $mode = null,
    int $expectedExitCode = 0,
): array {
    $root = createKpoConcurrencyTempDirectory($suffix);
    $release = $root.'/release';
    $workerPath = base_path('tests/Support/kpo-mariadb-worker.php');
    $processes = [];

    try {
        foreach ($workerEntries as $index => $entries) {
            $process = new Process([
                PHP_BINARY,
                $workerPath,
                (string) $companyId,
                '2026',
                base64_encode(json_encode($entries, JSON_THROW_ON_ERROR)),
                $root.'/worker-'.$index,
                $release,
                $mode ?? '',
            ], base_path(), null, null, 45);
            $process->start();
            $processes[] = $process;
        }

        if ($mode === null) {
            $deadline = microtime(true) + 15;
            foreach ($workerEntries as $index => $_) {
                $ready = $root.'/worker-'.$index.'.ready';
                while (! file_exists($ready) && microtime(true) < $deadline) {
                    usleep(10_000);
                }
                if (! file_exists($ready)) {
                    throw new RuntimeException("Worker {$index} did not reach the concurrency barrier.");
                }
            }
            touch($release);
        }

        foreach ($processes as $process) {
            $process->wait();
            if ($process->getExitCode() !== $expectedExitCode) {
                throw new RuntimeException(sprintf(
                    "MariaDB worker failed with exit code %s (expected %s).\nSTDERR:\n%s\nSTDOUT:\n%s",
                    (string) $process->getExitCode(),
                    (string) $expectedExitCode,
                    $process->getErrorOutput(),
                    $process->getOutput()
                ));
            }
        }

        return array_map(static fn (Process $process): string => $process->getOutput(), $processes);
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(5);
            }

            if ($process->isRunning()) {
                $process->wait();
            }
        }

        cleanupKpoConcurrencyTempDirectory($root, $workerEntries);
    }
}

final class KpoMariaDbConcurrencyTest extends BaseTestCase
{
    protected function setUp(): void
    {
        if (getenv('RUN_KPO_MARIADB_CONCURRENCY') !== '1') {
            $this->markTestSkipped('MariaDB concurrency tests require RUN_KPO_MARIADB_CONCURRENCY=1.');
        }

        parent::setUp();

        // This is intentionally before migrate:fresh and before any fixture write.
        requireDedicatedKpoMariaDb();

        // Safe only after the actual SELECT DATABASE() checks above have passed.
        Artisan::call('migrate:fresh', [
            '--database' => 'mariadb',
            '--force' => true,
        ]);
    }

    public function test_serializes_simultaneous_generation_of_the_same_mixed_invoice(): void
    {
    [$company, $invoices] = createKpoConcurrencyContext('same-invoice');
    $entries = [['invoice_id' => $invoices[0]->id, 'entry_date' => '2026-10-20']];

    runConcurrentKpoWorkers($company->id, [$entries, $entries], 'same-invoice');

        $this->assertSame(2, KpoEntry::where('source_invoice_id', $invoices[0]->id)->count());
        $this->assertSame(['product', 'service'], KpoEntry::where('source_invoice_id', $invoices[0]->id)->pluck('income_category')->sort()->values()->all());
        $this->assertSame(3, DB::table('kpo_counters')->where('company_id', $company->id)->where('year', 2026)->value('next_number'));
    }

    public function test_handles_reverse_order_multi_invoice_generation_without_duplicate_numbers(): void
    {
    [$company, $invoices] = createKpoConcurrencyContext('reverse-order', 2);
    $first = array_map(static fn (Invoice $invoice): array => ['invoice_id' => $invoice->id, 'entry_date' => '2026-10-20'], $invoices);
    $second = array_reverse($first);

    runConcurrentKpoWorkers($company->id, [$first, $second], 'reverse-order');

        $this->assertSame(4, KpoEntry::where('company_id', $company->id)->count());
        $this->assertSame([1, 2, 3, 4], KpoEntry::where('company_id', $company->id)->pluck('entry_number')->sort()->values()->all());
    }

    public function test_initializes_a_shared_company_year_counter_safely_when_different_invoices_race(): void
    {
    [$company, $invoices] = createKpoConcurrencyContext('counter-init', 3);
    $workers = array_map(
        static fn (Invoice $invoice): array => [['invoice_id' => $invoice->id, 'entry_date' => '2026-10-20']],
        $invoices
    );

    runConcurrentKpoWorkers($company->id, $workers, 'counter-init');

        $this->assertSame(6, KpoEntry::where('company_id', $company->id)->count());
        $this->assertSame(6, KpoEntry::where('company_id', $company->id)->distinct('entry_number')->count('entry_number'));
        $this->assertSame(7, DB::table('kpo_counters')->where('company_id', $company->id)->where('year', 2026)->value('next_number'));
    }

    public function test_rolls_back_all_entries_and_the_counter_after_a_worker_failure(): void
    {
    [$company, $invoices] = createKpoConcurrencyContext('rollback');
    $entries = [['invoice_id' => $invoices[0]->id, 'entry_date' => '2026-10-20']];

    runConcurrentKpoWorkers($company->id, [$entries], 'rollback', 'fail_after_first', 1);

        $this->assertSame(0, KpoEntry::where('company_id', $company->id)->count());
        $this->assertSame(0, DB::table('kpo_counters')->where('company_id', $company->id)->count());
    }
}

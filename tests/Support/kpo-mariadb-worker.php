<?php

use App\Models\Company;
use App\Models\KpoEntry;
use App\Services\KpoEntryService;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

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
    fwrite(STDERR, "Unsafe MariaDB test database configuration.\n");
    exit(2);
}

[$companyId, $year, $encodedEntries, $readyFile, $releaseFile, $mode] = array_pad(array_slice($argv, 1), 6, null);
$entries = json_decode(base64_decode((string) $encodedEntries), true, 512, JSON_THROW_ON_ERROR);

if ($mode === 'fail_after_first') {
    KpoEntry::creating(static function (KpoEntry $entry): void {
        if ($entry->income_category === 'service') {
            throw new RuntimeException('Concurrency rollback test failure.');
        }
    });
}

if ($mode !== 'fail_after_first') {
    file_put_contents((string) $readyFile.'.ready', 'ready', LOCK_EX);
    $deadline = microtime(true) + 30;
    while (! file_exists((string) $releaseFile) && microtime(true) < $deadline) {
        usleep(10_000);
    }
    if (! file_exists((string) $releaseFile)) {
        fwrite(STDERR, "Concurrency barrier timed out.\n");
        exit(3);
    }
}

try {
    app(KpoEntryService::class)->generateFromInvoices(
        Company::query()->findOrFail((int) $companyId),
        (int) $year,
        $entries
    );
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    $chain = [];
    for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
        $chain[] = [
            'class' => $current::class,
            'message' => $current->getMessage(),
            'code' => $current->getCode(),
            'sqlstate' => $current instanceof Illuminate\Database\QueryException
                ? ($current->errorInfo[0] ?? null)
                : null,
            'error_info' => $current instanceof Illuminate\Database\QueryException
                ? $current->errorInfo
                : null,
        ];
    }

    fwrite(STDERR, json_encode([
        'message' => 'KPO worker failed.',
        'database' => $configuredDatabase,
        'connection' => DB::connection()->getName(),
        'transaction_level' => DB::connection()->transactionLevel(),
        'exception_chain' => $chain,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    exit(1);
}

<?php

use App\Models\Company;
use App\Models\TaxSchedule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Zxing\QrReader;

function taxCompany(User $user): Company
{
    $company = Company::factory()->for($user)->create();
    $user->update(['active_company_id' => $company->id]);

    return $company;
}

function taxScheduleInput(array $override = []): array
{
    return [
        'request_id' => (string) Str::uuid(),
        'starts_on' => '2026-01-01',
        'frequency' => 'monthly',
        'due_day' => 15,
        'due_month_offset' => 1,
        'roll_forward' => true,
        'reminder_days' => 5,
        'items' => [[
            'category' => 'Porez',
            'name' => 'Paušalni porez',
            'amount_minor' => 100_025,
            'payment_details' => ['R' => '840-955845-10', 'N' => 'Čigra', 'SF' => '253', 'RO' => '9714123412'],
        ]],
        ...$override,
    ];
}

it('creates a full annual decision atomically and generates each period only once', function () {
    $user = User::factory()->create(['username' => Str::random(24)]);
    $company = taxCompany($user);
    $this->actingAs($user);
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-02-12 12:00:00', 'Europe/Belgrade'));
    $url = "/api/companies/{$company->id}/taxes";
    $this->putJson("$url/calendar", ['days' => ['2026-02-16', '2026-02-17']])->assertOk();
    $input = taxScheduleInput(['period_count' => 12]);
    $input['items'] = array_map(fn ($category) => [...$input['items'][0], 'category' => $category, 'name' => $category], TaxSchedule::CATEGORIES);

    $this->postJson("$url/schedules", $input)->assertCreated()->assertJsonCount(4);
    $this->postJson("$url/schedules", $input)->assertCreated()->assertJsonCount(4);
    $this->postJson("$url/schedules", [...$input, 'due_day' => 16])->assertConflict();
    $this->postJson("$url/generate", ['year' => 2026])->assertOk()->assertJsonPath('created', 0);
    $this->postJson("$url/generate", ['year' => 2027])->assertOk()->assertJsonPath('created', 0);

    $this->getJson("$url?year=2026")->assertOk()
        ->assertJsonPath('categories', TaxSchedule::CATEGORIES)
        ->assertJsonCount(48, 'obligations')
        ->assertJsonCount(0, 'reminders')
        ->assertJsonPath('obligations.0.period_end', '2026-01-31')
        ->assertJsonPath('obligations.0.due_on', '2026-02-18')
        ->assertJsonPath('obligations.0.remind_on', '2026-02-13')
        ->assertJsonPath('totals.assessed_minor', 4_801_200);
    $this->travel(1)->day();
    $this->getJson("$url?year=2025")->assertOk()->assertJsonCount(4, 'reminders');
});

it('applies dated payment versions to newly generated periods and preserves recorded snapshots', function () {
    $user = User::factory()->create(['username' => Str::random(24)]);
    $company = taxCompany($user);
    $this->actingAs($user);
    $url = "/api/companies/{$company->id}/taxes";
    $input = taxScheduleInput(['starts_on' => '2026-11-30', 'frequency' => 'quarterly', 'period_count' => 3, 'due_day' => 31, 'due_month_offset' => 0]);
    $id = $this->postJson("$url/schedules", $input)->assertCreated()->json('0.id');
    $version = [
        'effective_on' => '2026-12-01',
        'amount_minor' => 20000,
        'payment_details' => ['R' => '165-55-74', 'N' => 'Drugi primalac', 'SF' => '253'],
    ];
    $this->postJson("$url/schedules/$id/versions", $version)->assertCreated();
    $this->postJson("$url/schedules/$id/versions", $version)->assertCreated();
    $this->postJson("$url/schedules/$id/versions", [...$version, 'amount_minor' => 100])->assertConflict();
    $this->postJson("$url/generate", ['year' => 2027])->assertOk()->assertJsonPath('created', 2);

    $historical = $this->getJson("$url?year=2026")->assertOk()
        ->assertJsonPath('obligations.0.amount_minor', 100_025)
        ->assertJsonPath('obligations.0.period_end', '2027-01-31')->json('obligations.0.id');
    $future = $this->getJson("$url?year=2027")->assertOk()
        ->assertJsonPath('obligations.0.amount_minor', 20000)
        ->assertJsonPath('obligations.0.period_start', '2027-02-01')
        ->assertJsonCount(2, 'obligations')->json('obligations.0.id');
    $this->getJson("$url/obligations/$historical")->assertOk()->assertJsonPath('version.payment_details.R', '840-955845-10');
    $this->getJson("$url/obligations/$future")->assertOk()->assertJsonPath('version.payment_details.R', '165-55-74');
});

it('records variable amounts and idempotent partial payments and renders the remaining amount offline', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create(['username' => Str::random(24)]);
    $company = taxCompany($user);
    $this->actingAs($user);
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-02-22 12:00:00', 'Europe/Belgrade'));
    $url = "/api/companies/{$company->id}/taxes";
    $input = taxScheduleInput(['frequency' => 'once', 'due_on' => '2026-01-31']);
    $input['items'][0]['amount_minor'] = null;
    $input['items'][0]['category'] = 'Posebna naknada';
    $this->postJson("$url/schedules", $input)->assertCreated();
    $id = $this->getJson("$url?year=2026")->assertOk()->assertJsonCount(1, 'obligations')
        ->assertJsonPath('obligations.0.due_on', '2026-02-02')
        ->assertJsonPath('totals.unassessed_count', 1)->json('obligations.0.id');
    $entry = "$url/obligations/$id";
    $payment = ['request_id' => (string) Str::uuid(), 'amount_minor' => 20_000, 'paid_on' => '2026-02-22'];

    $this->getJson($entry)->assertOk()->assertJsonPath('qr.png', null)->assertJsonPath('qr.errors.I.0', 'Unesite iznos obaveze za ovaj period.');
    $this->postJson("$entry/payments", $payment)->assertUnprocessable()->assertJsonValidationErrors('amount_minor');
    $this->patchJson($entry, ['amount_minor' => 60_025, 'note' => 'Rešenje'])->assertOk();
    $this->postJson("$entry/payments", $payment)->assertCreated();
    $this->postJson("$entry/payments", $payment)->assertCreated();
    $this->postJson("$entry/payments", [...$payment, 'amount_minor' => 10])->assertConflict();
    $this->postJson("$entry/payments", [...$payment, 'request_id' => (string) Str::uuid(), 'amount_minor' => 40_026])->assertUnprocessable();
    $this->patchJson($entry, ['amount_minor' => 60_024])->assertConflict();

    $detail = $this->getJson($entry)->assertOk()
        ->assertJsonCount(1, 'payments')
        ->assertJsonPath('outstanding_minor', 40_025)
        ->assertJsonPath('note', 'Rešenje')->json();
    $png = base64_decode(Str::after($detail['qr']['png'], ','));
    $reader = new QrReader($png, QrReader::SOURCE_TYPE_BLOB, false);
    expect($reader->text())->toBe('K:PR|V:01|C:1|R:840000000095584510|N:Čigra|I:RSD400,25|SF:253|RO:9714123412');
    Http::assertNothingSent();

    $this->postJson("$entry/payments", [...$payment, 'request_id' => (string) Str::uuid(), 'amount_minor' => 40_025])->assertCreated();
    $this->getJson($entry)->assertOk()->assertJsonCount(2, 'payments')->assertJsonPath('qr.png', null);
    $this->getJson("$url?year=2026")->assertOk()
        ->assertJsonPath('totals.paid_minor', 60_025)->assertJsonPath('totals.overdue_minor', 0);
});

it('rejects malformed payment tags and identifies missing fields on an incomplete obligation', function () {
    $user = User::factory()->create(['username' => Str::random(24)]);
    $company = taxCompany($user);
    $this->actingAs($user);
    $url = "/api/companies/{$company->id}/taxes";
    $input = taxScheduleInput(['frequency' => 'yearly']);
    $input['items'][0]['payment_details'] = ['RO' => '9715123412'];

    $this->postJson("$url/schedules", [])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
    $this->postJson("$url/schedules", $input)->assertUnprocessable()->assertJsonValidationErrors('items.0.payment_details.RO');
    $this->assertDatabaseEmpty('tax_schedules');
    $input['items'][0]['payment_details'] = [];
    $this->postJson("$url/schedules", $input)->assertCreated();
    $id = $this->getJson("$url?year=2026")->assertOk()->assertJsonCount(1, 'obligations')->json('obligations.0.id');
    $this->getJson("$url/obligations/$id")->assertOk()
        ->assertJsonPath('qr.png', null)->assertJsonStructure(['qr' => ['errors' => ['R', 'N', 'SF', 'RO']]]);
});

it('scopes plans, obligations, payments, attachments and calendar to the owned active company', function () {
    Storage::fake('local');
    $owner = User::factory()->create(['username' => Str::random(24)]);
    $first = taxCompany($owner);
    $other = User::factory()->create(['username' => Str::random(24)]);
    $second = taxCompany($other);
    $url = "/api/companies/{$first->id}/taxes";
    $otherUrl = "/api/companies/{$second->id}/taxes";

    $this->getJson("$url?year=2026")->assertUnauthorized();
    $this->actingAs($owner);
    $this->putJson("$url/calendar", ['days' => ['2026-02-16']])->assertOk();
    $plan = $this->postJson("$url/schedules", taxScheduleInput(['period_count' => 1]))->assertCreated()->json('0.id');
    $entry = $this->getJson("$url?year=2026")->assertOk()->json('obligations.0.id');
    $file = UploadedFile::fake()->image('dokaz.png');
    $attachment = $this->post("$url/schedules/$plan/attachments", ['file' => $file, 'obligation_id' => $entry], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonMissingPath('path')->json('id');
    $this->get("$url/schedules/$plan/attachments/$attachment")->assertOk()->assertDownload('dokaz.png');

    $this->actingAs($other);
    $otherPlan = $this->postJson("$otherUrl/schedules", taxScheduleInput())->assertCreated()->json('0.id');
    $this->getJson("$otherUrl?year=2026")->assertOk()->assertJsonCount(0, 'non_working_days');
    $this->getJson("$url?year=2026")->assertNotFound();
    $this->getJson("$otherUrl/obligations/$entry")->assertNotFound();
    $this->patchJson("$otherUrl/obligations/$entry", ['note' => 'x'])->assertNotFound();
    $this->postJson("$otherUrl/obligations/$entry/payments", [
        'request_id' => (string) Str::uuid(), 'amount_minor' => 1, 'paid_on' => '2026-01-01',
    ])->assertNotFound();
    $this->postJson("$otherUrl/schedules/$plan/versions", [])->assertNotFound();
    $this->get("$otherUrl/schedules/$otherPlan/attachments/$attachment")->assertNotFound();

    $sibling = taxCompany($owner);
    $this->actingAs($owner);
    $this->getJson("$url/obligations/$entry")->assertNotFound();
    $this->getJson("/api/companies/{$sibling->id}/taxes?year=2026")->assertOk()->assertJsonCount(0, 'obligations');
});

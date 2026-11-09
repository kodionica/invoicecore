<?php

use App\Services\IpsQrService;
use App\Services\QrCodeService;
use Illuminate\Validation\ValidationException;
use Zxing\QrReader;

it('renders a PNG with the exact UTF-8 IPS payload', function () {
    $ips = app(IpsQrService::class);
    $payment = [
        'R' => '840-955845-10',
        'N' => "Čigra doo\r\nLeskovac",
        'P' => 'Petar Petrović',
        'SF' => '253',
        'S' => 'Primer uplate',
        'RO' => '9714123412',
    ];
    $expected = "K:PR|V:01|C:1|R:840000000095584510|N:Čigra doo\nLeskovac|I:RSD100,25|P:Petar Petrović|SF:253|S:Primer uplate|RO:9714123412";

    $code = $ips->render($payment, 10025);
    expect($code['payload'])->toBe($expected);
    $reader = new QrReader($code['png'], QrReader::SOURCE_TYPE_BLOB, false);
    expect($reader->text())->toBe($expected);
});

it('omits empty optional tags and serializes the maximum amount exactly', function () {
    $ips = app(IpsQrService::class);
    $payment = ['R' => '840000000095584510', 'N' => 'Primer', 'SF' => '253', 'RO' => null, 'P' => ''];

    expect($ips->payload($payment, IpsQrService::MAX_AMOUNT_MINOR))
        ->toBe('K:PR|V:01|C:1|R:840000000095584510|N:Primer|I:RSD999999999999,99|SF:253');
    expect($ips->payload([...$payment, 'RO' => '00ABC-12'], 1))->toEndWith('|I:RSD0,01|SF:253|RO:00ABC-12');
    expect($ips->payload([...$payment, 'RO' => '9705123456789012345678901'], 1))->toEndWith('|RO:9705123456789012345678901');
    expect($ips->payload([...$payment, 'RO' => '9712123ABC'], 1))->toEndWith('|RO:9712123ABC');
});

it('validates required tags and reports all invalid fields', function () {
    $ips = app(IpsQrService::class);
    expect(array_keys($ips->paymentDetailErrors([])))->toBe(['R', 'N', 'SF']);
    expect(fn () => $ips->payload(['R' => '840-955845-10', 'N' => 'Primer', 'SF' => '253'], -1))
        ->toThrow(ValidationException::class);

    $errors = $ips->paymentDetailErrors([
        'R' => '840-955845-11',
        'N' => 'Naziv|SF:253',
        'P' => "a\nb\nc\nd",
        'SF' => '299',
        'S' => str_repeat('Š', 36),
        'RO' => '9715123412',
    ]);

    expect(array_keys($errors))->toBe(['R', 'N', 'P', 'SF', 'S', 'RO']);
    expect($ips->paymentDetailErrors(['RO' => '9714-123412'], false))->toHaveKey('RO');
    expect($ips->paymentDetailErrors(['RO' => '9712123abc'], false))->toHaveKey('RO');
    expect($ips->paymentDetailErrors(['N' => 'Петар Петровић', 'P' => 'Jean Müller'], false))->toHaveKeys(['N', 'P']);
    expect($ips->paymentDetailErrors(['S' => "Svrha\tplaćanja"], false))->toHaveKey('S');
});

it('generates generic PNG and SVG without any IPS tags', function () {
    $qr = app(QrCodeService::class);
    $reader = new QrReader($qr->png('Tekst: Ž i Ђ'), QrReader::SOURCE_TYPE_BLOB, false);

    expect($reader->text())->toBe('Tekst: Ž i Ђ');
    expect($qr->svg('Tekst: Ž i Ђ'))->toContain('<svg');
});

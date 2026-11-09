<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class IpsQrService
{
    public const MAX_AMOUNT_MINOR = 99_999_999_999_999;

    public const PAYMENT_TAGS = ['R', 'N', 'P', 'SF', 'S', 'RO'];

    // NBS payment bases from Annex 3 of the decision on RSD payment orders.
    private const PAYMENT_BASES = [
        '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31',
        '40', '41', '42', '44', '45', '46', '47', '48', '49', '53', '54', '57', '58',
        '60', '61', '62', '63', '64', '65', '66', '70', '71', '72', '73', '75', '76',
        '77', '78', '79', '80', '81', '82', '83', '84', '85', '86', '87', '88', '89', '90',
    ];

    public function __construct(private QrCodeService $qrCode) {}

    /**
     * Build an NBS payment request (PR). Pass amounts as integer para.
     *
     * @param  array<string, string|null>  $details
     */
    public function payload(array $details, int $amountMinor): string
    {
        $errors = $this->paymentDetailErrors($details);

        if ($amountMinor < 0 || $amountMinor > self::MAX_AMOUNT_MINOR) {
            $errors['I'] = 'Iznos mora biti između 0,00 i 999999999999,99 RSD.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $amount = sprintf('RSD%d,%02d', intdiv($amountMinor, 100), $amountMinor % 100);
        $tags = ['K' => 'PR', 'V' => '01', 'C' => '1', 'R' => $this->account($details['R']), 'N' => $details['N'], 'I' => $amount];

        foreach (['P', 'SF', 'S', 'RO'] as $tag) {
            if (($details[$tag] ?? '') !== '') {
                $tags[$tag] = $details[$tag];
            }
        }

        return implode('|', array_map(
            fn (string $tag, string $value) => $tag.':'.str_replace(["\r\n", "\r"], "\n", $value),
            array_keys($tags),
            array_values($tags),
        ));
    }

    /** @return array{payload: string, png: string} */
    public function render(array $details, int $amountMinor): array
    {
        $payload = $this->payload($details, $amountMinor);

        return ['payload' => $payload, 'png' => $this->qrCode->png($payload)];
    }

    public function paymentDetailErrors(array $details, bool $requireComplete = true): array
    {
        $errors = [];
        $missing = [
            'R' => 'Unesite račun primaoca.',
            'N' => 'Unesite naziv primaoca.',
            'SF' => 'Unesite šifru plaćanja.',
        ];

        foreach (self::PAYMENT_TAGS as $tag) {
            $value = $details[$tag] ?? '';

            if ($value === '') {
                if ($requireComplete && isset($missing[$tag])) {
                    $errors[$tag] = $missing[$tag];
                }

                continue;
            }

            $error = match ($tag) {
                'R' => $this->account($value) === null ? 'Račun primaoca mora imati ispravan format i kontrolni broj.' : null,
                'N', 'P' => $this->validText($value, 70, 3) ? null : 'Koristite srpsku latinicu, brojeve i dozvoljenu interpunkciju u najviše tri reda i 70 znakova.',
                'S' => $this->validText($value, 35, 1) ? null : 'Svrha uplate može imati do 35 znakova u jednom redu, uz srpsku latinicu, brojeve i dozvoljenu interpunkciju.',
                'SF' => $this->validPaymentCode($value) ? null : 'Unesite važeću trocifrenu NBS šifru plaćanja koja počinje cifrom 1 ili 2.',
                'RO' => $this->validReference($value) ? null : 'Unesite model i poziv na broj do 25 znakova. Model 97 zahteva ispravan kontrolni broj i zapis bez crtica.',
            };

            if ($error !== null) {
                $errors[$tag] = $error;
            }
        }

        return $errors;
    }

    private function account(string $account): ?string
    {
        if (preg_match('/\A([0-9]{3})-([0-9]{1,13})-([0-9]{2})\z/', $account, $parts)) {
            $account = $parts[1].str_pad($parts[2], 13, '0', STR_PAD_LEFT).$parts[3];
        }

        return preg_match('/\A[0-9]{18}\z/', $account) && $this->mod97($account) === 1 ? $account : null;
    }

    private function validText(string $value, int $limit, int $maxLines): bool
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $value));

        if (count($lines) > $maxLines || mb_strlen(implode("\n", $lines), 'UTF-8') > $limit) {
            return false;
        }

        foreach ($lines as $line) {
            if (trim($line) === '' || trim($line) !== $line
                || ! preg_match('~\A[A-Za-zČĆŽŠĐčćžšđ0-9 !“”„"\'#\$%&()*+,./:;<=>?@\[\]^_`{}\~‘’\-]+\z~u', $line)) {
                return false;
            }
        }

        return true;
    }

    private function validPaymentCode(string $value): bool
    {
        return preg_match('/\A[12][0-9]{2}\z/', $value)
            && in_array(substr($value, 1), self::PAYMENT_BASES, true);
    }

    private function validReference(string $value): bool
    {
        if (! preg_match('/\A[0-9]{2}[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*\z/', $value) || strlen($value) > 25) {
            return false;
        }

        if (! str_starts_with($value, '97')) {
            return true;
        }

        if (! preg_match('/\A97([0-9]{2})([A-Z0-9]{1,21})\z/', $value, $parts)) {
            return false;
        }

        $digits = preg_replace_callback('/[A-Z]/', fn (array $letter) => (string) (ord($letter[0]) - 55), $parts[2]);

        return (int) $parts[1] === 98 - $this->mod97($digits.'00');
    }

    private function mod97(string $digits): int
    {
        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder;
    }
}

<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ProfileValidator;

final class ClientProfile
{
    public const LANGUAGES = ['sr-Latn' => 'Srpski (latinica)'];

    public static function countries(): array
    {
        return explode(' ', 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW');
    }

    public static function recognizeCountry(?string $value): ?string
    {
        $value = trim($value ?? '');
        $code = match (mb_strtolower($value)) {
            'srbija', 'serbia', 'србија' => 'RS',
            'nemačka', 'nemacka', 'germany', 'deutschland' => 'DE',
            'austrija', 'austria', 'österreich' => 'AT',
            'francuska', 'france' => 'FR',
            'malta' => 'MT',
            'ujedinjeno kraljevstvo', 'united kingdom', 'uk' => 'GB',
            'sjedinjene američke države', 'united states', 'usa' => 'US',
            default => strtoupper($value),
        };

        return in_array($code, self::countries(), true) ? $code : null;
    }

    public static function normalize(array $input, bool $creating): array
    {
        foreach ($input as $field => $value) {
            if (is_string($value)) {
                $input[$field] = trim($value) === '' ? null : trim($value);
            }
        }
        if ($creating) {
            $input['client_type'] ??= 'company';
            if (! array_key_exists('country_code', $input)) {
                $country = $input['country'] ?? null;
                $input['country_code'] = $country === null ? 'RS' : (is_string($country) ? self::recognizeCountry($country) : null);
            }
        }

        if (is_string($input['client_type'] ?? null)) {
            $input['client_type'] = match ($input['client_type']) {
                'b2b' => 'company', 'b2c' => 'individual', default => $input['client_type'],
            };
        }
        foreach (['country_code', 'default_currency'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = strtoupper($input[$field]);
            }
        }

        return $input;
    }

    public static function validator(array $input, int $companyId, ?Client $client = null): ProfileValidator
    {
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_type' => ['sometimes', 'required', Rule::in(['company', 'entrepreneur', 'individual'])],
            'country_code' => ['sometimes', 'required', Rule::in(self::countries())],
            'email' => ['nullable', 'email', 'max:255'],
            'default_currency' => ['nullable', Rule::in(array_keys(config('currency')))],
            'preferred_language' => ['nullable', Rule::in(array_keys(self::LANGUAGES))],
        ];
        if (! $client) {
            foreach (['name', 'client_type', 'country_code'] as $required) {
                array_shift($rules[$required]);
            }
        }
        foreach (['address', 'city', 'country', 'phone', 'postal_code', 'contact_person', 'tax_id', 'registration_number'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        $input = array_intersect_key($input, $rules);
        if ($client) {
            $input = array_filter($input, fn ($value, string $field) => $value !== $client->getAttribute($field), ARRAY_FILTER_USE_BOTH);
        }

        $validator = Validator::make($input, $rules);
        $validator->after(function (ProfileValidator $validator) use ($input, $companyId, $client): void {
            $country = $input['country_code'] ?? $client?->country_code;
            $countryChanged = array_key_exists('country_code', $input) && $country !== $client?->country_code;
            foreach (['tax_id', 'registration_number'] as $field) {
                if ($validator->errors()->has($field) || $validator->errors()->has('country_code')) {
                    continue;
                }
                if (! $countryChanged && ! array_key_exists($field, $input)) {
                    continue;
                }
                $value = array_key_exists($field, $input) ? $input[$field] : $client?->$field;
                if ($value === null) {
                    continue;
                }
                if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
                    $validator->errors()->add($field, 'Identifikator sadrži nedozvoljene kontrolne znakove.');
                    continue;
                }
                if ($country === 'RS' && ! ($field === 'tax_id' ? self::validPib($value) : preg_match('/^[0-9]{8}$/D', $value))) {
                    $validator->errors()->add($field, $field === 'tax_id' ? 'Unesite ispravan PIB od 9 cifara.' : 'Matični broj mora imati 8 cifara.');
                    continue;
                }
                if ($country && Client::query()->where('company_id', $companyId)->where('country_code', $country)
                    ->where($field, $value)->when($client, fn ($query) => $query->whereKeyNot($client->id))->exists()) {
                    $validator->errors()->add($field, 'Identifikator je već u upotrebi za ovu državu.');
                }
            }
        });

        return $validator;
    }

    public static function conflictField(\Illuminate\Database\UniqueConstraintViolationException $exception): string
    {
        $message = $exception->getMessage();

        return str_contains($message, 'clients_country_registration_unique')
            || str_contains($message, 'clients.registration_number')
            ? 'registration_number' : 'tax_id';
    }

    private static function validPib(string $value): bool
    {
        if (! preg_match('/^[0-9]{9}$/D', $value) || (int) substr($value, 0, 8) < 10000001) {
            return false;
        }
        $check = 10;
        for ($i = 0; $i < 8; $i++) {
            $check = ((($check + (int) $value[$i]) % 10 ?: 10) * 2) % 11;
        }

        return (11 - $check) % 10 === (int) $value[8];
    }
}

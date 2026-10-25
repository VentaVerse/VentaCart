<?php

namespace App\Services\Address;

use App\Services\AddressProviderRegistry;
use Illuminate\Validation\ValidationException;

/**
 * Which deep address levels (e.g. PH district, barangay) an address still
 * owes, judged the way the dropdowns are drawn: a required level counts only
 * when it has options under the parent the address chose. So barangay is owed
 * by every PH address, district only by a city that has districts (Manila),
 * and nothing at all by a country without a deep provider.
 *
 * Reported 2026-10-21: a Manila customer skipped the optional district, so
 * the barangay dropdown (which hangs off the district) never appeared, and
 * the address saved and checked out with neither.
 */
class DeepAddressRules
{
    public function __construct(
        private AddressProviderRegistry $registry,
        private DeepAddressData $data,
    ) {}

    /**
     * Keys of the required levels the address is missing, in cascade order.
     *
     * @param  array<string, ?string>  $address  level key => chosen name (state, city, district, barangay)
     * @return array<int, string>
     */
    public function missing(?int $countryId, array $address): array
    {
        $levels = $this->levels($countryId);
        if ($levels === null) {
            return [];
        }

        $iso = $this->registry->get($countryId)->countryCode();
        $codes = [];
        $missing = [];

        foreach ($levels as $level) {
            $key = $level['key'];
            $value = trim((string) ($address[$key] ?? ''));

            // Once a required level is missing, every later required level
            // is too: its dropdown hangs off the one not chosen.
            if ($missing !== []) {
                if (($level['required'] ?? false) && empty($level['conditional'])) {
                    $missing[] = $key;
                }
                continue;
            }

            $options = $this->data->level($iso, $key, $this->parentCode($level, $codes));
            if ($options === []) {
                continue;
            }

            if ($value === '') {
                if ($level['required'] ?? false) {
                    $missing[] = $key;
                }
                continue;
            }

            $match = collect($options)->firstWhere('name', $value);
            $codes[$key] = $match['id'] ?? null;
        }

        return $missing;
    }

    /**
     * Refuse a form whose address is missing a level, naming each field.
     *
     * @param  array<string, mixed>  $input   the validated request data
     * @param  string                $prefix  field prefix, e.g. "shipping_" or "addresses.0."
     */
    public function validate(array $input, string $prefix = '', ?string $countryField = null): void
    {
        $countryId = $input[$countryField ?? $prefix . 'country_id'] ?? null;
        $address = [];
        foreach ($this->levels($countryId ? (int) $countryId : null) ?? [] as $level) {
            $address[$level['key']] = $input[$prefix . $level['key']] ?? null;
        }

        $missing = $this->missing($countryId ? (int) $countryId : null, $address);
        if ($missing === []) {
            return;
        }

        $labels = $this->labels($countryId ? (int) $countryId : null);
        $messages = [];
        foreach ($missing as $key) {
            $messages[$prefix . $key] = 'Choose a ' . strtolower($labels[$key] ?? $key) . '.';
        }

        throw ValidationException::withMessages($messages);
    }

    /**
     * The checkout's sentence for a saved address that is missing a level,
     * or null when it is complete.
     */
    public function checkoutMessage(?int $countryId, array $address, string $which = 'delivery'): ?string
    {
        $missing = $this->missing($countryId, $address);
        if ($missing === []) {
            return null;
        }

        $labels = $this->labels($countryId);
        $names = array_map(fn ($key) => strtolower($labels[$key] ?? $key), $missing);

        return sprintf(
            'Your %s address is missing its %s. Edit the address and choose %s to continue.',
            $which,
            implode(' and ', $names),
            count($names) > 1 ? 'them' : 'one',
        );
    }

    /** @return array<int, array>|null the deep levels for a country, or null when it has none */
    private function levels(?int $countryId): ?array
    {
        if (!$countryId || !$this->registry->has($countryId)) {
            return null;
        }

        $iso = $this->registry->get($countryId)->countryCode();

        return config('localization.deep_address.' . strtoupper($iso) . '.levels');
    }

    /** @return array<string, string> level key => label */
    private function labels(?int $countryId): array
    {
        return collect($this->levels($countryId) ?? [])->pluck('label', 'key')->all();
    }

    /** The chosen parent's code; for "a|b" the deepest one chosen, as the dropdowns do. */
    private function parentCode(array $level, array $codes): ?string
    {
        if (empty($level['parent'])) {
            return null;
        }

        foreach (array_reverse(explode('|', $level['parent'])) as $source) {
            if (!empty($codes[$source])) {
                return (string) $codes[$source];
            }
        }

        return '';
    }
}

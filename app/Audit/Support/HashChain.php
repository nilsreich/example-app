<?php

namespace App\Audit\Support;

final class HashChain
{
    /**
     * Berechnet den Hash eines Audit-Blocks: sha256(prev_hash || kanonisches JSON des Inhalts).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function hash(?string $previousHash, array $payload): string
    {
        return hash('sha256', ($previousHash ?? '').self::canonicalJson($payload));
    }

    /**
     * Prüft, ob ein hinterlegter Hash zum (vorherigen) Hash und Inhalt passt.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function verify(?string $previousHash, array $payload, string $givenHash): bool
    {
        return hash_equals($givenHash, self::hash($previousHash, $payload));
    }

    /**
     * Deterministische JSON-Serialisierung: rekursiv sortierte Schlüssel,
     * unescaped Slashes/Unicode – Reihenfolge von assoziativen Schlüsseln ändert den Hash nicht.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function canonicalJson(array $payload): string
    {
        self::sortRecursively($payload);

        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private static function sortRecursively(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                self::sortRecursively($value);
            }
        }
    }
}

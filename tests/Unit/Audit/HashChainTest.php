<?php

namespace Tests\Unit\Audit;

use App\Audit\Support\HashChain;
use PHPUnit\Framework\TestCase;

final class HashChainTest extends TestCase
{
    public function test_hash_is_deterministic_and_sha256_length(): void
    {
        $payload = ['event_type' => 'login', 'actor_user_id' => 42];

        $first = HashChain::hash(null, $payload);
        $second = HashChain::hash(null, $payload);

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));
    }

    public function test_hash_changes_when_payload_changes(): void
    {
        $base = HashChain::hash(null, ['event_type' => 'login']);

        $changed = HashChain::hash(null, ['event_type' => 'logout']);

        $this->assertNotSame($base, $changed);
    }

    public function test_hash_chains_on_previous_hash(): void
    {
        $first = HashChain::hash(null, ['version' => 1]);

        $secondWithFirst = HashChain::hash($first, ['version' => 2]);
        $secondWithOther = HashChain::hash(str_repeat('0', 64), ['version' => 2]);

        $this->assertNotSame($secondWithFirst, $secondWithOther);
        $this->assertTrue(HashChain::verify($first, ['version' => 2], $secondWithFirst));
        $this->assertFalse(HashChain::verify(str_repeat('0', 64), ['version' => 2], $secondWithFirst));
    }

    public function test_canonicalization_ignores_associative_key_order(): void
    {
        $a = HashChain::hash(null, ['b' => 1, 'a' => 2]);
        $b = HashChain::hash(null, ['a' => 2, 'b' => 1]);

        $this->assertSame($a, $b);
    }

    public function test_canonicalization_ignores_key_order_in_nested_arrays(): void
    {
        $a = HashChain::hash(null, ['previous_state' => ['b' => 1, 'a' => 2]]);
        $b = HashChain::hash(null, ['previous_state' => ['a' => 2, 'b' => 1]]);

        $this->assertSame($a, $b);
    }

    public function test_verify_rejects_tampered_payload(): void
    {
        $first = HashChain::hash(null, ['version' => 1]);
        $second = HashChain::hash($first, ['version' => 2]);

        $this->assertFalse(HashChain::verify($first, ['version' => 999], $second));
    }

    public function test_verify_rejects_wrong_previous_hash(): void
    {
        $first = HashChain::hash(null, ['version' => 1]);
        $second = HashChain::hash($first, ['version' => 2]);

        $this->assertFalse(HashChain::verify($second, ['version' => 2], $second));
    }
}

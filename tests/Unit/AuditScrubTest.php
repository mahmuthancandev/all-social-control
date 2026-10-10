<?php

namespace Tests\Unit;

use App\Support\Audit;
use PHPUnit\Framework\TestCase;

class AuditScrubTest extends TestCase
{
    public function test_sensitive_keys_are_masked_recursively(): void
    {
        $scrubbed = Audit::scrub([
            'email' => 'a@b.com',
            'password' => 'x',
            'oauth' => ['access_token' => 'abc', 'refresh_token' => 'def', 'scope' => 'read'],
        ]);

        $this->assertSame('a@b.com', $scrubbed['email']);
        $this->assertSame('[GİZLENDİ]', $scrubbed['password']);
        $this->assertSame('[GİZLENDİ]', $scrubbed['oauth']['access_token']);
        $this->assertSame('[GİZLENDİ]', $scrubbed['oauth']['refresh_token']);
        $this->assertSame('read', $scrubbed['oauth']['scope']);
    }
}

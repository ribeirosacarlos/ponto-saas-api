<?php

namespace Tests\Unit\Support;

use App\Support\Audit\AuditSanitizer;
use PHPUnit\Framework\TestCase;

class AuditSanitizerTest extends TestCase
{
    public function test_it_redacts_sensitive_keys_recursively(): void
    {
        $sanitized = AuditSanitizer::sanitize([
            'password' => 'secret',
            'profile' => [
                'api_token' => 'abc',
                'invite_code_hash' => 'hash',
                'name' => 'Carlos',
            ],
        ]);

        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['profile']['api_token']);
        $this->assertSame('[REDACTED]', $sanitized['profile']['invite_code_hash']);
        $this->assertSame('Carlos', $sanitized['profile']['name']);
    }
}

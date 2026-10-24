<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** RFC 6238 appendix B: SHA-1 secret "12345678901234567890" in base32. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_it_matches_the_rfc_6238_sha1_test_vectors(): void
    {
        $totp = new Totp;

        $this->assertSame('287082', $totp->codeAt(self::RFC_SECRET, 59));
        $this->assertSame('081804', $totp->codeAt(self::RFC_SECRET, 1111111109));
        $this->assertSame('050471', $totp->codeAt(self::RFC_SECRET, 1111111111));
    }

    public function test_it_accepts_one_step_of_clock_drift_but_no_more(): void
    {
        $totp = new Totp;
        $code = $totp->codeAt(self::RFC_SECRET, 1111111109);

        $this->assertTrue($totp->verify(self::RFC_SECRET, $code, 1111111109 + 30));
        $this->assertFalse($totp->verify(self::RFC_SECRET, $code, 1111111109 + 90));
    }

    public function test_it_rejects_malformed_codes(): void
    {
        $totp = new Totp;

        $this->assertFalse($totp->verify(self::RFC_SECRET, 'abcdef', 59));
        $this->assertFalse($totp->verify(self::RFC_SECRET, '28708', 59));
    }
}

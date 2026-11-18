<?php

use PHPUnit\Framework\TestCase;

final class PasswordHashingTest extends TestCase
{
    private PasswordHashingCore $hashing;

    protected function setUp(): void
    {
        if (!defined('_COOKIE_KEY_')) {
            define('_COOKIE_KEY_', 'unit-test-static-key');
        }
        require_once dirname(__DIR__, 2).'/classes/PasswordHashing.php';
        $this->hashing = new PasswordHashingCore();
    }

    public function testNewPasswordsUseBcrypt(): void
    {
        $hash = $this->hashing->passwordHash('Correct-Horse-42');

        self::assertTrue(password_verify('Correct-Horse-42', $hash));
        self::assertTrue($this->hashing->isPrimaryHash('Correct-Horse-42', $hash));
        self::assertFalse($this->hashing->validateHash('wrong-password', $hash));
    }

    public function testLegacyMd5PasswordsRemainMigratable(): void
    {
        $legacyHash = md5(_COOKIE_KEY_.'legacy-password');

        self::assertTrue($this->hashing->validateHash('legacy-password', $legacyHash));
        self::assertFalse($this->hashing->isPrimaryHash('legacy-password', $legacyHash));
    }
}

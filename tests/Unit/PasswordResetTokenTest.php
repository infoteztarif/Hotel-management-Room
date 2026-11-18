<?php

use PHPUnit\Framework\TestCase;

final class PasswordResetTokenTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('_COOKIE_KEY_')) {
            define('_COOKIE_KEY_', 'unit-test-static-key');
        }
        require_once dirname(__DIR__, 2).'/classes/PasswordResetToken.php';
    }

    public function testValidTokenIsAccepted(): void
    {
        $token = PasswordResetTokenCore::create('customer', 42, 'current-password-hash');

        self::assertTrue(PasswordResetTokenCore::isValid(
            'customer',
            42,
            'current-password-hash',
            $token['expires'],
            $token['token']
        ));
    }

    public function testTokenIsBoundToAccountAndCurrentPassword(): void
    {
        $token = PasswordResetTokenCore::create('employee', 7, 'old-password-hash');

        self::assertFalse(PasswordResetTokenCore::isValid('employee', 8, 'old-password-hash', $token['expires'], $token['token']));
        self::assertFalse(PasswordResetTokenCore::isValid('employee', 7, 'new-password-hash', $token['expires'], $token['token']));
        self::assertFalse(PasswordResetTokenCore::isValid('customer', 7, 'old-password-hash', $token['expires'], $token['token']));
    }

    public function testExpiredAndMalformedTokensAreRejected(): void
    {
        $token = PasswordResetTokenCore::create('customer', 42, 'hash', time() - 10);

        self::assertFalse(PasswordResetTokenCore::isValid('customer', 42, 'hash', $token['expires'], $token['token']));
        self::assertFalse(PasswordResetTokenCore::isValid('customer', 42, 'hash', time() + 60, 'not-a-token'));
    }
}

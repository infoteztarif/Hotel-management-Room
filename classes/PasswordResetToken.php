<?php
/**
 * Short-lived, stateless password-reset tokens.
 *
 * The current password hash is part of the signature input, which makes every
 * outstanding token unusable immediately after a successful password change.
 */
class PasswordResetTokenCore
{
    const DEFAULT_TTL = 3600;

    public static function create($accountType, $accountId, $passwordHash, $expiresAt = null)
    {
        $expiresAt = $expiresAt ?: time() + self::DEFAULT_TTL;
        $payload = self::payload($accountType, $accountId, $expiresAt, $passwordHash);

        return array(
            'expires' => (int) $expiresAt,
            'token' => hash_hmac('sha256', $payload, self::secret()),
        );
    }

    public static function isValid($accountType, $accountId, $passwordHash, $expiresAt, $token)
    {
        $expiresAt = (int) $expiresAt;
        if ($expiresAt < time() || $expiresAt > time() + self::DEFAULT_TTL + 300) {
            return false;
        }

        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return false;
        }

        $expected = self::create($accountType, $accountId, $passwordHash, $expiresAt);

        return hash_equals($expected['token'], $token);
    }

    private static function payload($accountType, $accountId, $expiresAt, $passwordHash)
    {
        return implode('|', array(
            (string) $accountType,
            (int) $accountId,
            (int) $expiresAt,
            (string) $passwordHash,
        ));
    }

    private static function secret()
    {
        if (!defined('_COOKIE_KEY_') || strlen(_COOKIE_KEY_) < 16) {
            throw new RuntimeException('A valid application secret is required for password reset tokens.');
        }

        return _COOKIE_KEY_;
    }
}

<?php
use PHPUnit\Framework\TestCase;

class SessionAndSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    public function testCsrfTokenGenerationAndVerification()
    {
        $token = generateCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
        $this->assertTrue(verifyCsrfToken($token));
        $this->assertFalse(verifyCsrfToken('invalid_token_string_here_1234567890'));
        $this->assertFalse(verifyCsrfToken(''));
    }

    public function testCsrfFieldHelper()
    {
        $field = csrfField();
        $this->assertStringContainsString('name="' . CSRF_TOKEN_NAME . '"', $field);
        $this->assertStringContainsString('type="hidden"', $field);
    }

    public function testRoleCheckHelpers()
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['user_role'] = 'admin';
        $this->assertTrue(isAdmin());
        $this->assertFalse(isStaff());
        $this->assertFalse(isUser());
        $this->assertTrue(isStaffOrAdmin());

        $_SESSION['user_role'] = 'staff';
        $this->assertFalse(isAdmin());
        $this->assertTrue(isStaff());
        $this->assertFalse(isUser());
        $this->assertTrue(isStaffOrAdmin());

        $_SESSION['user_role'] = 'user';
        $this->assertFalse(isAdmin());
        $this->assertFalse(isStaff());
        $this->assertTrue(isUser());
        $this->assertFalse(isStaffOrAdmin());
    }

    public function testFlashMessaging()
    {
        setFlash('success', 'Operation completed successfully.');
        $flash = getFlash();
        $this->assertNotNull($flash);
        $this->assertEquals('success', $flash['type']);
        $this->assertEquals('Operation completed successfully.', $flash['message']);

        // getFlash clears the flash message
        $this->assertNull(getFlash());
    }

    public function testReleaseReadOnlySessionPreservesFlashAndCsrf()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        setFlash('info', 'Preloaded before session lock release.');
        releaseReadOnlySession();

        $this->assertNotEmpty(generateCsrfToken());
        $flash = getFlash();
        $this->assertNotNull($flash);
        $this->assertEquals('info', $flash['type']);
        $this->assertEquals('Preloaded before session lock release.', $flash['message']);
        $this->assertNull(getFlash());
    }

    public function testDashboardUrlResolution()
    {
        $this->assertStringContainsString('admin/dashboard.php', getDashboardUrl('admin'));
        $this->assertStringContainsString('staff/dashboard.php', getDashboardUrl('staff'));
        $this->assertStringContainsString('user/dashboard.php', getDashboardUrl('user'));
    }

    public function testResetRequestTokenHashingAndVerification()
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hashResetRequestToken($raw);

        $this->assertEquals(64, strlen($hash));
        $this->assertNotEquals($raw, $hash);
        $this->assertTrue(verifyResetRequestToken($hash, $raw));
        $this->assertFalse(verifyResetRequestToken($hash, 'wrong-token'));
        $this->assertFalse(verifyResetRequestToken(null, $raw));
        $this->assertFalse(verifyResetRequestToken($hash, ''));
    }

    public function testBuildResetStatusPayloadPendingAndDummyWatch()
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hashResetRequestToken($raw);

        // Dummy watch for non-existent email prevents enumeration
        $dummy = buildResetStatusPayload(null, [
            'request_id' => 0,
            'token'      => $raw,
            'email'      => 'nobody@example.com',
            'created_at' => strtotime('2026-11-19 20:00:00'),
        ]);
        $this->assertEquals('pending', $dummy['status']);
        $this->assertEquals('nobody@example.com', $dummy['email']);
        $this->assertArrayNotHasKey('new_password', $dummy);

        // Real pending request with valid token
        $pending = buildResetStatusPayload([
            'id'            => 7,
            'email'         => 'lan@gmail.com',
            'status'        => 'pending',
            'request_token' => $hash,
            'requested_at'  => '2026-11-19 19:58:00',
        ], [
            'request_id' => 7,
            'token'      => $raw,
            'email'      => 'lan@gmail.com',
        ]);
        $this->assertEquals('pending', $pending['status']);
        $this->assertEquals('lan@gmail.com', $pending['email']);
    }

    public function testBuildResetStatusPayloadRejectsMismatchedToken()
    {
        $hash = hashResetRequestToken('expected-token');
        $payload = buildResetStatusPayload([
            'id'                    => 7,
            'email'                 => 'lan@gmail.com',
            'status'                => 'completed',
            'dispatch_type'         => 'password',
            'new_password_assigned' => 'SecretPass123',
            'request_token'         => $hash,
            'resolved_at'           => '2026-11-19 20:00:00',
            'user_notified_at'      => null,
        ], [
            'request_id' => 7,
            'token'      => 'attacker-token',
            'email'      => 'lan@gmail.com',
        ], 15, strtotime('2026-11-19 20:02:00'));

        $this->assertEquals('none', $payload['status']);
        $this->assertArrayNotHasKey('new_password', $payload);
    }

    public function testBuildResetStatusPayloadCompletedPasswordAndOneTimeConsumption()
    {
        $raw = 'valid-session-watch-token';
        $hash = hashResetRequestToken($raw);
        $now = strtotime('2026-11-19 20:05:00');

        // Completed & unacknowledged within 15 minutes -> returns password
        $ready = buildResetStatusPayload([
            'id'                    => 7,
            'email'                 => 'lan@gmail.com',
            'status'                => 'completed',
            'dispatch_type'         => 'password',
            'new_password_assigned' => 'LanNewPass99',
            'request_token'         => $hash,
            'resolved_at'           => '2026-11-19 20:00:00',
            'user_notified_at'      => null,
        ], [
            'request_id' => 7,
            'token'      => $raw,
            'email'      => 'lan@gmail.com',
        ], 15, $now);

        $this->assertEquals('completed', $ready['status']);
        $this->assertEquals('password', $ready['dispatch_type']);
        $this->assertEquals('LanNewPass99', $ready['new_password']);
        $this->assertEquals('login.php?email=lan%40gmail.com', $ready['login_url']);

        // Already acknowledged -> strips password
        $acked = buildResetStatusPayload([
            'id'                    => 7,
            'email'                 => 'lan@gmail.com',
            'status'                => 'completed',
            'dispatch_type'         => 'password',
            'new_password_assigned' => 'LanNewPass99',
            'request_token'         => $hash,
            'resolved_at'           => '2026-11-19 20:00:00',
            'user_notified_at'      => '2026-11-19 20:03:00',
        ], [
            'request_id' => 7,
            'token'      => $raw,
            'email'      => 'lan@gmail.com',
        ], 15, $now);

        $this->assertEquals('acknowledged', $acked['status']);
        $this->assertArrayNotHasKey('new_password', $acked);

        // Expired (>15 minutes after resolved_at) -> strips password
        $expired = buildResetStatusPayload([
            'id'                    => 7,
            'email'                 => 'lan@gmail.com',
            'status'                => 'completed',
            'dispatch_type'         => 'password',
            'new_password_assigned' => 'LanNewPass99',
            'request_token'         => $hash,
            'resolved_at'           => '2026-11-19 19:40:00',
            'user_notified_at'      => null,
        ], [
            'request_id' => 7,
            'token'      => $raw,
            'email'      => 'lan@gmail.com',
        ], 15, $now);

        $this->assertEquals('expired', $expired['status']);
        $this->assertArrayNotHasKey('new_password', $expired);
    }

    public function testBuildResetStatusPayloadCompletedLinkAndExpiry()
    {
        $raw = 'link-watch-token';
        $hash = hashResetRequestToken($raw);
        $now = strtotime('2026-11-19 20:05:00');

        $readyLink = buildResetStatusPayload([
            'id'                    => 8,
            'email'                 => 'lan@gmail.com',
            'status'                => 'completed',
            'dispatch_type'         => 'link',
            'new_password_assigned' => null,
            'request_token'         => $hash,
            'resolved_at'           => '2026-11-19 20:01:00',
            'user_notified_at'      => null,
            'reset_token'           => 'abc123resettoken',
            'reset_expires'         => '2026-11-19 22:01:00',
        ], [
            'request_id' => 8,
            'token'      => $raw,
            'email'      => 'lan@gmail.com',
        ], 15, $now);

        $this->assertEquals('completed', $readyLink['status']);
        $this->assertEquals('link', $readyLink['dispatch_type']);
        $this->assertEquals('reset-password.php?token=abc123resettoken', $readyLink['reset_url']);
        $this->assertArrayNotHasKey('new_password', $readyLink);

        // Expired reset_expires on users table -> expired
        $expiredLink = buildResetStatusPayload([
            'id'                    => 8,
            'email'                 => 'lan@gmail.com',
            'status'                => 'completed',
            'dispatch_type'         => 'link',
            'new_password_assigned' => null,
            'request_token'         => $hash,
            'resolved_at'           => '2026-11-19 20:01:00',
            'user_notified_at'      => null,
            'reset_token'           => 'abc123resettoken',
            'reset_expires'         => '2026-11-19 20:00:00',
        ], [
            'request_id' => 8,
            'token'      => $raw,
            'email'      => 'lan@gmail.com',
        ], 15, $now);

        $this->assertEquals('expired', $expiredLink['status']);
        $this->assertArrayNotHasKey('reset_url', $expiredLink);
    }
}


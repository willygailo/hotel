<?php
declare(strict_types=1);

use MarvelousHotel\ApiAppFactory;
use MarvelousHotel\Services\RoomService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class SlimApiAndArchitectureTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name'], $_SESSION['user_email']);
    }

    public function testThreeTierDirectoryStructureAndFrameworksExist(): void
    {
        // Tier 1: Frontend (ALL pages, portals, components, Tailwind CSS + Alpine.js + HotelAPI)
        $this->assertDirectoryExists(SITE_ROOT . '/frontend/pages');
        $this->assertDirectoryExists(SITE_ROOT . '/frontend/auth');
        $this->assertDirectoryExists(SITE_ROOT . '/frontend/user');
        $this->assertDirectoryExists(SITE_ROOT . '/frontend/staff');
        $this->assertDirectoryExists(SITE_ROOT . '/frontend/admin');
        $this->assertFileExists(SITE_ROOT . '/frontend/pages/index.php');
        $this->assertFileExists(SITE_ROOT . '/frontend/pages/rooms.php');
        $this->assertFileExists(SITE_ROOT . '/frontend/tailwind.config.js');
        $this->assertFileExists(SITE_ROOT . '/frontend/assets/css/tailwind.css');
        $this->assertFileExists(SITE_ROOT . '/frontend/assets/vendor/alpine.min.js');
        $this->assertFileExists(SITE_ROOT . '/frontend/assets/js/api-client.js');
        $this->assertFileExists(SITE_ROOT . '/frontend/components/frontend-frameworks.php');

        // Tier 2: Backend (Slim 4 + PSR-4 Controllers/Services/Middleware + Core + AI + Endpoints)
        $this->assertDirectoryExists(SITE_ROOT . '/backend/core');
        $this->assertDirectoryExists(SITE_ROOT . '/backend/src/Controllers');
        $this->assertDirectoryExists(SITE_ROOT . '/backend/src/Services');
        $this->assertDirectoryExists(SITE_ROOT . '/backend/src/Middleware');
        $this->assertDirectoryExists(SITE_ROOT . '/backend/ai');
        $this->assertDirectoryExists(SITE_ROOT . '/backend/endpoints');
        $this->assertFileExists(SITE_ROOT . '/backend/api/index.php');
        $this->assertFileExists(SITE_ROOT . '/backend/api/.htaccess');
        $this->assertFileExists(SITE_ROOT . '/backend/endpoints/serve-file.php');
        $this->assertFileExists(SITE_ROOT . '/backend/endpoints/check-reset-status.php');
        $this->assertTrue(class_exists(\Slim\App::class), 'Slim 4 framework must be autoloadable');

        // Tier 3: Database (MySQL SQL Schema + 17 Migrations + Runner + ERD)
        $this->assertDirectoryExists(SITE_ROOT . '/database/migrations');
        $this->assertFileExists(SITE_ROOT . '/database/marvelous_hotel.sql');
        $this->assertFileExists(SITE_ROOT . '/database/migrate.php');
        $this->assertFileExists(SITE_ROOT . '/database/ERD.md');
        $this->assertGreaterThanOrEqual(17, count(glob(SITE_ROOT . '/database/migrations/*.sql') ?: []));
    }

    public function testRootDirectoryIsCleanWithZeroLoosePhpFiles(): void
    {
        $rootPhpFiles = glob(SITE_ROOT . '/*.php') ?: [];
        $this->assertEmpty($rootPhpFiles, 'Project root should have zero loose .php files; all app files live in frontend/, backend/, or database/.');
        $this->assertDirectoryDoesNotExist(SITE_ROOT . '/includes');
        $this->assertDirectoryDoesNotExist(SITE_ROOT . '/ai');
        $this->assertDirectoryDoesNotExist(SITE_ROOT . '/admin');
        $this->assertDirectoryDoesNotExist(SITE_ROOT . '/staff');
        $this->assertDirectoryDoesNotExist(SITE_ROOT . '/user');
        $this->assertDirectoryDoesNotExist(SITE_ROOT . '/auth');
        $this->assertStringEndsWith('/frontend/assets', ASSETS_URL);
        $this->assertStringEndsWith('/backend/api/v1', API_URL);
    }

    public function testSlimApiRootAndHealthEndpointsReturnJson(): void
    {
        $app = ApiAppFactory::create('');
        $factory = new ServerRequestFactory();

        // GET /
        $resRoot = $app->handle($factory->createServerRequest('GET', '/'));
        $this->assertEquals(200, $resRoot->getStatusCode());
        $bodyRoot = json_decode((string)$resRoot->getBody(), true);
        $this->assertTrue($bodyRoot['ok']);
        $this->assertEquals('Slim 4', $bodyRoot['meta']['framework']);
        $this->assertArrayHasKey('endpoints', $bodyRoot['data']);

        // GET /v1/health
        $resHealth = $app->handle($factory->createServerRequest('GET', '/v1/health'));
        $this->assertEquals(200, $resHealth->getStatusCode());
        $bodyHealth = json_decode((string)$resHealth->getBody(), true);
        $this->assertTrue($bodyHealth['ok']);
        $this->assertEquals('Slim 4 (PHP 8 PSR-4/PSR-15)', $bodyHealth['data']['tiers']['backend']);
        $this->assertEquals('Tailwind CSS + Alpine.js 3', $bodyHealth['data']['tiers']['frontend']);

        // GET /v1/does-not-exist -> clean 404 JSON
        $res404 = $app->handle($factory->createServerRequest('GET', '/v1/does-not-exist'));
        $this->assertEquals(404, $res404->getStatusCode());
        $body404 = json_decode((string)$res404->getBody(), true);
        $this->assertFalse($body404['ok']);
    }

    public function testSlimRoleAuthAndCsrfMiddlewareGuards(): void
    {
        $app = ApiAppFactory::create('');
        $factory = new ServerRequestFactory();

        // Unauthenticated GET /v1/bookings -> 401
        $res401 = $app->handle($factory->createServerRequest('GET', '/v1/bookings'));
        $this->assertEquals(401, $res401->getStatusCode());
        $this->assertFalse(json_decode((string)$res401->getBody(), true)['ok']);

        // Logged in as guest calling staff/admin-only POST /v1/payments/1/decision -> 403 role error
        $_SESSION['user_id'] = 21;
        $_SESSION['user_role'] = 'user';
        $resRole403 = $app->handle($factory->createServerRequest('POST', '/v1/payments/1/decision'));
        $this->assertEquals(403, $resRole403->getStatusCode());
        $this->assertStringContainsString('Forbidden', json_decode((string)$resRole403->getBody(), true)['error']);

        // Logged in as admin calling POST /v1/payments/1/decision WITHOUT CSRF token -> 403 CSRF error
        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'admin';
        $resCsrf403 = $app->handle($factory->createServerRequest('POST', '/v1/payments/1/decision'));
        $this->assertEquals(403, $resCsrf403->getStatusCode());
        $this->assertStringContainsString('CSRF', json_decode((string)$resCsrf403->getBody(), true)['error']);
    }

    public function testRoomServiceCalculateStayQuoteWeekdayAndWeekend(): void
    {
        $svc = new RoomService();
        // 2026-10-02 (Fri, weekday) + 2026-10-03 (Sat, weekend) + 2026-10-04 (Sun, weekend) -> checkout 2026-10-05 (3 nights)
        $quote = $svc->calculateStayQuote(1000.0, 1200.0, '2026-10-02', '2026-10-05');

        $this->assertEquals(3, $quote['nights']);
        $this->assertEquals(1, $quote['weekday_nights']);
        $this->assertEquals(2, $quote['weekend_nights']);
        $this->assertEquals(3400.0, $quote['estimated_total']);
        $this->assertEquals('₱ 3,400.00', $quote['formatted_total']);
    }
}

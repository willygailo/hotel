<?php
use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    public function testFormatCurrency()
    {
        $this->assertEquals('₱ 1,500.00', formatCurrency(1500));
        $this->assertEquals('₱ 0.00', formatCurrency(0));
    }

    public function testCalculateNights()
    {
        $this->assertEquals(3, calculateNights('2026-10-01', '2026-10-04'));
        $this->assertEquals(1, calculateNights('2026-10-01', '2026-10-02'));
    }

    public function testGenerateBookingCode()
    {
        $code = generateBookingCode();
        $this->assertStringStartsWith('MH-', $code);
        $this->assertEquals(11, strlen($code)); // 'MH-' + 8 chars
    }

    public function testFormatFullName()
    {
        $user1 = [
            'first_name' => 'Willy',
            'middle_name' => 'Cruz',
            'last_name' => 'Gailo',
            'suffix' => 'Jr.'
        ];
        $this->assertEquals('Willy Cruz Gailo Jr.', formatFullName($user1));

        $user2 = [
            'first_name' => 'Maria',
            'middle_name' => '',
            'last_name' => 'Santos',
            'suffix' => ''
        ];
        $this->assertEquals('Maria Santos', formatFullName($user2));

        // Test positional arguments
        $this->assertEquals('Juan Dela Cruz', formatFullName('Juan', '', 'Dela Cruz'));
        $this->assertEquals('John Adam Smith III', formatFullName('John', 'Adam', 'Smith', 'III'));
    }

    public function testKycBadge()
    {
        $verified = kycBadge('verified');
        $this->assertStringContainsString('Verified', $verified);
        $this->assertStringContainsString('#22c55e', $verified);

        $pending = kycBadge('pending');
        $this->assertStringContainsString('Pending Review', $pending);
        $this->assertStringContainsString('#eab308', $pending);

        $rejected = kycBadge('rejected');
        $this->assertStringContainsString('Rejected', $rejected);
        $this->assertStringContainsString('#ef4444', $rejected);

        $unverified = kycBadge('unverified');
        $this->assertStringContainsString('Unverified', $unverified);
        $this->assertStringContainsString('#64748b', $unverified);

        // Unknown status fallback to unverified
        $unknown = kycBadge('unknown_status');
        $this->assertStringContainsString('Unverified', $unknown);
    }

    public function testStatusBadge()
    {
        $this->assertStringContainsString('Confirmed', statusBadge('confirmed'));
        $this->assertStringContainsString('Cancelled', statusBadge('cancelled'));
        $this->assertStringContainsString('Pending', statusBadge('pending'));
    }

    public function testTruncateText()
    {
        $text = 'The quick brown fox jumps over the lazy dog';
        $this->assertEquals('The quick...', truncateText($text, 9));
        $this->assertEquals('The quick...', truncate($text, 9));
        $this->assertEquals('Short', truncateText('Short', 10));
    }

    public function testIsWeekend()
    {
        // 2026-10-03 is Saturday
        $this->assertTrue(isWeekend('2026-10-03'));
        // 2026-10-04 is Sunday
        $this->assertTrue(isWeekend('2026-10-04'));
        // 2026-10-02 is Friday
        $this->assertFalse(isWeekend('2026-10-02'));
    }

    public function testValidatePersonName()
    {
        // Valid names (firstName, lastName, middleName, suffix)
        $this->assertNull(validatePersonName('Willy', 'Gailo', 'Cruz', 'Jr.'));
        $this->assertNull(validatePersonName('John', 'Doe', '', ''));

        // Invalid: First name too short
        $err1 = validatePersonName('A', 'Gailo');
        $this->assertStringContainsString('at least 2 characters', $err1);

        // Invalid: Last name too short
        $err2 = validatePersonName('Willy', 'G');
        $this->assertStringContainsString('at least 2 characters', $err2);

        // Invalid: Special disallowed characters in name
        $err3 = validatePersonName('Willy<script>', 'Gailo');
        $this->assertStringContainsString('valid letters', $err3);
    }

    public function testDatesOverlap()
    {
        // Existing booking: Nov 15 to Nov 18
        $bookedIn = '2026-11-15';
        $bookedOut = '2026-11-18';

        // 1. Exact match overlaps
        $this->assertTrue(datesOverlap('2026-11-15', '2026-11-18', $bookedIn, $bookedOut));

        // 2. Partial overlap inside
        $this->assertTrue(datesOverlap('2026-11-16', '2026-11-17', $bookedIn, $bookedOut));

        // 3. Partial overlap starting earlier and ending inside
        $this->assertTrue(datesOverlap('2026-11-14', '2026-11-16', $bookedIn, $bookedOut));

        // 4. Partial overlap starting inside and ending later
        $this->assertTrue(datesOverlap('2026-11-17', '2026-11-19', $bookedIn, $bookedOut));

        // 5. Total enclosure (surrounding)
        $this->assertTrue(datesOverlap('2026-11-10', '2026-11-20', $bookedIn, $bookedOut));

        // 6. Adjacent non-overlap: Guest leaves on Nov 15 morning, new guest arrives Nov 15 afternoon
        $this->assertFalse(datesOverlap('2026-11-10', '2026-11-15', $bookedIn, $bookedOut));

        // 7. Adjacent non-overlap: Guest leaves on Nov 18 morning, new guest arrives Nov 18 afternoon
        $this->assertFalse(datesOverlap('2026-11-18', '2026-11-20', $bookedIn, $bookedOut));

        // 8. Completely separate before
        $this->assertFalse(datesOverlap('2026-11-01', '2026-11-05', $bookedIn, $bookedOut));

        // 9. Completely separate after
        $this->assertFalse(datesOverlap('2026-11-25', '2026-11-28', $bookedIn, $bookedOut));
    }
}

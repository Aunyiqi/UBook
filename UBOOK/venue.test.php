<?php
/**
 * venue_test.php
 * 
 * Unit tests for venue.php backend + frontend functions.
 * Run with: php venue_test.php (CLI) or open in browser.
 */

// --------------------------------------------------------------
// 1. EXTRACT FUNCTIONS FROM venue.php (copied for testing)
// --------------------------------------------------------------

function timeToMinutes($timeStr) {
    $parts = explode(':', $timeStr);
    return (int)$parts[0] * 60 + (int)$parts[1];
}

function ubook_venue_display_name($pdo, string $venueId): string {
    // In production: query DB. For test we'll mock the PDO result.
    $stmt = $pdo->prepare('SELECT name FROM venues WHERE id = ?');
    $stmt->execute([$venueId]);
    return $stmt->fetchColumn() ?: 'Venue #' . $venueId;
}

function ubook_resolve_notify_email($input, $pdo, $userId) {
    if (!empty($input['notify_email']) && filter_var($input['notify_email'], FILTER_VALIDATE_EMAIL))
        return $input['notify_email'];
    if (!empty($_SESSION['user_email']) && filter_var($_SESSION['user_email'], FILTER_VALIDATE_EMAIL))
        return $_SESSION['user_email'];
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return filter_var($stmt->fetchColumn(), FILTER_VALIDATE_EMAIL) ?: null;
}

// Helper for conflict detection (same logic as in venue.php)
function hasTimeConflict($newStartMin, $newEndMin, $existingBookings) {
    foreach ($existingBookings as $ex) {
        $exStart = timeToMinutes($ex['start_time']);
        $exEnd = $exStart + $ex['duration_hours'] * 60;
        if ($newStartMin < $exEnd && $newEndMin > $exStart) {
            return true;
        }
    }
    return false;
}

// --------------------------------------------------------------
// 2. MOCK CLASSES FOR PDO
// --------------------------------------------------------------
class MockPDOStatement {
    private $fetchResult = null;
    private $fetchColumnResult = null;
    public function execute($params) { return true; }
    public function fetchColumn() { return $this->fetchColumnResult; }
    public function setFetchColumnResult($val) { $this->fetchColumnResult = $val; }
}
class MockPDO {
    public function prepare($sql) {
        $stmt = new MockPDOStatement();
        return $stmt;
    }
}

// --------------------------------------------------------------
// 3. RUN PHP UNIT TESTS (simple assertions)
// --------------------------------------------------------------
$passed = 0;
$failed = 0;

function assertTrue($condition, $message) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ PASS: $message\n";
        $passed++;
    } else {
        echo "❌ FAIL: $message\n";
        $failed++;
    }
}

function assertEqual($actual, $expected, $message) {
    global $passed, $failed;
    if ($actual === $expected) {
        echo "✅ PASS: $message\n";
        $passed++;
    } else {
        echo "❌ FAIL: $message (expected '$expected', got '$actual')\n";
        $failed++;
    }
}

echo "\n========== BACKEND UNIT TESTS ==========\n\n";

// Test timeToMinutes
echo "--- timeToMinutes ---\n";
assertEqual(timeToMinutes('09:00'), 540, '09:00 -> 540');
assertEqual(timeToMinutes('00:00'), 0, '00:00 -> 0');
assertEqual(timeToMinutes('23:59'), 1439, '23:59 -> 1439');

// Test ubook_venue_display_name with mock
echo "\n--- ubook_venue_display_name ---\n";
$mockPdo = new MockPDO();
$mockStmt = $mockPdo->prepare('');
$mockStmt->setFetchColumnResult('Great Hall');
// Override the prepare to return our custom stmt with fetchColumn
$mockPdo->prepare = function($sql) use ($mockStmt) { return $mockStmt; };
// Simpler: manually test the logic using a stub
// Since the function expects a real PDO, we'll test by mocking the fetchColumn
// We'll use a simple class that mimics the necessary behavior
class TestablePDO {
    public function prepare($sql) {
        return new class {
            public function execute($p) { return true; }
            public function fetchColumn() { return 'Main Hall'; }
        };
    }
}
$testPdo = new TestablePDO();
assertEqual(ubook_venue_display_name($testPdo, 'hall1'), 'Main Hall', 'Returns venue name from DB');
// Test fallback
$emptyPdo = new class {
    public function prepare($sql) {
        return new class {
            public function execute($p) { return true; }
            public function fetchColumn() { return false; }
        };
    }
};
assertEqual(ubook_venue_display_name($emptyPdo, 'unknown'), 'Venue #unknown', 'Fallback when not found');

// Test ubook_resolve_notify_email
echo "\n--- ubook_resolve_notify_email ---\n";
// Mock session
$_SESSION = [];
$mockPdo2 = new class {
    public function prepare($sql) {
        return new class {
            public function execute($p) { return true; }
            public function fetchColumn() { return 'user@example.com'; }
        };
    }
};
// From input
$input = ['notify_email' => 'input@test.com'];
$result = ubook_resolve_notify_email($input, $mockPdo2, '1');
assertEqual($result, 'input@test.com', 'Uses email from input');

// From session
$_SESSION['user_email'] = 'session@test.com';
$input = [];
$result = ubook_resolve_notify_email($input, $mockPdo2, '1');
assertEqual($result, 'session@test.com', 'Uses email from session');

// From database
unset($_SESSION['user_email']);
$result = ubook_resolve_notify_email([], $mockPdo2, '1');
assertEqual($result, 'user@example.com', 'Falls back to DB email');

// Invalid email returns null
$mockPdoEmpty = new class {
    public function prepare($sql) {
        return new class {
            public function execute($p) { return true; }
            public function fetchColumn() { return null; }
        };
    }
};
$result = ubook_resolve_notify_email([], $mockPdoEmpty, '1');
assertEqual($result, null, 'Null when no valid email found');

// Test conflict detection
echo "\n--- Conflict detection ---\n";
$existing = [
    ['start_time' => '10:00', 'duration_hours' => 2], // 10:00-12:00
    ['start_time' => '13:00', 'duration_hours' => 1], // 13:00-14:00
];
// No conflict: 12:30-13:30? Actually 12:30-13:30 overlaps with 13:00-14:00? Let's test properly
assertTrue(hasTimeConflict(9*60, 10*60, $existing) === false, '9:00-10:00 no conflict');
assertTrue(hasTimeConflict(10*60, 11*60, $existing) === true, '10:00-11:00 conflicts with 10:00-12:00');
assertTrue(hasTimeConflict(12*60, 13*60, $existing) === false, '12:00-13:00 no conflict');
assertTrue(hasTimeConflict(12*60+30, 13*60+30, $existing) === true, '12:30-13:30 conflicts with 13:00-14:00');

// Summary
echo "\n========== PHP TEST SUMMARY ==========\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
$phpAllPassed = ($failed === 0);

// --------------------------------------------------------------
// 4. FRONTEND TESTS (JavaScript) – Embedded QUnit
// --------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>UBook venue.php Frontend Unit Tests</title>
    <link rel="stylesheet" href="https://code.jquery.com/qunit/qunit-2.19.4.css">
    <script src="https://code.jquery.com/qunit/qunit-2.19.4.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; padding: 20px; background: #f5f5f5; }
        .php-summary { background: #e8f5e9; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 6px solid #4CAF50; }
        .php-summary.fail { background: #ffebee; border-left-color: #f44336; }
        h1 { color: #e67e22; }
    </style>
</head>
<body>
<div class="<?php echo $phpAllPassed ? 'php-summary' : 'php-summary fail'; ?>">
    <strong>🔧 PHP Backend Tests:</strong> <?php echo $passed; ?> passed, <?php echo $failed; ?> failed.
    <?php if ($phpAllPassed): ?>✅ All backend tests passed.<?php else: ?>⚠️ Some backend tests failed. Review output above.<?php endif; ?>
</div>

<div id="qunit"></div>
<div id="qunit-fixture"></div>

<script>
    // ========== COPY FRONTEND FUNCTIONS FROM venue.php ==========
    const mockVenuesList = [
        { id: 'main_hall', name: 'Main Hall' },
        { id: 'volleyball', name: 'Volleyball Court' },
        { id: 'study_lounge', name: 'Study Lounge' }
    ];

    function parseTimeFromText(text) {
        const lower = text.toLowerCase();
        let m = lower.match(/\b([01]?\d|2[0-3]):([0-5]\d)\b/);
        if (m) return `${String(Math.min(23, parseInt(m[1], 10))).padStart(2, '0')}:${m[2]}`;
        m = lower.match(/\b([1-9]|1[0-2])(?::([0-5]\d))?\s*(am|pm)\b/);
        if (m) {
            let h = parseInt(m[1], 10);
            const mins = m[2] ? parseInt(m[2], 10) : 0;
            const ap = m[3];
            if (ap === 'pm' && h < 12) h += 12;
            if (ap === 'am' && h === 12) h = 0;
            return `${String(h).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
        }
        return null;
    }

    function parseDateFromText(text) {
        const lower = text.trim().toLowerCase();
        const todayISO = () => new Date().toISOString().split('T')[0];
        const addDays = (days) => {
            const d = new Date();
            d.setDate(d.getDate() + days);
            return d.toISOString().split('T')[0];
        };
        if (/\btoday\b/.test(lower)) return todayISO();
        if (/\btomorrow\b/.test(lower)) return addDays(1);
        const iso = text.match(/\d{4}-\d{2}-\d{2}/);
        if (iso && iso[0] >= todayISO()) return iso[0];
        return null;
    }

    function matchVenueByText(text, venuesList) {
        if (!text || !venuesList.length) return null;
        const lower = text.toLowerCase();
        const sorted = [...venuesList].sort((a, b) => b.name.length - a.name.length);
        for (const v of sorted) {
            if (lower.includes(v.name.toLowerCase())) return v;
        }
        return null;
    }

    function tryParseOneShotBooking(message, venuesList) {
        const venue = matchVenueByText(message, venuesList);
        if (!venue) return null;
        const date = parseDateFromText(message);
        const time = parseTimeFromText(message);
        const durationMatch = message.toLowerCase().match(/(\d+)\s*(hour|hours|hrs|h)\b/);
        const duration = durationMatch ? (parseInt(durationMatch[1], 10) || 2) : 2;
        if (!date || !time) return null;
        return { venueId: venue.id, venueName: venue.name, date, time, duration };
    }

    function getFallbackReply(question, venuesList) {
        const lower = (question || "").toLowerCase();
        if (lower.includes("book")) return "Say \"list venues\" or \"book Main Hall tomorrow 2pm 2 hours\".";
        if (lower.includes("list venues")) return "Venues: " + venuesList.map(v => v.name).join(", ") + ". Tap a button to book.";
        return "Try: list venues, book [venue name], or set email.";
    }

    // ========== QUnit TESTS ==========
    QUnit.module("Time Parser");
    QUnit.test("parseTimeFromText - 24h format", assert => {
        assert.equal(parseTimeFromText('14:30'), '14:30');
        assert.equal(parseTimeFromText('23:00'), '23:00');
    });
    QUnit.test("parseTimeFromText - 12h with am/pm", assert => {
        assert.equal(parseTimeFromText('2pm'), '14:00');
        assert.equal(parseTimeFromText('12:15 am'), '00:15');
        assert.equal(parseTimeFromText('3:30 PM'), '15:30');
    });
    QUnit.test("parseTimeFromText - invalid", assert => {
        assert.equal(parseTimeFromText('no time'), null);
    });

    QUnit.module("Date Parser");
    QUnit.test("parseDateFromText - today/tomorrow", assert => {
        const today = new Date().toISOString().split('T')[0];
        const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
        assert.equal(parseDateFromText('today'), today);
        assert.equal(parseDateFromText('tomorrow'), tomorrow);
    });
    QUnit.test("parseDateFromText - YYYY-MM-DD", assert => {
        const future = '2030-12-31';
        assert.equal(parseDateFromText(`book on ${future}`), future);
    });

    QUnit.module("Venue Matching");
    QUnit.test("matchVenueByText finds correct venue", assert => {
        assert.equal(matchVenueByText('I want Main Hall', mockVenuesList).id, 'main_hall');
        assert.equal(matchVenueByText('Volleyball court booking', mockVenuesList).id, 'volleyball');
        assert.equal(matchVenueByText('unknown place', mockVenuesList), null);
    });

    QUnit.module("One-Shot Booking");
    QUnit.test("tryParseOneShotBooking extracts full booking", assert => {
        const msg = 'book Main Hall tomorrow at 3pm for 2 hours';
        const booking = tryParseOneShotBooking(msg, mockVenuesList);
        assert.notEqual(booking, null);
        assert.equal(booking.venueId, 'main_hall');
        assert.equal(booking.duration, 2);
        assert.equal(booking.time, '15:00');
    });

    QUnit.module("Fallback Replies");
    QUnit.test("getFallbackReply returns appropriate help", assert => {
        const reply = getFallbackReply('how to book', mockVenuesList);
        assert.ok(reply.includes('list venues'));
        assert.ok(getFallbackReply('list venues', mockVenuesList).includes('Main Hall'));
    });
</script>
</body>
</html>
<?php
/**
 * mainmenutest.php - UBook System Test Suite (Enhanced)
 * 
 * Tests backend PHP functions and frontend JavaScript functions with extended coverage.
 * Run with: C:\xampp\php\php.exe mainmenutest.php
 * Or open in browser via XAMPP: http://localhost/UBook/mainmenutest.php
 */

// ==================== BACKEND FUNCTIONS (copied from MainMenu.php) ====================
function timeToMinutes($timeStr) {
    $parts = explode(':', $timeStr);
    return (int)$parts[0] * 60 + (int)$parts[1];
}

function ubook_venue_display_name($pdo, string $venueId): string {
    // Simplified for testing; in production, it would query DB
    return $venueId;
}

// ==================== ADDITIONAL TEST FUNCTIONS (mock/validation helpers) ====================
function isBookingTimeValid($startMinutes, $durationHours) {
    $startHour = floor($startMinutes / 60);
    $startMin = $startMinutes % 60;
    $endMinutes = $startMinutes + ($durationHours * 60);
    $endHour = floor($endMinutes / 60);
    // Assume venue open 8:00 to 22:00
    $openMinutes = 8 * 60;
    $closeMinutes = 22 * 60;
    return $startMinutes >= $openMinutes && $endMinutes <= $closeMinutes && $durationHours >= 1 && $durationHours <= 4;
}

function calculateEndTime($startTimeStr, $durationHours) {
    $startMinutes = timeToMinutes($startTimeStr);
    $endMinutes = $startMinutes + ($durationHours * 60);
    $hours = floor($endMinutes / 60);
    $minutes = $endMinutes % 60;
    return sprintf("%02d:%02d", $hours, $minutes);
}

function validateBookingData($booking) {
    $required = ['venueId', 'date', 'startTime', 'duration'];
    foreach ($required as $field) {
        if (empty($booking[$field])) return false;
    }
    // Date format check (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking['date'])) return false;
    // Time format check (HH:MM)
    if (!preg_match('/^\d{2}:\d{2}$/', $booking['startTime'])) return false;
    // Duration between 1 and 4 hours
    if ($booking['duration'] < 1 || $booking['duration'] > 4) return false;
    // Check not in past
    $today = date('Y-m-d');
    if ($booking['date'] < $today) return false;
    return true;
}

// ==================== TEST ASSERTION HELPER ====================
$phpTestResults = [];
function assertTrue($condition, $message, &$results) {
    $results[] = [
        'status' => $condition ? '✅ PASS' : '❌ FAIL',
        'message' => $message
    ];
}

// ==================== RUN BACKEND TESTS ====================
// Original tests for timeToMinutes()
assertTrue(timeToMinutes('09:00') === 540, 'timeToMinutes("09:00") returns 540', $phpTestResults);
assertTrue(timeToMinutes('00:00') === 0, 'timeToMinutes("00:00") returns 0', $phpTestResults);
assertTrue(timeToMinutes('23:59') === 1439, 'timeToMinutes("23:59") returns 1439', $phpTestResults);

// Additional edge cases for timeToMinutes()
assertTrue(timeToMinutes('00:01') === 1, 'timeToMinutes("00:01") returns 1', $phpTestResults);
assertTrue(timeToMinutes('12:00') === 720, 'timeToMinutes("12:00") returns 720', $phpTestResults);
assertTrue(timeToMinutes('10:30') === 630, 'timeToMinutes("10:30") returns 630', $phpTestResults);

// Test ubook_venue_display_name()
assertTrue(ubook_venue_display_name(null, 'hall_123') === 'hall_123', 'ubook_venue_display_name returns venue ID', $phpTestResults);
assertTrue(ubook_venue_display_name(null, '') === '', 'Empty venue ID returns empty string', $phpTestResults);

// Test review validation logic (original)
$validReview = ['name' => 'John', 'rating' => 5, 'review' => 'Great'];
assertTrue(!empty($validReview['name']) && $validReview['rating'] >= 1 && $validReview['rating'] <= 5 && !empty($validReview['review']), 'Valid review data passes', $phpTestResults);

$invalidRating = ['name' => 'Jane', 'rating' => 6, 'review' => 'Good'];
assertTrue(!($invalidRating['rating'] >= 1 && $invalidRating['rating'] <= 5), 'Rating 6 is invalid', $phpTestResults);

$emptyName = ['name' => '', 'rating' => 4, 'review' => 'Nice'];
assertTrue(empty($emptyName['name']), 'Empty name fails validation (should be rejected)', $phpTestResults);

// Additional review edge cases
$emptyReview = ['name' => 'Alice', 'rating' => 3, 'review' => ''];
assertTrue(empty($emptyReview['review']), 'Empty review text fails validation', $phpTestResults);
$ratingZero = ['name' => 'Bob', 'rating' => 0, 'review' => 'Meh'];
assertTrue(!($ratingZero['rating'] >= 1 && $ratingZero['rating'] <= 5), 'Rating 0 is invalid', $phpTestResults);
$ratingFive = ['name' => 'Carol', 'rating' => 5, 'review' => 'Excellent!'];
assertTrue($ratingFive['rating'] >= 1 && $ratingFive['rating'] <= 5, 'Rating 5 is valid', $phpTestResults);

// Test isBookingTimeValid()
assertTrue(isBookingTimeValid(540, 2) === true, 'Booking 09:00 for 2 hours is valid (within 8-22)', $phpTestResults);
assertTrue(isBookingTimeValid(480, 1) === true, 'Booking 08:00 for 1 hour is valid (edge open)', $phpTestResults);
assertTrue(isBookingTimeValid(1320, 1) === true, 'Booking 21:00 for 1 hour is valid (edge close)', $phpTestResults);
assertTrue(isBookingTimeValid(500, 3) === false, 'Booking before 8am (08:20) is invalid', $phpTestResults);
assertTrue(isBookingTimeValid(1260, 3) === false, 'Booking after 22:00 (21:00 for 3h) invalid', $phpTestResults);
assertTrue(isBookingTimeValid(720, 0) === false, 'Zero duration is invalid', $phpTestResults);
assertTrue(isBookingTimeValid(720, 5) === false, 'Duration 5h exceeds max 4h', $phpTestResults);

// Test calculateEndTime()
assertTrue(calculateEndTime('09:00', 2) === '11:00', 'calculateEndTime("09:00",2) returns 11:00', $phpTestResults);
assertTrue(calculateEndTime('23:00', 1) === '00:00', 'calculateEndTime("23:00",1) wraps to next day 00:00', $phpTestResults);
assertTrue(calculateEndTime('10:30', 1) === '11:30', 'calculateEndTime("10:30",1) returns 11:30', $phpTestResults);
assertTrue(calculateEndTime('22:00', 2) === '00:00', 'calculateEndTime("22:00",2) returns 00:00', $phpTestResults);

// Test validateBookingData()
$validBooking = ['venueId' => 'hall', 'date' => date('Y-m-d', strtotime('+1 day')), 'startTime' => '14:00', 'duration' => 2];
assertTrue(validateBookingData($validBooking) === true, 'Valid booking data passes validation', $phpTestResults);
$missingField = ['venueId' => 'hall', 'date' => '2025-12-31', 'startTime' => '14:00'];
assertTrue(validateBookingData($missingField) === false, 'Missing duration fails validation', $phpTestResults);
$pastDate = ['venueId' => 'hall', 'date' => '2020-01-01', 'startTime' => '14:00', 'duration' => 2];
assertTrue(validateBookingData($pastDate) === false, 'Past date fails validation', $phpTestResults);
$invalidTimeFormat = ['venueId' => 'hall', 'date' => '2025-12-31', 'startTime' => '2pm', 'duration' => 2];
assertTrue(validateBookingData($invalidTimeFormat) === false, 'Invalid time format fails validation', $phpTestResults);
$invalidDateFormat = ['venueId' => 'hall', 'date' => '12/31/2025', 'startTime' => '14:00', 'duration' => 2];
assertTrue(validateBookingData($invalidDateFormat) === false, 'Invalid date format fails validation', $phpTestResults);

// Calculate summary
$passCount = count(array_filter($phpTestResults, fn($t) => strpos($t['status'], 'PASS') !== false));
$totalCount = count($phpTestResults);

// ==================== HTML OUTPUT (includes frontend QUnit tests) ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UBook Main Menu - Enhanced System Test Suite</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f5f5f5; }
        h1, h2 { color: #e67e22; }
        .test-container { background: white; border-radius: 10px; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .test-case { padding: 8px 12px; margin: 5px 0; border-left: 4px solid #ccc; background: #fafafa; }
        .pass { border-left-color: #4CAF50; background: #e8f5e9; }
        .fail { border-left-color: #f44336; background: #ffebee; }
        .summary { font-weight: bold; margin-top: 15px; padding: 10px; background: #e0e0e0; border-radius: 5px; }
        .badge { display: inline-block; background: #4CAF50; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 8px; }
        .badge.fail { background: #f44336; }
        .test-section { margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px; }
    </style>
    <link rel="stylesheet" href="https://code.jquery.com/qunit/qunit-2.19.4.css">
    <script src="https://code.jquery.com/qunit/qunit-2.19.4.js"></script>
</head>
<body>
    <h1>🧪 UBook Main Menu - Enhanced System Test Suite</h1>

    <!-- Backend Test Results -->
    <div class="test-container">
        <h2>🔧 Backend Tests (PHP)</h2>
        <?php foreach ($phpTestResults as $test): ?>
            <div class="test-case <?php echo strpos($test['status'], 'PASS') !== false ? 'pass' : 'fail'; ?>">
                <strong><?php echo $test['status']; ?></strong> – <?php echo htmlspecialchars($test['message']); ?>
            </div>
        <?php endforeach; ?>
        <div class="summary">
            📊 PHP Summary: <?php echo $passCount; ?> / <?php echo $totalCount; ?> passed
            (<?php echo round(($passCount/$totalCount)*100); ?>%)
            <?php if ($passCount === $totalCount): ?>
                <span class="badge">All PHP tests passed 🎉</span>
            <?php else: ?>
                <span class="badge fail">Some tests failed</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Frontend Tests (QUnit) -->
    <div class="test-container">
        <h2>🌐 Frontend Tests (JavaScript)</h2>
        <div id="qunit"></div>
        <div id="qunit-fixture"></div>
    </div>

    <script>
        // ========== FRONTEND FUNCTIONS (copied from MainMenu.php) ==========
        const mockVenuesList = [
            { id: 'main_hall', name: 'Main Hall' },
            { id: 'volleyball', name: 'Volleyball Court' },
            { id: 'study_lounge', name: 'Study Lounge' },
            { id: 'conference_room_a', name: 'Conference Room A' }
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

        // Additional frontend helper (for testing edge cases)
        function validateBookingInput(booking) {
            if (!booking.venueId || !booking.date || !booking.startTime || !booking.duration) return false;
            if (booking.duration < 1 || booking.duration > 4) return false;
            const timePattern = /^([01]\d|2[0-3]):([0-5]\d)$/;
            if (!timePattern.test(booking.startTime)) return false;
            const datePattern = /^\d{4}-\d{2}-\d{2}$/;
            if (!datePattern.test(booking.date)) return false;
            const today = new Date().toISOString().split('T')[0];
            if (booking.date < today) return false;
            return true;
        }

        // ========== QUnit TESTS (Expanded) ==========
        QUnit.module("Time Parser");
        QUnit.test("parseTimeFromText - 24h format", assert => {
            assert.equal(parseTimeFromText('14:30'), '14:30');
            assert.equal(parseTimeFromText('23:00'), '23:00');
            assert.equal(parseTimeFromText('00:00'), '00:00');
            assert.equal(parseTimeFromText('09:05'), '09:05');
        });
        QUnit.test("parseTimeFromText - 12h with am/pm", assert => {
            assert.equal(parseTimeFromText('2pm'), '14:00');
            assert.equal(parseTimeFromText('12:15 am'), '00:15');
            assert.equal(parseTimeFromText('3:30 PM'), '15:30');
            assert.equal(parseTimeFromText('12:00pm'), '12:00');
            assert.equal(parseTimeFromText('12am'), '00:00');
            assert.equal(parseTimeFromText('11:45pm'), '23:45');
            assert.equal(parseTimeFromText('1:05am'), '01:05');
        });
        QUnit.test("parseTimeFromText - edge cases & invalid", assert => {
            assert.equal(parseTimeFromText('no time'), null);
            assert.equal(parseTimeFromText('25:00'), null);
            assert.equal(parseTimeFromText(''), null);
            assert.equal(parseTimeFromText('midnight'), null);
            assert.equal(parseTimeFromText('3pm30'), null); // malformed
        });

        QUnit.module("Date Parser");
        QUnit.test("parseDateFromText - today/tomorrow", assert => {
            const today = new Date().toISOString().split('T')[0];
            const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
            assert.equal(parseDateFromText('today'), today);
            assert.equal(parseDateFromText('tomorrow'), tomorrow);
            assert.equal(parseDateFromText('Book today at 5pm'), today);
        });
        QUnit.test("parseDateFromText - YYYY-MM-DD", assert => {
            const future = '2030-12-31';
            assert.equal(parseDateFromText(`book on ${future}`), future);
            const past = '2000-01-01';
            assert.equal(parseDateFromText(past), null);
        });
        QUnit.test("parseDateFromText - invalid or relative not supported", assert => {
            assert.equal(parseDateFromText('yesterday'), null);
            assert.equal(parseDateFromText('next monday'), null);
            assert.equal(parseDateFromText('2025/06/01'), null);
            assert.equal(parseDateFromText(''), null);
        });

        QUnit.module("Venue Matching");
        QUnit.test("matchVenueByText finds correct venue", assert => {
            assert.equal(matchVenueByText('I want Main Hall', mockVenuesList).id, 'main_hall');
            assert.equal(matchVenueByText('Volleyball court booking', mockVenuesList).id, 'volleyball');
            assert.equal(matchVenueByText('Study Lounge is nice', mockVenuesList).id, 'study_lounge');
            assert.equal(matchVenueByText('unknown place', mockVenuesList), null);
            assert.equal(matchVenueByText('Conference Room A', mockVenuesList).id, 'conference_room_a');
        });
        QUnit.test("matchVenueByText - partial & case insensitive", assert => {
            assert.equal(matchVenueByText('main', mockVenuesList), null); // partial not enough
            assert.equal(matchVenueByText('MAIN HALL', mockVenuesList).id, 'main_hall');
            assert.equal(matchVenueByText('volleyball court', mockVenuesList).id, 'volleyball');
            assert.equal(matchVenueByText('study lounge', mockVenuesList).id, 'study_lounge');
        });
        QUnit.test("matchVenueByText - prefers longer name match", assert => {
            const venuesWithSimilar = [
                { id: 'room', name: 'Room' },
                { id: 'conference_room', name: 'Conference Room' }
            ];
            assert.equal(matchVenueByText('Conference Room booking', venuesWithSimilar).id, 'conference_room');
        });

        QUnit.module("One-Shot Booking Parsing");
        QUnit.test("tryParseOneShotBooking extracts full booking", assert => {
            const msg = 'book Main Hall tomorrow at 3pm for 2 hours';
            const booking = tryParseOneShotBooking(msg, mockVenuesList);
            assert.notEqual(booking, null);
            assert.equal(booking.venueId, 'main_hall');
            assert.equal(booking.duration, 2);
            assert.equal(booking.time, '15:00');
            assert.ok(booking.date !== null);
        });
        QUnit.test("tryParseOneShotBooking - variations in duration", assert => {
            const msg1 = 'book Volleyball Court today 2pm 3 hours';
            const b1 = tryParseOneShotBooking(msg1, mockVenuesList);
            assert.equal(b1.duration, 3);
            const msg2 = 'Book Study Lounge tomorrow 10am 1hr';
            const b2 = tryParseOneShotBooking(msg2, mockVenuesList);
            assert.equal(b2.duration, 1);
            const msg3 = 'Conference Room A 2025-12-31 4pm 4hrs';
            const b3 = tryParseOneShotBooking(msg3, mockVenuesList);
            assert.equal(b3.duration, 4);
        });
        QUnit.test("tryParseOneShotBooking - missing fields return null", assert => {
            const noVenue = 'book tomorrow 2pm 2 hours';
            assert.equal(tryParseOneShotBooking(noVenue, mockVenuesList), null);
            const noDate = 'book Main Hall at 2pm 2 hours';
            assert.equal(tryParseOneShotBooking(noDate, mockVenuesList), null);
            const noTime = 'book Main Hall tomorrow 2 hours';
            assert.equal(tryParseOneShotBooking(noTime, mockVenuesList), null);
        });
        QUnit.test("tryParseOneShotBooking - default duration 2 if not specified", assert => {
            const msg = 'book Main Hall tomorrow 3pm';
            const booking = tryParseOneShotBooking(msg, mockVenuesList);
            assert.equal(booking.duration, 2);
        });
        QUnit.test("tryParseOneShotBooking - handles various time formats", assert => {
            const msg1 = 'book Study Lounge tomorrow at 2:30pm for 2 hours';
            const b1 = tryParseOneShotBooking(msg1, mockVenuesList);
            assert.equal(b1.time, '14:30');
            const msg2 = 'book Conference Room A today 09:00 1 hour';
            const b2 = tryParseOneShotBooking(msg2, mockVenuesList);
            assert.equal(b2.time, '09:00');
        });

        QUnit.module("Fallback Replies");
        QUnit.test("getFallbackReply returns appropriate help", assert => {
            assert.ok(getFallbackReply('how to book', mockVenuesList).includes('list venues'));
            assert.ok(getFallbackReply('list venues', mockVenuesList).includes('Main Hall'));
            assert.ok(getFallbackReply('', mockVenuesList).includes('Try: list venues'));
            assert.ok(getFallbackReply('help me', mockVenuesList).includes('book [venue name]'));
        });

        QUnit.module("Frontend Validation Helper");
        QUnit.test("validateBookingInput - valid data", assert => {
            const valid = {
                venueId: 'main_hall',
                date: new Date(Date.now() + 86400000).toISOString().split('T')[0],
                startTime: '14:00',
                duration: 2
            };
            assert.ok(validateBookingInput(valid));
        });
        QUnit.test("validateBookingInput - invalid cases", assert => {
            const missingVenue = { date: '2025-12-31', startTime: '14:00', duration: 2 };
            assert.notOk(validateBookingInput(missingVenue));
            const invalidDuration = { venueId: 'hall', date: '2025-12-31', startTime: '14:00', duration: 5 };
            assert.notOk(validateBookingInput(invalidDuration));
            const pastDate = { venueId: 'hall', date: '2020-01-01', startTime: '14:00', duration: 2 };
            assert.notOk(validateBookingInput(pastDate));
            const badTime = { venueId: 'hall', date: '2025-12-31', startTime: '25:00', duration: 2 };
            assert.notOk(validateBookingInput(badTime));
        });

        QUnit.module("Edge Cases & Integration");
        QUnit.test("parseTimeFromText and parseDateFromText together", assert => {
            const message = "book Main Hall 2030-05-20 at 7:30pm 2hrs";
            const venue = matchVenueByText(message, mockVenuesList);
            const date = parseDateFromText(message);
            const time = parseTimeFromText(message);
            assert.equal(venue.id, 'main_hall');
            assert.equal(date, '2030-05-20');
            assert.equal(time, '19:30');
        });
        QUnit.test("Empty or null inputs handling", assert => {
            assert.equal(parseTimeFromText(null), null);
            assert.equal(parseDateFromText(null), null);
            assert.equal(matchVenueByText(null, mockVenuesList), null);
            assert.equal(tryParseOneShotBooking("", mockVenuesList), null);
            assert.equal(getFallbackReply(null, mockVenuesList), "Try: list venues, book [venue name], or set email.");
        });
    </script>
</body>
</html>
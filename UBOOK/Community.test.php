<?php
/**
 * communitytest.php - UBook Community Chat & AI Booking Test Suite
 * 
 * Tests core backend and frontend functions from community.php.
 * Run with: C:\xampp\php\php.exe communitytest.php
 * Or open in browser via XAMPP: http://localhost/UBook/communitytest.php
 */

// ==================== BACKEND FUNCTIONS (pure logic only, no DB/API) ====================
function timeToMinutes($timeStr) {
    $parts = explode(':', $timeStr);
    return (int)$parts[0] * 60 + (int)$parts[1];
}

/**
 * Extract booking details from a text using a given list of venues (mockable).
 */
function extractBookingDetailsMock($text, $venues) {
    $lower = strtolower($text);
    $venue = null;
    foreach ($venues as $v) {
        if (strpos($lower, strtolower($v['name'])) !== false) {
            $venue = $v;
            break;
        }
    }
    if (!$venue) return null;

    $date = null;
    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
        $date = $m[1];
    } elseif (preg_match('/\b(today|tomorrow)\b/i', $text, $m)) {
        $date = strtolower($m[1]) === 'today' ? date('Y-m-d') : date('Y-m-d', strtotime('+1 day'));
    }
    if (!$date) return null;

    $time = null;
    if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $text, $m)) {
        $time = sprintf("%02d:%02d", (int)$m[1], (int)$m[2]);
    } elseif (preg_match('/\b([1-9]|1[0-2])(?::([0-5]\d))?\s*(am|pm)\b/i', $text, $m)) {
        $h = (int)$m[1];
        $mins = isset($m[2]) ? (int)$m[2] : 0;
        $ap = strtolower($m[3]);
        if ($ap === 'pm' && $h < 12) $h += 12;
        if ($ap === 'am' && $h === 12) $h = 0;
        $time = sprintf("%02d:%02d", $h, $mins);
    }
    if (!$time) return null;

    $duration = 2;
    if (preg_match('/(\d+)\s*(hour|hours|hrs|h)\b/i', $text, $m)) {
        $d = (int)$m[1];
        if ($d >= 1 && $d <= 4) $duration = $d;
    }

    return [
        'venueId' => $venue['id'],
        'venueName' => $venue['name'],
        'date' => $date,
        'time' => $time,
        'duration' => $duration,
        'comment' => ''
    ];
}

/**
 * Validate booking data (reusable helper).
 */
function validateBookingData($booking) {
    $required = ['venueId', 'date', 'time', 'duration'];
    foreach ($required as $field) {
        if (empty($booking[$field])) return false;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking['date'])) return false;
    if (!preg_match('/^\d{2}:\d{2}$/', $booking['time'])) return false;
    if ($booking['duration'] < 1 || $booking['duration'] > 4) return false;
    $today = date('Y-m-d');
    if ($booking['date'] < $today) return false;
    return true;
}

function isBookingTimeValid($startMinutes, $durationHours) {
    $endMinutes = $startMinutes + ($durationHours * 60);
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

// ==================== TEST ASSERTION HELPER ====================
$phpTestResults = [];
function assertTrue($condition, $message, &$results) {
    $results[] = [
        'status' => $condition ? '✅ PASS' : '❌ FAIL',
        'message' => $message
    ];
}

// ==================== RUN BACKEND TESTS ====================
// Time conversion tests
assertTrue(timeToMinutes('09:00') === 540, 'timeToMinutes("09:00") returns 540', $phpTestResults);
assertTrue(timeToMinutes('00:00') === 0, 'timeToMinutes("00:00") returns 0', $phpTestResults);
assertTrue(timeToMinutes('23:59') === 1439, 'timeToMinutes("23:59") returns 1439', $phpTestResults);
assertTrue(timeToMinutes('10:30') === 630, 'timeToMinutes("10:30") returns 630', $phpTestResults);

// Mock venues for extraction tests
$mockVenues = [
    ['id' => 'bas101', 'name' => 'Basketball Court'],
    ['id' => 'cpl616', 'name' => 'Computer Lab'],
    ['id' => 'mme123', 'name' => 'Main Hall']
];

// extractBookingDetailsMock tests
$booking1 = extractBookingDetailsMock('book Main Hall tomorrow at 2pm for 3 hours', $mockVenues);
assertTrue($booking1 !== null && $booking1['venueId'] === 'mme123', 'Extract: Main Hall, tomorrow, 2pm, 3h', $phpTestResults);
assertTrue($booking1['time'] === '14:00', 'Extract: time conversion 2pm -> 14:00', $phpTestResults);
assertTrue($booking1['duration'] === 3, 'Extract: duration 3 hours', $phpTestResults);

$booking2 = extractBookingDetailsMock('Book Computer Lab today 09:30 1 hour', $mockVenues);
assertTrue($booking2 !== null && $booking2['venueId'] === 'cpl616', 'Extract: Computer Lab, today, 09:30, 1h', $phpTestResults);
assertTrue($booking2['date'] === date('Y-m-d'), 'Extract: today date matches', $phpTestResults);
assertTrue($booking2['duration'] === 1, 'Extract: duration 1 hour', $phpTestResults);

$booking3 = extractBookingDetailsMock('Basketball Court 2025-12-31 11am 4hrs', $mockVenues);
assertTrue($booking3 !== null && $booking3['venueId'] === 'bas101', 'Extract: Basketball Court, future date, 11am, 4hrs', $phpTestResults);
assertTrue($booking3['date'] === '2025-12-31', 'Extract: date 2025-12-31', $phpTestResults);
assertTrue($booking3['duration'] === 4, 'Extract: duration 4 hours', $phpTestResults);

assertTrue(extractBookingDetailsMock('no venue or date', $mockVenues) === null, 'Extract: null when missing venue', $phpTestResults);
assertTrue(extractBookingDetailsMock('Book Main Hall tomorrow', $mockVenues) === null, 'Extract: null when missing time', $phpTestResults);
assertTrue(extractBookingDetailsMock('tomorrow 2pm 2 hours', $mockVenues) === null, 'Extract: null when missing venue', $phpTestResults);

// validateBookingData tests
$valid = ['venueId' => 'mme123', 'date' => date('Y-m-d', strtotime('+1 day')), 'time' => '15:00', 'duration' => 2];
assertTrue(validateBookingData($valid) === true, 'Valid booking passes validation', $phpTestResults);
$missingField = ['venueId' => 'mme123', 'date' => '2025-12-31', 'time' => '15:00'];
assertTrue(validateBookingData($missingField) === false, 'Missing duration fails', $phpTestResults);
$pastDate = ['venueId' => 'mme123', 'date' => '2020-01-01', 'time' => '15:00', 'duration' => 2];
assertTrue(validateBookingData($pastDate) === false, 'Past date fails', $phpTestResults);
$invalidTime = ['venueId' => 'mme123', 'date' => '2025-12-31', 'time' => '25:00', 'duration' => 2];
assertTrue(validateBookingData($invalidTime) === false, 'Invalid time format fails', $phpTestResults);
$invalidDuration = ['venueId' => 'mme123', 'date' => '2025-12-31', 'time' => '15:00', 'duration' => 5];
assertTrue(validateBookingData($invalidDuration) === false, 'Duration >4 fails', $phpTestResults);

// isBookingTimeValid tests
assertTrue(isBookingTimeValid(540, 2) === true, '09:00 for 2h is valid (8-22)', $phpTestResults);
assertTrue(isBookingTimeValid(1320, 1) === true, '22:00 for 1h is valid', $phpTestResults);
assertTrue(isBookingTimeValid(480, 1) === true, '08:00 for 1h is valid (edge open)', $phpTestResults);
assertTrue(isBookingTimeValid(1260, 3) === false, '21:00 for 3h exceeds 22:00', $phpTestResults);
assertTrue(isBookingTimeValid(500, 2) === true, '08:20 for 2h is valid', $phpTestResults);
assertTrue(isBookingTimeValid(0, 1) === false, '00:00 for 1h invalid (before open)', $phpTestResults);
assertTrue(isBookingTimeValid(1320, 2) === false, '22:00 for 2h ends at 00:00 (next day) -> invalid', $phpTestResults);
assertTrue(isBookingTimeValid(720, 5) === false, '12:00 for 5h exceeds max duration', $phpTestResults);

// calculateEndTime tests
assertTrue(calculateEndTime('09:00', 2) === '11:00', '09:00 + 2h = 11:00', $phpTestResults);
assertTrue(calculateEndTime('23:00', 1) === '00:00', '23:00 + 1h wraps to 00:00', $phpTestResults);
assertTrue(calculateEndTime('10:30', 1) === '11:30', '10:30 + 1h = 11:30', $phpTestResults);
assertTrue(calculateEndTime('22:00', 2) === '00:00', '22:00 + 2h = 00:00', $phpTestResults);
assertTrue(calculateEndTime('00:00', 1) === '01:00', '00:00 + 1h = 01:00', $phpTestResults);

$passCount = count(array_filter($phpTestResults, function($t) {
    return strpos($t['status'], 'PASS') !== false;
}));
$totalCount = count($phpTestResults);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UBook Community Chat - Test Suite</title>
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
    </style>
    <link rel="stylesheet" href="https://code.jquery.com/qunit/qunit-2.19.4.css">
    <script src="https://code.jquery.com/qunit/qunit-2.19.4.js"></script>
</head>
<body>
    <h1>🧪 UBook Community Chat & AI Booking - Test Suite</h1>

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
        // ========== FRONTEND FUNCTIONS (mimic modal pre-fill logic from community.php) ==========
        const venuesList = [
            { id: 'bas101', name: 'Basketball Court' },
            { id: 'cpl616', name: 'Computer Lab' },
            { id: 'mme123', name: 'Main Hall' },
            { id: 'lec131', name: 'Lecture Hall' }
        ];

        function extractVenueFromText(text, venues) {
            if (!text || !venues.length) return null;
            const lower = text.toLowerCase();
            for (let v of venues) {
                if (lower.includes(v.name.toLowerCase())) return v;
            }
            return null;
        }

        function extractDateFromText(text) {
            const lower = text.toLowerCase();
            const todayISO = () => new Date().toISOString().split('T')[0];
            const tomorrowISO = () => {
                let d = new Date();
                d.setDate(d.getDate() + 1);
                return d.toISOString().split('T')[0];
            };
            if (lower.includes('today')) return todayISO();
            if (lower.includes('tomorrow')) return tomorrowISO();
            const match = text.match(/\b(\d{4}-\d{2}-\d{2})\b/);
            if (match && match[1] >= todayISO()) return match[1];
            return null;
        }

        function extractTimeFromText(text) {
            let match = text.match(/\b([01]?\d|2[0-3]):([0-5]\d)\b/);
            if (match) return `${String(parseInt(match[1],10)).padStart(2,'0')}:${match[2]}`;
            match = text.match(/\b([1-9]|1[0-2])(?::([0-5]\d))?\s*(am|pm)\b/i);
            if (match) {
                let h = parseInt(match[1],10);
                const mins = match[2] ? parseInt(match[2],10) : 0;
                const ap = match[3].toLowerCase();
                if (ap === 'pm' && h < 12) h += 12;
                if (ap === 'am' && h === 12) h = 0;
                return `${String(h).padStart(2,'0')}:${String(mins).padStart(2,'0')}`;
            }
            return null;
        }

        function extractDurationFromText(text) {
            const match = text.match(/(\d+)\s*(hour|hours|hrs|h)\b/i);
            if (match) {
                let d = parseInt(match[1],10);
                if (d >= 1 && d <= 4) return d;
            }
            return 2; // default 2 hours as in community.php
        }

        function prefillBookingFromText(initialText) {
            const venue = extractVenueFromText(initialText, venuesList);
            const date = extractDateFromText(initialText);
            const time = extractTimeFromText(initialText);
            const duration = extractDurationFromText(initialText);
            if (!venue || !date || !time) return null;
            return { venueId: venue.id, venueName: venue.name, date, time, duration };
        }

        // ========== QUnit TESTS ==========
        QUnit.module("Venue Extraction");
        QUnit.test("extractVenueFromText finds correct venue", assert => {
            assert.equal(extractVenueFromText('book Main Hall tomorrow', venuesList).id, 'mme123');
            assert.equal(extractVenueFromText('I love Basketball Court', venuesList).id, 'bas101');
            assert.equal(extractVenueFromText('Computer Lab is great', venuesList).id, 'cpl616');
            assert.equal(extractVenueFromText('unknown venue', venuesList), null);
            assert.equal(extractVenueFromText('', venuesList), null);
        });

        QUnit.module("Date Extraction");
        QUnit.test("extractDateFromText today/tomorrow", assert => {
            const today = new Date().toISOString().split('T')[0];
            const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
            assert.equal(extractDateFromText('booking today'), today);
            assert.equal(extractDateFromText('tomorrow at 2pm'), tomorrow);
        });
        QUnit.test("extractDateFromText YYYY-MM-DD", assert => {
            const future = '2030-12-31';
            assert.equal(extractDateFromText(`on ${future}`), future);
            assert.equal(extractDateFromText('2020-01-01'), null); // past
            assert.equal(extractDateFromText('no date'), null);
        });

        QUnit.module("Time Extraction");
        QUnit.test("extractTimeFromText 24h & 12h formats", assert => {
            assert.equal(extractTimeFromText('14:30'), '14:30');
            assert.equal(extractTimeFromText('2pm'), '14:00');
            assert.equal(extractTimeFromText('3:30 PM'), '15:30');
            assert.equal(extractTimeFromText('12:15 am'), '00:15');
            assert.equal(extractTimeFromText('12am'), '00:00');
            assert.equal(extractTimeFromText('invalid'), null);
        });

        QUnit.module("Duration Extraction");
        QUnit.test("extractDurationFromText returns numeric hours", assert => {
            assert.equal(extractDurationFromText('for 3 hours'), 3);
            assert.equal(extractDurationFromText('1hr'), 1);
            assert.equal(extractDurationFromText('4 hrs'), 4);
            assert.equal(extractDurationFromText('5 hours'), 2); // clamped to max 4, but default 2? Actually function returns default 2 if >4.
            assert.equal(extractDurationFromText('no duration'), 2);
            assert.equal(extractDurationFromText('2 hour'), 2);
        });

        QUnit.module("Full Booking Pre-fill");
        QUnit.test("prefillBookingFromText extracts all fields", assert => {
            const text = 'book Main Hall tomorrow at 2pm for 3 hours';
            const booking = prefillBookingFromText(text);
            assert.ok(booking !== null);
            assert.equal(booking.venueId, 'mme123');
            assert.equal(booking.date, new Date(Date.now() + 86400000).toISOString().split('T')[0]);
            assert.equal(booking.time, '14:00');
            assert.equal(booking.duration, 3);
        });
        QUnit.test("prefillBookingFromText returns null when missing mandatory", assert => {
            assert.equal(prefillBookingFromText('tomorrow 2pm 2 hours'), null); // no venue
            assert.equal(prefillBookingFromText('book Main Hall 2pm'), null);   // no date
            assert.equal(prefillBookingFromText('book Main Hall tomorrow'), null); // no time
        });
        QUnit.test("prefillBookingFromText handles various formats", assert => {
            const text1 = 'Basketball Court 2025-12-31 11am 4hrs';
            const b1 = prefillBookingFromText(text1);
            assert.equal(b1.venueId, 'bas101');
            assert.equal(b1.date, '2025-12-31');
            assert.equal(b1.time, '11:00');
            assert.equal(b1.duration, 4);

            const text2 = 'Computer Lab today 09:30 1 hour';
            const b2 = prefillBookingFromText(text2);
            assert.equal(b2.venueId, 'cpl616');
            assert.equal(b2.date, new Date().toISOString().split('T')[0]);
            assert.equal(b2.time, '09:30');
            assert.equal(b2.duration, 1);
        });
    </script>
</body>
</html>
<?php
/**
 * profiletest.php - UBook Profile Page Test Suite
 * 
 * Tests backend logic (validation, date/time helpers) and frontend JavaScript
 * functions used in profile.php.
 * 
 * Run with: C:\xampp\php\php.exe profiletest.php
 * Or open in browser via XAMPP: http://localhost/UBook/profiletest.php
 */

// ==================== BACKEND HELPER FUNCTIONS (copied/mocked from profile.php logic) ====================

/**
 * Validate email format.
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (simple: optional, but if provided must be digits, spaces, +, -, min 8 chars).
 */
function isValidPhone($phone) {
    if (empty($phone)) return true; // optional
    return preg_match('/^[\+\d\s\-\(\)]{8,20}$/', $phone);
}

/**
 * Validate file upload for profile image.
 * Returns true if file is valid (no error, allowed extension, size < 2MB).
 */
function isValidProfileImage($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return false;
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return false;
    if ($file['size'] > 2 * 1024 * 1024) return false; // 2MB limit
    return true;
}

/**
 * Calculate end time from start time and duration hours (24h format).
 * Returns string 'HH:MM'.
 */
function calculateEndTime($startTime, $durationHours) {
    $datetime = DateTime::createFromFormat('H:i', $startTime);
    if (!$datetime) return '';
    $datetime->modify("+{$durationHours} hours");
    return $datetime->format('H:i');
}

/**
 * Get status CSS class mapping.
 */
function getStatusClass($status) {
    $map = [
        'pending'   => 'status-pending',
        'confirmed' => 'status-confirmed',
        'rejected'  => 'status-cancelled',
        'cancelled' => 'status-cancelled',
        'completed' => 'status-completed'
    ];
    return $map[strtolower($status)] ?? 'status-pending';
}

/**
 * Validate profile update input.
 */
function validateProfileInput($name, $email, $phone) {
    $errors = [];
    if (empty(trim($name))) $errors[] = 'Name is required';
    if (empty($email) || !isValidEmail($email)) $errors[] = 'Valid email is required';
    if (!isValidPhone($phone)) $errors[] = 'Phone number is invalid (optional but if provided use digits, spaces, +, -)';
    return $errors;
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
// Email validation
assertTrue(isValidEmail('john@example.com') === true, 'Valid email passes', $phpTestResults);
assertTrue(isValidEmail('invalid-email') === false, 'Invalid email fails', $phpTestResults);
assertTrue(isValidEmail('user+tag@domain.co.uk') === true, 'Email with plus and dot passes', $phpTestResults);
assertTrue(isValidEmail('') === false, 'Empty email fails', $phpTestResults);

// Phone validation
assertTrue(isValidPhone('') === true, 'Empty phone (optional) passes', $phpTestResults);
assertTrue(isValidPhone('0123456789') === true, 'Digits only passes', $phpTestResults);
assertTrue(isValidPhone('+6012-345 6789') === true, 'Phone with + and dash passes', $phpTestResults);
assertTrue(isValidPhone('123') === false, 'Too short phone fails', $phpTestResults);
assertTrue(isValidPhone('abc123') === false, 'Letters in phone fails', $phpTestResults);

// Profile image validation
$validFile = ['error' => UPLOAD_ERR_OK, 'name' => 'avatar.jpg', 'size' => 100000, 'tmp_name' => '/tmp/php123'];
assertTrue(isValidProfileImage($validFile) === true, 'Valid JPG image passes', $phpTestResults);
$invalidExt = ['error' => UPLOAD_ERR_OK, 'name' => 'avatar.exe', 'size' => 100000];
assertTrue(isValidProfileImage($invalidExt) === false, 'Invalid extension fails', $phpTestResults);
$tooLarge = ['error' => UPLOAD_ERR_OK, 'name' => 'big.png', 'size' => 3 * 1024 * 1024];
assertTrue(isValidProfileImage($tooLarge) === false, 'File >2MB fails', $phpTestResults);
$uploadError = ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0];
assertTrue(isValidProfileImage($uploadError) === false, 'No file upload error fails', $phpTestResults);

// End time calculation
assertTrue(calculateEndTime('09:00', 2) === '11:00', '09:00 + 2h = 11:00', $phpTestResults);
assertTrue(calculateEndTime('23:30', 1) === '00:30', '23:30 + 1h wraps to 00:30', $phpTestResults);
assertTrue(calculateEndTime('10:15', 0) === '10:15', '0 hours returns same time', $phpTestResults);
assertTrue(calculateEndTime('invalid', 2) === '', 'Invalid time returns empty string', $phpTestResults);

// Status CSS class mapping
assertTrue(getStatusClass('pending') === 'status-pending', 'Pending status maps correctly', $phpTestResults);
assertTrue(getStatusClass('confirmed') === 'status-confirmed', 'Confirmed maps correctly', $phpTestResults);
assertTrue(getStatusClass('rejected') === 'status-cancelled', 'Rejected maps to cancelled style', $phpTestResults);
assertTrue(getStatusClass('cancelled') === 'status-cancelled', 'Cancelled maps correctly', $phpTestResults);
assertTrue(getStatusClass('COMPLETED') === 'status-completed', 'Case insensitive works', $phpTestResults);
assertTrue(getStatusClass('unknown') === 'status-pending', 'Unknown status defaults to pending', $phpTestResults);

// Profile input validation
$errors = validateProfileInput('John Doe', 'john@example.com', '0123456789');
assertTrue(count($errors) === 0, 'Valid profile input passes', $phpTestResults);
$errors = validateProfileInput('', 'john@example.com', '0123456789');
assertTrue(in_array('Name is required', $errors), 'Empty name triggers error', $phpTestResults);
$errors = validateProfileInput('John', 'invalid', '0123456789');
assertTrue(in_array('Valid email is required', $errors), 'Invalid email triggers error', $phpTestResults);
$errors = validateProfileInput('John', 'john@example.com', '123');
assertTrue(in_array('Phone number is invalid (optional but if provided use digits, spaces, +, -)', $errors), 'Invalid phone triggers error', $phpTestResults);

// ==================== SUMMARY ====================
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
    <title>UBook Profile - Test Suite</title>
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
    <h1>🧪 UBook Profile Page - Test Suite</h1>

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
        // ========== FRONTEND FUNCTIONS (mimic client-side validation & helpers) ==========
        
        // Email validation (basic)
        function isValidEmailJS(email) {
            const re = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            return re.test(email);
        }

        // Phone validation (optional, digits, spaces, +, -, parentheses, min 8 chars)
        function isValidPhoneJS(phone) {
            if (!phone.trim()) return true;
            const re = /^[\+\d\s\-\(\)]{8,20}$/;
            return re.test(phone);
        }

        // Name validation (non-empty)
        function isValidNameJS(name) {
            return name.trim().length > 0;
        }

        // Validate entire profile form
        function validateProfileFormJS(name, email, phone) {
            const errors = [];
            if (!isValidNameJS(name)) errors.push('Name is required');
            if (!isValidEmailJS(email)) errors.push('Valid email is required');
            if (!isValidPhoneJS(phone)) errors.push('Phone number is invalid (optional but if provided use digits, spaces, +, -)');
            return errors;
        }

        // Preview profile image before upload
        function previewProfileImage(fileInput, imgElementId) {
            const file = fileInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById(imgElementId);
                    if (img) img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        // Format end time from start time and duration (simulate PHP calculateEndTime in JS)
        function formatEndTime(startTime, durationHours) {
            if (!startTime.match(/^\d{2}:\d{2}$/)) return '';
            let [hours, minutes] = startTime.split(':').map(Number);
            let totalMinutes = hours * 60 + minutes + durationHours * 60;
            let endHours = Math.floor(totalMinutes / 60) % 24;
            let endMinutes = totalMinutes % 60;
            return `${String(endHours).padStart(2,'0')}:${String(endMinutes).padStart(2,'0')}`;
        }

        // ========== QUnit TESTS ==========
        QUnit.module("Frontend Validation");
        
        QUnit.test("isValidEmailJS", assert => {
            assert.ok(isValidEmailJS('user@example.com'));
            assert.ok(isValidEmailJS('first.last@domain.co.uk'));
            assert.notOk(isValidEmailJS('invalid-email'));
            assert.notOk(isValidEmailJS(''));
            assert.notOk(isValidEmailJS('@missinglocal'));
        });
        
        QUnit.test("isValidPhoneJS", assert => {
            assert.ok(isValidPhoneJS(''));
            assert.ok(isValidPhoneJS('0123456789'));
            assert.ok(isValidPhoneJS('+6012-3456789'));
            assert.ok(isValidPhoneJS('(03) 1234 5678'));
            assert.notOk(isValidPhoneJS('123'));
            assert.notOk(isValidPhoneJS('abc123'));
        });
        
        QUnit.test("isValidNameJS", assert => {
            assert.ok(isValidNameJS('John Doe'));
            assert.notOk(isValidNameJS(''));
            assert.notOk(isValidNameJS('   '));
        });
        
        QUnit.test("validateProfileFormJS integration", assert => {
            let errors = validateProfileFormJS('John', 'john@mail.com', '0123456789');
            assert.equal(errors.length, 0, 'Valid data passes');
            
            errors = validateProfileFormJS('', 'john@mail.com', '0123456789');
            assert.ok(errors.includes('Name is required'), 'Empty name caught');
            
            errors = validateProfileFormJS('John', 'invalid', '0123456789');
            assert.ok(errors.includes('Valid email is required'), 'Invalid email caught');
            
            errors = validateProfileFormJS('John', 'john@mail.com', '123');
            assert.ok(errors[0].includes('Phone number is invalid'), 'Invalid phone caught');
        });
        
        QUnit.module("Date/Time Helpers");
        
        QUnit.test("formatEndTime", assert => {
            assert.equal(formatEndTime('09:00', 2), '11:00');
            assert.equal(formatEndTime('23:30', 1), '00:30');
            assert.equal(formatEndTime('10:15', 0), '10:15');
            assert.equal(formatEndTime('invalid', 2), '');
        });
        
        QUnit.module("Image Preview (mock)");
        
        QUnit.test("previewProfileImage - file reader simulation", assert => {
            // Simulate file input change
            const mockFile = new File(['dummy'], 'avatar.jpg', { type: 'image/jpeg' });
            const fileInput = { files: [mockFile] };
            let imgElement = null;
            let readerLoaded = false;
            
            // Mock FileReader
            const originalFileReader = window.FileReader;
            window.FileReader = function() {
                this.onload = null;
                this.readAsDataURL = function(file) {
                    assert.ok(file === mockFile, 'File passed to reader');
                    readerLoaded = true;
                    // Simulate load event
                    if (this.onload) {
                        this.onload({ target: { result: 'data:image/jpeg;base64,xxx' } });
                    }
                };
            };
            
            // Create fake img element
            const imgDiv = document.createElement('img');
            imgDiv.id = 'test-profile-img';
            document.getElementById('qunit-fixture').appendChild(imgDiv);
            
            previewProfileImage(fileInput, 'test-profile-img');
            assert.ok(readerLoaded, 'FileReader was called');
            assert.equal(imgDiv.src, 'data:image/jpeg;base64,xxx', 'Image source updated');
            
            // Restore FileReader
            window.FileReader = originalFileReader;
        });
    </script>
</body>
</html>
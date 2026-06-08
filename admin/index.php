<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/utils/OTPGenerator.php';
require_once __DIR__ . '/../src/utils/Mailer.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    if (isset($_SESSION['otp_email'])) {
        OTPGenerator::deleteOTP($conn, $_SESSION['otp_email']);
    }
    unset($_SESSION['otp_email']);
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$show_otp_form = isset($_SESSION['otp_email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'send_otp') {
        $email = trim($_POST['email'] ?? '');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            
            if (!$admin) {
                $error = 'Email not found in our records';
            } else {
                $otp = OTPGenerator::generate(OTP_LENGTH);
                
                if (OTPGenerator::save($conn, $email, $otp)) {
                    if (Mailer::sendOTP($email, $otp)) {
                        $_SESSION['otp_email'] = $email;
                        $show_otp_form = true;
                        $success = 'OTP sent to your email';
                    } else {
                        $error = 'Failed to send OTP email';
                    }
                } else {
                    $error = 'Failed to generate OTP';
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $email = $_SESSION['otp_email'] ?? null;
        $otp = trim($_POST['otp'] ?? '');
        
        if (!$email) {
            $error = 'Session expired. Please try again.';
            $show_otp_form = false;
        } elseif (!OTPGenerator::verify($conn, $email, $otp)) {
            $error = 'Invalid or expired OTP';
        } else {
            $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            
            OTPGenerator::deleteOTP($conn, $email);
            
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['last_activity'] = time();
            
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sukhdham</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center px-4 py-8">
        <div class="bg-white rounded-lg shadow-xl p-6 sm:p-8 w-full max-w-md">
            <div class="text-center mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-blue-600">Sukhdham</h1>
                <p class="text-sm sm:text-base text-gray-600 mt-2">Admin Login</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm sm:text-base">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-sm sm:text-base">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$show_otp_form): ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_otp">
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter your admin email">
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                        Send OTP
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="verify_otp">
                    
                    <div class="mb-6">
                        <p class="text-gray-600 mb-4 text-sm sm:text-base break-words">Enter the 6-digit OTP sent to <strong><?php echo htmlspecialchars($_SESSION['otp_email']); ?></strong></p>
                        
                        <label class="block text-gray-700 font-medium mb-2">OTP Code</label>
                        <input type="text" name="otp" required maxlength="6" minlength="6"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-center text-xl sm:text-2xl tracking-widest"
                               placeholder="123456">
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                        Verify OTP
                    </button>
                    
                    <div class="mt-4 text-center">
                        <a href="index.php?reset=1" class="text-blue-600 hover:text-blue-800 text-sm">
                            Change email
                        </a>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="mt-6 text-center">
                <a href="../index.html" class="text-gray-600 hover:text-gray-800">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>

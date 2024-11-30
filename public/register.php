<?php
// Konfigurasi Keamanan
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
session_regenerate_id(true); // Cegah session fixation

// Konfigurasi Koneksi
require_once '../config/database.php';

// Inisialisasi Variabel
$username = $email = $password = $confirm_password = '';
$errors = [];

// Fungsi Validasi Input
function sanitizeInput($input)
{
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}

// Proses Registrasi
function registerUser($username, $email, $password)
{
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Validasi Input Tambahan
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception("Semua field harus diisi");
        }

        // Validasi Email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid");
        }

        // Cek Ketersediaan Username/Email
        $existingUser = $database->select('users', [
            'username' => $username,
            'email' => $email
        ]);

        if (!empty($existingUser)) {
            throw new Exception("Username atau email sudah terdaftar");
        }

        // Hash Password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Mulai Transaksi
        $database->beginTransaction();

        // Insert User
        $userId = $database->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => $hashed_password,
            'created_at' => date('Y-m-d H:i:s'),
            'avatar' => null,  // Opsional, bisa diisi default
            'job' => null,
            'instagram' => null,
            'linkedin' => null,
            'twitter' => null,
            'tiktok' => null,
            'biography' => null
        ]);

        // Commit Transaksi
        $database->commit();

        return $userId !== null;
    } catch (Exception $e) {
        // Rollback Transaksi jika terjadi error
        if (isset($database)) {
            $database->rollBack();
        }
        
        error_log("Registrasi Error: " . $e->getMessage());
        throw $e;
    } finally {
        // Tutup koneksi
        if (isset($database)) {
            $database->closeConnection();
        }
    }
}

// Proses Form Submit
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Sanitasi Input
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validasi Password
        if (empty($password) || empty($confirm_password)) {
            throw new Exception("Password tidak boleh kosong");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Password tidak cocok");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password minimal 8 karakter");
        }

        // Validasi Username
        if (strlen($username) < 3) {
            throw new Exception("Username minimal 3 karakter");
        }

        // Proses Registrasi
        if (registerUser($username, $email, $password)) {
            // Set session untuk pesan sukses
            $_SESSION['register_success'] = "Registrasi berhasil! Silakan login.";

            // Redirect ke halaman login
            header("Location: login.php");
            exit();
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-gray-800 p-8 rounded-lg shadow-lg w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center">Register</h2>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-500 text-white p-4 rounded mb-4">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" novalidate>
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium mb-2">Username</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 text-sm text-gray-400 bg-gray-700 border border-r-0 border-gray-600 rounded-l-md">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="username" name="username"
                            class="w-full p-3 rounded-r bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                            required 
                            minlength="3"
                            value="<?php echo htmlspecialchars($username); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium mb-2">Email</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 text-sm text-gray-400 bg-gray-700 border border-r-0 border-gray-600 rounded-l-md">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" id="email" name="email"
                            class="w-full p-3 rounded-r bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                            required 
                            value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium mb-2">Password</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 text-sm text-gray-400 bg-gray-700 border border-r-0 border-gray-600 rounded-l-md">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password"
                            class="w-full p-3 rounded-r bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                            required 
                            minlength="8">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="block text -sm font-medium mb-2">Confirm Password</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 text-sm text-gray-400 bg-gray-700 border border-r-0 border-gray-600 rounded-l-md">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="confirm_password" name="confirm_password"
                            class="w-full p-3 rounded-r bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                            required 
                            minlength="8">
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded">
                    Register
                </button>
            </form>

            <p class="mt-4 text-center">
                Sudah punya akun? <a href="login.php" class="text-blue-400 hover:underline">Login di sini</a>
            </p>
        </div>
    </div>
</body>
</html>
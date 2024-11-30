<?php
session_start();
require_once '../config/database.php'; // Sesuaikan path ke file database

// Ambil pesan sukses registrasi jika ada
$register_success = isset($_SESSION['register_success']) ? $_SESSION['register_success'] : '';
// Hapus pesan sukses dari session
unset($_SESSION['register_success']);

// Konfigurasi Supabase
$host = 'aws-0-ap-southeast-1.pooler.supabase.com'; // Sesuaikan host Anda
$dbname = 'postgres';
$db_username = 'postgres.tqilpyehwaaknppnpyah'; // Sesuaikan username Supabase Anda
$db_password = 'Omtelolet123.'; // Sesuaikan password Anda
$port = 5432;

// Pesan error
$error_message = '';

// Proses login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        // Membuat koneksi PDO untuk Supabase (PostgreSQL)
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
        $conn = new PDO($dsn, $db_username, $db_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        // Cek kredensial
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();

        // Verifikasi password
        if ($user && password_verify($password, $user['password'])) {
            // Login berhasil
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Redirect ke halaman dashboard atau home
            header("Location: dashboard.php");
            exit();
        } else {
            $error_message = "Username atau password salah";
        }
    } catch (PDOException $e) {
        // Log error untuk debugging
        error_log("Login Error: " . $e->getMessage());
        $error_message = "Gagal login: Terjadi kesalahan sistem";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
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
        <a href="home.php" class="absolute top-4 left-4 text-white text-2xl">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="bg-gray-800 p-8 rounded-lg shadow-lg w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>

            <?php if (!empty($register_success)): ?>
                <div class="bg-green-500 text-white p-4 rounded mb-4">
                    <?php echo htmlspecialchars($register_success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-500 text-white p-4 rounded mb-4">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium mb-2">Username</label>
                    <div class="flex">
                        <span
                            class="inline-flex items-center px-3 text-sm text-gray-400 bg-gray-700 border border-r-0 border-gray-600 rounded-l-md">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="username" name="username"
                            class="w-full p-3 rounded-r bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                            required placeholder="Masukkan username">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium mb-2">Password</label>
                    <div class="flex">
                        <span
                            class="inline-flex items-center px-3 text-sm text-gray-400 bg-gray-700 border border-r-0 border-gray-600 rounded-l-md">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password"
                            class="w-full p-3 rounded-r bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                            required placeholder="Masukkan password">
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded transition duration-300 ease-in-out">
                    Login
                </button>
            </form>
            <p class="mt-6 text-center text-sm">Don't have an account? <a href="register.php"
                    class="text-blue-400 hover:underline">Register</a></p>
        </div>
    </div>
</body>

</html>
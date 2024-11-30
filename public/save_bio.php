<?php
session_start();

// Periksa apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['message' => 'Unauthorized']);
    exit();
}

// Konfigurasi database Supabase
$host = 'aws-0-ap-southeast-1.pooler.supabase.com'; // Ganti dengan host Supabase Anda
$dbname = 'postgres'; // Ganti dengan nama database Anda
$db_username = 'postgres.tqilpyehwaaknppnpyah'; // Ganti dengan username Supabase Anda
$db_password = 'Omtelolet123.'; // Ganti dengan password Supabase Anda
$port = 5432; // Port default untuk PostgreSQL

try {
    // Membuat koneksi
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $conn = new PDO($dsn, $db_username, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil data biografi dari permintaan
    if (isset($_POST['biography'])) {
        $biography = trim($_POST['biography']); // Menghapus spasi di awal dan akhir

        // Validasi panjang biografi
        if (strlen($biography) > 500) { // Misalnya, batasi biografi hingga 500 karakter
            http_response_code(400); // Bad Request
            echo json_encode(['message' => 'Biography too long.']);
            exit();
        }

        // Update biografi di database
        $stmt = $conn->prepare("UPDATE users SET biography = :biography WHERE id = :user_id::uuid");
        $stmt->bindParam(':biography', $biography);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_STR);
        $stmt->execute();

        echo json_encode(['message' => 'Biography updated successfully']);
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Biography not provided.']);
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
}
?>
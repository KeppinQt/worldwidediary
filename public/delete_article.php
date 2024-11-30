<?php
session_start();

// Periksa apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Konfigurasi database Supabase
$host = 'aws-0-ap-southeast-1.pooler.supabase.com'; // Ganti dengan host Supabase Anda
$dbname = 'postgres'; // Nama database Anda
$db_username = 'postgres.tqilpyehwaaknppnpyah'; // Ganti dengan username Supabase Anda
$db_password = 'Omtelolet123.'; // Ganti dengan password Supabase Anda
$port = 5432; // Port default untuk PostgreSQL

// Periksa apakah ID artikel diberikan
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$article_id = $_GET['id'];

try {
    // Membuat koneksi ke Supabase
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require"; // DSN untuk Supabase
    $conn = new PDO($dsn, $db_username, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Hapus artikel
    $stmt = $conn->prepare("DELETE FROM articles WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $article_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);

    if ($stmt->execute() && $stmt->rowCount() > 0) {
        // Set pesan sukses di session
        $_SESSION['delete_success'] = "Artikel berhasil dihapus";
    } else {
        // Set pesan gagal di session
        $_SESSION['delete_error'] = "Artikel tidak ditemukan atau Anda tidak memiliki izin";
    }

    // Redirect ke dashboard
    header("Location: dashboard.php");
    exit();

} catch (PDOException $e) {
    // Set pesan error di session
    $_SESSION['delete_error'] = "Gagal menghapus artikel: " . $e->getMessage();
    header("Location: dashboard.php");
    exit();
}
?>
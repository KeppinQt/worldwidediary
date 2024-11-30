<?php
session_start();

// Konfigurasi database Supabase
$host = 'aws-0-ap-southeast-1.pooler.supabase.com'; // Ganti dengan host Supabase Anda
$dbname = 'postgres'; // Nama database Anda
$db_username = 'postgres.tqilpyehwaaknppnpyah'; // Ganti dengan username Supabase Anda
$db_password = 'Omtelolet123.'; // Ganti dengan password Supabase Anda
$port = 5432; // Port default untuk PostgreSQL

try {
    // Membuat koneksi ke database menggunakan PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require"; // DSN untuk Supabase
    $conn = new PDO($dsn, $db_username, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pastikan pengguna telah login
    if (!isset($_SESSION['user_id'])) {
        die("Anda harus login untuk meninggalkan komentar.");
    }

    // Ambil data dari form
    $article_id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    // Validasi input
    if (empty($comment)) {
        die("Komentar tidak boleh kosong.");
    }

    // Siapkan dan jalankan query untuk menyimpan komentar
    $stmt = $conn->prepare("INSERT INTO comments (article_id, user_id, comment, created_at) VALUES (:article_id, :user_id, :comment, NOW())");
    $stmt->bindParam(':article_id', $article_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']); // Pastikan user_id ada di session
    $stmt->bindParam(':comment', $comment);

    // Eksekusi query
    if ($stmt->execute()) {
        // Jika berhasil, alihkan kembali ke artikel
        header("Location: article.php?id=" . $article_id);
        exit();
    } else {
        die("Gagal menambahkan komentar.");
    }

} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
?>
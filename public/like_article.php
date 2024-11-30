<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

// Konfigurasi database Supabase
$host = 'aws-0-ap-southeast-1.pooler.supabase.com'; // Ganti dengan host Supabase Anda
$dbname = 'postgres'; // Nama database Anda
$db_username = 'postgres.tqilpyehwaaknppnpyah'; // Ganti dengan username Supabase Anda
$db_password = 'Omtelolet123.'; // Ganti dengan password Supabase Anda
$port = 5432; // Port default untuk PostgreSQL

try {
    // Buat koneksi ke database menggunakan PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require"; // DSN untuk Supabase
    $conn = new PDO($dsn, $db_username, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $article_id = intval($_POST['article_id']);
        $user_id = $_SESSION['user_id'];
        $like = isset($_POST['like']) ? intval($_POST['like']) : 0;

        // Cek apakah user sudah like artikel ini
        $stmt = $conn->prepare("SELECT * FROM likes WHERE article_id = :article_id AND user_id = :user_id");
        $stmt->bindParam(':article_id', $article_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        if ($like === 1) {
            // Jika like = 1, insert ke database jika belum ada
            if ($stmt->rowCount() == 0) {
                $stmt = $conn->prepare("INSERT INTO likes (article_id, user_id) VALUES (:article_id, :user_id)");
                $stmt->bindParam(':article_id', $article_id);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
            }
        } else {
            // Jika like = 0, hapus dari database jika ada
            if ($stmt->rowCount() > 0) {
                $stmt = $conn->prepare("DELETE FROM likes WHERE article_id = :article_id AND user_id = :user_id");
                $stmt->bindParam(':article_id', $article_id);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
            }
        }

        // Hitung jumlah like setelah perubahan
        $stmt_like_count = $conn->prepare("SELECT COUNT(*) as like_count FROM likes WHERE article_id = :article_id");
        $stmt_like_count->bindParam(':article_id', $article_id);
        $stmt_like_count->execute();
        $like_count = $stmt_like_count->fetch(PDO::FETCH_ASSOC)['like_count'];

        echo json_encode(['status' => 'success', 'likeCount' => $like_count]);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
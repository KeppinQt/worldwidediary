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

    // Ambil ID artikel dari URL
    $article_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Ambil data artikel dari database
    $stmt = $conn->prepare("SELECT a.*, u.username FROM articles a JOIN users u ON a.user_id = u.id WHERE a.id = :id");
    $stmt->bindParam(':id', $article_id);
    $stmt->execute();
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika artikel tidak ditemukan
    if (!$article) {
        die("Artikel tidak ditemukan.");
    }

    // Ambil artikel rekomendasi
    $stmt_recommended = $conn->prepare("SELECT * FROM articles WHERE id != :current_id ORDER BY created_at DESC LIMIT 10");
    $stmt_recommended->bindParam(':current_id', $article_id);
    $stmt_recommended->execute();
    $recommended_articles = $stmt_recommended->fetchAll(PDO::FETCH_ASSOC);

    // Ambil komentar untuk artikel ini
    $stmt_comments = $conn->prepare("SELECT c.comment, u.username, c.created_at FROM comments c JOIN users u ON c.user_id = u.id WHERE c.article_id = :article_id ORDER BY c.created_at DESC");
    $stmt_comments->bindParam(':article_id', $article_id);
    $stmt_comments->execute();
    $comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);

    // Hitung jumlah like
    $stmt_like_count = $conn->prepare("SELECT COUNT(*) as like_count FROM likes WHERE article_id = :article_id");
    $stmt_like_count->bindParam(':article_id', $article_id);
    $stmt_like_count->execute();
    $like_count = $stmt_like_count->fetch(PDO::FETCH_ASSOC)['like_count'];

    // Cek apakah user sudah like artikel ini
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt_user_like = $conn->prepare("SELECT * FROM likes WHERE article_id = :article_id AND user_id = :user_id");
    $stmt_user_like->bindParam(':article_id', $article_id);
    $stmt_user_like->bindParam(':user_id', $user_id);
    $stmt_user_like->execute();
    $user_liked = $stmt_user_like->rowCount() > 0;

} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

function getAvatar($userAvatar, $username = '')
{
    $defaultAvatar = 'path/to/default-avatar.png'; // Sesuaikan path default avatar

    // Jika avatar kosong atau null, gunakan icon profil
    if (empty($userAvatar)) {
        // Cek apakah username tersedia
        if (!empty($username)) {
            return 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=random&color=fff';
        }
        return $defaultAvatar;
    }

    // Cek apakah avatar adalah URL dari Supabase Storage
    if (strpos($userAvatar, 'https://') === 0 || strpos($userAvatar, 'http://') === 0) {
        // Jika sudah URL lengkap, kembalikan URL tersebut
        return $userAvatar;
    }

    // Jika avatar adalah path lokal, cek file
    $localPath = 'uploads/avatars/' . $userAvatar;
    if (file_exists($localPath)) {
        return $localPath;
    }

    // Jika tidak cocok dengan kondisi apa pun, gunakan generator avatar berdasarkan username
    if (!empty($username)) {
        return 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=random&color=fff';
    }

    // Fallback ke avatar default
    return $defaultAvatar;
}

// Ambil data pengguna
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Dapatkan avatar yang valid
    $avatar = getAvatar($user['avatar']);
} else {
    $avatar = 'path/to/default-avatar.png';
}

// Fungsi untuk mendapatkan thumbnail dengan fallback
function getThumbnail($article)
{
    $thumbnail_columns = ['thumbnail', 'image', 'image_url'];

    foreach ($thumbnail_columns as $column) {
        if (!empty($article[$column])) {
            return htmlspecialchars($article[$column]);
        }
    }

    return 'path/to/default-image.jpg';
}

// Fungsi untuk memotong deskripsi menjadi n kata
function truncateDescription($description, $wordLimit)
{
    $words = explode(' ', $description);
    if (count($words) > $wordLimit) {
        return implode(' ', array_slice($words, 0, $wordLimit)) . '...';
    }
    return $description;
}

$isLoggedIn = isset($_SESSION['user_id']);
?>

<style>
    .article-thumbnail {
        width: 100%;
        height: 500px;
        object-fit: cover;
        border-radius: 8px;
    }

    .article-content a {
        color: #60a5fa;
        /* Warna biru muda */
        text-decoration: none;
        border-bottom: 1px dotted #60a5fa;
        transition: color 0.3s ease;
    }

    .article-content a:hover {
        color: #3b82f6;
        border-bottom: 1px solid #3b82f6;
    }

    .like-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(90deg, #212121 0%, transparent 1%);
        border: 0.1em solid #313131;
        padding: 0.5em;
        border-radius: 0.35em;
        box-shadow: 0 0 1em 0.5em rgba(0, 0, 0, 0.1);
        cursor: pointer;
        margin-bottom: 1rem;
    }

    .check[type="checkbox"] {
        display: none;
    }

    .container {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .icon {
        width: 1.5em;
        height: 1.5em;
        margin-left: 0.5em;
        fill: white;
        transition: opacity 0.3s ease-in-out;
    }

    .icon.active {
        display: none;
        fill: #f52121;
    }

    .check[type="checkbox"]:checked+.container .icon.active {
        display: inline-block;
        animation: wiggle 0.5s ease-in-out;
    }

    .check[type="checkbox"]:checked+.container .icon.inactive {
        display: none;
    }

    .like-text {
        margin-left: 0.5em;
        padding: 0.5em;
        color: white;
        font-family: Arial, sans-serif;
        font-weight: bolder;
    }

    @keyframes wiggle {

        0%,
        100% {
            transform: rotate(0deg);
        }

        25% {
            transform: rotate(-10deg);
        }

        50% {
            transform: rotate(10deg);
        }

        75% {
            transform: rotate(-10deg);
        }
    }

    .share-wrapper {
        display: flex;
        align-items: center;
        margin-left: 16px;
        padding-right: 15px;
    }

    .share-text {
        color: white;
        margin-right: 8px;
        font-weight: bold;
    }

    .share-icons {
        display: flex;
        align-items: center;
    }

    .share-icon {
        color: white;
        margin-left: 0.5em;
        font-size: 1.5em;
        transition: color 0.3s;
    }

    .share-icon.twitter:hover {
        color: #1DA1F2;
        /* Twitter blue */
    }

    .share-icon.whatsapp:hover {
        color: #25D366;
        /* WhatsApp green */
    }

    .share-icon.facebook:hover {
        color: #3b5998;
        /* Facebook blue */
    }

    .share-icon.copy-link:hover {
        color: #727372;
        /* Same as your like icon color */
    }

    @media (max-width: 640px) {
        .like-wrapper {
            display: flex;
            flex-direction: row;
            align-items: center;
            /* Vertical alignment */
            justify-content: flex-start;
            /* Alignment dari kiri */
            padding: 0.5em;
            gap: 1rem;
            /* Tambahkan jarak antar elemen */
        }

        .like-wrapper .container {
            display: flex;
            align-items: center;
            margin-right: 0;
            /* Hapus margin kanan */
        }

        .like-text {
            font-size: 0.8rem;
            margin-left: 0.3em;
            padding: 0.3em;
        }

        .share-wrapper {
            display: flex;
            align-items: center;
        }

        .share-text {
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }

        .share-icons {
            display: flex;
            align-items: center;
        }

        .share-icon {
            font-size: 1.2em;
            margin-left: 0.3em;
        }
    }

    .social {
        width: fit-content;
        height: fit-content;
        background-color: #111827;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 18px 25px;
        gap: 20px;
        box-shadow: 0px 0px 20px rgba(0, 0, 0, 0.055);

        /* Tambahkan properti ini untuk posisi center di bawah footer */
        margin: 20px auto;
        /* auto di samping kiri-kanan akan membuat elemen center */
    }

    /* for all social containers*/
    .socialContainer {
        width: 30px;
        height: 30px;
        background-color: rgb(44, 44, 44);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        transition-duration: .3s;
    }

    /* instagram*/
    .containerOne:hover {
        background-color: #d62976;
        transition-duration: .3s;
    }

    /* twitter*/
    .containerTwo:hover {
        background-color: #00acee;
        transition-duration: .3s;
    }

    /* TikTok*/
    .containerThree:hover {
        background-color: #a83242;
        transition-duration: .3s;
    }

    .socialContainer:active {
        transform: scale(0.9);
        transition-duration: .3s;
    }

    .socialSvg {
        width: 17px;
    }

    .socialSvg path {
        fill: rgb(255, 255, 255);
    }

    .socialContainer:hover .socialSvg {
        animation: slide-in-top 0.3s both;
    }

    @keyframes slide-in-top {
        0% {
            transform: translateY(-50px);
            opacity: 0;
        }

        100% {
            transform: translateY(0);
            opacity: 1;
        }
    }

    #notification {
        position: fixed;
        bottom: 10px;
        left: 50%;
        transform: translateX(-50%);
        background-color: #3b82f6;
        /* Warna biru */
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        transition: opacity 0.5s ease-in-out;
        z-index: 1000;
        /* Pastikan notifikasi berada di atas elemen lain */
    }

    .article-thumbnail {
        width: 100%;
        height: 500px;
        /* Ubah tinggi default */
        object-fit: cover;
        /* Pastikan gambar tetap proporsional */
        object-position: center;
        /* Pusatkan gambar */
        border-radius: 8px;
        max-height: 500px;
        /* Batasi tinggi maksimum */
    }

    @media (max-width: 640px) {
        .article-thumbnail {
            height: 200px;
            object-fit: cover;
        }
    }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.quilljs.com/1.3.6/quill.core.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }

        .article-content strong {
            font-weight: bold;
        }

        .article-content em {
            font-style: italic;
        }

        .article-content u {
            text-decoration: underline;
        }

        .article-content .ql-align-center {
            text-align: center;
        }

        .article-content .ql-align-right {
            text-align: right;
        }

        .article-content .ql-align-justify {
            text-align: justify;
        }

        .article-content ul {
            list-style-type: disc;
            padding-left: 30px;
        }

        .article-content ol {
            list-style-type: decimal;
            padding-left: 30px;
        }
    </style>
</head>

<body class="bg-gray-900 text-white">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div id="sidebar"
            class="fixed inset-y-0 left-0 w-64 bg-gray-800 transform -translate-x-full transition-transform duration-300 ease-in-out z-50">
            <div class="p-4">
                <div class="flex justify-between items-center mb-6">
                    <a href="home.php" class="flex items-center">
                        <img src="https://www.nicepng.com/png/full/39-395708_globe-clipart-black-and-white-earth-clipart-black.png"
                            alt="WWD Logo" class="h-8 mr-2">
                        <h1 class="text-xl font-bold text-white font-serif">WWD.</h1>
                    </a>
                    <button id="closeSidebar" class="text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <nav>
                    <ul class="space-y-4">
                        <li>
                            <a class="text-white hover:text-blue-400 <?php echo (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'text-blue-500' : ''; ?>"
                                href="home.php">
                                <i class="fas fa-home mr-2"></i>Home
                            </a>
                        </li>
                        <li>
                            <a class="text-white hover:text-blue-400 <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'text-blue-500' : ''; ?>"
                                href="dashboard.php">
                                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                            </a>
                        </li>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <li>
                                <a class="text-white hover:text-blue-400" href="login.php">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                                </a>
                            </li>
                        <?php else: ?>
                            <li>
                                <a class="text-white hover:text-red-600" href="logout.php">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>

                <div class="mt-4 px-2">
                    <form action="search.php" method="GET" class="relative">
                        <input type="text" name="query" placeholder="Search articles..."
                            class="w-full px-3 py-2 bg-gray-700 text-white rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 pl-8">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </form>
                </div>
            </div>
        </div>

        <!-- Overlay -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-black opacity-50 hidden z-40"></div>

        <!-- Main Content -->
        <div class="flex-grow flex flex-col">
            <!-- Hamburger Menu -->
            <header class="bg-gray-800 p-4 flex justify-between items-center">
                <button id="openSidebar" class="text-white">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                <div class="relative group">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <img src="<?php echo getAvatar($avatar, $user['username']); ?>" alt="User Avatar"
                            class="w-8 h-8 rounded-full cursor-pointer" />
                    <?php else: ?>
                        <i class="fas fa-user-circle text-2xl cursor-pointer"></i>
                    <?php endif; ?>
                </div>
            </header>

            <main class="flex-grow p-8 flex flex-col lg:flex-row">
                <div class="lg:w-2/3 lg:pr-8">
                    <h2 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($article['title']); ?></h2>
                    <p class="text-gray-400 mb-2">By <span
                            class="text-blue-400"><?php echo htmlspecialchars($article['username']); ?></span> on <span
                            class="text-gray-500"><?php echo date('F j, Y', strtotime($article['created_at'])); ?></span>
                    </p>

                    <img src="<?php echo getThumbnail($article); ?>" alt="Artikel thumbnail"
                        class="article-thumbnail rounded mb-4">

                    <div class="text-gray-400 mb-4 article-content">
                        <?php echo $article['content']; ?>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-2xl font-bold mb-4">Comments</h3>

                        <!-- Tombol Like dan Jumlah Like -->
                        <div class="like-wrapper">
                            <input class="check" type="checkbox" id="like-toggle" <?php echo $user_liked ? 'checked' : ''; ?> />
                            <label class="container" for="like-toggle"
                                data-logged-in="<?php echo $isLoggedIn ? 'true' : 'false'; ?>">
                                <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" class="icon inactive">
                                    <path
                                        d="M225.8 468.2l-2.5-2.3L48.1 303.2C17.4 274.7 0 234.7 0 192.8v-3.3c0-70.4 50-130.8 119.2-144C158.6 37.9 198.9 47 231 69.6c9 6.4 17.4 13.8 25 22.3c4.2-4.8 8.7-9.2 13.5-13.3c3.7-3.2 7.5-6.2 11.5-9c0 0 0 0 0 0C313.1 47 353.4 37.9 392.8 45.4C462 58.6 512 119.1 512 189.5v3.3c0 41.9-17.4 81.9-48.1 110.4L288.7 465.9l-2.5 2.3c-8.2 7.6-19 11.9-30.2 11.9s-22-4.2-30.2-11.9zM239.1 145c-.4-.3-.7-.7-1-1.1l-17.8-20c0 0-.1-.1-.1-.1c0 0 0 0 0 0c-23.1-25.9-58-37.7-92-31.2C81.6 101.5 48 142.1 48 189.5v3.3c0 28.5 11.9 55.8 32.8  75.2L256 430.7 431.2 268c20.9-19.4 32.8-46.7 32.8-75.2v-3.3c0-47.3-33.6-88-80.1-96.9c-34-6.5-69 5.4-92 31.2c0 0 0 0-.1 .1s0 0-.1 .1l-17.8 20c-.3 .4-.7 .7-1 1.1c-4.5 4.5-10.6 7-16.9 7s-12.4-2.5-16.9-7z">
                                    </path>
                                </svg>
                                <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" class="icon active">
                                    <path
                                        d="M47.6 300.4L228.3 469.1c7.5 7 17.4 10.9 27.7 10.9 s-20.2-3.9 27.7-10.9L464.4 300.4c30.4-28.3 47.6-68 47.6-109.5v-5.8c0-69.9-50.5-129.5-119.4-141C347 36.5 300.6 51.4 268 84L256 96 244 84c-32.6-32.6-79-47.5-124.6-39.9C50.5 55.6 0 115.2 0 185.1v5.8c0 41.5 17.2 81.2 47.6 109.5z">
                                    </path>
                                </svg>
                                <span class="like-text" id="likeCount"><?php echo $like_count; ?> Likes</span>
                            </label>
                            <div class="share-wrapper">
                                <span class="share-text">Share:</span>
                                <div class="share-icons">
                                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://yourwebsite.com/article.php?id=' . $article_id); ?>"
                                        target="_blank" class="share-icon twitter" title="Share on Twitter">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode('Check this out: http://yourwebsite.com/article.php?id=' . $article_id); ?>"
                                        target="_blank" class="share-icon whatsapp" title="Share on WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://yourwebsite.com/article.php?id=' . $article_id); ?>"
                                        target="_blank" class="share-icon facebook" title="Share on Facebook">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                    <a href="#" class="share-icon copy-link" title="Copy Link" onclick="copyLink()">
                                        <i class="fas fa-link"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <p class="text-red-400 mb-4">You must <a href="login.php"
                                    class="text-blue-400 hover:underline">login</a> to leave a comment.</p>
                        <?php else: ?>
                            <form action="post_comment.php" method="POST" class="mb-4">
                                <input type="hidden" name="article_id" value="<?php echo $article_id; ?>">
                                <textarea name="comment" rows="4"
                                    class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                                    placeholder="Add a comment..." required></textarea>
                                <button type="submit"
                                    class="mt-2 bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">Post
                                    Comment</button>
                            </form>
                        <?php endif; ?>
                        <div class="space-y-4">
                            <?php foreach ($comments as $comment): ?>
                                <div class="bg-gray-800 p-4 rounded-lg flex items-start">
                                    <i class="fas fa-user-circle text-3xl mr-4"></i>
                                    <div>
                                        <p class="text-blue-400"><?php echo htmlspecialchars($comment['username']); ?></p>
                                        <p class="text-gray-500 text-sm mb-2">
                                            <?php echo date('F j, Y', strtotime($comment['created_at'])); ?>
                                        </p>
                                        <p class="text-gray-400"><?php echo htmlspecialchars($comment['comment']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="hidden lg:block lg:w-1/3 rounded-lg p-4">
                    <h3 class="text-xl font-bold mb-4 text-white">Recommended Articles</h3>
                    <div class="space-y-3">
                        <?php foreach ($recommended_articles as $rec_article): ?>
                            <div class="p-3 rounded-lg max-w-xs mx-auto transition-all duration-300 
                                    bg-gray-800 hover:bg-white 
                                    flex items-center 
                                    group">
                                <img src="<?php echo getThumbnail($rec_article); ?>"
                                    alt="Thumbnail image for <?php echo htmlspecialchars($rec_article['title']); ?>"
                                    class="w-16 h-16 object-cover rounded-md mr-3 flex-shrink-0">
                                <div class="flex-grow">
                                    <h4
                                        class="text-base font-semibold mb-1 text-white group-hover:text-gray-800 line-clamp-2">
                                        <?php echo htmlspecialchars($rec_article['title']); ?>
                                    </h4>
                                    <p class="text-gray-300 group-hover:text-gray-600 text-xs mb-1">
                                        <?php echo truncateDescription(htmlspecialchars($rec_article['description']), 12); ?>
                                    </p>
                                    <a href="article.php?id=<?php echo $rec_article['id']; ?>"
                                        class="text-blue-200 group-hover:text-blue-500 text-xs">Read More →</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tombol Go Up -->
                <button id="goUpButton" class="fixed bottom-6 right-6 bg-blue-500 text-white 
                                                w-12 h-12 rounded-full flex items-center justify-center 
                                                shadow-lg hover:bg-blue-600 transition-all duration-300 
                                                opacity-0 pointer-events-none z-50" aria-label="Scroll to top">
                    <i class="fas fa-arrow-up"></i>
                </button>
            </main>

            <footer class="bg-gray-800 text-white p-4 text-center">
                <div class="social">
                    <a href="#" class="socialContainer containerOne">
                        <svg class="socialSvg instagramSvg" viewBox="0 0 16 16">
                            <path
                                d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z">
                            </path>
                        </svg>
                    </a>

                    <a href="#" class="socialContainer containerTwo">
                        <svg class="socialSvg twitterSvg" viewBox="0 0 16 16">
                            <path
                                d="M5.026 15c6.038 0 9.341-5.003 9.341-9.334 0-.14 0-.282-.006-.422A6.685 6.685 0 0 0 16 3.542a6.658 6.658 0 0 1-1.889.518 3.301 3.301 0 0 0 1.447-1.817 6.533 6.533 0 0 1-2.087.793A3.286 3.286 0 0 0 7.875 6.03a9.325 9.325 0 0 1-6.767-3.429 3.289 3.289 0 0 0 1.018 4.382A3.323 3.323 0 0 1 .64 6.575v.045a3.288 3.288 0 0 0 2.632 3.218 3.203 3.203 0 0 1-.865.115 3.23 3.23 0 0 1-.614-.057 3.283 3.283 0 0 0 3.067 2.277A6.588 6.588 0 0 1 .78 13.58a6.32 6.32 0 0 1-.78-.045A9.344 9.344 0 0 0 5.026 15z">
                            </path>
                        </svg>
                    </a>

                    <a href="https://www.tiktok.com/@worldwidedairy?_t=ZS -8rjMvXgxdxv&_r=1"
                        class="socialContainer containerThree">
                        <svg class="socialSvg tiktokSvg" viewBox="0 0 448 512">
                            <path
                                d="M412.19,118.66a109.27,109.27,0,0,1-9.45-5.5,132.87,132.87,0,0,1-24.27-20.62c-18.1-20.71-24.86-41.72-27.35-56.43h.1C349.14,23.9,350,16,350.13,16H267.69V334.78c0,4.28,0,8.51-.18,12.69,0,.52-.05,1-.08,1.56,0,.23,0,.47-.05.71,0,.06,0,.12,0,.18a70,70,0,0,1-35.22,55.56,68.8,68.8,0,0,1-34.11,9c-38.41,0-69.54-31.32-69.54-70s31.13-70,69.54-70a68.9,68.9,0,0,1,21.41,3.39l.1-83.94a153.14,153.14,0,0,0-118,34.52,161.79,161.79,0,0,0-35.3,43.53c-3.48,6-16.61,30.11-18.2,69.24-1,22.21,5.67,45.22,8.85,54.73v.2c2,5.6,9.75,24.71,22.38,40.82A167.53,167.53,0,0,0,115,470.66v-.2l.2.2C155.11,497.78,199.36,496,199.36,496c7.66-.31,33.32,0,62.46-13.81,32.32-15.31,50.72-38.12,50.72-38.12a158.46,158.46,0,0,0,27.64-45.93c7.46-19.61,9.95-43.13,9.95-52.53V176.49c1,.6,14.32,9.41,14.32,9.41s19.19,12.3,49.13,20.31c21.48,5.7,50.42,6.9,50.42,6.9V131.27C453.86,132.37,433.27,129.17,412.19,118.66Z" />
                        </svg>
                    </a>
                </div>

                <div class="container mx-auto flex flex-col items-center">
                    <p>&copy; <?php echo date('Y'); ?> WorldWideDiary (WWD). All Rights Reserved.</p>
                    <div class="mt-2 text-sm text-gray-400 text-center">
                        <a href="#" class="hover:text-blue-400 mx-2">Privacy Policy</a>
                        <a href="#" class="hover:text-blue-400 mx-2">Terms of Service</a>
                        <a href="#" class="hover:text-blue-400 mx-2">Contact Us</a>
                    </div>
                </div>

            </footer>
        </div>

        <div id="notification"
            class="hidden fixed bottom-10 left-1/2 transform -translate-x-1/2 bg-blue-500 text-white p-4 rounded shadow-lg transition-opacity duration-300 opacity-0">
            Link copied to clipboard!
        </div>
    </div>

    <script>
        // JavaScript untuk kontrol sidebar
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const openSidebarBtn = document.getElementById('openSidebar');
        const closeSidebarBtn = document.getElementById('closeSidebar');

        // Fungsi untuk membuka sidebar
        openSidebarBtn.addEventListener('click', () => {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('hidden');
        });

        // Fungsi untuk menutup sidebar
        closeSidebarBtn.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });

        // Menutup sidebar saat overlay diklik
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });

        // Fungsi untuk tombol Go Up
        const goUpButton = document.getElementById('goUpButton');

        // Tampilkan/sembunyikan tombol berdasarkan scroll
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                goUpButton.classList.remove('opacity-0', 'pointer-events-none');
                goUpButton.classList.add('opacity-100');
            } else {
                goUpButton.classList.add('opacity-0', 'pointer-events-none');
                goUpButton.classList.remove('opacity-100');
            }
        });

        // Scroll ke atas dengan smooth
        goUpButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // JavaScript untuk Like
        document.addEventListener('DOMContentLoaded', function () {
            const likeCheckbox = document.getElementById('like-toggle');
            const likeCountElement = document.getElementById('likeCount');
            const articleId = <?php echo json_encode($article_id); ?>; // Ambil ID artikel dari PHP
            const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>; // Ambil status login dari PHP
            let likeCount = parseInt(likeCountElement.textContent) || 0;

            // Event listener untuk checkbox like
            likeCheckbox.addEventListener('change', function () {
                if (!isLoggedIn) {
                    // Jika pengguna tidak login, kembalikan checkbox ke status semula
                    this.checked = !this.checked;

                    // Tampilkan notifikasi untuk pengguna yang tidak login
                    showNotification('You must be logged in to like this article.');
                    return;
                }

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'like_article.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            likeCount = response.likeCount; // Update like count dari response
                            likeCountElement.textContent = likeCount + ' Likes';
                        } else {
                            console.error(response.message);
                        }
                    }
                };
                xhr.send('article_id=' + articleId + '&like=' + (this.checked ? '1' : '0'));
            });

            // Fungsi untuk menampilkan notifikasi
            function showNotification(message) {
                const notification = document.getElementById('notification');
                notification.textContent = message; // Ubah teks notifikasi
                notification.classList.remove('hidden', 'opacity-0');
                notification.classList.add('opacity-100');

                // Menghilangkan notifikasi setelah 3 detik
                setTimeout(() => {
                    notification.classList.remove('opacity-100');
                    notification.classList.add('opacity-0');

                    // Menggunakan setTimeout untuk menyembunyikan elemen setelah animasi selesai
                    setTimeout(() => {
                        notification.classList.add('hidden');
                    }, 300); // Waktu ini harus sama dengan durasi transisi opacity
                }, 3000); // Menampilkan notifikasi selama 3 detik
            }

            // Fungsi untuk menyalin link
            function copyLink(event) {
                event.preventDefault(); // Mencegah refresh halaman

                const url = window.location.href;
                navigator.clipboard.writeText(url).then(() => {
                    showNotification('Link copied to clipboard!');
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                    showNotification('Failed to copy link');
                });
            }

            // Tambahkan event listener ke semua elemen dengan class copy-link
            document.querySelectorAll('.copy-link').forEach(link => {
                link.addEventListener('click', copyLink);
            });

            // Tambahkan event listener untuk tombol salin link
            const copyButton = document.getElementById('copy-link-button');
            if (copyButton) {
                copyButton.addEventListener('click', copyLink);
            }
        });
    </script>
</body>

</html>
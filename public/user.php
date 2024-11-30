<?php
session_start();

// Konfigurasi database Supabase
$host = 'aws-0-ap-southeast-1.pooler.supabase.com';
$dbname = 'postgres';
$db_username = 'postgres.tqilpyehwaaknppnpyah';
$db_password = 'Omtelolet123.';
$port = 5432;

try {
    // Buat koneksi ke database menggunakan PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $conn = new PDO($dsn, $db_username, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi ke database gagal: " . $e->getMessage());
}

// Tambahkan di bagian awal skrip, setelah koneksi database
$logged_in_user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id::uuid");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_STR);
    $stmt->execute();
    $logged_in_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fungsi validasi UUID
function isValidUUID($uuid)
{
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    return preg_match($pattern, $uuid) === 1;
}

// Fungsi untuk mendapatkan avatar default atau yang ada di database
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

function limitWords($string, $wordLimit)
{
    $words = explode(' ', $string);
    if (count($words) > $wordLimit) {
        return implode(' ', array_slice($words, 0, $wordLimit)) . '...';
    }
    return $string;
}

// Tambahkan variabel untuk ID profil yang dikunjungi
$profile_user_id = isset($_GET['id']) ? $_GET['id'] : null;

// Jika tidak ada ID yang diberikan, redirect atau tangani
if (!$profile_user_id) {
    header("Location: home.php");
    exit();
}

try {
    // Validasi UUID sebelum query
    if (!isValidUUID($profile_user_id)) {
        throw new Exception("Format UUID tidak valid");
    }

    // Query untuk mendapatkan data pengguna yang dikunjungi
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :profile_user_id::uuid");
    $stmt->bindParam(':profile_user_id', $profile_user_id, PDO::PARAM_STR);
    $stmt->execute();
    $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile_user) {
        throw new Exception("Pengguna tidak ditemukan");
    }

    // Dapatkan avatar yang valid
    $avatar = getAvatar($profile_user['avatar']);

    // Ambil artikel milik pengguna yang dikunjungi dengan informasi tambahan
    $query = "
        SELECT a.*, 
            COUNT(l.id) AS like_count,
            u.username AS author_name 
        FROM articles a 
        LEFT JOIN likes l ON a.id = l.article_id 
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.user_id = :profile_user_id::uuid
        GROUP BY a.id, u.username 
        ORDER BY a.created_at DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':profile_user_id', $profile_user_id, PDO::PARAM_STR);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log error database
    error_log("Kesalahan Database: " . $e->getMessage());
    die("Koneksi database gagal. Silakan coba lagi nanti.");
} catch (Exception $e) {
    // Log error aplikasi
    error_log("Kesalahan Aplikasi: " . $e->getMessage());
    header("Location: home.php?error=" . urlencode($e->getMessage()));
    exit();
}

// Query Artikel Hero Rekomendasi
$heroStmt = $conn->prepare("
    SELECT a.*, 
           u.username AS author_name,
           COUNT(l.id) AS like_count
    FROM articles a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN likes l ON a.id = l.article_id
    GROUP BY a.id, u.username
    ORDER BY a.created_at DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Sama seperti dashboard.php -->
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Profile - <?php echo htmlspecialchars($profile_user['username']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet" />

    <!-- Gunakan style yang sama seperti dashboard.php -->
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }

        .card {
            width: 100%;
            height: auto;
            background-color: #1F2937;
            border-radius: 12px;
            /* Slightly increased border radius for softer corners */
            color: white;
            overflow: hidden;
            position: relative;
            transform-style: preserve-3d;
            perspective: 1000px;
            transition: all 0.5s cubic-bezier(0.23, 1, 0.320, 1);
            cursor: pointer;
            padding: 20px;
            /* Added padding to give more breathing room */
        }

        .card-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 15px;
            /* Increased gap between elements */
            max-width: 90%;
            margin: 0 auto;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: inherit;
            text-transform: uppercase;
            margin: 0;
            line-height: 1.3;
            letter-spacing: 1px;
            /* Added letter spacing for better readability */
        }

        .card-para {
            color: inherit;
            opacity: 0.7;
            /* Slightly reduced opacity for softer text */
            font-size: 14px;
            /* Slightly increased font size for better readability */
            margin: 0;
            line-height: 1.6;
            /* Increased line height for better spacing */
            max-width: 100%;
            /* Ensure text doesn't overflow */
        }

        .card:hover {
            transform: rotateY(8deg) rotateX(8deg) scale(1.03);
            /* Slightly reduced transform effect */
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
            /* Softer, more refined shadow */
        }

        .card:before,
        .card:after {
            content: "";
            position: absolute;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.1));
            transition: transform 0.5s cubic-bezier(0.23, 1, 0.320, 1);
            z-index: 1;
        }

        .card:before {
            left: 0;
        }

        .card:after {
            right: 0;
        }

        .card:hover:before {
            transform: translateX(-100%);
        }

        .card:hover:after {
            transform: translateX(100%);
        }

        /* From Uiverse.io by vinodjangid07 */
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
            border-radius: 10px;
            margin: 20px auto;
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
                                <a class="text-white hover:text-blue-400" href="logout.php">
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

        <div id="sidebarOverlay" class="fixed inset-0 bg-black opacity-50 hidden z-40"></div>

        <!-- Main Content -->
        <div class="flex-grow flex flex-col">
            <!-- Header -->
            <header class="bg-gray-800 p-4 flex justify-between items-center">
                <button class="text-white" id="openSidebar">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                <div class="relative group">
                    <?php if ($logged_in_user): ?>
                        <img src="<?php
                        echo getAvatar(
                            $logged_in_user['avatar'] ?? '',
                            $logged_in_user['username']
                        );
                        ?>" alt="User Avatar" class="w-8 h-8 rounded-full cursor-pointer" />
                    <?php else: ?>
                        <i class="fas fa-user-circle text-2xl cursor-pointer"></i>
                    <?php endif; ?>
                </div>
            </header>

            <main class="flex-grow p-8">
                <!-- Bio Section -->
                <section class="bg-gray-800 p-6 rounded-lg shadow-lg mb-8">
                    <div class="flex items-center space-x-6">
                        <img alt="Profile picture" class="w-32 h-32 rounded-full"
                            src="<?php echo htmlspecialchars($avatar); ?>"
                            onerror="this.onerror=null; this.src='path/to/default-avatar.png';" width="150"
                            height="150" />
                        <div>
                            <h2 class="text-3xl font-bold">
                                <?php echo htmlspecialchars($profile_user['username']); ?>
                            </h2>
                            <p class="text-gray-400">
                                <?php echo isset($profile_user['job']) ? htmlspecialchars($profile_user['job']) : 'No Job Specified'; ?>
                            </p>
                            <div class="mt-4 flex space-x-4">
                                <?php if (!empty($profile_user['instagram'])): ?>
                                    <a class="text-blue-400 hover:text-blue-600"
                                        href="<?php echo htmlspecialchars($profile_user['instagram']); ?>" target="_blank">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($profile_user['linkedin'])): ?>
                                    <a class="text-blue-400 hover:text-blue-600"
                                        href="<?php echo htmlspecialchars($profile_user['linkedin']); ?>" target="_blank">
                                        <i class="fab fa-linkedin"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($profile_user['tiktok'])): ?>
                                    <a class="text-blue-400 hover:text-blue-600"
                                        href="<?php echo htmlspecialchars($profile_user['tiktok']); ?>" target="_blank">
                                        <i class="fab fa-tiktok"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($profile_user['twitter'])): ?>
                                    <a class="text-blue-400 hover:text-blue-600"
                                        href="<?php echo htmlspecialchars($profile_user['twitter']); ?>" target="_blank">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6">
                        <h3 class="text-2xl font-bold">Biography</h3>
                        <p id="bioText" class="mt-2 text-gray-400">
                            <?php echo htmlspecialchars($profile_user['biography']); ?>
                        </p>
                    </div>
                </section>

                <!-- Artikel Section -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Articles by
                        <?php echo htmlspecialchars($profile_user['username']); ?>
                    </h2>
                </div>

                <!-- Artikel Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($articles as $article): ?>
                        <div class="card bg-gray-800 p-4 rounded-lg shadow-lg">
                            <img alt="Thumbnail image of <?php echo htmlspecialchars($article['title']); ?>"
                                class="w-full h-40 object-cover rounded mb-4"
                                src="<?php echo htmlspecialchars($article['thumbnail']); ?>"
                                onerror="this.onerror=null; this.src='path/to/default-thumbnail.png';" />

                            <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($article['title']); ?></h3>

                            <div class="flex items-center text-sm text-gray-400 mb-2">
                                <i class="fas fa-user mr-2"></i>
                                <?php
                                // Use author's name, fallback to 'Unknown Author' if not set
                                echo htmlspecialchars($article['author_name'] ?? 'Unknown Author');
                                ?>
                            </div>

                            <p class="text-gray-400 mb-4">
                                <?php
                                // Limit description to 19 words
                                $words = explode(' ', $article['description']);
                                $limited_description = implode(' ', array_slice($words, 0, 19));
                                echo htmlspecialchars($limited_description);

                                // Add ellipsis if description is longer than 19 words
                                if (count($words) > 19) {
                                    echo '...';
                                }
                                ?>
                            </p>

                            <div class="flex items-center justify-between">
                                <a class="text-blue-400 hover:underline"
                                    href="article.php?id=<?php echo $article['id']; ?>">
                                    Read More
                                </a>
                                <div class="flex items-center">
                                    <span class="mr-1 text-red-500">
                                        <i class="fas fa-heart"></i>
                                    </span>
                                    <span class="text-gray-300">
                                        <?php echo htmlspecialchars($article['like_count'] ?? '0'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- Button Go up -->
                <button id="goUpButton" class="fixed bottom-6 right-6 bg-blue-500 text-white 
                                                w-12 h-12 rounded-full flex items-center justify-center 
                                                shadow-lg hover:bg-blue-600 transition-all duration-300 
                                                opacity-0 pointer-events-none z-50" aria-label="Scroll to top">
                    <i class="fas fa-arrow-up"></i>
                </button>
            </main>

            <!-- Footer -->
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

                <div class="container mx-auto">
                    <p>&copy; <?php echo date('Y'); ?> WorldWideDiary (WWD). All Rights Reserved.</p>
                    <div class="mt-2 text-sm text-gray-400">
                        <a href="#" class="hover:text-blue-400 mx-2">Privacy Policy</a>
                        <a href="#" class="hover:text-blue-400 mx-2">Terms of Service</a>
                        <a href="#" class="hover:text-blue-400 mx-2">Contact Us</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script>
        // JavaScript untuk sidebar dan scroll to top button
        document.getElementById('openSidebar').addEventListener('click', function () {
            document.getElementById('sidebar').classList.remove('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.remove('hidden');
        });

        document.getElementById('closeSidebar').addEventListener('click', function () {
            document.getElementById('sidebar').classList.add('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.add('hidden');
        });

        // Scroll to top button functionality
        const goUpButton = document.getElementById('goUpButton');
        window.addEventListener('scroll', function () {
            if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
                goUpButton.style.opacity = '1';
                goUpButton.style.pointerEvents = 'auto';
            } else {
                goUpButton.style.opacity = '0';
                goUpButton.style.pointerEvents = 'none';
            }
        });

        goUpButton.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>

</html>
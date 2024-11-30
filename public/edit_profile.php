<?php
session_start();

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Konfigurasi koneksi database (gunakan environment variables untuk keamanan)
$host = getenv('DB_HOST') ?: 'aws-0-ap-southeast-1.pooler.supabase.com';
$dbname = getenv('DB_NAME') ?: 'postgres';
$username = getenv('DB_USERNAME') ?: 'postgres.tqilpyehwaaknppnpyah';
$password = getenv('DB_PASSWORD') ?: 'Omtelolet123.';
$port = getenv('DB_PORT') ?: 5432;

// Konfigurasi Imgur (sembunyikan client ID di environment)
$imgur_client_id = getenv('IMGUR_CLIENT_ID') ?: 'cfc5629b71cfc5e';

// Fungsi validasi UUID
function isValidUUID($uuid)
{
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    return preg_match($pattern, $uuid) === 1;
}

// Fungsi upload gambar ke Imgur dengan error handling lebih baik
function uploadToImgur($image, $client_id)
{
    // Validasi file gambar
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    $file_type = mime_content_type($image);
    $file_size = filesize($image);

    if (!in_array($file_type, $allowed_types)) {
        throw new Exception("Tipe file tidak didukung. Gunakan JPEG, PNG, atau GIF.");
    }

    if ($file_size > $max_file_size) {
        throw new Exception("Ukuran file terlalu besar. Maksimal 5MB.");
    }

    // Membaca file gambar
    $data = file_get_contents($image);
    $base64 = base64_encode($data);

    // Endpoint API Imgur
    $url = 'https://api.imgur.com/3/image';
    $headers = [
        'Authorization: Client-ID ' . $client_id,
        'Content-Type: application/x-www-form-urlencoded',
    ];

    $postData = http_build_query([
        'image' => $base64
    ]);

    // Inisialisasi CURL dengan timeout
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout 30 detik
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    // Eksekusi CURL
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Logging untuk debugging
    error_log("Imgur Upload Response Code: " . $httpCode);
    error_log("Imgur Upload Response: " . $response);

    // Validasi respon
    if ($httpCode !== 200) {
        $errorDetails = json_decode($response, true);
        $errorMsg = $errorDetails['data']['error'] ?? "Gagal meng-upload gambar";
        throw new Exception($errorMsg);
    }

    $responseData = json_decode($response, true);
    if (!isset($responseData['data']['link'])) {
        throw new Exception("Tidak dapat menemukan URL gambar");
    }

    return $responseData['data']['link'];
}

try {
    // Validasi UUID
    if (!isValidUUID($_SESSION['user_id'])) {
        throw new Exception("Format UUID tidak valid");
    }

    // Koneksi database dengan opsi koneksi yang aman
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5, // Timeout koneksi 5 detik
            PDO::ATTR_PERSISTENT => false // Nonaktifkan koneksi persisten
        ]
    );

    // Ambil data user saat ini dengan casting UUID
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id::uuid");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Periksa apakah user ditemukan
    if (!$user) {
        throw new Exception("Pengguna tidak ditemukan");
    }

    // Proses update profil
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Validasi dan sanitasi input
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $job = filter_input(INPUT_POST, 'job', FILTER_SANITIZE_STRING);
        $instagram = filter_input(INPUT_POST, 'instagram', FILTER_SANITIZE_STRING);
        $linkedin = filter_input(INPUT_POST, 'linkedin', FILTER_SANITIZE_STRING);
        $tiktok = filter_input(INPUT_POST, 'tiktok', FILTER_SANITIZE_STRING);
        $twitter = filter_input(INPUT_POST, 'twitter', FILTER_SANITIZE_STRING);

        // Gunakan avatar_url dari user jika ada
        $avatar_url = $user['avatar'] ?? '';

        // Proses upload avatar ke Imgur
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            try {
                $avatar_url = uploadToImgur($_FILES['avatar']['tmp_name'], $imgur_client_id);
            } catch (Exception $uploadError) {
                // Log error untuk debugging
                error_log("Upload Imgur Error: " . $uploadError->getMessage());
                $error_message = $uploadError->getMessage();
            }
        }

        // Update informasi pengguna di database
        $stmt = $conn->prepare("UPDATE users SET 
            username = :username, 
            email = :email, 
            job = :job, 
            instagram = :instagram, 
            linkedin = :linkedin, 
            tiktok = :tiktok, 
            twitter = :twitter, 
            avatar = :avatar 
            WHERE id = :user_id::uuid");

        // Bind parameter
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':job', $job);
        $stmt->bindParam(':instagram', $instagram);
        $stmt->bindParam(':linkedin', $linkedin);
        $stmt->bindParam(':tiktok', $tiktok);
        $stmt->bindParam(':twitter', $twitter);
        $stmt->bindParam(':avatar', $avatar_url);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_STR);

        // Eksekusi query
        try {
            if ($stmt->execute()) {
                // Redirect dengan parameter update_success
                header("Location: edit_profile.php?update_success=true");
                exit();
            } else {
                $error_message = "Gagal memperbarui profil.";
            }
        } catch (PDOException $e) {
            // Log error database
            error_log("Database Update Error: " . $e->getMessage());
            $error_message = "Terjadi kesalahan pada database.";
        }
    }
} catch (Exception $e) {
    // Log semua error
    error_log("General Error: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <!-- Tambahkan link CSS Cropper.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" />

    <!-- Tambahkan script Cropper.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
</head>

<body class="bg-gray-900 text-white">
    <div class="min-h-screen flex items-center justify-center">
        <a href="dashboard.php" class="absolute top-4 left-4 text-white text-2xl">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="bg-gray-800 p-8 rounded-lg shadow-lg w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center">Edit Profil</h2>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-500 text-white p-4 rounded mb-4">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="bg-green-500 text-white p-4 rounded mb-4">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="mb-4 relative flex flex-col items-center">
                    <input type="file" id="avatar" name="avatar" accept="image/*" class="hidden">
                    <div id="avatar-preview" class="w-48 h-48 rounded-full relative cursor-pointer mb-4">
                        <img id="image-preview" src="<?php
                        // Jika avatar kosong, gunakan UI Avatars
                        if (empty($user['avatar'])) {
                            echo 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=random&color=fff';
                        } else {
                            echo htmlspecialchars($user['avatar']);
                        }
                        ?>" alt="Avatar" class="w-full h-full object-cover rounded-full">
                        <div
                            class="absolute inset-0 bg-black bg-opacity-40 rounded-full flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-pencil-alt text-white text-xl"></i>
                        </div>
                    </div>
                    <div id="crop-container" class="hidden">
                        <div class="w-80 h-80 mb-4">
                            <img id="crop-image" src="" alt="Crop Image" class="max-w-full max-h-full">
                        </div>
                        <div class="flex space-x-4">
                            <button id="crop-confirm"
                                class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                                Konfirmasi Crop
                            </button>
                            <button id="crop-cancel"
                                class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
                                Batalkan
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium mb-2">Username</label>
                    <input type="text" id="username" name="username"
                        value="<?php echo htmlspecialchars($user['username']); ?>"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                        required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium mb-2">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                        required>
                </div>
                <div class="mb-4">
                    <label for="job" class="block text-sm font-medium mb-2">Pekerjaan <span class="text-gray-400">(e.g.
                            Student)</span></label>
                    <input type="text" id="job" name="job" value="<?php echo htmlspecialchars($user['job'] ?? ''); ?>"
                        placeholder="Student"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                        required>
                </div>
                <div class="mb-4">
                    <label for="instagram" class="block text-sm font-medium mb-2">Instagram <span
                            class="text-gray-400">(Optional)</span></label>
                    <input type="url" id="instagram" name="instagram"
                        value="<?php echo htmlspecialchars($user['instagram'] ?? ''); ?>"
                        placeholder="https://instagram.com/yourprofile"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="linkedin" class="block text-sm font-medium mb-2">LinkedIn <span
                            class="text-gray-400">(Optional)</span></label>
                    <input type="url" id="linkedin" name="linkedin"
                        value="<?php echo htmlspecialchars($user['linkedin'] ?? ''); ?>"
                        placeholder="https://linkedin.com/in/yourprofile"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="tiktok" class="block text-sm font-medium mb-2">TikTok <span
                            class="text-gray-400">(Optional)</span></label>
                    <input type="url" id="tiktok" name="tiktok"
                        value="<?php echo htmlspecialchars($user['tiktok'] ?? ''); ?>"
                        placeholder="https://tiktok.com/@yourprofile"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="twitter" class="block text-sm font-medium mb-2">Twitter <span
                            class="text-gray-400">(Optional)</span></label>
                    <input type="url" id="twitter" name="twitter"
                        value="<?php echo htmlspecialchars($user['twitter'] ?? ''); ?>"
                        placeholder="https://twitter.com/yourprofile"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-medium mb-2">Password Baru (Opsional)</label>
                    <input type="password" id="new_password" name="new_password"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="block text-sm font-medium mb-2">Konfirmasi Password
                        Baru</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                        class="w-full p-3 rounded bg-gray-700 border border -gray-600 focus:outline-none focus:border-blue-500">
                </div>

                <button type="submit"
                    class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded">Simpan
                    Perubahan</button>
            </form>
        </div>
    </div>
</body>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const avatarInput = document.getElementById('avatar');
        const imagePreview = document.getElementById('image-preview');
        const avatarPreview = document.getElementById('avatar-preview');
        const cropContainer = document.getElementById('crop-container');
        const cropImage = document.getElementById('crop-image');
        const cropConfirmBtn = document.getElementById('crop-confirm');
        const cropCancelBtn = document.getElementById('crop-cancel');
        let cropper;

        avatarPreview.addEventListener('click', () => {
            avatarInput.click();
        });

        avatarInput.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    cropImage.src = e.target.result;
                    avatarPreview.classList.add('hidden');
                    cropContainer.classList.remove('hidden');

                    if (cropper) {
                        cropper.destroy();
                    }

                    cropper = new Cropper(cropImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        minCropBoxWidth: 100,
                        minCropBoxHeight: 100,
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        cropConfirmBtn.addEventListener('click', () => {
            if (cropper) {
                const canvas = cropper.getCroppedCanvas({
                    width: 300,
                    height: 300
                });

                canvas.toBlob((blob) => {
                    const url = URL.createObjectURL(blob);
                    imagePreview.src = url;

                    cropContainer.classList.add('hidden');
                    avatarPreview.classList.remove('hidden');

                    // Optional: Prepare file for upload
                    const file = new File([blob], 'avatar.png', { type: 'image/png' });
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    avatarInput.files = dataTransfer.files;
                });
            }
        });

        cropCancelBtn.addEventListener('click', () => {
            if (cropper) {
                cropper.destroy();
            }
            cropContainer.classList.add('hidden');
            avatarPreview.classList.remove('hidden');
            avatarInput.value = ''; // Reset input
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        // Cek apakah ada parameter di URL yang menandakan update sukses
        const urlParams = new URLSearchParams(window.location.search);
        const updateSuccess = urlParams.get('update_success');

        if (updateSuccess === 'true') {
            const successMessage = document.querySelector('.bg-green-500');

            if (successMessage) {
                // Fade out
                successMessage.classList.add('transition', 'duration-500', 'opacity-0');

                // Refresh setelah fade out
                setTimeout(function () {
                    // Hapus parameter dari URL
                    window.history.replaceState({}, document.title, window.location.pathname);

                    // Optional: Refresh halaman
                    window.location.reload();
                }, 500);
            }
        }
    });
</script>

</html>
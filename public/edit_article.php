<?php
session_start();

// Periksa apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Konfigurasi Imgur
define('IMGUR_CLIENT_ID', 'cfc5629b71cfc5e');

// Konfigurasi database Supabase
$host = 'aws-0-ap-southeast-1.pooler.supabase.com';
$dbname = 'postgres';
$db_username = 'postgres.tqilpyehwaaknppnpyah';
$db_password = 'Omtelolet123.';
$port = 5432;

$error_message = '';
$success_message = '';

// Buat koneksi ke database menggunakan PDO
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $conn = new PDO($dsn, $db_username, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $error_message = "Koneksi ke database gagal: " . $e->getMessage();
    exit();
}

// Fungsi untuk mengunggah gambar ke Imgur
function uploadToImgur($file)
{
    $client_id = 'cfc5629b71cfc5e';
    $imgur_api_url = 'https://api.imgur.com/3/image';

    // Pastikan file ada dan valid
    if (!file_exists($file)) {
        return ['error' => 'File tidak ditemukan'];
    }

    // Baca konten file
    $image_data = file_get_contents($file);

    // Siapkan payload untuk API Imgur
    $data = array(
        'image' => base64_encode($image_data),
        'type' => 'base64'
    );

    // Inisialisasi cURL
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $imgur_api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => array(
            "Authorization: Client-ID " . $client_id
        ),
    ));

    // Eksekusi request
    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    // Proses response
    if ($err) {
        return ['error' => "cURL Error: " . $err];
    } else {
        $response_data = json_decode($response, true);

        // Periksa apakah upload berhasil
        if (isset($response_data['success']) && $response_data['success'] === true) {
            return [
                'link' => $response_data['data']['link'],
                'delete_hash' => $response_data['data']['deletehash']
            ];
        } else {
            return ['error' => 'Upload gagal: ' . print_r($response_data, true)];
        }
    }
}

// Ambil ID artikel dari URL
if (isset($_GET['id'])) {
    $article_id = $_GET['id'];

    // Ambil data artikel dari database
    $stmt = $conn->prepare("SELECT * FROM articles WHERE id = :id");
    $stmt->bindParam(':id', $article_id);
    $stmt->execute();
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        $error_message = "Artikel tidak ditemukan.";
    }
}

// Proses pembaruan artikel
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $content = $_POST['content'];
    $thumbnail_url = $article['thumbnail']; // Simpan URL thumbnail saat ini

    // Validasi dan proses upload thumbnail
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
        // Tentukan batas ukuran file (2 MB)
        $max_file_size = 2 * 1024 * 1024; // 2 MB

        // Validasi ukuran file
        if ($_FILES['thumbnail']['size'] > $max_file_size) {
            $error_message = "Ukuran file thumbnail tidak boleh lebih dari 2 MB.";
        } else {
            // Upload ke Imgur
            $upload_result = uploadToImgur($_FILES['thumbnail']['tmp_name']);

            if (isset($upload_result['link'])) {
                // Berhasil upload
                $thumbnail_url = $upload_result['link'];
            } else {
                // Gagal upload
                $error_message = $upload_result['error'];
            }
        }
    }

    // Update artikel di database
    if (empty($error_message)) {
        try {
            $stmt = $conn->prepare("UPDATE articles SET title = :title, description = :description, content = :content, thumbnail = :thumbnail WHERE id = :id");
            $stmt->bindParam(':id', $article_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':thumbnail', $thumbnail_url);

            if ($stmt->execute()) {
                $success_message = "Artikel berhasil diperbarui";
                header("refresh:2;url=dashboard.php");
                exit();
            } else {
                $error_message = "Gagal memperbarui artikel.";
            }
        } catch (PDOException $e) {
            $error_message = "Gagal memperbarui artikel: " . $e->getMessage();
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Article</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }

        #editor-container {
            height: 375px;
            overflow: visible;
            /* Ubah dari hidden ke visible */
        }

        <style>.ql-editor {
            min-height: 300px;
            font-size: 16px;
        }

        .ql-container {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-900 text-white">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-gray-800 p-8 rounded-lg shadow-lg w-full max-w-2xl">
            <h2 class="text-2xl font-bold mb-6 text-center">Edit Article</h2>

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

            <form action="" method="POST" enctype="multipart/form-data" id="article-form">
                <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium mb-2">Title</label>
                    <input type="text" id="title" name="title"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                        value="<?php echo htmlspecialchars($article['title']); ?>" required>
                </div>
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium mb-2">Description</label>
                    <textarea id="description" name="description"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500"
                        rows="4" required><?php echo htmlspecialchars($article['description']); ?></textarea>
                </div>
                <div class="mb-4">
                    <label for="thumbnail" class="block text-sm font-medium mb-2">Thumbnail</label>
                    <input type="file" id="thumbnail" name="thumbnail"
                        class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500">
                    <img src="<?php echo htmlspecialchars($article['thumbnail']); ?>" alt="Current thumbnail image"
                        class="w-full h-40 object-cover rounded mt-4">
                </div>
                <div class="mb-4">
                    <label for="content" class="block text-sm font-medium mb-2">Content</label>
                    <!-- Quill Editor Container -->
                    <div id="editor-container" class="bg-gray-800 text-white"></div>
                    <!-- Hidden input to store Quill content -->
                    <input type="hidden" name="content" id="content-input">
                </div>
                <button type="submit"
                    class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded">Update
                    Article</button>
            </form>
        </div>
    </div>

    <!-- Quill JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        // Initialize Quill editor
        var toolbarOptions = [
            [{ 'font': [] }],
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            ['bold', 'italic', 'underline', 'strike'],        // toggled buttons
            [{ 'color': [] }, { 'background': [] }],          // dropdown with defaults from theme
            [{ 'script': 'sub' }, { 'script': 'super' }],      // superscript/subscript
            [{ 'align': [] }],

            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'indent': '-1' }, { 'indent': '+1' }],          // outdent/indent

            ['link', 'image', 'video'],                       // link and image, video
            ['clean']                                         // remove formatting button
        ];

        var quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: toolbarOptions
            }
        });

        // Fungsi untuk memastikan protokol URL
        function normalizeUrl(url) {
            // Cek apakah sudah memiliki protokol
            if (/^https?:\/\//i.test(url)) {
                return url;
            }

            // Cek domain umum yang memerlukan protokol lengkap
            const commonDomains = ['instagram.com', 'twitter.com', 'facebook.com', 'linkedin.com'];

            if (commonDomains.some(domain => url.includes(domain))) {
                return 'https://' + url;
            }

            // Untuk domain lain, tambahkan http://
            return 'http://' + url;
        }

        // Override fungsi link pada Quill
        var Link = Quill.import('formats/link');
        class CustomLink extends Link {
            static create(value) {
                const normalizedValue = normalizeUrl(value); // Normalisasi URL di sini
                const node = super.create(normalizedValue);
                node.setAttribute('target', '_blank');
                node.setAttribute('rel', 'noopener noreferrer');
                return node;
            }
        }
        Quill.register(CustomLink, true);

        quill.keyboard.addBinding({
            key: 'enter',
            handler: function (range, context) {
                if (context.format.link) {
                    const url = context.format.link;
                    const normalizedUrl = normalizeUrl(url);

                    // Update tautan dengan URL yang dinormalisasi
                    quill.format('link', normalizedUrl);
                }
                return true;
            }
        });

        // Set initial content from PHP
        quill.root.innerHTML = `<?php echo addslashes($article['content']); ?>`;

        // Form submission handler
        document.getElementById('article-form').onsubmit = function () {
            // Set the hidden input value with Quill content before form submission
            document.getElementById('content-input').value = quill.root.innerHTML;
        };
    </script>
</body>

</html>
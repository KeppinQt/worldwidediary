<?php
session_start();

// Hancurkan semua sesi
session_destroy();

// Redirect ke halaman home setelah logout
header("Location: home.php");
exit();
?>
<?php
session_start();
$admin_password = 'admin123';
$error = '';

if ( $_SERVER[ 'REQUEST_METHOD' ] == 'POST' ) {
    if ( isset( $_POST[ 'password' ] ) && $_POST[ 'password' ] === $admin_password ) {
        $_SESSION[ 'admin_logged_in' ] = true;
        header( 'Location: admin.php' );
        exit();
    } else {
        $error = 'Invalid password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang = 'en'>
<head>
<meta charset = 'UTF-8'>
<meta name = 'viewport' content = 'width=device-width, initial-scale=1.0'>
<title>Admin Login - Plant Catalogue</title>
<link href = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel = 'stylesheet'>
<link href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel = 'stylesheet'>
<style>
body {
    background: linear-gradient( 135deg, #667eea 0%, #764ba2 100% );
    min-height: 100vh;
}
.login-card {
    background: rgba( 255, 255, 255, 0.95 );
    backdrop-filter: blur( 10px );
    border: none;
    box-shadow: 0 20px 40px rgba( 0, 0, 0, 0.1 );
}
</style>
</head>
<body class = 'd-flex align-items-center'>
<div class = 'container'>
<div class = 'row justify-content-center'>
<div class = 'col-md-6 col-lg-4'>
<div class = 'card login-card'>
<div class = 'card-body p-5'>
<div class = 'text-center mb-4'>
<h1 class = 'h3 text-success'>
<i class = 'fas fa-lock me-2'></i>Admin Login
</h1>
<p class = 'text-muted'>Enter your password to access the admin panel</p>
</div>

<?php if ( $error ): ?>
<div class = 'alert alert-danger' role = 'alert'>
<i class = 'fas fa-exclamation-triangle me-2'></i>
<?php echo htmlspecialchars( $error );
?>
</div>
<?php endif;
?>

<form method = 'POST'>
<div class = 'mb-3'>
<label for = 'password' class = 'form-label'>Password</label>
<input type = 'password' class = 'form-control' id = 'password' name = 'password' required>
</div>
<button type = 'submit' class = 'btn btn-success w-100'>
<i class = 'fas fa-sign-in-alt me-2'></i>Login
</button>
</form>

<div class = 'text-center mt-3'>
<a href = 'index.html' class = 'text-decoration-none'>
<i class = 'fas fa-arrow-left me-2'></i>Back to Catalogue
</a>
</div>
</div>
</div>
</div>
</div>
</div>
</body>
</html>
<?php
// login.php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Security.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    Security::redirect(BASE_URL . 'index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = Security::sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = $auth->login($username, $password);
        if ($result['success']) {
            $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . 'index.php';
            unset($_SESSION['redirect_after_login']);
            Security::redirect($redirect);
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo getCompanyName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Login Header Logo */
        .login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .login-brand .logo-img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .login-brand .logo-icon {
            font-size: 2rem;
        }
        
        .login-brand .logo-text {
            font-size: 1.75rem;
            font-weight: bold;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <!-- <div class="login-brand">
                    <?php 
                    $companyLogo = getSetting('company_logo', '');
                    if ($companyLogo && !empty($companyLogo)): 
                    ?>
                        <img src="<?php echo $companyLogo; ?>" alt="<?php echo getCompanyName(); ?>" class="logo-img">
                    <?php else: ?>
                        <span class="logo-icon">🛞</span>
                    <?php endif; ?>
                    <span class="logo-text"><?php echo APP_NAME; ?></span>
                </div>  -->
                
                <p>Log in to your account</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="Enter your username or email" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                 Log In
                </button>
            </form>
        </div>
    </div>
</body>
</html>
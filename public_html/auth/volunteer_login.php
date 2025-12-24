<?php
require_once 'db.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, full_name, password FROM volunteers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $name, $hashedPassword);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            $_SESSION['volunteer_id'] = $id;
            $_SESSION['volunteer_name'] = $name;
            header("Location: ../users/profile.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "No account found with this email.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Volunteer Login | IMPERIUM TRUST</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
          },
          colors: {
            primary: '#5DADE2',
            accent: '#A569BD',
            background: '#FBFCFC',
            text: '#1C2833',
            subtle: '#D5D8DC',
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
    }

    /* Subtle background pattern */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: 
        radial-gradient(circle at 25% 25%, rgba(93, 173, 226, 0.03) 0%, transparent 50%),
        radial-gradient(circle at 75% 75%, rgba(165, 105, 189, 0.03) 0%, transparent 50%);
      z-index: -1;
    }

    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.8);
      box-shadow: 
        0 4px 6px -1px rgba(0, 0, 0, 0.05),
        0 10px 15px -3px rgba(0, 0, 0, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.5);
    }

    .input-field {
      transition: all 0.2s ease;
      background: white;
    }

    .input-field:focus {
      border-color: #5DADE2;
      box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
    }

    .login-btn {
      background: linear-gradient(135deg, #5DADE2 0%, #5DADE2 100%);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .login-btn:hover {
      background: linear-gradient(135deg, #4a9cd6 0%, #4a9cd6 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(93, 173, 226, 0.3);
    }

    .logo-container {
      border: 2px solid rgba(255, 255, 255, 0.9);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }

    .error-message {
      background: rgba(239, 68, 68, 0.05);
      border: 1px solid rgba(239, 68, 68, 0.2);
      color: #991b1b;
      border-radius: 8px;
    }

    /* Subtle floating elements */
    .floating-element {
      position: absolute;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(93, 173, 226, 0.1) 0%, rgba(165, 105, 189, 0.1) 100%);
      animation: float 8s ease-in-out infinite;
      z-index: -1;
    }

    .floating-1 {
      width: 120px;
      height: 120px;
      top: 10%;
      left: 5%;
      animation-delay: 0s;
    }

    .floating-2 {
      width: 80px;
      height: 80px;
      top: 70%;
      right: 10%;
      animation-delay: -2s;
    }

    .floating-3 {
      width: 60px;
      height: 60px;
      bottom: 20%;
      left: 15%;
      animation-delay: -4s;
    }

    @keyframes float {
      0%, 100% {
        transform: translateY(0) rotate(0deg);
      }
      50% {
        transform: translateY(-20px) rotate(5deg);
      }
    }

    /* Mobile First Responsive Design */
    @media (max-width: 640px) {
      .login-container {
        margin: 1rem;
        border-radius: 16px;
      }
      
      .header-section {
        padding: 2rem 1.5rem;
      }
      
      .form-section {
        padding: 1.5rem;
      }
      
      .logo-container {
        width: 70px;
        height: 70px;
      }
    }

    @media (min-width: 641px) and (max-width: 768px) {
      .login-container {
        margin: 2rem auto;
        max-width: 400px;
        border-radius: 20px;
      }
      
      .header-section {
        padding: 2.5rem 2rem;
      }
      
      .form-section {
        padding: 2rem;
      }
    }

    @media (min-width: 769px) {
      .login-container {
        margin: 3rem auto;
        max-width: 420px;
        border-radius: 24px;
      }
      
      .header-section {
        padding: 3rem 2.5rem;
      }
      
      .form-section {
        padding: 2.5rem;
      }
      
      .logo-container {
        width: 80px;
        height: 80px;
      }
    }

    .password-toggle {
      transition: all 0.2s ease;
    }

    .password-toggle:hover {
      color: #5DADE2;
    }

    .checkbox:checked {
      background-color: #5DADE2;
      border-color: #5DADE2;
    }

    .checkbox:focus {
      ring: 2px;
      ring-color: #5DADE2;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
  <!-- Subtle floating background elements -->
  <div class="floating-element floating-1"></div>
  <div class="floating-element floating-2"></div>
  <div class="floating-element floating-3"></div>

  <!-- Login Container -->
  <div class="login-container w-full login-card animate__animated animate__fadeIn">
    <!-- Header Section -->
    <div class="header-section bg-gradient-to-br from-primary to-accent text-center relative rounded-t-2xl">
      <div class="absolute -bottom-8 left-1/2 transform -translate-x-1/2">
        <div class="logo-container rounded-full bg-white p-1.5 flex items-center justify-center overflow-hidden">
          <img src="../admin/admin.jpg" alt="IMPERIUM TRUST" class="w-full h-full object-cover rounded-full">
        </div>
      </div>
      <div class="text-white">
        <h1 class="text-xl font-bold mb-1">IMPERIUM TRUST</h1>
        <p class="text-blue-100 text-sm opacity-90">Volunteer Portal</p>
      </div>
    </div>
    
    <!-- Login Form -->
    <div class="form-section pt-10">
      <?php if ($error): ?>
        <div class="error-message p-3 mb-6 flex items-center animate__animated animate__fadeIn">
          <i class="fas fa-exclamation-circle mr-3 text-red-500 flex-shrink-0"></i>
          <span class="text-sm font-medium"><?php echo $error; ?></span>
        </div>
      <?php endif; ?>

      <div class="text-center mb-8">
        <h2 class="text-lg font-semibold text-text">Welcome Back</h2>
        <p class="text-text/60 text-sm mt-1">Sign in to your volunteer account</p>
      </div>
      
      <form method="POST" class="space-y-5">
        <!-- Email Field -->
        <div>
          <label class="block text-sm font-medium text-text mb-2">
            Email Address
          </label>
          <div class="relative">
            <input type="email" name="email" required 
                   class="input-field w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary"
                   placeholder="Enter your email">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-envelope text-gray-400 text-sm"></i>
            </div>
          </div>
        </div>
        
        <!-- Password Field -->
        <div>
          <label class="block text-sm font-medium text-text mb-2">
            Password
          </label>
          <div class="relative">
            <input type="password" name="password" required 
                   class="input-field w-full pl-10 pr-10 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary"
                   placeholder="Enter your password"
                   id="passwordInput">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-lock text-gray-400 text-sm"></i>
            </div>
            <button type="button" 
                    class="password-toggle absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary"
                    onclick="togglePassword()">
              <i class="fas fa-eye text-sm" id="passwordToggleIcon"></i>
            </button>
          </div>
        </div>
        
        
        <!-- Submit Button -->
        <div class="pt-2">
          <button type="submit" 
                  class="login-btn w-full text-white font-medium py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center">
            <i class="fas fa-sign-in-alt mr-2 text-sm"></i>
            Sign In
          </button>
        </div>
      </form>
      
      <!-- Registration Link -->
      <div class="mt-8 pt-6 border-t border-gray-100 text-center">
        <p class="text-text/60 text-sm">
          New to IMPERIUM TRUST?
        </p>
        <a href="https://imperiumtrust.org/user/join-Imperium" 
           class="inline-flex items-center mt-2 text-primary hover:text-accent font-medium transition-colors text-sm">
          <i class="fas fa-user-plus mr-2 text-xs"></i>
          Join as Volunteer
        </a>
      </div>
    </div>
  </div>

  <script>
    function togglePassword() {
      const passwordInput = document.getElementById('passwordInput');
      const icon = document.getElementById('passwordToggleIcon');
      
      if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.className = "fas fa-eye-slash text-sm";
      } else {
        passwordInput.type = "password";
        icon.className = "fas fa-eye text-sm";
      }
    }

    // Add focus effects for better UX
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('.input-field');
      
      inputs.forEach(input => {
        input.addEventListener('focus', function() {
          this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
          this.parentElement.classList.remove('focused');
        });
      });
    });
  </script>
  
  <!-- Font Awesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
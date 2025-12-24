<?php session_start(); if (isset($_SESSION['admin_logged_in'])) { header("Location: dashboard.php"); exit(); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | VMS</title>
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #FBFCFC;
      color: #1C2833;
    }
    
    .login-container {
      min-height: 100vh;
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
    }
    
    .login-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
    }
    
    .form-input {
      width: 100%;
      padding: 14px 16px;
      border-radius: 12px;
      border: 1px solid #D5D8DC;
      background-color: white;
      font-family: 'Inter', sans-serif;
      font-size: 1rem;
      transition: all 0.3s ease;
      color: #1C2833;
    }
    
    .form-input:focus {
      outline: none;
      border-color: #5DADE2;
      box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
      transform: translateY(-1px);
    }
    
    .form-label {
      color: #1C2833;
      font-weight: 600;
      font-size: 0.9rem;
      margin-bottom: 8px;
      display: block;
    }
    
    .login-btn {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
      color: white;
      padding: 16px 24px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 1rem;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    
    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(93, 173, 226, 0.4);
    }
    
    .login-btn:active {
      transform: translateY(0);
    }
    
    .login-btn::after {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: 0.5s;
    }
    
    .login-btn:hover::after {
      left: 100%;
    }
    
    .security-notice {
      background: rgba(93, 173, 226, 0.1);
      border: 1px solid rgba(93, 173, 226, 0.3);
      color: #1C2833;
      border-radius: 10px;
      padding: 12px 16px;
    margin-top: 2rem;

    }
    
    .header-gradient {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
    }
    
    /* Mobile First Responsive Design */
    @media (max-width: 640px) {
      .login-container {
        padding: 1rem;
      }
      
      .login-card {
        padding: 2rem 1.5rem;
        margin: 1rem;
      }
      
      .header-section {
        text-align: center;
        padding: 2rem 1rem;
      }
      
      .logo-container {
        margin-bottom: 1.5rem;
      }
      
      .logo-icon {
        width: 5rem;
        height: 5rem;
      }
      
      .form-section {
        margin-bottom: 2rem;
      }
      
      .form-input {
        padding: 16px;
        font-size: 1.1rem;
      }
      
      .login-btn {
        padding: 18px 24px;
        font-size: 1.1rem;
      }
      
      .security-notice {
        margin-top: 2rem;
        text-align: center;
      }
    }
    
    @media (min-width: 641px) and (max-width: 768px) {
      .login-container {
        padding: 2rem;
      }
      
      .login-card {
        padding: 3rem 2.5rem;
        max-width: 400px;
        margin: 0 auto;
      }
      
      .header-section {
        padding: 2.5rem 2rem;
      }
    }
    
    @media (min-width: 769px) {
      .login-container {
        padding: 2rem;
      }
      
      .login-card {
        padding: 3rem;
        max-width: 420px;
        margin: 0 auto;
      }
      
      .header-section {
        padding: 3rem 2rem;
      }
      
      .logo-container {
        margin-bottom: 2rem;
      }
      
      .logo-icon {
        width: 6rem;
        height: 6rem;
      }
    }
    
    @media (min-width: 1024px) {
      .login-container {
        padding: 3rem;
      }
    }
    
    /* Animation for form elements */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .animate-fade-in-up {
      animation: fadeInUp 0.6s ease-out;
    }
  </style>
</head>
<body>
  <div class="login-container flex items-center justify-center p-4">
    <!-- Login Card -->
    <div class="login-card w-full max-w-md animate-fade-in-up">
      <!-- Header Section -->
      <div class="header-gradient text-white rounded-2xl mb-8">
        <div class="text-center p-8">
          <div class="logo-container flex justify-center">
            <div class="logo-icon bg-white/20 rounded-full flex items-center justify-center p-4">
              <i class="fas fa-users text-3xl text-white"></i>
            </div>
          </div>
          <h1 class="text-2xl md:text-3xl font-bold mb-2">Welcome Back</h1>
          <p class="text-white/80 text-sm md:text-base">Volunteer Management System</p>
        </div>
      </div>

      <!-- Login Form -->
      <div class="form-section">
        <form method="POST" action="authenticate.php" class="space-y-6">
          <!-- Username Field -->
          <div class="animate-fade-in-up" style="animation-delay: 0.1s">
            <label class="form-label">
              <i class="fas fa-user mr-2 text-primary"></i>
              Username
            </label>
            <input type="text" name="username" placeholder="Enter your username" required
                  class="form-input"
                  autocomplete="username">
          </div>

          <!-- Password Field -->
          <div class="animate-fade-in-up" style="animation-delay: 0.2s">
            <label class="form-label">
              <i class="fas fa-lock mr-2 text-primary"></i>
              Password
            </label>
            <input type="password" name="password" placeholder="Enter your password" required
                  class="form-input"
                  autocomplete="current-password">
          </div>

          <!-- Submit Button -->
          <div class="animate-fade-in-up" style="animation-delay: 0.3s">
            <button type="submit" class="login-btn w-full flex items-center justify-center">
              <i class="fas fa-sign-in-alt mr-2"></i>
              Sign In to Dashboard
            </button>
          </div>
        </form>
      </div>

      <!-- Security Notice -->
      <div class="security-notice animate-fade-in-up" style="animation-delay: 0.4s">
        <div class="flex items-center justify-center text-center">
          <i class="fas fa-shield-alt text-primary mr-2"></i>
          <span class="text-sm font-medium">Secure Admin Access Only</span>
        </div>
      </div>

      <!-- Error Message Display (if any) -->
      <?php if (isset($_SESSION['login_error'])): ?>
        <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm animate-fade-in-up">
          <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?= $_SESSION['login_error'] ?>
          </div>
          <?php unset($_SESSION['login_error']); ?>
        </div>
      <?php endif; ?>

      <!-- Success Message Display (if any) -->
      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm animate-fade-in-up">
          <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <?= $_SESSION['success_message'] ?>
          </div>
          <?php unset($_SESSION['success_message']); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Add interactive effects
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('.form-input');
      const loginBtn = document.querySelector('.login-btn');
      
      // Add focus effects
      inputs.forEach(input => {
        input.addEventListener('focus', function() {
          this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
          this.parentElement.classList.remove('focused');
        });
      });
      
      // Add loading state to button
      const form = document.querySelector('form');
      form.addEventListener('submit', function() {
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Signing In...';
        loginBtn.disabled = true;
      });
      
      // Add pulse animation to security notice
      const securityNotice = document.querySelector('.security-notice');
      setInterval(() => {
        securityNotice.style.transform = 'scale(1.02)';
        setTimeout(() => {
          securityNotice.style.transform = 'scale(1)';
        }, 300);
      }, 3000);
    });
    
    // Add floating animation to logo
    const logo = document.querySelector('.logo-icon');
    let floatDirection = 1;
    setInterval(() => {
      const currentTransform = logo.style.transform || 'translateY(0px)';
      const currentY = parseInt(currentTransform.match(/translateY\((-?\d+)px\)/)?.[1] || 0);
      
      if (currentY >= 3) floatDirection = -1;
      if (currentY <= -3) floatDirection = 1;
      
      logo.style.transform = `translateY(${currentY + floatDirection}px)`;
    }, 100);
  </script>
</body>
</html>
<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../auth/volunteer_login.php");
    exit();
}

$volunteer_id = $_SESSION['volunteer_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Suggestion | VMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBFCFC;
            color: #1C2833;
        }

        @media (max-width: 1023px) {
            .content-area {
                padding-top: 4rem;
            }
        }

        .form-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #D5D8DC;
            transition: all 0.3s ease;
        }

        .form-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .form-input {
            border: 1px solid #D5D8DC;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.2s ease;
            background: white;
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            border-color: #5DADE2;
            box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
        }

        .submit-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.3);
        }

        .alert-transition {
            transition: all 0.5s ease;
        }

        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding: 1rem;
            }
            
            .form-container {
                margin-top: 1rem;
                margin-bottom: 1rem;
            }
        }

        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }
        }

        @media (min-width: 769px) {
            .page-container {
                padding: 2rem;
                width: calc(100% - 16rem);
                margin-left: 16rem;
                max-width: 600px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="page-container lg:ml-96">
        <!-- Header Section -->
        <div class="header-section mt-12 sm:mt-0 mb-8 text-center sm:text-left">
            <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Submit Suggestion or Query</h1>
            <p class="text-text/70">Share your ideas or ask questions with the admin team</p>
        </div>

        <!-- Success Alert -->
        <?php if (isset($_SESSION['suggestion_success'])): ?>
            <div x-data="{ show: true }" 
                 x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform translate-y-4"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform translate-y-4"
                 x-init="setTimeout(() => show = false, 3000)"
                 class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg alert-transition"
                 role="alert">
                <div class="flex items-center">
                    <div class="py-1">
                        <svg class="w-6 h-6 mr-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-green-800">Success!</p>
                        <p class="text-green-700">Suggestion submitted successfully!</p>
                    </div>
                    <button @click="show = false" class="text-green-700 hover:text-green-900">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['suggestion_success']); ?>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="form-container form-card p-6 w-full mx-auto">
            <div class="mb-6 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-r from-primary to-accent text-white mb-4">
                    <i class="fas fa-lightbulb text-xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-text">
                    Suggest or Ask a Query
                </h2>
                <p class="text-text/60 mt-2">We value your feedback and questions</p>
            </div>
            
            <form action="submit_suggestion.php" method="POST" class="space-y-6">
                <div>
                    <label class="block mb-2 font-semibold text-text">Type <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select name="subject" required class="form-input pl-12 appearance-none">
                            <option value="">-- Select Type --</option>
                            <option value="Suggestion">Suggestion</option>
                            <option value="Query">Query</option>
                        </select>
                        <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                            <i class="fas fa-tag text-text/40"></i>
                        </div>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                            <i class="fas fa-chevron-down text-text/40"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block mb-2 font-semibold text-text">Message <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <textarea name="message" rows="4" required 
                            class="form-input pl-12"
                            placeholder="Your suggestion or query..."></textarea>
                        <div class="absolute top-4 left-4 pointer-events-none">
                            <i class="fas fa-comment text-text/40"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block mb-2 font-semibold text-text">Preferred Contact Method</label>
                    <div class="relative">
                        <select name="preferred_contact" class="form-input pl-12 appearance-none">
                            <option value="Chat">Chat</option>
                            <option value="Call">Call</option>
                            <option value="WhatsApp">WhatsApp</option>
                        </select>
                        <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                            <i class="fas fa-phone text-text/40"></i>
                        </div>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                            <i class="fas fa-chevron-down text-text/40"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn flex items-center justify-center">
                    <i class="fas fa-paper-plane mr-2"></i> Send to Admin
                </button>
            </form>
        </div>

        <!-- Additional Info Section -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
                <div class="flex items-start">
                    <div class="bg-blue-100 text-primary p-3 rounded-lg mr-4">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-text mb-1">Response Time</h3>
                        <p class="text-text/70 text-sm">We typically respond to queries within 24-48 hours</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-purple-50 border border-purple-100 rounded-xl p-5">
                <div class="flex items-start">
                    <div class="bg-purple-100 text-accent p-3 rounded-lg mr-4">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-text mb-1">Confidentiality</h3>
                        <p class="text-text/70 text-sm">All suggestions and queries are handled confidentially</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form validation enhancement
        document.querySelector('form').addEventListener('submit', function(e) {
            const subject = document.querySelector('select[name="subject"]').value;
            const message = document.querySelector('textarea[name="message"]').value;
            
            if (!subject || !message) {
                e.preventDefault();
                // Add shake animation to empty fields
                const emptyFields = document.querySelectorAll('select[name="subject"], textarea[name="message"]');
                emptyFields.forEach(field => {
                    if (!field.value) {
                        field.classList.add('animate-pulse', 'border-red-500');
                        setTimeout(() => {
                            field.classList.remove('animate-pulse', 'border-red-500');
                        }, 2000);
                    }
                });
            }
        });
    </script>
</body>
</html>
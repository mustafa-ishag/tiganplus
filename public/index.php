<?php
// بدء output buffering لتجنب مشاكل headers
ob_start();

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// التحقق من تسجيل الدخول
if (isset($_SESSION['user_id'])) {
    // إذا كان مسجل دخول، توجيه للوحة التحكم
    ob_end_clean();
    header('Location: dashboard.php');
    exit();
} else {
    // إذا لم يكن مسجل دخول، توجيه لصفحة تسجيل الدخول
    ob_end_clean();
    header('Location: auth/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام تِقان - إدارة المقاولات</title>

    <!-- Bootstrap RTL CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts - Tajawal -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">

    <!-- Animate.css for animations -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">

    <!-- AOS (Animate On Scroll) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #176cb4;
            --secondary-color: #0e2942;
            --accent-color: #f093fb;
            --gradient-1: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --text-dark: #2d3748;
            --text-light: #718096;
            --white: #ffffff;
            --shadow-light: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-medium: 0 10px 25px rgba(0, 0, 0, 0.1);
            --shadow-heavy: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        * {
            font-family: 'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--gradient-1);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated background particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .particle:nth-child(1) { width: 80px; height: 80px; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 60px; height: 60px; left: 20%; animation-delay: 1s; }
        .particle:nth-child(3) { width: 40px; height: 40px; left: 30%; animation-delay: 2s; }
        .particle:nth-child(4) { width: 100px; height: 100px; left: 40%; animation-delay: 3s; }
        .particle:nth-child(5) { width: 50px; height: 50px; left: 50%; animation-delay: 4s; }
        .particle:nth-child(6) { width: 70px; height: 70px; left: 60%; animation-delay: 5s; }
        .particle:nth-child(7) { width: 30px; height: 30px; left: 70%; animation-delay: 6s; }
        .particle:nth-child(8) { width: 90px; height: 90px; left: 80%; animation-delay: 7s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.7; }
            50% { transform: translateY(-100px) rotate(180deg); opacity: 0.3; }
        }

        .welcome-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            box-shadow: var(--shadow-heavy);
            padding: 4rem 3rem;
            text-align: center;
            max-width: 700px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 10;
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .welcome-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
        }
        
        .logo {
            font-size: 4rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
            animation: logoGlow 3s ease-in-out infinite alternate;
        }

        .logo::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 120px;
            height: 120px;
            background: var(--gradient-3);
            border-radius: 50%;
            opacity: 0.1;
            z-index: -1;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes logoGlow {
            0% { filter: drop-shadow(0 0 10px rgba(102, 126, 234, 0.3)); }
            100% { filter: drop-shadow(0 0 20px rgba(102, 126, 234, 0.6)); }
        }

        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.1; }
            50% { transform: translate(-50%, -50%) scale(1.1); opacity: 0.2; }
        }

        .system-title {
            font-size: 3rem;
            font-weight: 800;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .system-subtitle {
            font-size: 1.3rem;
            color: var(--text-light);
            margin-bottom: 2.5rem;
            font-weight: 500;
        }

        .welcome-text {
            font-size: 1.2rem;
            color: var(--text-dark);
            margin-bottom: 3rem;
            line-height: 1.8;
            font-weight: 400;
        }
        
        .btn-login {
            background: var(--gradient-1);
            border: none;
            padding: 18px 50px;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: 60px;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: var(--shadow-medium);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-login:active {
            transform: translateY(-1px) scale(1.02);
        }

        .btn-login i {
            transition: transform 0.3s ease;
        }

        .btn-login:hover i {
            transform: translateX(5px);
        }
        
        .features {
            margin-top: 4rem;
            padding-top: 3rem;
            border-top: 1px solid rgba(102, 126, 234, 0.1);
        }

        .features h5 {
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 1.5rem;
            padding: 15px 20px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.1);
            position: relative;
            overflow: hidden;
        }

        .feature-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--gradient-3);
            opacity: 0.1;
            transition: left 0.4s ease;
        }

        .feature-item:hover::before {
            left: 0;
        }

        .feature-item:hover {
            transform: translateX(10px);
            box-shadow: var(--shadow-light);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .feature-item i {
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-left: 15px;
            font-size: 1.4rem;
            transition: transform 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .feature-item:hover i {
            transform: scale(1.2) rotate(5deg);
        }

        .feature-item span {
            color: var(--text-dark);
            font-weight: 500;
            position: relative;
            z-index: 2;
        }
        
        .footer-text {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(102, 126, 234, 0.1);
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .footer-text i {
            background: var(--gradient-2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1024px) {
            .welcome-container {
                max-width: 600px;
                padding: 3rem 2.5rem;
            }

            .particle {
                opacity: 0.5;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .welcome-container {
                padding: 2.5rem 2rem;
                margin: 0.5rem;
                border-radius: 25px;
                max-width: 100%;
            }

            .logo {
                font-size: 3rem;
            }

            .logo::before {
                width: 100px;
                height: 100px;
            }

            .system-title {
                font-size: 2.2rem;
                line-height: 1.2;
            }

            .system-subtitle {
                font-size: 1.1rem;
                margin-bottom: 2rem;
            }

            .welcome-text {
                font-size: 1.1rem;
                line-height: 1.6;
                margin-bottom: 2.5rem;
            }

            .btn-login {
                padding: 16px 40px;
                font-size: 1.1rem;
                width: 100%;
                max-width: 300px;
                margin: 0 auto;
                display: flex;
            }

            .feature-item {
                padding: 15px 20px;
                margin-bottom: 1rem;
                font-size: 0.95rem;
            }

            .feature-item i {
                font-size: 1.3rem;
                margin-left: 12px;
            }

            .particle {
                display: none;
            }

            .features h5 {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .welcome-container {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }

            .logo {
                font-size: 2.5rem;
            }

            .logo::before {
                width: 80px;
                height: 80px;
            }

            .system-title {
                font-size: 1.8rem;
            }

            .system-subtitle {
                font-size: 1rem;
            }

            .welcome-text {
                font-size: 1rem;
                margin-bottom: 2rem;
            }

            .btn-login {
                padding: 14px 30px;
                font-size: 1rem;
                width: 100%;
            }

            .feature-item {
                padding: 12px 15px;
                font-size: 0.9rem;
            }

            .feature-item i {
                font-size: 1.2rem;
                margin-left: 10px;
            }

            .features {
                margin-top: 3rem;
                padding-top: 2rem;
            }

            .features h5 {
                font-size: 1.2rem;
                margin-bottom: 1.5rem;
            }
        }

        @media (max-width: 360px) {
            .welcome-container {
                padding: 1.5rem 1rem;
            }

            .logo {
                font-size: 2rem;
            }

            .system-title {
                font-size: 1.6rem;
            }

            .btn-login {
                padding: 12px 25px;
                font-size: 0.95rem;
            }
        }

        /* Touch-friendly interactions */
        @media (hover: none) and (pointer: coarse) {
            .btn-login:hover {
                transform: none;
            }

            .feature-item:hover {
                transform: none;
            }

            .welcome-container:hover {
                transform: none;
            }
        }

        /* High DPI displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .logo {
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            .system-title {
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
        }

        /* Landscape orientation on mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .welcome-container {
                padding: 1.5rem 2rem;
                margin: 0.5rem;
            }

            .logo {
                font-size: 2rem;
                margin-bottom: 0.5rem;
            }

            .system-title {
                font-size: 1.5rem;
                margin-bottom: 0.3rem;
            }

            .system-subtitle {
                font-size: 0.9rem;
                margin-bottom: 1rem;
            }

            .welcome-text {
                font-size: 0.9rem;
                margin-bottom: 1.5rem;
            }

            .features {
                margin-top: 1.5rem;
                padding-top: 1rem;
            }

            .feature-item {
                padding: 8px 12px;
                margin-bottom: 0.5rem;
                font-size: 0.85rem;
            }
        }
        
        /* Advanced Animations */
        .animate-fade-in {
            animation: fadeInUp 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .animate-bounce {
            animation: gentleBounce 3s ease-in-out infinite;
        }

        .animate-slide-in {
            animation: slideInRight 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .animate-scale-in {
            animation: scaleIn 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes gentleBounce {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-8px) rotate(2deg); }
            50% { transform: translateY(0) rotate(0deg); }
            75% { transform: translateY(-4px) rotate(-1deg); }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Staggered animation delays */
        .feature-item:nth-child(1) { animation-delay: 0.1s; }
        .feature-item:nth-child(2) { animation-delay: 0.2s; }
        .feature-item:nth-child(3) { animation-delay: 0.3s; }
        .feature-item:nth-child(4) { animation-delay: 0.4s; }
        .feature-item:nth-child(5) { animation-delay: 0.5s; }
        .feature-item:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <!-- Animated Background Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="welcome-container animate-fade-in" data-aos="fade-up" data-aos-duration="1200">
        <div class="logo animate-bounce" data-aos="zoom-in" data-aos-delay="200">
            <i class="fas fa-building"></i>
        </div>

        <h1 class="system-title" data-aos="fade-up" data-aos-delay="400">نظام تِقان</h1>
        <p class="system-subtitle" data-aos="fade-up" data-aos-delay="600">نظام إدارة المقاولات المتكامل</p>

        <p class="welcome-text" data-aos="fade-up" data-aos-delay="800">
            مرحباً بك في نظام تِقان لإدارة المقاولات. نظام شامل ومتطور لإدارة جميع عمليات المقاولات
            من أوامر العمل والمستخلصات إلى إدارة الفروع والمستخدمين بأحدث التقنيات والواجهات التفاعلية.
        </p>

        <a href="auth/login.php" class="btn-login" data-aos="zoom-in" data-aos-delay="1000">
            <i class="fas fa-sign-in-alt"></i>
            <span>تسجيل الدخول</span>
        </a>
        
        <div class="features" data-aos="fade-up" data-aos-delay="1200">
            <h5 class="mb-4">✨ مميزات النظام المتطورة</h5>

            <div class="feature-item animate-slide-in" data-aos="fade-right" data-aos-delay="1300">
                <i class="fas fa-clipboard-list"></i>
                <span>إدارة أوامر العمل والمتابعة الذكية</span>
            </div>

            <div class="feature-item animate-slide-in" data-aos="fade-right" data-aos-delay="1400">
                <i class="fas fa-file-invoice"></i>
                <span>إدارة المستخلصات (جزئية ونهائية) بدقة عالية</span>
            </div>

            <div class="feature-item animate-slide-in" data-aos="fade-right" data-aos-delay="1500">
                <i class="fas fa-map-marker-alt"></i>
                <span>إدارة الفروع والمواقع الجغرافية</span>
            </div>

            <div class="feature-item animate-slide-in" data-aos="fade-right" data-aos-delay="1600">
                <i class="fas fa-users"></i>
                <span>إدارة المستخدمين والصلاحيات المتقدمة</span>
            </div>

            <div class="feature-item animate-slide-in" data-aos="fade-right" data-aos-delay="1700">
                <i class="fas fa-chart-bar"></i>
                <span>تقارير تحليلية شاملة ومفصلة</span>
            </div>

            <div class="feature-item animate-slide-in" data-aos="fade-right" data-aos-delay="1800">
                <i class="fas fa-mobile-alt"></i>
                <span>واجهة متجاوبة وتفاعلية لجميع الأجهزة</span>
            </div>
        </div>
        
        <div class="footer-text" data-aos="fade-up" data-aos-delay="1900">
            <p class="mb-0">
                <i class="fas fa-copyright me-1"></i>
                2024 نظام تِقان - جميع الحقوق محفوظة | تطوير متقدم بأحدث التقنيات
            </p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- GSAP Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            easing: 'ease-in-out-cubic',
            once: true,
            offset: 50
        });

        // Advanced animations with GSAP
        document.addEventListener('DOMContentLoaded', function() {
            // Create timeline for entrance animations
            const tl = gsap.timeline();

            // Animate particles
            gsap.to('.particle', {
                y: -100,
                rotation: 360,
                duration: 6,
                ease: 'power2.inOut',
                repeat: -1,
                yoyo: true,
                stagger: 0.5
            });

            // Animate welcome container on load
            gsap.fromTo('.welcome-container',
                {
                    scale: 0.8,
                    opacity: 0,
                    y: 50
                },
                {
                    scale: 1,
                    opacity: 1,
                    y: 0,
                    duration: 1.2,
                    ease: 'back.out(1.7)'
                }
            );

            // Animate features with stagger
            gsap.fromTo('.feature-item',
                {
                    x: 50,
                    opacity: 0
                },
                {
                    x: 0,
                    opacity: 1,
                    duration: 0.8,
                    ease: 'power2.out',
                    stagger: 0.1,
                    delay: 1.5
                }
            );

            // Mouse movement parallax effect
            document.addEventListener('mousemove', (e) => {
                const mouseX = e.clientX / window.innerWidth;
                const mouseY = e.clientY / window.innerHeight;

                gsap.to('.particle', {
                    x: mouseX * 20,
                    y: mouseY * 20,
                    duration: 2,
                    ease: 'power2.out',
                    stagger: 0.02
                });

                gsap.to('.welcome-container', {
                    rotationY: (mouseX - 0.5) * 5,
                    rotationX: (mouseY - 0.5) * -5,
                    duration: 1,
                    ease: 'power2.out'
                });
            });

            // Enhanced button interactions
            const loginBtn = document.querySelector('.btn-login');

            loginBtn.addEventListener('mouseenter', function() {
                gsap.to(this, {
                    scale: 1.05,
                    y: -3,
                    duration: 0.3,
                    ease: 'power2.out'
                });

                gsap.to(this.querySelector('i'), {
                    x: 5,
                    rotation: 10,
                    duration: 0.3,
                    ease: 'power2.out'
                });
            });

            loginBtn.addEventListener('mouseleave', function() {
                gsap.to(this, {
                    scale: 1,
                    y: 0,
                    duration: 0.3,
                    ease: 'power2.out'
                });

                gsap.to(this.querySelector('i'), {
                    x: 0,
                    rotation: 0,
                    duration: 0.3,
                    ease: 'power2.out'
                });
            });

            // Feature items hover animations
            document.querySelectorAll('.feature-item').forEach(item => {
                item.addEventListener('mouseenter', function() {
                    gsap.to(this, {
                        x: 10,
                        scale: 1.02,
                        duration: 0.3,
                        ease: 'power2.out'
                    });

                    gsap.to(this.querySelector('i'), {
                        scale: 1.2,
                        rotation: 5,
                        duration: 0.3,
                        ease: 'power2.out'
                    });
                });

                item.addEventListener('mouseleave', function() {
                    gsap.to(this, {
                        x: 0,
                        scale: 1,
                        duration: 0.3,
                        ease: 'power2.out'
                    });

                    gsap.to(this.querySelector('i'), {
                        scale: 1,
                        rotation: 0,
                        duration: 0.3,
                        ease: 'power2.out'
                    });
                });
            });

            // Scroll-triggered animations
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const parallax = scrolled * 0.5;

                gsap.to('.particles', {
                    y: parallax,
                    duration: 0.5,
                    ease: 'power2.out'
                });
            });

            // Loading animation complete
            setTimeout(() => {
                document.body.classList.add('loaded');
            }, 2000);
        });

        // Add some interactive sparkle effects
        function createSparkle(x, y) {
            const sparkle = document.createElement('div');
            sparkle.style.position = 'fixed';
            sparkle.style.left = x + 'px';
            sparkle.style.top = y + 'px';
            sparkle.style.width = '4px';
            sparkle.style.height = '4px';
            sparkle.style.background = 'linear-gradient(45deg, #176cb4, #4fa5e6)';
            sparkle.style.borderRadius = '50%';
            sparkle.style.pointerEvents = 'none';
            sparkle.style.zIndex = '9999';

            document.body.appendChild(sparkle);

            gsap.to(sparkle, {
                scale: 0,
                opacity: 0,
                duration: 1,
                ease: 'power2.out',
                onComplete: () => sparkle.remove()
            });
        }

        // Enhanced touch and click interactions
        function handleInteraction(e) {
            const x = e.clientX || (e.touches && e.touches[0].clientX);
            const y = e.clientY || (e.touches && e.touches[0].clientY);

            if (x && y) {
                for (let i = 0; i < 6; i++) {
                    setTimeout(() => {
                        createSparkle(
                            x + (Math.random() - 0.5) * 20,
                            y + (Math.random() - 0.5) * 20
                        );
                    }, i * 50);
                }
            }
        }

        // Add sparkles on click and touch
        document.addEventListener('click', handleInteraction);
        document.addEventListener('touchstart', handleInteraction);

        // Prevent zoom on double tap for iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        // Enhanced mobile interactions
        if ('ontouchstart' in window) {
            // Mobile-specific enhancements
            const loginBtn = document.querySelector('.btn-login');

            loginBtn.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.98)';
            });

            loginBtn.addEventListener('touchend', function() {
                this.style.transform = 'scale(1)';
            });

            // Feature items touch feedback
            document.querySelectorAll('.feature-item').forEach(item => {
                item.addEventListener('touchstart', function() {
                    gsap.to(this, {
                        scale: 0.98,
                        duration: 0.1,
                        ease: 'power2.out'
                    });
                });

                item.addEventListener('touchend', function() {
                    gsap.to(this, {
                        scale: 1,
                        duration: 0.2,
                        ease: 'power2.out'
                    });
                });
            });
        }

        // Optimize animations for mobile
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            // Reduce animation complexity on mobile
            gsap.set('.particle', { display: 'none' });

            // Simpler animations for mobile
            gsap.fromTo('.welcome-container',
                { opacity: 0, y: 30 },
                { opacity: 1, y: 0, duration: 0.8, ease: 'power2.out' }
            );
        }
    </script>

</body>
</html>

<?php
// تنظيف output buffer
ob_end_flush();
?>

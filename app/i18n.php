<?php
declare(strict_types=1);

function available_languages(): array
{
    return ['en', 'hi'];
}

function get_language(): string
{
    $config = get_app_config();
    $default = $config['langDefault'] ?? 'hi';

    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], available_languages(), true)) {
        return $_SESSION['lang'];
    }

    if (!empty($_COOKIE['yojak_lang']) && in_array($_COOKIE['yojak_lang'], available_languages(), true)) {
        return $_COOKIE['yojak_lang'];
    }

    return $default;
}

function rotate_language_from_request(): void
{
    if (isset($_GET['lang'])) {
        $lang = $_GET['lang'];
        if (in_array($lang, available_languages(), true)) {
            $_SESSION['lang'] = $lang;
            setcookie('yojak_lang', $lang, time() + 60 * 60 * 24 * 30, '/', '', false, true);
        }
    }
}

function translations(): array
{
    return [
        'en' => [
            'welcome_title' => 'Welcome to YOJAK',
            'welcome_body' => 'Your secure gateway for streamlined project collaboration.',
            'login' => 'Login',
            'logout' => 'Logout',
            'username' => 'Username',
            'password' => 'Password',
            'password_new' => 'New Password',
            'password_confirm' => 'Confirm Password',
            'submit' => 'Submit',
            'dashboard' => 'Dashboard',
            'profile' => 'Profile',
            'language' => 'Language',
            'english' => 'English',
            'hindi' => 'Hindi',
            'home_tagline' => 'Built for accountability, security, and clarity.',
            'nav_home' => 'Home',
            'nav_auth' => 'Sign In',
            'nav_dashboard' => 'Superadmin',
            'force_reset_title' => 'Reset Password',
            'force_reset_body' => 'For your security, please set a new password before continuing.',
            'password_requirements' => 'Password must be at least 8 characters and differ from your previous password.',
            'error_title' => 'Something went wrong',
            'error_generic' => 'We hit a snag. The team has been notified.',
            'csrf_invalid' => 'Your session security token is invalid or missing.',
            'rate_limited' => 'Too many attempts. Please wait before trying again.',
            'login_invalid' => 'Invalid credentials. Please try again.',
            'login_success' => 'Login successful.',
            'reset_success' => 'Password updated successfully.',
            'logout_success' => 'You have been signed out.',
            'last_login' => 'Last login',
            'must_reset' => 'Password reset required',
            'status_active' => 'Active',
            'superadmin_profile' => 'Superadmin Profile',
            'device_locked' => 'Login temporarily locked for this device.',
            'choose_language' => 'Choose Language',
            'auth_required' => 'Please login to continue.',
            'password_mismatch' => 'Passwords do not match.',
            'invalid_password' => 'Password must be at least 8 characters and different from the previous one.',
            'force_reset_cta' => 'Update Password',
            'stay_signed_out' => 'Stay Signed Out',
            'back_home' => 'Back to Home',
        ],
        'hi' => [
            'welcome_title' => 'योज़क में आपका स्वागत है',
            'welcome_body' => 'सुरक्षित और सरल परियोजना सहयोग का आपका द्वार।',
            'login' => 'लॉगिन',
            'logout' => 'लॉगआउट',
            'username' => 'उपयोगकर्ता नाम',
            'password' => 'पासवर्ड',
            'password_new' => 'नया पासवर्ड',
            'password_confirm' => 'पासवर्ड की पुष्टि करें',
            'submit' => 'सबमिट',
            'dashboard' => 'डैशबोर्ड',
            'profile' => 'प्रोफ़ाइल',
            'language' => 'भाषा',
            'english' => 'अंग्रेज़ी',
            'hindi' => 'हिन्दी',
            'home_tagline' => 'जवाबदेही, सुरक्षा और स्पष्टता के लिए तैयार।',
            'nav_home' => 'मुख्य पृष्ठ',
            'nav_auth' => 'साइन इन',
            'nav_dashboard' => 'सुपरएडमिन',
            'force_reset_title' => 'पासवर्ड रीसेट करें',
            'force_reset_body' => 'आपकी सुरक्षा के लिए, आगे बढ़ने से पहले नया पासवर्ड सेट करें।',
            'password_requirements' => 'पासवर्ड कम से कम 8 अक्षरों का हो और पुराने से भिन्न हो।',
            'error_title' => 'कुछ गलत हो गया',
            'error_generic' => 'हमें समस्या हुई। टीम को सूचित किया गया है।',
            'csrf_invalid' => 'आपका सत्र सुरक्षा टोकन अमान्य या गायब है।',
            'rate_limited' => 'बहुत प्रयास हो गए। कृपया बाद में पुनः प्रयास करें।',
            'login_invalid' => 'गलत प्रमाण-पत्र। कृपया फिर से प्रयास करें।',
            'login_success' => 'लॉगिन सफल।',
            'reset_success' => 'पासवर्ड सफलतापूर्वक अपडेट हुआ।',
            'logout_success' => 'आप लॉगआउट हो चुके हैं।',
            'last_login' => 'अंतिम लॉगिन',
            'must_reset' => 'पासवर्ड रीसेट आवश्यक',
            'status_active' => 'सक्रिय',
            'superadmin_profile' => 'सुपरएडमिन प्रोफ़ाइल',
            'device_locked' => 'इस डिवाइस के लिए लॉगिन अस्थायी रूप से बंद है।',
            'choose_language' => 'भाषा चुनें',
            'auth_required' => 'कृपया आगे बढ़ने के लिए लॉगिन करें।',
            'password_mismatch' => 'पासवर्ड मेल नहीं खाते।',
            'invalid_password' => 'पासवर्ड कम से कम 8 अक्षरों का होना चाहिए और पुराने से अलग होना चाहिए।',
            'force_reset_cta' => 'पासवर्ड अपडेट करें',
            'stay_signed_out' => 'लॉगआउट रहिए',
            'back_home' => 'मुख्य पृष्ठ पर जाएं',
        ],
    ];
}

function t(string $key): string
{
    $lang = get_language();
    $dictionary = translations();
    if (isset($dictionary[$lang][$key])) {
        return $dictionary[$lang][$key];
    }
    return $dictionary['en'][$key] ?? $key;
}


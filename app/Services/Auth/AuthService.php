<?php

declare(strict_types=1);

namespace NEvents\Services\Auth;

use NEvents\Core\Application;
use NEvents\Core\Log;
use NEvents\Repositories\UserRepository;
use NEvents\Services\Mail\MailService;
use Ramsey\Uuid\Uuid;

class AuthService
{
    public function __construct(
        private UserRepository $users,
        private MailService    $mail,
    ) {}

    public function register(array $data): array
    {
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $existing = $this->users->findByEmail($data['email']);
        if ($existing) {
            return ['success' => false, 'errors' => ['email' => 'This email address is already registered.']];
        }

        $algorithm = PASSWORD_ARGON2ID;
        if (!defined('PASSWORD_ARGON2ID')) {
            $algorithm = PASSWORD_BCRYPT;
        }

        $email = strtolower(trim($data['email']));

        $userId = $this->users->create([
            'uuid'          => Uuid::uuid4()->toString(),
            'name'          => trim($data['name']),
            'email'         => $email,
            'password_hash' => password_hash($data['password'], $algorithm),
            // Single canonical copy of the number (E.164). Collected at signup
            // only — nothing in the app sends WhatsApp messages.
            'whatsapp_number' => $this->normalizePhone($data['whatsapp_number'] ?? ''),
        ]);

        $this->users->createProfile($userId);
        $this->users->createNotificationPreferences($userId);

        // Assign default 'user' role (role_id = 5)
        $this->users->assignRole($userId, 5);

        // Record consents
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->users->recordConsent($userId, 'terms', true, $ip, $ua);
        $this->users->recordConsent($userId, 'privacy', true, $ip, $ua);

        // Generate + store the email verification token, then attempt delivery.
        // User creation itself already committed — a failed send never undoes the account.
        $token    = $this->generateSecureToken();
        $mailSent = $this->issueVerificationToken($userId, $token, trim($data['name']), $email);

        return [
            'success'   => true,
            'user_id'   => $userId,
            'token'     => $token,
            'mail_sent' => $mailSent,
        ];
    }

    /**
     * Creates a fresh verification token for the user and emails it, invalidating
     * any previously issued (still-unused) verification tokens first. Shared by
     * register() and resendVerification().
     */
    private function issueVerificationToken(int $userId, string $rawToken, string $name, string $email): bool
    {
        $this->users->invalidatePendingTokens($userId, 'email_verify');

        $ttlHours  = (int) Application::getInstance()->config('app.auth.verify_token_ttl_hours', 24);
        $tokenHash = hash('sha256', $rawToken);
        $expires   = new \DateTimeImmutable("+{$ttlHours} hours");
        $this->users->createAuthToken($userId, 'email_verify', $tokenHash, $expires);

        return $this->mail->sendVerificationEmail(['name' => $name, 'email' => $email], $rawToken);
    }

    /**
     * Resends the verification email for an unverified account. Deliberately
     * returns the same generic result regardless of whether the email exists,
     * is already verified, or is rate-limited — the caller always shows the
     * same "if eligible, a new link has been sent" message so this endpoint
     * can't be used to enumerate registered accounts.
     */
    public function resendVerification(string $email): array
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));

        if (!$user || $user['email_verified_at'] !== null) {
            return ['success' => true, 'sent' => false];
        }

        $cooldown = (int) Application::getInstance()->config('app.auth.resend_verification_cooldown', 60);
        $lastSentAt = $this->users->lastTokenIssuedAt($user['id'], 'email_verify');
        if ($lastSentAt !== null) {
            $elapsed = time() - strtotime($lastSentAt);
            if ($elapsed < $cooldown) {
                return ['success' => true, 'sent' => false, 'throttled' => true, 'retry_after' => $cooldown - $elapsed];
            }
        }

        $token    = $this->generateSecureToken();
        $mailSent = $this->issueVerificationToken($user['id'], $token, $user['name'], $user['email']);

        return ['success' => true, 'sent' => $mailSent];
    }

    public function login(string $email, string $password, string $ip): array
    {
        $config      = require dirname(__DIR__, 3) . '/config/app.php';
        $maxAttempts = (int)($config['rate_limit']['login']  ?? 5);
        $window      = (int)($config['rate_limit']['window'] ?? 900);

        $attempts = $this->users->countRecentLoginAttempts($email, $ip, $window);
        if ($attempts >= $maxAttempts) {
            return ['success' => false, 'error' => 'Too many login attempts. Please try again in 15 minutes.', 'throttled' => true];
        }

        $user = $this->users->findByEmail(strtolower(trim($email)));

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->users->recordLoginAttempt($email, $ip, false);
            return ['success' => false, 'error' => 'Incorrect email or password.'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'error' => 'This account has been suspended or deactivated.'];
        }

        $requireVerification = (bool) ($config['auth']['require_email_verification'] ?? true);
        if ($requireVerification && $user['email_verified_at'] === null) {
            return [
                'success'    => false,
                'error'      => 'Please verify your email address before logging in.',
                'unverified' => true,
                'email'      => $user['email'],
            ];
        }

        $this->users->recordLoginAttempt($email, $ip, true);
        $this->users->update($user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ]);

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        $roles = $this->users->getRoles($user['id']);
        $roleSlugs = array_column($roles, 'slug');

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_roles'] = $roleSlugs;
        $_SESSION['onboarding_done'] = (bool)$user['onboarding_done'];

        return ['success' => true, 'user' => $user, 'roles' => $roleSlugs];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function verifyEmail(string $token): array
    {
        if ($token === '') {
            return ['success' => false, 'error' => 'This verification link is missing its token.'];
        }

        $tokenHash = hash('sha256', $token);
        $record    = $this->users->findValidToken($tokenHash, 'email_verify');

        if (!$record) {
            // Distinguish "you already did this" from "this link is genuinely broken" —
            // a previously-used-but-valid-looking link means the account may well
            // already be verified, which isn't an error from the user's point of view.
            $anyState = $this->users->findTokenAnyState($tokenHash, 'email_verify');
            if ($anyState) {
                $user = $this->users->findById((int) $anyState['user_id']);
                if ($user && $user['email_verified_at'] !== null) {
                    return ['success' => false, 'already_verified' => true, 'error' => 'Your email address has already been verified.'];
                }
                if ($anyState['used_at'] === null && strtotime($anyState['expires_at']) < time()) {
                    Log::get()->info('verification_token_expired', ['user_id' => $anyState['user_id']]);
                }
            }
            return ['success' => false, 'error' => 'This verification link is invalid or has expired.'];
        }

        $this->users->update($record['user_id'], ['email_verified_at' => date('Y-m-d H:i:s')]);
        $this->users->markTokenUsed($record['id']);
        Log::get()->info('email_verified', ['user_id' => $record['user_id']]);

        return ['success' => true, 'user_id' => $record['user_id']];
    }

    public function sendPasswordReset(string $email): array
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if (!$user) {
            // Return success even for unknown emails (security: don't reveal registration)
            return ['success' => true];
        }

        $this->users->invalidatePendingTokens($user['id'], 'password_reset');

        $token     = $this->generateSecureToken();
        $tokenHash = hash('sha256', $token);
        $expires   = new \DateTimeImmutable('+1 hour');
        $this->users->createAuthToken($user['id'], 'password_reset', $tokenHash, $expires);

        $mailSent = $this->mail->sendPasswordResetEmail(['name' => $user['name'], 'email' => $user['email']], $token);

        return ['success' => true, 'token' => $token, 'user' => $user, 'mail_sent' => $mailSent];
    }

    public function resetPassword(string $token, string $password): array
    {
        $errors = $this->validatePassword($password);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $tokenHash = hash('sha256', $token);
        $record    = $this->users->findValidToken($tokenHash, 'password_reset');
        if (!$record) {
            return ['success' => false, 'error' => 'This reset link is invalid or has expired.'];
        }

        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $this->users->update($record['user_id'], ['password_hash' => password_hash($password, $algorithm)]);
        $this->users->markTokenUsed($record['id']);

        return ['success' => true];
    }

    private function validateRegistration(array $data): array
    {
        $errors = [];

        if (empty($data['name']) || mb_strlen(trim($data['name'])) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        $pwErrors = $this->validatePassword($data['password'] ?? '');
        if (!empty($pwErrors)) {
            $errors['password'] = $pwErrors['password'];
        }

        if (($data['password'] ?? '') !== ($data['password_confirm'] ?? '')) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        if (($data['whatsapp_number'] ?? '') === '' ) {
            $errors['whatsapp_number'] = 'Please enter your WhatsApp/mobile number.';
        } elseif ($this->normalizePhone($data['whatsapp_number']) === '') {
            $errors['whatsapp_number'] = 'Please enter a valid WhatsApp/mobile number (10-digit Indian mobile, or +countrycode number).';
        }

        if (empty($data['accept_terms'])) {
            $errors['accept_terms'] = 'You must accept the Terms and Privacy Policy to create an account.';
        }

        return $errors;
    }

    private function validatePassword(string $password): array
    {
        $errors = [];
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one letter and one number.';
        }
        return $errors;
    }

    /**
     * Edit own profile: display name + WhatsApp/mobile number (same rules as signup).
     * Email is the login identity and is not editable here.
     * @return array{success: bool, errors?: array<string,string>}
     */
    public function updateProfile(int $userId, array $data): array
    {
        $name  = trim((string) ($data['name'] ?? ''));
        $phone = trim((string) ($data['whatsapp_number'] ?? ''));
        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        } elseif (mb_strlen($name) > 100 || $name !== strip_tags($name)) {
            $errors['name'] = 'Please enter a plain name (up to 100 characters).';
        }
        if ($phone === '') {
            $errors['whatsapp_number'] = 'Please enter your WhatsApp/mobile number.';
        } elseif ($this->normalizePhone($phone) === '') {
            $errors['whatsapp_number'] = 'Please enter a valid WhatsApp/mobile number (10-digit Indian mobile, or +countrycode number).';
        }
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }
        $this->users->update($userId, ['name' => $name, 'whatsapp_number' => $this->normalizePhone($phone)]);
        $_SESSION['user_name'] = $name;
        return ['success' => true];
    }

    /**
     * Normalizes to E.164 (+<country><number>). Returns '' when the input
     * can't be a real number. Indian numbers: 10 digits starting 6-9, with an
     * optional 0 / 91 / +91 prefix. Any other number must be entered with an
     * explicit + and country code (8-15 digits total, per E.164).
     */
    private function normalizePhone(string $phone): string
    {
        $raw    = trim($phone);
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') return '';

        if (str_starts_with($raw, '+') || str_starts_with($raw, '00')) {
            if (str_starts_with($raw, '00')) {
                $digits = substr($digits, 2);
            }
            if (str_starts_with($digits, '91')) {
                return preg_match('/^91[6-9]\d{9}$/', $digits) ? '+' . $digits : '';
            }
            return preg_match('/^[1-9]\d{7,14}$/', $digits) ? '+' . $digits : '';
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? '+91' . $digits : '';
    }

    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}

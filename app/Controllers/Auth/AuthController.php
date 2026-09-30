<?php

declare(strict_types=1);

namespace NEvents\Controllers\Auth;

use NEvents\Core\Application;
use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Services\Auth\AuthService;
use NEvents\Repositories\UserRepository;

class AuthController
{
    public function __construct(
        private AuthService    $auth,
        private UserRepository $users,
        private View           $view
    ) {}

    public function registerForm(Request $request): Response
    {
        if ($request->isLoggedIn()) {
            return Response::redirect(View::url('dashboard'));
        }
        return $this->view->makeResponse('auth.register', ['title' => 'Create Account — N Events']);
    }

    public function register(Request $request): Response
    {
        $data   = $request->only(['name', 'email', 'whatsapp_number', 'password', 'password_confirm', 'accept_terms']);
        $result = $this->auth->register($data);

        if (!$result['success']) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'errors' => $result['errors']], 422);
            }
            return $this->view->makeResponse('auth.register', [
                'title'  => 'Create Account — N Events',
                'errors' => $result['errors'],
                'old'    => $data,
            ], 422);
        }

        if ($result['mail_sent']) {
            $_SESSION['flash_success'] = 'Account created! Check your email (' . $data['email'] . ') for a verification link.';
        } else {
            // Never claim the email was sent when it wasn't — the account still
            // exists and works, but be honest that delivery failed so the user
            // knows to use "Resend Verification Email" instead of just waiting.
            $_SESSION['flash_error'] = 'Account created, but we couldn\'t send the verification email right now. '
                . 'Use "Resend Verification Email" on the sign-in page once you\'re ready.';
        }

        if ((bool) Application::getInstance()->config('app.debug', false)) {
            // Dev convenience only — never exposed when APP_DEBUG=false.
            $_SESSION['pending_verify_token'] = $result['token'];
        }

        return Response::redirect(View::url('login'));
    }

    public function loginForm(Request $request): Response
    {
        if ($request->isLoggedIn()) {
            return Response::redirect(View::url('dashboard'));
        }
        return $this->view->makeResponse('auth.login', ['title' => 'Sign In — N Events']);
    }

    public function login(Request $request): Response
    {
        $email    = (string)$request->post('email', '');
        $password = (string)$request->post('password', '');
        $ip       = $request->getIp();

        $result = $this->auth->login($email, $password, $ip);

        if (!$result['success']) {
            if (!empty($result['unverified'])) {
                if ($request->expectsJson()) {
                    return Response::json([
                        'success'    => false,
                        'error'      => $result['error'],
                        'unverified' => true,
                    ], 403);
                }
                return $this->view->makeResponse('auth.verify-required', [
                    'title' => 'Verify Your Email — N Events',
                    'email' => $result['email'],
                ], 403);
            }

            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'error' => $result['error']], 401);
            }
            return $this->view->makeResponse('auth.login', [
                'title' => 'Sign In — N Events',
                'error' => $result['error'],
                'old'   => ['email' => $email],
            ], 401);
        }

        $intended = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);

        if (!$result['roles'] || empty($result['roles'])) {
            $redirect = View::url('dashboard');
        } elseif (array_intersect($result['roles'], ['admin', 'super_admin', 'moderator'])) {
            $redirect = View::url('admin');
        } else {
            $redirect = $intended ?? View::url('dashboard');
        }

        return Response::redirect($redirect);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        return Response::redirect(View::url(''));
    }

    public function verifyEmail(Request $request): Response
    {
        $token  = (string) $request->param('token', '');
        $result = $this->auth->verifyEmail($token);

        if (!$result['success']) {
            return $this->view->makeResponse('auth.verify-email', [
                'title'            => 'Email Verification — N Events',
                'error'            => $result['error'],
                'already_verified' => !empty($result['already_verified']),
            ]);
        }

        $_SESSION['flash_success'] = 'Email verified! You can now sign in.';
        return Response::redirect(View::url('login'));
    }

    public function resendVerificationForm(Request $request): Response
    {
        return $this->view->makeResponse('auth.resend-verification', [
            'title' => 'Resend Verification Email — N Events',
            'email' => $request->query('email', ''),
        ]);
    }

    public function resendVerificationPost(Request $request): Response
    {
        $email  = (string) $request->post('email', '');
        $result = $this->auth->resendVerification($email);

        // Always the same message regardless of whether the account exists,
        // is already verified, or was rate-limited — see AuthService::resendVerification().
        $message = 'If that email is registered and not yet verified, a new verification link is on its way.';
        if (!empty($result['throttled'])) {
            $message = 'A verification email was already sent recently — please wait a bit before requesting another.';
        }

        if ($request->expectsJson()) {
            return Response::json(['success' => true, 'message' => $message]);
        }

        $_SESSION['flash_success'] = $message;
        return Response::redirect(View::url('login'));
    }

    public function forgotForm(Request $request): Response
    {
        return $this->view->makeResponse('auth.forgot-password', ['title' => 'Reset Password — N Events']);
    }

    public function forgotPost(Request $request): Response
    {
        $email  = (string)$request->post('email', '');
        $result = $this->auth->sendPasswordReset($email);

        // Always show same message for security
        $_SESSION['flash_success'] = 'If this email is registered, a reset link has been sent.';
        return Response::redirect(View::url('forgot-password'));
    }

    public function resetForm(Request $request): Response
    {
        return $this->view->makeResponse('auth.reset-password', [
            'title' => 'Set New Password — N Events',
            'token' => $request->param('token'),
        ]);
    }

    public function resetPost(Request $request): Response
    {
        $token    = (string)$request->post('token', '');
        $password = (string)$request->post('password', '');
        $result   = $this->auth->resetPassword($token, $password);

        if (!$result['success']) {
            return $this->view->makeResponse('auth.reset-password', [
                'title'  => 'Set New Password — N Events',
                'token'  => $token,
                'error'  => $result['error'] ?? null,
                'errors' => $result['errors'] ?? [],
            ]);
        }

        $_SESSION['flash_success'] = 'Password updated! Please sign in with your new password.';
        return Response::redirect(View::url('login'));
    }
}


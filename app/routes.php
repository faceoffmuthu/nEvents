<?php

declare(strict_types=1);

use NEvents\Core\Router;
use NEvents\Controllers\Public\HomeController;
use NEvents\Controllers\Public\EventController;
use NEvents\Controllers\Public\SearchController;
use NEvents\Controllers\Public\OrganizerController;
use NEvents\Controllers\Public\PageController;
use NEvents\Controllers\Auth\AuthController;
use NEvents\Controllers\Auth\OnboardingController;
use NEvents\Controllers\Dashboard\DashboardController;
use NEvents\Controllers\Dashboard\UserEventController;
use NEvents\Controllers\Admin\AdminController;
use NEvents\Controllers\Admin\AdminEventController;
use NEvents\Controllers\Admin\AdminSourceController;
use NEvents\Controllers\Admin\AdminUserController;
use NEvents\Controllers\Organizer\OrganizerPortalController;
use NEvents\Controllers\Api\EventApiController;
use NEvents\Middleware\CsrfMiddleware;
use NEvents\Middleware\AuthMiddleware;
use NEvents\Middleware\AdminMiddleware;

/** @var Router $router */

// ============================================================
// PUBLIC ROUTES
// ============================================================

$router->group(['middleware' => [CsrfMiddleware::class]], function (Router $r) {

    // Homepage
    $r->get('/',                  [HomeController::class, 'index']);
    $r->get('/home',              [HomeController::class, 'index']);

    // Post an Event — registered BEFORE /events/{city} so "create" isn't
    // treated as a district slug. Visitors are sent to /login and returned here.
    $r->group(['middleware' => [AuthMiddleware::class]], function (Router $r) {
        $r->get('/events/create',  [UserEventController::class, 'createForm']);
        $r->post('/events/create', [UserEventController::class, 'store']);
    });

    // Event discovery
    $r->get('/discover',                    [EventController::class, 'discover']);
    $r->get('/events/today',                [EventController::class, 'today']);
    $r->get('/events/tomorrow',             [EventController::class, 'tomorrow']);
    $r->get('/events/this-weekend',         [EventController::class, 'weekend']);
    $r->get('/events/this-week',            [EventController::class, 'thisWeek']);
    $r->get('/events/free',                 [EventController::class, 'freeEvents']);
    $r->get('/events/online',               [EventController::class, 'onlineEvents']);
    $r->get('/events/nearby',               [EventController::class, 'nearby']);
    $r->get('/events/featured',             [EventController::class, 'featured']);

    // City & category landing pages
    $r->get('/events/{city}',               [EventController::class, 'cityPage']);
    $r->get('/events/{city}/{category}',    [EventController::class, 'cityCategoryPage']);

    // Event detail & registration redirect
    $r->get('/event/{slug}',                [EventController::class, 'show']);
    $r->get('/event/{slug}/register',       [EventController::class, 'registerRedirect']);
    $r->post('/event/{slug}/save',          [EventController::class, 'save']);
    $r->post('/event/{slug}/unsave',        [EventController::class, 'unsave']);
    $r->post('/event/{slug}/report',        [EventController::class, 'report']);
    $r->get('/event/{slug}/calendar.ics',   [EventController::class, 'icsDownload']);

    // Search
    $r->get('/search',                      [SearchController::class, 'index']);

    // Category pages
    $r->get('/category/{slug}',             [EventController::class, 'categoryPage']);

    // Organizers
    $r->get('/organizers',                  [OrganizerController::class, 'directory']);
    $r->get('/organizer/{slug}',            [OrganizerController::class, 'profile']);

    // Static pages
    $r->get('/about',                       [PageController::class, 'about']);
    $r->get('/how-it-works',                [PageController::class, 'howItWorks']);
    $r->get('/submit-event',                [PageController::class, 'submitEvent']);
    $r->post('/submit-event',               [PageController::class, 'submitEventPost']);
    $r->get('/contact',                     [PageController::class, 'contact']);
    $r->post('/contact',                    [PageController::class, 'contactPost']);
    $r->get('/faq',                         [PageController::class, 'faq']);
    $r->get('/privacy',                     [PageController::class, 'privacy']);
    $r->get('/terms',                       [PageController::class, 'terms']);
    $r->get('/sitemap.xml',                 [PageController::class, 'sitemap']);
    $r->get('/robots.txt',                  [PageController::class, 'robots']);

    // ============================================================
    // AUTHENTICATION
    // ============================================================
    $r->get('/register',                    [AuthController::class, 'registerForm']);
    $r->post('/register',                   [AuthController::class, 'register']);
    $r->get('/login',                       [AuthController::class, 'loginForm']);
    $r->post('/login',                      [AuthController::class, 'login']);
    $r->post('/logout',                     [AuthController::class, 'logout']);
    $r->get('/verify-email/{token}',        [AuthController::class, 'verifyEmail']);
    $r->get('/resend-verification',         [AuthController::class, 'resendVerificationForm']);
    $r->post('/resend-verification',        [AuthController::class, 'resendVerificationPost']);
    $r->get('/forgot-password',             [AuthController::class, 'forgotForm']);
    $r->post('/forgot-password',            [AuthController::class, 'forgotPost']);
    $r->get('/reset-password/{token}',      [AuthController::class, 'resetForm']);
    $r->post('/reset-password',             [AuthController::class, 'resetPost']);

    // ============================================================
    // AUTHENTICATED USER ROUTES
    // ============================================================
    $r->group(['middleware' => [AuthMiddleware::class]], function (Router $r) {

        // Onboarding
        $r->get('/onboarding',              [OnboardingController::class, 'step']);
        $r->post('/onboarding',             [OnboardingController::class, 'save']);

        // Dashboard
        $r->get('/dashboard',               [DashboardController::class, 'index']);

        // Events the user posted (ownership re-checked in UserEventService)
        $r->get('/my-events',                    [UserEventController::class, 'index']);
        $r->get('/my-events/{id}/submitted',     [UserEventController::class, 'submitted']);
        $r->get('/my-events/{id}/edit',          [UserEventController::class, 'editForm']);
        $r->post('/my-events/{id}/edit',         [UserEventController::class, 'update']);
        $r->post('/my-events/{id}/cancel',       [UserEventController::class, 'cancel']);
        $r->post('/my-events/{id}/delete',       [UserEventController::class, 'delete']);

        $r->get('/my-events/saved',         [DashboardController::class, 'savedEvents']);
        $r->get('/my-events/history',       [DashboardController::class, 'viewHistory']);
        $r->get('/my-organizers',           [DashboardController::class, 'followedOrganizers']);
        $r->get('/notifications',           [DashboardController::class, 'notifications']);
        $r->get('/account',                 [DashboardController::class, 'account']);
        $r->post('/account',                [DashboardController::class, 'updateAccount']);
        $r->get('/account/preferences',     [DashboardController::class, 'preferences']);
        $r->post('/account/preferences',    [DashboardController::class, 'updatePreferences']);
        $r->get('/account/interests',       [DashboardController::class, 'interests']);
        $r->post('/account/interests',      [DashboardController::class, 'updateInterests']);
        $r->get('/account/privacy',         [DashboardController::class, 'privacy']);
        $r->post('/account/delete',         [DashboardController::class, 'deleteAccount']);
    });

    // ============================================================
    // ORGANIZER PORTAL
    // ============================================================
    $r->group(['prefix' => '/organizer-portal', 'middleware' => [AuthMiddleware::class]], function (Router $r) {
        $r->get('',                         [OrganizerPortalController::class, 'dashboard']);
        $r->get('/setup',                   [OrganizerPortalController::class, 'setup']);
        $r->get('/create-event',            [OrganizerPortalController::class, 'createEvent']);
        $r->post('/setup',                  [OrganizerPortalController::class, 'setupPost']);
        $r->get('/my-events',               [OrganizerPortalController::class, 'myEvents']);
        $r->get('/submit',                  [OrganizerPortalController::class, 'submitForm']);
        $r->post('/submit',                 [OrganizerPortalController::class, 'submitPost']);
        $r->get('/event/{id}/edit',         [OrganizerPortalController::class, 'editForm']);
        $r->post('/event/{id}/edit',        [OrganizerPortalController::class, 'editPost']);
        $r->get('/analytics',               [OrganizerPortalController::class, 'analytics']);
        $r->get('/verification',            [OrganizerPortalController::class, 'verification']);
        $r->post('/verification',           [OrganizerPortalController::class, 'requestVerification']);
        $r->get('/settings',                [OrganizerPortalController::class, 'settings']);
        $r->post('/settings',               [OrganizerPortalController::class, 'settingsPost']);
    });

    // ============================================================
    // ADMIN PANEL
    // ============================================================
    $r->group(['prefix' => '/admin', 'middleware' => [AdminMiddleware::class]], function (Router $r) {
        $r->get('',                         [AdminController::class, 'dashboard']);
        $r->get('/events',                  [AdminEventController::class, 'index']);
        $r->get('/events/pending',          [AdminEventController::class, 'pending']);
        // Static segments before /events/{id} — previously /duplicates was unreachable
        $r->get('/events/duplicates',       [AdminEventController::class, 'duplicates']);
        $r->post('/events/duplicates/{id}/merge',   [AdminEventController::class, 'merge']);
        $r->post('/events/duplicates/{id}/dismiss', [AdminEventController::class, 'dismissDuplicate']);
        $r->get('/events/create',           [AdminEventController::class, 'createForm']);
        $r->post('/events/create',          [AdminEventController::class, 'createPost']);
        $r->get('/events/{id}',             [AdminEventController::class, 'show']);
        $r->get('/events/{id}/edit',        [AdminEventController::class, 'editForm']);
        $r->post('/events/{id}/edit',       [AdminEventController::class, 'editPost']);
        $r->post('/events/{id}/status',     [AdminEventController::class, 'updateStatus']);
        $r->post('/events/{id}/moderate',   [AdminEventController::class, 'moderate']);
        $r->post('/events/{id}/publish',    [AdminEventController::class, 'publish']);
        $r->post('/events/{id}/reject',     [AdminEventController::class, 'reject']);
        $r->post('/events/{id}/cancel',     [AdminEventController::class, 'cancel']);
        $r->post('/users/{id}/posting',     [AdminEventController::class, 'submitterPosting']);
        $r->post('/conflicts/{id}/resolve', [AdminEventController::class, 'resolveConflict']);
        $r->post('/reports/{id}/resolve',   [AdminController::class, 'resolveReport']);
        $r->get('/sources',                 [AdminSourceController::class, 'index']);
        $r->get('/sources/{id}',            [AdminSourceController::class, 'show']);
        $r->post('/sources/{id}/toggle',    [AdminSourceController::class, 'toggle']);
        $r->post('/sources/{id}/run',       [AdminSourceController::class, 'runNow']);
        $r->get('/users',                   [AdminUserController::class, 'index']);
        $r->get('/users/{id}',              [AdminUserController::class, 'show']);
        $r->post('/users/{id}/status',      [AdminUserController::class, 'updateStatus']);
        $r->post('/users/{id}/suspend',     [AdminUserController::class, 'suspend']);
        $r->get('/organizers',              [AdminController::class, 'organizers']);
        $r->post('/organizers/{id}/verify', [AdminController::class, 'verifyOrganizer']);
        $r->get('/categories',              [AdminController::class, 'categories']);
        $r->get('/districts',               [AdminController::class, 'districts']);
        $r->post('/districts/{id}/toggle',  [AdminController::class, 'districtToggle']);
        $r->get('/reports',                 [AdminController::class, 'reports']);
        $r->get('/analytics',               [AdminController::class, 'analytics']);
        $r->get('/audit-log',               [AdminController::class, 'auditLog']);
        $r->get('/system',                  [AdminController::class, 'system']);
        $r->post('/system',                 [AdminController::class, 'systemPost']);
    });

    // ============================================================
    // AJAX/API ENDPOINTS
    // ============================================================
    $r->group(['prefix' => '/api'], function (Router $r) {
        $r->get('/events',                  [EventApiController::class, 'list']);
        $r->get('/events/autocomplete',     [EventApiController::class, 'autocomplete']);
        $r->get('/cities',                  [EventApiController::class, 'cities']);
        $r->get('/districts',               [EventApiController::class, 'districts']);
        $r->get('/locations',               [EventApiController::class, 'locations']);
        $r->get('/categories',              [EventApiController::class, 'categories']);
        $r->post('/set-city',               [EventApiController::class, 'setCity']);
        $r->post('/set-district',           [EventApiController::class, 'setDistrict']);
        $r->post('/user/preferences/district', [EventApiController::class, 'updateDistrictPreference']);
    });
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\Author;
use App\Http\Controllers\Editor;
use App\Http\Controllers\Reviewer;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'verified'])->group(function (): void {
    Route::prefix('author')->name('author.')->middleware('role:author,contributor')->group(function (): void {
        Route::get('/dashboard', Author\DashboardController::class)->name('dashboard');
        Route::get('/submissions', [Author\SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/reviews', Author\ReviewFeedbackController::class)->name('reviews.index');
        Route::get('/profile', [Author\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [Author\ProfileController::class, 'update'])->name('profile.update');
        Route::get('/settings', [Author\AccountSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [Author\AccountSettingsController::class, 'update'])->middleware('throttle:6,1')->name('settings.update');
        Route::resource('articles', Author\ArticleController::class);
        Route::patch('/articles/{article}/autosave', Author\AutosaveController::class)->middleware('throttle:30,1')->name('articles.autosave');
        Route::post('/articles/{article}/submit', [Author\SubmissionController::class, 'store'])->middleware('throttle:5,1')->name('articles.submit');
        Route::get('/articles/{article}/manuscript', Author\ManuscriptController::class)->name('articles.manuscript');
    });

    Route::prefix('reviewer')->name('reviewer.')->middleware('role:reviewer,editor,admin,super-admin')->group(function (): void {
        Route::get('/dashboard', Reviewer\DashboardController::class)->name('dashboard');
        Route::get('/reviews/{review}', [Reviewer\ReviewController::class, 'show'])->name('reviews.show');
        Route::put('/reviews/{review}', [Reviewer\ReviewController::class, 'update'])->middleware('throttle:6,1')->name('reviews.update');
        Route::post('/reviews/{review}/accept', [Reviewer\ReviewController::class, 'accept'])->name('reviews.accept');
        Route::post('/reviews/{review}/decline', [Reviewer\ReviewController::class, 'decline'])->name('reviews.decline');
        Route::get('/reviews/{review}/manuscript', [Reviewer\ReviewController::class, 'download'])->name('reviews.manuscript');
    });

    Route::prefix('editor')->name('editor.')->middleware('role:editor')->group(function (): void {
        Route::get('/dashboard', Editor\DashboardController::class)->name('dashboard');
    });

    Route::prefix('admin')->name('admin.')->middleware(['local-admin-bypass', 'role:editor,admin,super-admin'])->group(function (): void {
        Route::get('/', Admin\DashboardController::class)->middleware('role:admin,super-admin')->name('dashboard');
        Route::post('/articles/bulk', [Admin\ArticleController::class, 'bulk'])->middleware('throttle:10,1')->name('articles.bulk');
        Route::post('/articles/{article}/restore', [Admin\ArticleController::class, 'restore'])->name('articles.restore');
        Route::post('/articles/{article}/action', [Admin\ArticleController::class, 'action'])->middleware('throttle:20,1')->name('articles.action');
        Route::resource('articles', Admin\ArticleController::class);
        Route::resource('categories', Admin\CategoryController::class)->except('show');
        Route::post('/tags/{tag}/merge', [Admin\TagController::class, 'merge'])->name('tags.merge');
        Route::resource('tags', Admin\TagController::class)->except('show');
        Route::get('/media/{medium}/download', [Admin\MediaController::class, 'download'])->name('media.download');
        Route::resource('media', Admin\MediaController::class)->only(['index', 'store', 'edit', 'update', 'destroy'])->parameters(['media' => 'medium']);
        Route::get('/comments', [Admin\CommentController::class, 'index'])->name('comments.index');
        Route::patch('/comments/{comment}', [Admin\CommentController::class, 'update'])->name('comments.update');
        Route::delete('/comments/{comment}', [Admin\CommentController::class, 'destroy'])->name('comments.destroy');
        Route::get('/contact-submissions/export', [Admin\ContactSubmissionController::class, 'export'])->name('contacts.export');
        Route::post('/contact-submissions/{contact}/reply', [Admin\ContactSubmissionController::class, 'reply'])->middleware('throttle:10,1')->name('contacts.reply');
        Route::resource('contact-submissions', Admin\ContactSubmissionController::class)->only(['index', 'show', 'update', 'destroy'])
            ->names(['index' => 'contacts.index', 'show' => 'contacts.show', 'update' => 'contacts.update', 'destroy' => 'contacts.destroy'])
            ->parameters(['contact-submissions' => 'contact']);
        Route::get('/newsletter-subscribers/export', [Admin\NewsletterSubscriberController::class, 'export'])->name('newsletter.export');
        Route::get('/newsletter-campaigns/create', [Admin\NewsletterSubscriberController::class, 'createCampaign'])->name('newsletter.campaigns.create');
        Route::post('/newsletter-campaigns', [Admin\NewsletterSubscriberController::class, 'storeCampaign'])->name('newsletter.campaigns.store');
        Route::get('/newsletter-campaigns/{campaign}/edit', [Admin\NewsletterSubscriberController::class, 'editCampaign'])->name('newsletter.campaigns.edit');
        Route::put('/newsletter-campaigns/{campaign}', [Admin\NewsletterSubscriberController::class, 'updateCampaign'])->name('newsletter.campaigns.update');
        Route::post('/newsletter-campaigns/{campaign}/send', [Admin\NewsletterSubscriberController::class, 'sendCampaign'])->middleware('throttle:3,1')->name('newsletter.campaigns.send');
        Route::delete('/newsletter-campaigns/{campaign}', [Admin\NewsletterSubscriberController::class, 'destroyCampaign'])->name('newsletter.campaigns.destroy');
        Route::get('/newsletter-subscribers', [Admin\NewsletterSubscriberController::class, 'index'])->name('newsletter.index');
        Route::delete('/newsletter-subscribers/{subscriber}', [Admin\NewsletterSubscriberController::class, 'destroy'])->name('newsletter.destroy');
        Route::resource('reviews', Admin\ReviewController::class)->only(['index', 'show', 'update']);
        Route::resource('submissions', Admin\SubmissionController::class)->only(['index', 'show', 'update']);
        Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/audit-logs/{auditLog}', [Admin\AuditLogController::class, 'show'])->name('audit.show');

        Route::middleware('role:super-admin')->group(function (): void {
            Route::post('/users/{user}/approve', [Admin\UserController::class, 'approve'])->middleware('throttle:20,1')->name('users.approve');
            Route::post('/users/{user}/reject', [Admin\UserController::class, 'reject'])->middleware('throttle:20,1')->name('users.reject');
            Route::resource('users', Admin\UserController::class)->except('show');
            Route::resource('roles', Admin\RoleController::class)->except('show');
            Route::get('/settings', [Admin\SettingController::class, 'index'])->name('settings.index');
            Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
            Route::get('/readiness/{resource}', [Admin\ReadinessController::class, 'index'])->name('readiness.index');
            Route::post('/readiness/{resource}', [Admin\ReadinessController::class, 'store'])->name('readiness.store');
            Route::put('/readiness/{resource}/{record}', [Admin\ReadinessController::class, 'update'])->name('readiness.update');
            Route::delete('/readiness/{resource}/{record}', [Admin\ReadinessController::class, 'destroy'])->name('readiness.destroy');
        });
    });
});

Route::middleware(['auth', 'active', 'verified', 'role:author,contributor,reviewer,editor,admin,super-admin'])->group(function () {
    Route::get('/workflow/staff/directory', [WorkflowOperationsController::class, 'staff'])->name('workflow.staff');
    Route::post('/workflow/staff/{user}', [WorkflowOperationsController::class, 'staffUpdate'])->name('workflow.staff.update');
    Route::get('/workflow/settings/configuration', [WorkflowOperationsController::class, 'settings'])->name('workflow.settings');
    Route::post('/workflow/settings/configuration', [WorkflowOperationsController::class, 'saveSettings'])->name('workflow.settings.save');
    Route::post('/workflow/{article}/deadline', [WorkflowOperationsController::class, 'deadline'])->name('workflow.deadline');
    Route::post('/workflow/reviews/{review}/reopen', [WorkflowOperationsController::class, 'reopen'])->name('workflow.review.reopen');
    Route::get('/workflow/reviews/{review}/attachment', [WorkflowOperationsController::class, 'reviewFile'])->name('workflow.review.file');
    Route::get('/workflow', [WorkflowController::class, 'index'])->name('workflow.index');
    Route::get('/workflow/files/{file}', [WorkflowController::class, 'download'])->name('workflow.download');
    Route::get('/workflow/{article}', [WorkflowController::class, 'show'])->name('workflow.show');
    Route::post('/workflow/{article}/action', [WorkflowController::class, 'action'])->middleware('throttle:30,1')->name('workflow.action');
    Route::post('/workflow/{article}/assignment', [WorkflowController::class, 'assignEditor'])->name('workflow.assignment');
    Route::post('/workflow/{article}/adopt', [WorkflowController::class, 'adopt'])->name('workflow.adopt');
    Route::post('/workflow/{article}/override', [WorkflowController::class, 'override'])->name('workflow.override');
    Route::get('/workflow/{article}/acceptance', [WorkflowController::class, 'acceptance'])->name('workflow.acceptance');
});

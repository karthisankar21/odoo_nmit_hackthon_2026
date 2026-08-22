<?php
// =============================================================================
// routes/web.php
// -----------------------------------------------------------------------------
// All web routes for Dayflow HRMS.
//
// Structure:
//   /              → redirect to login
//   /login         → show login form (GET) / process login (POST)
//   /logout        → logout (POST)
//   /employee/*    → protected by auth + role:employee
//   /admin/*       → protected by auth + role:admin
// =============================================================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

// Employee controllers (Sub-Task 10)
use App\Http\Controllers\Employee\DashboardController  as EmpDashboard;
use App\Http\Controllers\Employee\ProfileController    as EmpProfile;
use App\Http\Controllers\Employee\AttendanceController as EmpAttendance;
use App\Http\Controllers\Employee\LeaveController      as EmpLeave;
use App\Http\Controllers\Employee\PayrollController    as EmpPayroll;

// Admin controllers (Sub-Task 11)
use App\Http\Controllers\Admin\DashboardController  as AdminDashboard;
use App\Http\Controllers\Admin\EmployeeController   as AdminEmployee;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendance;
use App\Http\Controllers\Admin\LeaveController      as AdminLeave;
use App\Http\Controllers\Admin\PayrollController    as AdminPayroll;

// =============================================================================
// ROOT — redirect to login
// =============================================================================

Route::get('/', fn() => redirect()->route('login'));

// =============================================================================
// AUTH ROUTES — no middleware, accessible to everyone
// =============================================================================

Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',   [AuthController::class, 'login'])->name('login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register',[AuthController::class, 'register'])->name('register.submit');
Route::post('/logout',  [AuthController::class, 'logout'])->name('logout');

// =============================================================================
// EMPLOYEE ROUTES — protected by auth.custom + role:employee
// =============================================================================

Route::middleware(['auth.custom', 'role:employee'])->prefix('employee')->group(function () {

    // Dashboard
    Route::get('/dashboard',  [EmpDashboard::class,  'index'])->name('employee.dashboard');

    // Profile
    Route::get('/profile',    [EmpProfile::class,    'index'])->name('employee.profile');
    Route::post('/profile',   [EmpProfile::class,    'update'])->name('employee.profile.update');

    // Attendance — check in / check out / view
    Route::get('/attendance',       [EmpAttendance::class, 'index'])->name('employee.attendance');
    Route::post('/attendance/checkin',  [EmpAttendance::class, 'checkIn'])->name('employee.checkin');
    Route::post('/attendance/checkout', [EmpAttendance::class, 'checkOut'])->name('employee.checkout');

    // Leave — apply + view history
    Route::get('/leave',      [EmpLeave::class, 'index'])->name('employee.leave');
    Route::post('/leave',     [EmpLeave::class, 'apply'])->name('employee.leave.apply');
    // Alias: GET /employee/leave is also the form page, so apply routes to same view

    // Payroll — read only
    Route::get('/payroll',    [EmpPayroll::class, 'index'])->name('employee.payroll');
});

// =============================================================================
// ADMIN ROUTES — protected by auth.custom + role:admin
// =============================================================================

Route::middleware(['auth.custom', 'role:admin'])->prefix('admin')->group(function () {

    // Dashboard (analytics summary)
    Route::get('/dashboard',   [AdminDashboard::class,  'index'])->name('admin.dashboard');

    // Employee management
    Route::get('/employees',          [AdminEmployee::class, 'index'])->name('admin.employees');
    Route::get('/employees/{id}',     [AdminEmployee::class, 'show'])->name('admin.employees.show');
    Route::post('/employees/{id}',    [AdminEmployee::class, 'update'])->name('admin.employees.update');

    // Attendance
    Route::get('/attendance',  [AdminAttendance::class, 'index'])->name('admin.attendance');

    // Leave approvals
    Route::get('/leave',              [AdminLeave::class, 'index'])->name('admin.leave');
    Route::post('/leave/{id}/approve',[AdminLeave::class, 'approve'])->name('admin.leave.approve');
    Route::post('/leave/{id}/reject', [AdminLeave::class, 'reject'])->name('admin.leave.reject');

    // Payroll management
    Route::get('/payroll',         [AdminPayroll::class, 'index'])->name('admin.payroll');
    Route::post('/payroll/{id}',   [AdminPayroll::class, 'update'])->name('admin.payroll.update');
});

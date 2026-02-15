@extends('layouts.app')

@section('content')
<div class="container-fluid pt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="df-panel border-0 shadow-sm">
                <!-- Header Background -->
                <div class="p-4 bg-primary rounded-top" style="height: 160px; background: linear-gradient(135deg, var(--primary), #6366f1) !important;">
                    <div class="d-flex align-items-end gap-3 h-100 pb-3">
                        <div class="avatar-wrapper shadow-lg rounded-circle bg-white p-1" style="margin-bottom: -50px;">
                            <img src="/images/avatar.svg" alt="Avatar" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover; background: white;">
                        </div>
                        <div class="pb-1">
                            <h2 class="h3 fw-bold text-white mb-0">{{ auth()->user()->name }}</h2>
                            <span class="badge {{ auth()->user()->isSuperAdmin() ? 'bg-warning text-dark' : 'bg-white text-primary' }} shadow-sm">
                                {{ ucfirst(auth()->user()->role) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Profile Content -->
                <div class="p-4 pt-5 mt-3">
                    <div class="row g-4">
                        <div class="col-md-7">
                            <h4 class="h6 text-uppercase fw-bold text-secondary mb-4">Account Information</h4>
                            
                            <div class="mb-4">
                                <label class="small text-muted mb-1 d-block">Full Name</label>
                                <div class="p-3 rounded-3 bg-light border d-flex align-items-center gap-3">
                                    <i class="bi bi-person text-primary"></i>
                                    <span class="fw-semibold text-dark">{{ auth()->user()->name }}</span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="small text-muted mb-1 d-block">Email Address</label>
                                <div class="p-3 rounded-3 bg-light border d-flex align-items-center gap-3">
                                    <i class="bi bi-envelope text-primary"></i>
                                    <span class="fw-semibold text-dark">{{ auth()->user()->email }}</span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="small text-muted mb-1 d-block">Current Role</label>
                                <div class="p-3 rounded-3 bg-light border d-flex align-items-center gap-3">
                                    <i class="bi bi-shield-check text-primary"></i>
                                    <span class="fw-semibold text-dark">
                                        @if(auth()->user()->isSuperAdmin())
                                            Shadow SuperAdmin
                                        @elseif(auth()->user()->isAdmin())
                                            System Administrator
                                        @elseif(auth()->user()->isCreator())
                                            Strategy Creator
                                        @else
                                            Investor
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <h4 class="h6 text-uppercase fw-bold text-secondary mb-4">Security & Settings</h4>
                            <div class="df-panel p-3 border-danger-subtle bg-danger-subtle bg-opacity-10 mb-3">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-lock-fill text-danger"></i>
                                    <h6 class="mb-0 text-danger">Security Verification</h6>
                                </div>
                                <p class="small text-muted mb-3">Ensure your account uses a strong password and multi-factor authentication (coming soon).</p>
                                <button class="btn btn-sm btn-outline-danger w-100" disabled>Change Password</button>
                            </div>

                            <div class="alert alert-info border-0 shadow-sm" style="background: rgba(var(--primary-rgb), 0.1);">
                                <div class="d-flex gap-2">
                                    <i class="bi bi-info-circle-fill text-primary"></i>
                                    <div class="small">Profil kamu diatur oleh sistem berdasarkan lisensi akses yang terdaftar.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


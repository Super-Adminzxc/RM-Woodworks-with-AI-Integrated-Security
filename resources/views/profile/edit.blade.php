@extends('layouts.system')

@section('main-content')
<style>
    [x-cloak] { display: none !important; }
</style>

<div class="h-full flex flex-col bg-amber-50 overflow-hidden">
    <!-- Header -->
    <header class="bg-amber-50 p-5 relative z-10 border-b border-amber-200">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-800">My Profile</h1>
                <p class="text-base text-gray-600 mt-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Account information &amp; security
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-lg text-xs font-semibold uppercase tracking-wider border border-amber-200">
                    {{ ucfirst(str_replace('_', ' ', $user->role ?? 'User')) }}
                </span>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-5 relative">
        <div class="max-w-5xl mx-auto space-y-5">

            @if (session('status') === 'profile-updated' || session('status') === 'password-updated')
                <div class="flex items-center gap-3 bg-green-100 border-2 border-green-400 text-green-800 rounded-xl px-5 py-3 shadow-sm">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium text-sm">
                        {{ session('status') === 'profile-updated' ? 'Profile information updated.' : 'Password updated.' }}
                    </span>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                <!-- Left: forms -->
                <div class="lg:col-span-2 space-y-5">

                    <!-- Profile Information -->
                    <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50">
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Profile Information</h2>
                            <p class="text-xs text-slate-500 mt-1">Update your account's name and email address.</p>
                        </div>

                        <form method="POST" action="{{ route('profile.update') }}" class="p-5 space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="name" class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5">Name</label>
                                <input id="name" name="name" type="text" required autocomplete="name"
                                       value="{{ old('name', $user->name) }}"
                                       class="w-full border-2 rounded-xl px-3 py-1.5 text-sm text-black transition-all focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('name', 'updateProfileInformation') border-red-500 @else border-gray-300 @enderror">
                                @error('name', 'updateProfileInformation')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5">Email Address</label>
                                <input id="email" name="email" type="email" required autocomplete="username"
                                       value="{{ old('email', $user->email) }}"
                                       class="w-full border-2 rounded-xl px-3 py-1.5 text-sm text-black transition-all focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('email', 'updateProfileInformation') border-red-500 @else border-gray-300 @enderror">
                                @error('email', 'updateProfileInformation')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-slate-500 mt-1.5">
                                    Changing your email address requires it to be verified again.
                                </p>
                            </div>

                            <div class="flex items-center gap-3 pt-1">
                                <button type="submit"
                                        class="px-4 py-2 bg-gradient-to-r from-orange-600 to-red-600 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg hover:from-orange-700 hover:to-red-700 transition-all">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Update Password -->
                    <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50">
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Update Password</h2>
                            <p class="text-xs text-slate-500 mt-1">Use a long, random password to keep your account secure.</p>
                        </div>

                        <form method="POST" action="{{ route('password.update') }}" class="p-5 space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="current_password" class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5">Current Password</label>
                                <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                                       class="w-full border-2 rounded-xl px-3 py-1.5 text-sm text-black transition-all focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('current_password', 'updatePassword') border-red-500 @else border-gray-300 @enderror">
                                @error('current_password', 'updatePassword')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password" class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5">New Password</label>
                                <input id="password" name="password" type="password" autocomplete="new-password"
                                       class="w-full border-2 rounded-xl px-3 py-1.5 text-sm text-black transition-all focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('password', 'updatePassword') border-red-500 @else border-gray-300 @enderror">
                                @error('password', 'updatePassword')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5">Confirm New Password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                                       class="w-full border-2 border-gray-300 rounded-xl px-3 py-1.5 text-sm text-black transition-all focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>

                            <div class="flex items-center gap-3 pt-1">
                                <button type="submit"
                                        class="px-4 py-2 bg-gradient-to-r from-orange-600 to-red-600 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg hover:from-orange-700 hover:to-red-700 transition-all">
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right: summary + danger zone -->
                <div class="space-y-5">

                    <!-- Account summary -->
                    <div class="bg-gradient-to-br from-slate-700 to-slate-800 rounded-xl shadow-xl border border-slate-600 text-white overflow-hidden">
                        <div class="p-5 flex items-center gap-3">
                            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-yellow-500 to-orange-500 p-0.5 shadow-lg shrink-0">
                                <div class="w-full h-full bg-slate-900 rounded-[10px] flex items-center justify-center text-white font-bold text-lg">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold truncate">{{ $user->name }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $user->email }}</p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Role</span>
                                <span class="font-semibold text-amber-300">{{ ucfirst(str_replace('_', ' ', $user->role ?? 'User')) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Email status</span>
                                @if ($user->email_verified_at)
                                    <span class="px-2 py-0.5 rounded bg-green-500/20 text-green-300 font-semibold">Verified</span>
                                @else
                                    <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-semibold">Unverified</span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Member since</span>
                                <span class="font-semibold text-slate-200">{{ $user->created_at?->format('M d, Y') ?? '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Danger zone -->
                    <div class="bg-white rounded-xl shadow-lg border border-red-200 overflow-hidden"
                         x-data="{ confirmingDeletion: {{ $errors->userDeletion->any() ? 'true' : 'false' }} }">
                        <div class="px-5 py-4 border-b border-red-100 bg-red-50">
                            <h2 class="text-sm font-bold text-red-800 uppercase tracking-wide">Delete Account</h2>
                            <p class="text-xs text-red-600 mt-1">Once deleted, your data cannot be recovered.</p>
                        </div>
                        <div class="p-5">
                            <button type="button"
                                    @click="confirmingDeletion = true"
                                    class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg transition-all">
                                Delete Account
                            </button>
                        </div>

                        <!-- Confirmation modal -->
                        <div x-cloak x-show="confirmingDeletion" class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background: rgba(15, 23, 42, 0.6);">
                            <div @click.outside="confirmingDeletion = false"
                                 class="w-full max-w-md bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden">
                                <form method="POST" action="{{ route('profile.destroy') }}" class="p-5 space-y-4">
                                    @csrf
                                    @method('DELETE')

                                    <div>
                                        <h3 class="text-base font-bold text-slate-800">Are you sure you want to delete your account?</h3>
                                        <p class="text-xs text-slate-500 mt-1.5">
                                            This will permanently remove your account. Enter your password to confirm.
                                        </p>
                                    </div>

                                    <div>
                                        <label for="delete_password" class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5">Password</label>
                                        <input id="delete_password" name="password" type="password" placeholder="Password"
                                               class="w-full border-2 rounded-xl px-3 py-1.5 text-sm text-black transition-all focus:ring-2 focus:ring-red-500 focus:border-red-500 @error('password', 'userDeletion') border-red-500 @else border-gray-300 @enderror">
                                        @error('password', 'userDeletion')
                                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex items-center justify-end gap-3">
                                        <button type="button" @click="confirmingDeletion = false"
                                                class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold rounded-xl transition-all">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg transition-all">
                                            Delete Account
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection

<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $navLinks = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'match' => 'dashboard'],
        ['route' => 'planner', 'label' => 'Plan', 'match' => 'planner*'],
        ['route' => 'shopping', 'label' => 'Courses', 'match' => 'shopping'],
        ['route' => 'recipes.index', 'label' => 'Ensembles', 'match' => 'recipes.*'],
        ['route' => 'programs', 'label' => 'Programmes', 'match' => 'programs'],
        ['route' => 'metrics', 'label' => 'Corps', 'match' => 'metrics'],
        ['route' => 'charts', 'label' => 'Graphiques', 'match' => 'charts'],
        ['route' => 'discover', 'label' => 'Découvrir', 'match' => 'discover'],
        ['route' => 'notifications', 'label' => 'Notifications', 'match' => 'notifications', 'badge' => true],
    ];
    $unreadCount = auth()->user()->unreadNotifications()->count();
@endphp

<nav
    class="border-b border-fm-border bg-fm-bg sticky top-0 z-50"
    x-data="{ mobileOpen: false }"
    @keydown.escape.window="mobileOpen = false"
    x-on:livewire:navigating.window="mobileOpen = false"
    @click.outside="mobileOpen = false"
>
    <div class="fm-container flex justify-between items-center h-nav gap-3">
        <div class="flex items-center gap-6 lg:gap-10 min-w-0">
            <x-fm.logo :href="route('dashboard')" wire:navigate size="sm" />
            <div class="hidden lg:flex items-center gap-6">
                @foreach ($navLinks as $link)
                    <a href="{{ route($link['route']) }}" wire:navigate
                       @class(['fm-nav-link relative', 'fm-nav-link-active' => request()->routeIs($link['match'])])>
                        {{ $link['label'] }}
                        @if (($link['badge'] ?? false) && $unreadCount > 0)
                            <span class="absolute -top-1 -right-3 min-w-[1rem] h-4 px-1 rounded-full bg-fm-accent text-[10px] text-white flex items-center justify-center">
                                {{ $unreadCount }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-1 sm:gap-3 shrink-0">
            {{-- Notifications rapides sur tablette --}}
            <a
                href="{{ route('notifications') }}"
                wire:navigate
                class="lg:hidden relative inline-flex min-h-touch min-w-touch items-center justify-center rounded-lg text-fm-muted hover:text-fm-text"
                aria-label="Notifications"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                @if ($unreadCount > 0)
                    <span class="absolute top-1 right-1 min-w-[1rem] h-4 px-1 rounded-full bg-fm-accent text-[10px] text-white flex items-center justify-center">
                        {{ $unreadCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('settings') }}" wire:navigate class="hidden md:inline fm-nav-link">Réglages</a>
            <span class="hidden md:inline text-caption text-fm-muted max-w-[8rem] truncate">{{ auth()->user()->name }}</span>
            <button wire:click="logout" type="button" class="hidden sm:inline-flex fm-btn-ghost text-caption">Déconnexion</button>

            <button
                type="button"
                class="lg:hidden inline-flex min-h-touch min-w-touch items-center justify-center rounded-lg text-fm-muted hover:text-fm-text"
                @click="mobileOpen = !mobileOpen"
                :aria-expanded="mobileOpen"
                aria-controls="mobile-nav"
                aria-label="Menu"
            >
                <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
                <svg x-cloak x-show="mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Menu mobile --}}
    <div
        id="mobile-nav"
        x-cloak
        x-show="mobileOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="lg:hidden border-t border-fm-border bg-fm-bg"
    >
        <div class="fm-container py-3 space-y-1 max-h-[calc(100dvh-var(--fm-nav-height))] overflow-y-auto">
            @foreach ($navLinks as $link)
                <a
                    href="{{ route($link['route']) }}"
                    wire:navigate
                    @click="mobileOpen = false"
                    @class(['fm-nav-link-mobile relative', 'fm-nav-link-mobile-active' => request()->routeIs($link['match'])])
                >
                    {{ $link['label'] }}
                    @if (($link['badge'] ?? false) && $unreadCount > 0)
                        <span class="ml-auto min-w-[1.25rem] h-5 px-1.5 rounded-full bg-fm-accent text-[10px] text-white inline-flex items-center justify-center">
                            {{ $unreadCount }}
                        </span>
                    @endif
                </a>
            @endforeach
            <div class="pt-2 mt-2 border-t border-fm-border space-y-1">
                <a href="{{ route('settings') }}" wire:navigate @click="mobileOpen = false" @class(['fm-nav-link-mobile', 'fm-nav-link-mobile-active' => request()->routeIs('settings*')])>
                    Réglages
                </a>
                <p class="px-3 py-2 text-caption text-fm-muted truncate">{{ auth()->user()->name }}</p>
                <button wire:click="logout" type="button" class="fm-nav-link-mobile w-full text-left text-fm-accent">
                    Déconnexion
                </button>
            </div>
        </div>
    </div>
</nav>

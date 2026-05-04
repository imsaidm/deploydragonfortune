 <!DOCTYPE html>
 <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

 <head>
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width, initial-scale=1">
     <title>@yield('title', config('app.name', 'DragonFortune AI'))</title>

     <meta name="csrf-token" content="{{ csrf_token() }}">
     <meta name="api-base-url" content="{{ config('services.api.base_url') }}">
     <meta name="spot-microstructure-api" content="{{ config('services.spot_microstructure.base_url') }}">

     <!-- API Configuration from Laravel -->
     <script>
         window.APP_CONFIG = {
             apiBaseUrl: "{{ config('app.api_urls.internal') }}",
             coinglassUrl: "{{ config('app.api_urls.coinglass') }}",
             environment: "{{ config('app.env') }}"
         };
     </script>

     @vite(['resources/css/app.css', 'resources/js/app.js'])
     @livewireStyles
     @stack('head')
 </head>

 <body>
     <div class="df-layout" x-data="{
        sidebarOpen: window.innerWidth >= 768,
        sidebarCollapsed: false,
        openSubmenus: {},
        isMobile: window.innerWidth < 768,

        init() {
            // Restore sidebar state from localStorage
            this.restoreSidebarState();
            
            // Handle window resize
            window.addEventListener('resize', () => {
                this.isMobile = window.innerWidth < 768;
                if (!this.isMobile) {
                    this.sidebarOpen = true;
                    document.body.classList.remove('sidebar-open');
                } else {
                    this.sidebarOpen = false;
                    document.body.classList.remove('sidebar-open');
                }
            });

            // Watch for sidebar state changes
            this.$watch('sidebarOpen', (value) => {
                if (this.isMobile) {
                    if (value) {
                        document.body.classList.add('sidebar-open');
                    } else {
                        document.body.classList.remove('sidebar-open');
                    }
                }
            });
        },

        restoreSidebarState() {
            try {
                const savedState = localStorage.getItem('sidebarState');
                if (savedState) {
                    const parsedState = JSON.parse(savedState);
                    this.openSubmenus = parsedState.openSubmenus || {};
                    // Keep Derivatives submenu closed by default.
                    delete this.openSubmenus['derivatives'];
                }
            } catch (error) {
                console.warn('Failed to restore sidebar state:', error);
                this.openSubmenus = {};
            }
        },

        saveSidebarState() {
            try {
                const state = {
                    openSubmenus: this.openSubmenus
                };
                localStorage.setItem('sidebarState', JSON.stringify(state));
            } catch (error) {
                console.warn('Failed to save sidebar state:', error);
            }
        },

        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },

        closeSidebar() {
            if (this.isMobile) {
                this.sidebarOpen = false;
            }
        },

        toggleSubmenu(menuId) {
            this.openSubmenus[menuId] = !this.openSubmenus[menuId];
            this.saveSidebarState();
        }
    }" @theme-toggle.window="document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');">

         <!-- Mobile Overlay -->
         <div class="mobile-overlay d-md-none"
             :class="{ 'show': sidebarOpen && isMobile }"
             @click="closeSidebar()">
         </div>

         <!-- Sidebar -->
         <aside class="df-sidebar"
             :class="{
                   'collapsed': sidebarCollapsed && !isMobile,
                   'mobile-open': sidebarOpen && isMobile
               }"
             x-show="sidebarOpen || isMobile">

             <!-- Sidebar Header -->
             <div class="df-sidebar-header">
                 <div class="df-sidebar-menu">
                     <div class="df-sidebar-menu-item">
                         <button class="df-sidebar-menu-button df-sidebar-menu-button-lg">
                             <div class="bg-primary rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M3 3v18h18" />
                                     <path d="M7 12l3-3 3 3 5-5" />
                                 </svg>
                             </div>
                             <div class="d-flex flex-column text-start flex-grow-1" x-show="!sidebarCollapsed">
                                 <span class="fw-semibold" style="font-size: 1rem;">Dragon Fortune</span>
                             </div>
                             <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ms-auto" x-show="!sidebarCollapsed">
                                 <path d="M7 13l3 3 7-7" />
                             </svg>
                         </button>
                     </div>
                 </div>
             </div>

             <!-- Sidebar Content -->
             <div class="df-sidebar-content df-scrollbar flex-grow-1">
                 <!-- Navigation Section -->
                 <div class="df-sidebar-group">
                     <div class="df-sidebar-group-label" x-show="!sidebarCollapsed">Navigation</div>
                     <ul class="df-sidebar-menu">
                         <li class="df-sidebar-menu-item">
                             <a href="/" class="df-sidebar-menu-button {{ request()->routeIs('workspace') ? 'active' : '' }}" @click="closeSidebar()">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <rect x="3" y="3" width="7" height="7" />
                                     <rect x="14" y="3" width="7" height="7" />
                                     <rect x="14" y="14" width="7" height="7" />
                                     <rect x="3" y="14" width="7" height="7" />
                                 </svg>
                                 <span>Dashboard</span>
                             </a>
                         </li>
                     </ul>
                 </div>

                 <div class="df-sidebar-group">
                     <div class="df-sidebar-group-label" x-show="!sidebarCollapsed">Research</div>
                     <ul class="df-sidebar-menu">
                         <li class="df-sidebar-menu-item">
                             <a href="/signal-analytics" class="df-sidebar-menu-button {{ request()->routeIs('signal-analytics.*') ? 'active' : '' }}" @click="closeSidebar()">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M3 3v18h18" />
                                     <path d="M7 14l3-3 4 4 6-6" />
                                     <circle cx="7" cy="14" r="1.5" />
                                     <circle cx="14" cy="15" r="1.5" />
                                     <circle cx="20" cy="9" r="1.5" />
                                 </svg>
                                 <span>Signal and Analytics</span>
                             </a>
                         </li>
                         <ul class="df-sidebar-menu">
                             <li class="df-sidebar-menu-item">
                                 <a href="/summary" class="df-sidebar-menu-button {{ request()->routeIs('summary.*') ? 'active' : '' }}" @click="closeSidebar()">
                                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-files" viewBox="0 0 16 16">
                                         <path d="M13 0H6a2 2 0 0 0-2 2 2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2 2 2 0 0 0 2-2V2a2 2 0 0 0-2-2m0 13V4a2 2 0 0 0-2-2H5a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1M3 4a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z" />
                                     </svg>
                                     <span>Summary</span>
                                 </a>
                             </li>

                             <!-- <li class="df-sidebar-menu-item">
	                            <a href="/backtest-result" class="df-sidebar-menu-button {{ request()->routeIs('backtest-result.*') ? 'active' : '' }}" @click="closeSidebar()">
	                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
	                                    <path d="M3 3v18h18"/>
	                                    <path d="M7 16l3-3 3 3 5-5"/>
	                                    <circle cx="7" cy="16" r="2"/>
	                                    <circle cx="13" cy="13" r="2"/>
	                                    <circle cx="18" cy="8" r="2"/>
	                                </svg>
	                                <span>Backtest Result</span>
	                            </a>
	                        </li> -->
                         </ul>
                 </div>

                 {{-- <div class="df-sidebar-group">
                     <div class="df-sidebar-group-label" x-show="!sidebarCollapsed" style="margin-bottom: 5px;">Strategies</div>
                     <ul class="df-sidebar-menu" style="gap: 0px !important;">
                         <li class="df-sidebar-menu-item" style="margin-bottom: 2px;">
                             <a href="/strategies/said" class="df-sidebar-menu-button {{ request()->routeIs('strategies.said.*') ? 'active' : '' }}" @click="closeSidebar()" style="padding-top: 4px; padding-bottom: 4px;">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                     <circle cx="12" cy="7" r="4" />
                                 </svg>
                                 <span>Said</span>
                             </a>
                         </li>

                         <li class="df-sidebar-menu-item" style="margin-bottom: 2px;">
                             <a href="/strategies/wisnu" class="df-sidebar-menu-button {{ request()->routeIs('strategies.wisnu.*') ? 'active' : '' }}" @click="closeSidebar()" style="padding-top: 4px; padding-bottom: 4px;">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                     <circle cx="12" cy="7" r="4" />
                                 </svg>
                                 <span>Wisnu</span>
                             </a>
                         </li>

                         <li class="df-sidebar-menu-item" style="margin-bottom: 2px;">
                             <a href="/strategies/romin" class="df-sidebar-menu-button {{ request()->routeIs('strategies.romin.*') ? 'active' : '' }}" @click="closeSidebar()" style="padding-top: 4px; padding-bottom: 4px;">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                     <circle cx="12" cy="7" r="4" />
                                 </svg>
                                 <span>Romin</span>
                             </a>
                         </li>

                         <li class="df-sidebar-menu-item" style="margin-bottom: 2px;">
                             <a href="/strategies/tsaqif" class="df-sidebar-menu-button {{ request()->routeIs('strategies.tsaqif.*') ? 'active' : '' }}" @click="closeSidebar()" style="padding-top: 4px; padding-bottom: 4px;">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                     <circle cx="12" cy="7" r="4" />
                                 </svg>
                                 <span>Tsaqif</span>
                             </a>
                         </li>
                     </ul>
                 </div> --}}

                 <div class="df-sidebar-group">
                     <div class="df-sidebar-group-label" x-show="!sidebarCollapsed">Markets</div>
                     <ul class="df-sidebar-menu">
                         <li class="df-sidebar-menu-item">
                             <button class="df-sidebar-menu-button" @click="toggleSubmenu('coinglass')">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                                 </svg>
                                 <span>Coinglass</span>
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ms-auto" :class="{ 'rotate-90': openSubmenus['coinglass'] }">
                                     <path d="M9 18l6-6-6-6" />
                                 </svg>
                             </button>

                             <div class="df-submenu" :class="{ 'show': openSubmenus['coinglass'] }" style="padding-left: 10px;">

                                 <div class="df-sidebar-menu-item">
                                     <button class="df-sidebar-menu-button {{ request()->routeIs('derivatives.*') ? 'active' : '' }}" @click.stop="toggleSubmenu('derivatives')">
                                         <span>Derivatives Core</span>
                                         <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ms-auto" :class="{ 'rotate-90': openSubmenus['derivatives'] }">
                                             <path d="M9 18l6-6-6-6" />
                                         </svg>
                                     </button>
                                     <div class="df-submenu" :class="{ 'show': openSubmenus['derivatives'] }">
                                         <a href="/derivatives/funding-rate" class="df-submenu-item">Funding Rate</a>
                                         <a href="/derivatives/open-interest" class="df-submenu-item">Open Interest</a>
                                         <a href="/derivatives/long-short-ratio" class="df-submenu-item">Long/Short Ratio</a>
                                         <a href="/derivatives/liquidations" class="df-submenu-item">Liquidation Heatmap</a>
                                         <a href="/derivatives/liquidations-stream" class="df-submenu-item">Liquidation Order Stream</a>
                                         <a href="/derivatives/liquidations-aggregated" class="df-submenu-item">Aggregated Liquidations</a>
                                         <a href="/derivatives/basis-term-structure" class="df-submenu-item">Basis & Term Structure</a>
                                     </div>
                                 </div>

                                 <a href="/spot-microstructure" class="df-submenu-item">Spot Microstructure</a>
                                 <a href="/onchain-metrics" class="df-submenu-item">On-Chain Metrics</a>
                                 <a href="/etf-institutional/dashboard" class="df-submenu-item">ETF & Institutional</a>
                                 <a href="/volatility-regime/dashboard" class="df-submenu-item">Volatility & Regime</a>
                                 <a href="/macro-overlay" class="df-submenu-item">Macro Overlay</a>
                                 <a href="/sentiment-flow/dashboard" class="df-submenu-item">Sentiment & Flow</a>
                                 <a href="/derivatives/exchange-inflow-cdd" class="df-submenu-item">₿ Exchange Inflow CDD</a>

                             </div>
                         </li>
                     </ul>
                 </div>

                 <div class="df-sidebar-group">
                     <div class="df-sidebar-group-label" x-show="!sidebarCollapsed">Watchlist</div>
                     <ul class="df-sidebar-menu">
                         <li class="df-sidebar-menu-item">
                             <a href="#" class="df-sidebar-menu-button">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M3 3v18h18" />
                                     <path d="M7 12l3-3 3 3 5-5" />
                                 </svg>
                                 <span>BTC · Binance</span>
                             </a>
                         </li>
                         <li class="df-sidebar-menu-item">
                             <a href="#" class="df-sidebar-menu-button">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M3 3v18h18" />
                                     <path d="M7 12l3-3 3 3 5-5" />
                                 </svg>
                                 <span>ETH · Coinbase</span>
                             </a>
                         </li>
                         <li class="df-sidebar-menu-item">
                             <a href="#" class="df-sidebar-menu-button">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M3 3v18h18" />
                                     <path d="M7 12l3-3 3 3 5-5" />
                                 </svg>
                                 <span>NASDAQ Futures</span>
                             </a>
                         </li>
                         <li class="df-sidebar-menu-item">
                             <button class="df-sidebar-menu-button text-muted">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <circle cx="12" cy="12" r="1" />
                                     <circle cx="19" cy="12" r="1" />
                                     <circle cx="5" cy="12" r="1" />
                                 </svg>
                                 <span>Manage lists</span>
                             </button>
                         </li>
                     </ul>
                 </div> --}}
             </div>
         </aside>

         <!-- Main Content Area -->
         <main class="df-sidebar-inset">
             <!-- Toolbar -->
             <header class="df-toolbar">
                 <div class="d-flex align-items-center gap-3">
                     <!-- Mobile Sidebar Toggle -->
                     <button class="btn-df-ghost d-md-none" @click="toggleSidebar()">
                         <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                             <rect x="3" y="6" width="18" height="2" />
                             <rect x="3" y="11" width="18" height="2" />
                             <rect x="3" y="16" width="18" height="2" />
                         </svg>
                     </button>

                     <!-- Desktop Sidebar Toggle -->
                     <button class="btn-df-ghost d-none d-md-block" @click="sidebarCollapsed = !sidebarCollapsed; openSubmenus = {}">
                         <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                             <rect x="3" y="6" width="18" height="2" />
                             <rect x="3" y="11" width="18" height="2" />
                             <rect x="3" y="16" width="18" height="2" />
                         </svg>
                     </button>

                     <div class="d-flex flex-column">
                         <h1 class="h6 mb-0 fw-semibold">Dashboard</h1>
                         {{-- <p class="small mb-0" style="color: var(--muted-foreground);">BTCUSD · 1D · Bitstamp</p> --}}
                     </div>
                 </div>

                 <div class="d-flex align-items-center gap-2">
                     <!-- Global manual refresh -->
                     <!-- <button class="btn btn-outline-primary d-flex align-items-center gap-2" type="button" onclick="window.location.reload()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.137-3.36L23 10"/>
                            <path d="M20.49 15a9 9 0 0 1-14.137 3.36L1 14"/>
                        </svg>
                        <span>Refresh</span>
                    </button> -->

                     <!-- Theme Toggle -->
                     <button class="btn-df-ghost" @click="$dispatch('theme-toggle')">
                         <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                             <circle cx="12" cy="12" r="5" />
                             <path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
                         </svg>
                     </button>

                     <!-- Profile Dropdown -->
                     <div class="profile-dropdown-container" x-data="{ profileDropdownOpen: false }">
                         <!-- Avatar Button -->
                         <button class="profile-avatar-btn" @click="profileDropdownOpen = !profileDropdownOpen">
                             <img src="/images/avatar.svg"
                                 alt="User Avatar"
                                 class="avatar-image"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                             <div class="avatar-fallback" style="display: none;">
                                 <span>AA</span>
                             </div>
                         </button>

                         <!-- Dropdown Menu -->
                         <div class="profile-dropdown-menu"
                             x-show="profileDropdownOpen"
                             x-transition:enter="profile-dropdown-enter"
                             x-transition:enter-start="profile-dropdown-enter-start"
                             x-transition:enter-end="profile-dropdown-enter-end"
                             x-transition:leave="profile-dropdown-leave"
                             x-transition:leave-start="profile-dropdown-leave-start"
                             x-transition:leave-end="profile-dropdown-leave-end"
                             @click.away="profileDropdownOpen = false"
                             style="display: none;">
                             <!-- Profile Link -->
                             <a href="{{ route('profile.show') }}" class="dropdown-item">
                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                     <circle cx="12" cy="7" r="4" />
                                 </svg>
                                 Profile
                             </a>

                             <!-- Divider -->
                             <div class="dropdown-divider"></div>

                             <!-- Logout Link -->
                             <form method="POST" action="{{ route('logout') }}">
                                 @csrf
                                 <button type="submit" class="dropdown-item">
                                     <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                         <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                         <polyline points="16,17 21,12 16,7" />
                                         <line x1="21" y1="12" x2="9" y2="12" />
                                     </svg>
                                     Logout
                                 </button>
                             </form>
                         </div>
                     </div>
                 </div>
             </header>

             <!-- Page Content -->
             <div class="flex-grow-1 p-4 fade-in">
                 @yield('content')
             </div>
         </main>
     </div>

     @livewireScripts

     {{-- Ensure Alpine.js is available --}}
     <script>
         // Check if Alpine is loaded, if not wait for it
         if (!window.Alpine) {
             console.warn('Alpine.js not immediately available, waiting for Livewire...');
             document.addEventListener('livewire:init', () => {
                 console.log('Livewire initialized, Alpine should be available now');
             });
         } else {
             console.log('Alpine.js is ready!');
         }
     </script>

     {{-- Additional Scripts from Views --}}
     @yield('scripts')
 </body>

 </html>
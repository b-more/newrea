<nav class="bg-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <img class="h-8 w-8" src="{{ asset('logo.png') }}" alt="Logo">
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="{{ route('dashboard.payments.index') }}"
                            class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium
                            {{ request()->routeIs('dashboard.payments.*') ? 'bg-gray-900 text-white' : '' }}">
                            Payments
                        </a>
                        <a href="{{ route('dashboard.float.index') }}"
                            class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium
                            {{ request()->routeIs('dashboard.float.*') ? 'bg-gray-900 text-white' : '' }}">
                            Float Transactions
                        </a>
                        <a href="{{ route('dashboard.sales.index') }}"
                            class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium
                            {{ request()->routeIs('dashboard.sales.*') ? 'bg-gray-900 text-white' : '' }}">
                            Sales
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden">
                <button type="button" class="bg-gray-800 inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white">
                    <span class="sr-only">Open main menu</span>
                    <!-- Menu icon -->
                    <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div class="md:hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="{{ route('dashboard.payments.index') }}"
                class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium
                {{ request()->routeIs('dashboard.payments.*') ? 'bg-gray-900 text-white' : '' }}">
                Payments
            </a>
            <a href="{{ route('dashboard.float.index') }}"
                class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium
                {{ request()->routeIs('dashboard.float.*') ? 'bg-gray-900 text-white' : '' }}">
                Float Transactions
            </a>
            <a href="{{ route('dashboard.sales.index') }}"
                class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium
                {{ request()->routeIs('dashboard.sales.*') ? 'bg-gray-900 text-white' : '' }}">
                Sales
            </a>
        </div>
    </div>
</nav>

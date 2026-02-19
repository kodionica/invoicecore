@props(['user' => null])

<div class="user-profile-actions">
    @auth
        <div class="logged-in-actions">
            <button type="button" class="btn btn--link btn--icon btn--toggler" data-toggle="#user-actions-dropdown">
                {{ $user->display_name }}
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" class="btn__icon">
                    <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <div id="user-actions-dropdown" class="dropdown-menu">
                <a class="dropdown-item" href="{{ route('profile.edit') }}">Podešavanje profila</a>
                <hr class="dropdown-item dropdown-item--sepparator"/>
                <form action="{{ route('logout') }}" method="POST" class="dropdown-item logout-form">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn--link logout-button">Odjava</button>
                </form>
            </div>
        </div>
    @endauth

    @guest
        <div class="guest-actions">
            <a href="{{ route('login') }}" class="btn btn-outline-primary login-link">Prijava</a>
            <a href="{{ route('register') }}" class="btn signup-button">Registracija</a>
        </div>
    @endguest
</div>

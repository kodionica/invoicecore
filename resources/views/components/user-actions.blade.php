<div class="user-profile-actions">
    @auth
        <div class="dropdown text-end">
            <a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                {{ $user->first_name ?? '' }}
            </a>
            <ul class="dropdown-menu text-small">
                {{--                <li><a class="dropdown-item" href="{{ route('settings.invoice.edit') }}">Podešavanja firme</a></li>--}}
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Podešavanja profila</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <form action="{{ route('logout') }}" method="POST" class="dropdown-item logout-form">
                        @csrf
                        @method('DELETE')
                        <button class="btn p-0 text-danger logout-button">Odjava</button>
                    </form>
                </li>
            </ul>
        </div>
    @endauth

    @guest
        <div class="guest-actions space-x-4">
            <a href="{{ route('login') }}" class="btn btn-outline-primary login-link">Prijava</a>
            <a href="{{ route('register') }}" class="btn signup-button">Registracija</a>
        </div>
    @endguest
</div>

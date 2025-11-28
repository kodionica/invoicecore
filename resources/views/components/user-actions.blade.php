<div class="user-profile-actions">
    @auth
        <div class="dropdown text-end">
            <a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="https://github.com/mdo.png" alt="mdo" width="32" height="32" class="rounded-circle">
            </a>
            <ul class="dropdown-menu text-small" style="">
                <li><a class="dropdown-item" href="#">New project...</a></li>
                <li><a class="dropdown-item" href="#">Settings</a></li>
                <li><a class="dropdown-item" href="#">Profile</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <form action="{{ route('logout') }}" method="POST" class="dropdown-item logout-form">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn--danger logout-button">Log out</button>
                    </form>
                </li>
            </ul>
        </div>
    @endauth

    @guest
        <div class="guest-actions space-x-4">
            <a href="{{ route('login') }}" class="btn btn-outline-primary login-link">Log in</a>
            <a href="{{ route('register') }}" class="btn signup-button">Sign up</a>
        </div>
    @endguest
</div>

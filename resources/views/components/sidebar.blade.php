<aside class="sidebar">
    <ul class="nav nav--sidebar">
        <li class="nav__item">
            <a class="nav__link current" href="{{ route('home') }}">Kontrolna tabla</a>
        </li>
        <li class="nav__item">
            <button class="nav__link btn btn--link" data-toggle="#company-collapse">Firme</button>
            <ul class="nav__sub-item" id="company-collapse">
                <li><a href="{{ route('companies.create') }}" class="nav__link">Dodaj</a></li>
                <li><a href="{{ route('companies.index') }}" class="nav__link">Pogledaj sve</a></li>
            </ul>
        </li>
        <li class="nav__item">
            <button class="nav__link btn btn--link" data-toggle="#clients-collapse">Klijenti</button>
            <ul class="nav__sub-item" id="clients-collapse">
                <li><a href="{{ route('clients.create') }}" class="nav__link">Dodaj</a></li>
                <li><a href="{{ route('clients.index') }}" class="nav__link">Pogledaj sve</a></li>
            </ul>
        </li>
        <li class="nav__item">
            <button class="nav__link btn btn--link" data-toggle="#invoices-collapse">Fakture</button>
            <ul class="nav__sub-item" id="invoices-collapse">
                <li><a href="{{ route('invoices.create') }}" class="nav__link">Dodaj</a></li>
                <li><a href="{{ route('invoices.index') }}" class="nav__link">Pogledaj sve</a></li>
            </ul>
        </li>
    </ul>
</aside>

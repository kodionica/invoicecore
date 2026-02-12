<ul class="sidebar-nav nav flex-column">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('home') }}">Kontrolna tabla</a>
    </li>
    <li class="nav-item">
        <button class="btn btn-toggle" data-bs-toggle="collapse" data-bs-target="#company-collapse" aria-expanded="true">Firme</button>
        <div class="collapse sub-item" id="company-collapse">
            <ul class="btn-toggle-nav list-unstyled">
                <li><a href="{{ route('companies.create') }}" class="nav-link">Dodaj</a></li>
                <li><a href="{{ route('companies.index') }}" class="nav-link">Pogledaj sve</a></li>
            </ul>
        </div>
    </li>
    <li class="nav-item">
        <button class="btn btn-toggle" data-bs-toggle="collapse" data-bs-target="#clients-collapse" aria-expanded="true">Klijenti</button>
        <div class="collapse sub-item" id="clients-collapse">
            <ul class="btn-toggle-nav list-unstyled">
                <li><a href="{{ route('client.create') }}" class="nav-link">Dodaj</a></li>
                <li><a href="{{ route('client.index') }}" class="nav-link">Pogledaj sve</a></li>
            </ul>
        </div>
    </li>
    <li class="nav-item">
        <button class="btn btn-toggle" data-bs-toggle="collapse" data-bs-target="#invoices-collapse" aria-expanded="true">Fakture</button>
        <div class="collapse sub-item" id="invoices-collapse">
            <ul class="btn-toggle-nav list-unstyled">
                {{--                <li><a href="{{ route('invoices.create') }}" class="nav-link">Add</a></li>--}}
                {{--                <li><a href="{{ route('invoices.index') }}" class="nav-link">View</a></li>--}}
            </ul>
        </div>
    </li>
</ul>

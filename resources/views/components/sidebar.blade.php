<ul class="sidebar-nav nav flex-column">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('home') }}">Dashboard</a>
    </li>
    <li class="nav-item">
        <button class="btn btn-toggle" data-bs-toggle="collapse" data-bs-target="#clients-collapse" aria-expanded="true">Clients</button>
        <div class="collapse sub-item" id="clients-collapse">
            <ul class="btn-toggle-nav list-unstyled">
                <li><a href="{{ route('clients.create') }}" class="nav-link">Add</a></li>
                <li><a href="{{ route('clients.index') }}" class="nav-link">View</a></li>
            </ul>
        </div>
    </li>
    <li class="nav-item">
        <button class="btn btn-toggle" data-bs-toggle="collapse" data-bs-target="#invoices-collapse" aria-expanded="true">Invoices</button>
        <div class="collapse sub-item" id="invoices-collapse">
            <ul class="btn-toggle-nav list-unstyled">
                <li><a href="{{ route('invoices.create') }}" class="nav-link">Add</a></li>
                <li><a href="{{ route('invoices.index') }}" class="nav-link">View</a></li>
            </ul>
        </div>
    </li>
</ul>

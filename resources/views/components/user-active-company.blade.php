@props(['company', 'user'])

<div class="user-active-company">
    <div class="dropdown text-end">
        <a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            {{ $company->name }}
        </a>
        <form action="{{ route('companies.switch') }}" method="POST" class="dropdown-item">
            @csrf

            <ul class="dropdown-menu text-small">
                @foreach($user->companies as $user_company)
                    @continue($user_company->id === $company->id)
                    <li>
                        <input type="radio" name="company_id" id="company_id" class="dropdown-item" value="{{ $user_company->id }}" onchange="this.form.submit()"/>
                        <label for="company_id">{{ $user_company->name }}</label>
                    </li>
                @endforeach
            </ul>
        </form>
    </div>
</div>

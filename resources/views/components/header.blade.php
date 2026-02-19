<header id="header">
    <div class="container">
        @if(auth()->user() && auth()->user()->activeCompany)
            <x-user-active-company :company="auth()->user()->activeCompany" :user="auth()->user()"/>
        @endif

        <x-user-actions :user="auth()->user()"/>
    </div>
</header>

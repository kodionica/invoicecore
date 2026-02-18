<header id="header" class="border-bottom">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-lg-start">
            <x-logo/>

            @if(auth()->user() && auth()->user()->activeCompany)
                <x-user-active-company :company="auth()->user()->activeCompany" :user="auth()->user()"/>
            @endif

            <x-user-actions :user="auth()->user()"/>
        </div>
    </div>
</header>

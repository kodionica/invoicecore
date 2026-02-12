<header id="header" class="border-bottom">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-lg-start">
            <x-logo/>

            <x-user-active-company :company="auth()->user()->activeCompany" :user="auth()->user()"/>

            <x-user-actions :user="auth()->user()"/>
        </div>
    </div>
</header>

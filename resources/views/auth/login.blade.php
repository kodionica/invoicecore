<x-layouts.layout-2>
    @section('page-style')
        @vite('resources/css/register.scss')
    @endsection
    <div class="row content-centered">
        <div class="col-sm-8 col-lg-6">
            <x-forms.form action="{{ route('login.store') }}" method="POST" class="auth-form auth-form--login">
                <x-forms.input name="login" label="Korisničko ime ili Email" :label_hidden="true" required autocomplete="username"/>
                <x-forms.input name="password" label="Šifra" type="password" :label_hidden="true" required autocomplete="current-password"/>
                <x-forms.checkbox name="remember" label="Zapamti me"/>

                <div class="form-group form-group--actions">
                    <button type="submit" class="btn btn--primary">Prijavi se</button>
                    <a href="{{ route('register') }}">Registruj se</a>
                </div>
            </x-forms.form>
        </div>
    </div>
</x-layouts.layout-2>

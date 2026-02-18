<x-layouts.layout-2>
    @section('page-style')
        @vite('resources/css/register.scss')
    @endsection

    <div class="row content-centered">
        <div class="col-sm-8 col-lg-6">
            <x-forms.form action="{{ route('register.store') }}" method="POST" class="auth-form auth-form--register">
                <x-forms.input name="first_name" label="Ime" :label_hidden="true" wrapper_class="columns-6"/>
                <x-forms.input name="last_name" label="Prezime" :label_hidden="true" wrapper_class="columns-6"/>
                <x-forms.input name="email" label="Email" type="email" :label_hidden="true" wrapper_class="columns-6"/>
                <x-forms.input name="phone" label="Telefon" type="tel" :label_hidden="true" wrapper_class="columns-6"/>
                <x-forms.input name="password" label="Šifra" type="password" autocomplete="new-password" aria-describedby="passwordHelpBlock" :label_hidden="true" wrapper_class="columns-6">
                    <div id="passwordHelpBlock" class="form-text">
                        Šifra mora biti minimum dužine 5 karaktera
                    </div>
                </x-forms.input>
                <x-forms.input name="password_confirmation" label="Potvrdi šifru" type="password" :label_hidden="true" wrapper_class="columns-6"/>

                <div class="form-group form-group--actions">
                    <button type="submit" class="btn btn--primary">Registruj se</button>
                    <a href="{{ route('login') }}">Prijavi se</a>
                </div>
            </x-forms.form>
        </div>
    </div>
</x-layouts.layout-2>

<x-layouts.layout>
    <x-page-header heading="Registruj se"/>

    <x-forms.form action="{{ route('register.store') }}" method="POST">
        <x-forms.input name="first_name" label="Ime"/>
        <x-forms.input name="last_name" label="Prezime"/>
        <x-forms.input name="email" label="Email" type="email"/>
        <x-forms.input name="phone" label="Telefon" type="tel"/>
        <x-forms.input name="password" label="Šifra" type="password" autocomplete="new-password" aria-describedby="passwordHelpBlock">
            <div id="passwordHelpBlock" class="form-text">
                Šifra mora biti minimum dužine 5 karaktera
            </div>
        </x-forms.input>
        <x-forms.input name="password_confirmation" label="Potvrdi šifru" type="password"/>

        <div class="form-group columns-12">
            <button type="submit" class="btn btn-primary">Registruj se</button>
        </div>
    </x-forms.form>
</x-layouts.layout>

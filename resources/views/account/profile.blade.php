<x-layouts.layout>
    <x-page-header heading="Podešavanja profile"/>

    <x-forms.form action="{{ route('profile.update') }}" method="PATCH">
        <x-forms.input name="first_name" label="Ime" value="{{ $user->first_name }}"/>
        <x-forms.input name="last_name" label="Prezime" value="{{ $user->last_name }}"/>
        <x-forms.input name="email" label="Email" type="email" value="{{ $user->email }}"/>
        <x-forms.input name="username" label="Korisničko ime" value="{{ $user->username }}"/>
        <x-forms.input name="phone" label="Telefon" type="tel" value="{{ $user->phone }}"/>

        <div class="form-group columns-12">
            <button type="submit" class="btn btn-primary">Sačuvaj</button>
            <a href="{{ route('password.edit') }}">Promeni šifru</a>
        </div>

    </x-forms.form>
</x-layouts.layout>

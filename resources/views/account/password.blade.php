<x-layouts.layout>
    <x-page-header heading="Promeni šifru"/>

    <x-forms.form action="{{ route('password.update') }}" method="PATCH">
        <x-forms.input name="current_password" label="Trenutna šifra" type="password" autocomplete="current-password" wrapper_class="columns-12"/>
        <x-forms.input name="password" label="Nova šifra" type="password" autocomplete="new-password">
            <div id="passwordHelpBlock" class="form-text">
                Šifra mora biti minimum dužine 5 karaktera
            </div>
        </x-forms.input>
        <x-forms.input name="password_confirmation" label="Potvrdi šifru" type="password" autocomplete="new-password"/>

        <div class="form-group columns-12">
            <button type="submit" class="btn btn-primary">Promeni šifru</button>
        </div>
    </x-forms.form>
</x-layouts.layout>

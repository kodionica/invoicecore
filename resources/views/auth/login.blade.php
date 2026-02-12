<x-layouts.layout-2>
    <x-page-header heading="Prijava"/>

    <x-forms.form action="{{ route('login.store') }}" method="POST">
        <x-forms.input name="login" label="Korisničko ime ili Email" required/>
        <x-forms.input name="password" label="Šifra" type="password" required/>
        <x-forms.checkbox name="remember" label="Zapamti me"/>

        <div class="form-group columns-12">
            <button type="submit" class="btn btn-primary">Prijavi se</button>
        </div>
    </x-forms.form>
</x-layouts.layout-2>

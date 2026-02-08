<x-layouts.layout>
    <x-page-header heading="Prijava"/>

    <x-forms.form action="{{ route('login.store') }}" method="POST" class="my-5 col-md-8 mx-auto">
        <div class="form-row">
            <div class="form-floating">
                <input type="email" name="email" class="form-control" id="email" required placeholder="Email" autocomplete="email">
                <label for="email">Email</label>
            </div>

            <div class="form-floating">
                <input type="password" name="password" class="form-control" id="password" required placeholder="Šifra" autocomplete="current-password">
                <label for="password">Šifra</label>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">Zapamti me</label>
            </div>

            <div class="form-group columns-12">
                <button type="submit" class="btn btn-primary">Prijavi se</button>
            </div>
        </div>
    </x-forms.form>
</x-layouts.layout>

<x-layouts.layout>
    <x-page-header heading="Registruj se"/>

    <x-forms.form action="{{ route('register.store') }}" method="POST" class="my-5 col-md-8 mx-auto">

        <div class="form-row">
            <div class="form-floating ">
                <input type="text" name="first_name" class="form-control" id="first_name" placeholder="Ime" autocomplete="first name">
                <label for="first_name">Ime</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="last_name" class="form-control" id="last_name" placeholder="Prezime" autocomplete="last name">
                <label for="last_name">Prezime</label>
            </div>
            <div class="form-floating ">
                <input type="email" name="email" class="form-control" id="email" required placeholder="Email" autocomplete="email">
                <label for="email">Email</label>
            </div>
            <div class="form-floating ">
                <input type="tel" name="phone" class="form-control" id="phone" placeholder="Telefon" autocomplete="phone">
                <label for="phone">Telefon</label>
            </div>
            <div class="form-floating ">
                <input type="password" name="password" class="form-control" id="password" required placeholder="Šifra" autocomplete="new-password" aria-describedby="passwordHelpBlock">
                <label for="password">Šifra</label>
                <div id="passwordHelpBlock" class="form-text">
                    Šifra mora biti minimum dužine 5 karaktera
                </div>
            </div>
            <div class="form-floating ">
                <input type="password" name="password_confirmation" class="form-control" id="password_confirmation" required placeholder="Potvrdi šifru" autocomplete="new-password">
                <label for="password_confirmation">Potvrdi šifru</label>
            </div>

            <div class="form-group columns-12">
                <button type="submit" class="btn btn-primary">Registruj se</button>
            </div>
        </div>
    </x-forms.form>
</x-layouts.layout>

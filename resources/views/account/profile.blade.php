<x-layouts.layout>
    <x-page-header heading="Podešavanja profile"/>

    <x-forms.form action="{{ route('profile.update') }}" method="POST" class="my-5 col-md-8 mx-auto">
        @method('PATCH')

        <div class="form-row">
            <div class="form-floating ">
                <input type="text" name="first_name" class="form-control" id="first_name" placeholder="Ime" autocomplete="first name" value="{{ $user->first_name }}">
                <label for="first_name">Ime</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="last_name" class="form-control" id="last_name" placeholder="Prezime" autocomplete="last name" value="{{ $user->last_name }}">
                <label for="last_name">Prezime</label>
            </div>
            <div class="form-floating ">
                <input type="email" name="email" class="form-control" id="email" required placeholder="Email" autocomplete="email" readonly value="{{ $user->email }}">
                <label for="email">Email</label>
            </div>
            <div class="form-floating ">
                <input type="tel" name="phone" class="form-control" id="phone" placeholder="Telefon" autocomplete="phone" value="{{ $user->phone }}">
                <label for="phone">Telefon</label>
            </div>

            <div class="form-group columns-12">
                <button type="submit" class="btn btn-primary">Sačuvaj</button>
            </div>

            <div class="form-group columns-12">
                <a href="{{ route('password.edit') }}">Promeni šifru</a>
            </div>
        </div>
    </x-forms.form>
</x-layouts.layout>

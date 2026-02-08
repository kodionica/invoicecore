<x-layouts.layout>
    <x-page-header heading="Promeni šifru"/>

    <x-forms.form action="{{ route('password.update') }}" method="POST" class="my-5 col-md-8 mx-auto">
        @method('PATCH')

        <div class="form-row">
            <div class="form-floating columns-12">
                <input type="password"
                       name="current_password"
                       class="form-control {{ $errors->first('current_password') ? 'is-invalid' : '' }}"
                       required
                       id="current_password"
                       placeholder="Potvrdi šifru"
                       autocomplete="current-password">
                <label for="current_password">Trenutna šifra</label>
                <x-forms.error :error="$errors->first('current_password')"/>
            </div>
            <div class="form-floating columns-12">
                <input type="password"
                       name="password"
                       class="form-control {{ $errors->first('password') ? 'is-invalid' : '' }}"
                       required
                       id="password"
                       placeholder="Šifra"
                       autocomplete="new-password"
                       aria-describedby="passwordHelpBlock">
                <label for="password">Šifra</label>
                <x-forms.error :error="$errors->first('password')"/>
                <div id="passwordHelpBlock" class="form-text">
                    Šifra mora biti minimum dužine 5 karaktera
                </div>
            </div>
            <div class="form-floating columns-12">
                <input type="password" name="password_confirmation" class="form-control" id="password_confirmation" placeholder="Potvrdi šifru" autocomplete="new-password">
                <label for="password_confirmation">Potvrdi šifru</label>
            </div>

            <div class="form-group columns-12">
                <button type="submit" class="btn btn-primary">Promeni šifru</button>
            </div>
        </div>
    </x-forms.form>
</x-layouts.layout>

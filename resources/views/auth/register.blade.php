<x-layout>
    <x-forms.form action="{{ route('register.store') }}" method="POST" class="my-5 col-md-8 mx-auto">
        <h1 class="h3 mb-3 fw-normal">Register</h1>

        <div class="row">
            <div class="col-md-6">
                <x-forms.input label="Name" name="name" required="true" class="mb-2"/>
            </div>
            <div class="col-md-6">
                <x-forms.input label="Email" name="email" type="email" required="true" class="mb-2"/>
            </div>
            <div class="col-md-6">
                <x-forms.input label="Password" name="password" type="password" required="true" class="mb-2"/>
            </div>
            <div class="col-md-6">
                <x-forms.input label="Password Confirm" name="password_confirm" type="password" required="true" class="mb-2"/>
            </div>
        </div>

        <x-forms.button>Register</x-forms.button>
    </x-forms.form>
</x-layout>

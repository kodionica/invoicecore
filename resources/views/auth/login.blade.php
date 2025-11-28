<x-layout>
    <x-forms.form action="{{ route('login.store') }}" method="POST" class="my-5 col-md-8 mx-auto">
        <h1 class="h3 mb-3 fw-normal">Log in</h1>

        <x-forms.input label="Email" name="email" type="email" required="true" class="mb-2"/>
        <x-forms.input label="Password" name="password" type="password" required="true" class="mb-2"/>
        <x-forms.checkbox label="Remember me" name="remember" wrapper_class="mb-2"/>

        <x-forms.button>Register</x-forms.button>
    </x-forms.form>
</x-layout>

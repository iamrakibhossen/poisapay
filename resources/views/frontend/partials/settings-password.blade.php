{{-- Settings › Password tab — change the account sign-in password.
     Expects (from the settings view scope): $errors. --}}
<x-settings.section :title="__('Password')" :description="__('Change the password you use to sign in.')">
    <form method="POST" action="{{ route('settings.password') }}" class="max-w-md space-y-4">
        @csrf @method('PUT')
        <x-ui.input type="password" :label="__('Current password')" name="current_password"
            autocomplete="current-password" :error="$errors->first('current_password')" />
        <x-ui.input type="password" :label="__('New password')" name="password"
            autocomplete="new-password" :hint="__('At least 8 characters.')" :error="$errors->first('password')" />
        <x-ui.input type="password" :label="__('Confirm new password')" name="password_confirmation"
            autocomplete="new-password" />
        <x-ui.button type="submit" variant="dark" icon="key">{{ __('Update password') }}</x-ui.button>
    </form>
</x-settings.section>

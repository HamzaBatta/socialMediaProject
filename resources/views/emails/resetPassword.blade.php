<x-mail::message>
    # Password Reset Code

    Your 4-digit reset code is: **{{ $code }}**

    Use this code to reset your password.

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>

<x-mail::message>
    # Change Email Code

    Your 4-digit reset code is: **{{ $code }}**

    Use this code to change your email.

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>

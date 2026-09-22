# Plain HTML forms

Use `HoneypotService` for forms submitted to a Laravel controller. The Livewire `<x-honeypot />` component does not generate the signed token needed here.

Generate the fields when rendering the form:

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;

public function create(HoneypotService $honeypot)
{
    return view('contact', [
        'hp' => $honeypot->generate(),
        'baitName' => config('livewire-honeypot.field_name', 'hp_website'),
    ]);
}
```

Add the bait and token alongside your regular fields:

```blade
<form method="POST" action="/contact">
    @csrf
    <div hidden aria-hidden="true">
        <input type="text" name="{{ $baitName }}" value="{{ $hp[$baitName] }}"
               tabindex="-1" autocomplete="off"
               data-1p-ignore="true" data-lpignore="true"
               data-bwignore="true" data-form-type="other">
    </div>
    <input type="hidden" name="hp_token" value="{{ $hp['hp_token'] }}">

    {{-- Your regular fields and submit button. --}}
</form>
```

Validate before processing the request:

```php
use Illuminate\Http\Request;

public function store(Request $request, HoneypotService $honeypot)
{
    $honeypot->validate($request->only(
        config('livewire-honeypot.field_name', 'hp_website'),
        'hp_token',
        'hp_js',
    ));

    // Validate and process the rest of the form.
    return redirect()->back()->with('success', 'Sent!');
}
```

`validate($data, minimumSeconds: 2)` overrides the minimum waiting time. Empty bait values converted to `null` by Laravel's middleware are accepted; a missing bait field is rejected.

Tokens require `APP_KEY`, expire after one hour, and can be reused within that period. Keep CSRF protection and rate limiting. When rotating application keys, retain previous keys in `APP_PREVIOUS_KEYS` if open forms should continue working.

Do not submit `hp_started_at` or build tokens yourself. The signed token contains the trusted start time; `generate()` returns the separate timestamp only for compatibility. Render a fresh token when redisplaying a form instead of restoring it from old input.

This recipe uses the default disabled JS verification setting. If you enable `require_js_verification`, your own JavaScript must populate and submit `hp_js` with a nonempty string. The `randomize_field_name` option applies to the Livewire trait, not to this service recipe.

[Back to the README](../README.md)

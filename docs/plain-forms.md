# Plain HTML forms

Use `<x-honeypot />` and `HoneypotService` for forms submitted to a Laravel controller. Outside a Livewire component with `HasHoneypot`, the component renders the bait field and a signed token instead of Livewire bindings.

Add the component inside the form:

```blade
<form method="POST" action="/contact">
    @csrf
    <x-honeypot />

    {{-- Your regular fields and submit button. --}}
</form>
```

Validate before processing the request:

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Http\Request;

public function store(Request $request, HoneypotService $honeypot)
{
    $honeypot->validate($request->all());

    // Validate and process the rest of the form.
    return redirect()->back()->with('success', 'Sent!');
}
```

A rejected submission redirects back with a validation error, which the component shows beside the form when it is rendered again. The page gets a fresh token on every render, so do not cache it, and do not restore the token from old input.

The bait gets a name derived from the token, such as `referral_3f9a`, that changes with every form and avoids names that browsers autofill. With `randomize_field_name` disabled, it uses `field_name`. Errors are always reported under `field_name`. A `field-name` attribute must match one of those two names, because validation only looks there, and `wire:model` attributes are ignored in a plain form.

This also works for a plain form inside a Livewire component that does not use `HasHoneypot`, such as a newsletter form posting to a controller. Inside a component with the trait, `<x-honeypot />` always renders the Livewire version.

To answer bots with a fake success instead of an error, use `isCaught()` with the same data. It never calls the configured responder, and it remembers caught tokens for an hour so a retry with the same token is caught too. An expired form still throws the normal validation error, so the visitor sees it and can reload. See [silent rejection](advanced.md#silent-rejection).

```php
if ($honeypot->isCaught($request->all())) {
    return redirect()->back()->with('success', 'Sent!');
}
```

`validate($data, minimumSeconds: 2)` overrides the minimum waiting time. Empty bait values converted to `null` by Laravel's middleware are accepted; a missing bait field is rejected.

Tokens require `APP_KEY`, expire after one hour, and can be reused within that period. Keep CSRF protection and rate limiting. When rotating application keys, retain previous keys in `APP_PREVIOUS_KEYS` if open forms should continue working.

## JavaScript verification

With `require_js_verification` enabled, the component adds an empty `hp_js` input and a small inline script that fills it. It needs neither Livewire nor Alpine. Under a Content Security Policy, the script uses the same nonce as the component's stylesheet, so allow that nonce in `script-src` as well. A form inserted with `innerHTML` does not run the script; populate `hp_js` with your own JavaScript in that case.

## Rendering the fields yourself

If you cannot use the Blade component, generate the token when rendering the form:

```php
public function create(HoneypotService $honeypot)
{
    $hp = $honeypot->generate();

    return view('contact', [
        'hp' => $hp,
        'baitName' => $honeypot->baitName($hp['hp_token']),
    ]);
}
```

Render the bait and token alongside your regular fields, and validate as above:

```blade
<form method="POST" action="/contact">
    @csrf
    <div hidden aria-hidden="true">
        <input type="text" name="{{ $baitName }}" value=""
               tabindex="-1" autocomplete="off"
               data-1p-ignore="true" data-lpignore="true"
               data-bwignore="true" data-form-type="other">
    </div>
    <input type="hidden" name="hp_token" value="{{ $hp['hp_token'] }}">

    {{-- Your regular fields and submit button. --}}
</form>
```

The `hidden` attribute is the simplest way to hide the field, but some bots skip fields hidden that way. The component hides it like screen-reader-only text instead. If you enable `require_js_verification`, your own JavaScript must populate and submit `hp_js` with a nonempty string.

The service finds the bait under the derived name again, including for tokens signed with a key in `APP_PREVIOUS_KEYS`. Forms that render `field_name` as the bait name keep working. Pass the whole request, or include the derived name if you pass only selected fields.

Do not submit `hp_started_at` or build tokens yourself. The signed token contains the trusted start time; `generate()` returns the separate timestamp only for compatibility.

[Back to the README](../README.md)

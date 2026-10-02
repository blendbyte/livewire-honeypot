{{-- Honeypot component. Usage: <x-honeypot /> --}}
{{-- In a HasHoneypot component the bait binds to Livewire and the HTML name comes from hp_field_name. --}}
{{-- Anywhere else it renders a plain form: the bait plus a signed hp_token for HoneypotService::validate(). --}}
@props(['fieldName' => null, 'errorKey' => null, 'nonce' => null])
@php
    $honeypotService = app(\Blendbyte\LivewireHoneypot\Services\HoneypotService::class);
    $honeypotComponent = isset($__livewire) && in_array(\Blendbyte\LivewireHoneypot\Traits\HasHoneypot::class, class_uses_recursive($__livewire), true)
        ? $__livewire
        : null;
    // A plain form, including one inside a Livewire component without the trait, posts to a controller.
    $plainToken = $honeypotComponent === null ? $honeypotService->token() : null;
    // Livewire re-renders would swap in a new token, restarting the timer and clearing the JS marker.
    $ignoreLivewireUpdates = $plainToken !== null && isset($__livewire);
    $requiresJsVerification = $honeypotComponent?->isHoneypotJsVerificationRequired()
        ?? \Blendbyte\LivewireHoneypot\HoneypotConfig::get('require_js_verification');
    $cspNonce = $nonce ?? \Illuminate\Support\Facades\Vite::cspNonce();
    $staticFieldName = $honeypotComponent?->getHoneypotFieldName()
        ?? \Blendbyte\LivewireHoneypot\HoneypotConfig::get('field_name');
    $displayName = $fieldName ?? match (true) {
        $honeypotComponent !== null => $honeypotComponent->hp_field_name ?: $staticFieldName,
        (bool) \Blendbyte\LivewireHoneypot\HoneypotConfig::get('randomize_field_name') => $honeypotService->baitName($plainToken),
        default => $staticFieldName,
    };
    // Visually hidden like screen-reader-only text, under a class that does not name the honeypot.
    $wrapperClass = $honeypotService->wrapperClass();
    $modelAttributes = $plainToken === null ? $attributes->whereStartsWith('wire:model') : null;
    $errorKeys = [$errorKey ?? $modelAttributes?->first() ?? $staticFieldName, 'hp_started_at', 'hp_token'];
    $errorMessage = null;

    if (isset($errors)) {
        foreach ($errorKeys as $key) {
            if ($errors->has($key)) {
                $errorMessage = $errors->first($key);
                break;
            }
        }
    }
@endphp
<div class="{{ $wrapperClass }}" aria-hidden="true"{{ $ignoreLivewireUpdates ? ' wire:ignore' : '' }}>
    <label>
        <span>{{ __('livewire-honeypot::validation.honeypot_label') }}</span>
        <input type="text"
               name="{{ $displayName }}"
               @if($plainToken !== null)
                   value=""
               @elseif($modelAttributes->isNotEmpty())
                   {{ $modelAttributes }}
               @else
                   wire:model="{{ $staticFieldName }}"
               @endif
               tabindex="-1"
               autocomplete="off"
               data-1p-ignore="true"
               data-lpignore="true"
               data-bwignore="true"
               data-form-type="other" />
    </label>
    @if($plainToken !== null)
    <input type="hidden" name="hp_token" value="{{ $plainToken }}" />
    @endif
    @if($requiresJsVerification && $plainToken !== null)
    <input type="hidden" name="hp_js" value="" />
    <script @if($cspNonce !== null) nonce="{{ $cspNonce }}" @endif>document.currentScript.previousElementSibling.value = '1';</script>
    @elseif($requiresJsVerification)
    <input type="hidden"
           name="hp_js"
           wire:model="hp_js"
           wire:key="hp-js-{{ $honeypotComponent->getId() }}-{{ $honeypotComponent->hp_token }}"
           x-data
           x-bind:value="'1'"
           x-init="$dispatch('input', '1')" />
    @endif

    <style @if($cspNonce !== null) nonce="{{ $cspNonce }}" @endif>
        .{{ $wrapperClass }} {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip-path: inset(50%) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
    </style>
</div>
@if($errorMessage !== null)
    <p class="hp-error" role="alert">{{ $errorMessage }}</p>
@endif

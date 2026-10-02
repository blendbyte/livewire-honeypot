{{-- Anonymous honeypot component. Usage: <x-honeypot /> --}}
{{-- In a HasHoneypot component the HTML name comes from hp_field_name; field-name overrides it. --}}
@props(['fieldName' => null, 'errorKey' => null, 'nonce' => null])
@php
    $honeypotComponent = isset($__livewire) && in_array(\Blendbyte\LivewireHoneypot\Traits\HasHoneypot::class, class_uses_recursive($__livewire), true)
        ? $__livewire
        : null;
    $requiresJsVerification = $honeypotComponent?->isHoneypotJsVerificationRequired()
        ?? \Blendbyte\LivewireHoneypot\HoneypotConfig::get('require_js_verification');
    $cspNonce = $nonce ?? \Illuminate\Support\Facades\Vite::cspNonce();
    $staticFieldName = $honeypotComponent?->getHoneypotFieldName()
        ?? \Blendbyte\LivewireHoneypot\HoneypotConfig::get('field_name');
    $displayName = $fieldName ?? (($honeypotComponent?->hp_field_name ?? '') ?: $staticFieldName);
    // Visually hidden like screen-reader-only text, under a class that does not name the honeypot.
    $wrapperClass = app(\Blendbyte\LivewireHoneypot\Services\HoneypotService::class)->wrapperClass();
    $modelAttributes = $attributes->whereStartsWith('wire:model');
    $errorKeys = [$errorKey ?? $modelAttributes->first() ?? $staticFieldName, 'hp_started_at', 'hp_token'];
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
<div class="{{ $wrapperClass }}" aria-hidden="true">
    <label>
        <span>{{ __('livewire-honeypot::validation.honeypot_label') }}</span>
        <input type="text"
               name="{{ $displayName }}"
               @if($modelAttributes->isNotEmpty())
                   {{ $modelAttributes }}
               @else
                   wire:model.lazy="{{ $staticFieldName }}"
               @endif
               tabindex="-1"
               autocomplete="off"
               data-1p-ignore="true"
               data-lpignore="true"
               data-bwignore="true"
               data-form-type="other" />
    </label>
    @if($requiresJsVerification)
    <input type="hidden"
           name="hp_js"
           wire:model="hp_js"
           @if($honeypotComponent)
               wire:key="hp-js-{{ $honeypotComponent->getId() }}-{{ $honeypotComponent->hp_token }}"
           @endif
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

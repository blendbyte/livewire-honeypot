{{-- Anonymous honeypot component. Usage: <x-honeypot /> --}}
{{-- With randomized field name: <x-honeypot :field-name="$hp_field_name" /> --}}
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
    $displayName = $fieldName ?? $staticFieldName;
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
<div class="hp-field" aria-hidden="true">
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
        .hp-field {
            position: absolute !important;
            left: -10000px !important;
            top: auto !important;
            width: 1px !important;
            height: 1px !important;
            overflow: hidden !important;
        }
    </style>
</div>
@if($errorMessage !== null)
    <p class="hp-error" role="alert">{{ $errorMessage }}</p>
@endif

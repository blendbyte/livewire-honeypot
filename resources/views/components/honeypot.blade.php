{{-- Anonymous honeypot component. Usage: <x-honeypot /> --}}
{{-- With randomized field name: <x-honeypot :field-name="$hp_field_name" /> --}}
@props(['fieldName' => null])
@php
    $staticFieldName = config('livewire-honeypot.field_name', 'hp_website');
    $displayName = $fieldName ?? $staticFieldName;
    $modelAttributes = $attributes->whereStartsWith('wire:model');
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
               autocomplete="off" />
    </label>
    @if(config('livewire-honeypot.require_js_verification', false))
    <input type="hidden"
           name="hp_js"
           wire:model="hp_js"
           x-data
           x-init="$el.value = btoa(String(Date.now())); $el.dispatchEvent(new Event('input', {bubbles: true}))" />
    @endif

    <style>
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

{{-- Anonymous honeypot component. Usage: <x-honeypot /> --}}
{{-- With randomized field name: <x-honeypot :field-name="$hp_field_name" /> --}}
@props(['fieldName' => null])
@php
    $staticFieldName = config('livewire-honeypot.field_name', 'hp_website');
    $displayName = $fieldName ?? $staticFieldName;
@endphp
<div class="hp-field" aria-hidden="true">
    <label>
        <span>{{ __('livewire-honeypot::validation.honeypot_label') }}</span>
        <input type="text"
               name="{{ $displayName }}"
               {!! $attributes->whereStartsWith('wire:model')->first() ? '' : "wire:model.lazy={$staticFieldName}" !!}
               tabindex="-1"
               autocomplete="off" />
    </label>
    <input type="hidden" name="hp_started_at" {!! $attributes->whereStartsWith('wire:model')->first() ? '' : 'wire:model=hp_started_at' !!}>
    <input type="hidden" name="hp_token" {!! $attributes->whereStartsWith('wire:model')->first() ? '' : 'wire:model=hp_token' !!}>

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

<?php

use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(fn () => $this->freezeTime());

test('subclasses can keep the original validation and reset signatures', function () {
    $component = Livewire::test(LegacyHoneypotChild::class);
    $token = $component->hp_token;
    $component->assertSet('resetCount', 1);
    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSet('validationCount', 1)
        ->assertSet('resetCount', 2);

    expect($component->hp_token)->not->toBe($token);
    expect($component->hp_started_at)->toBe(now()->getTimestamp());
});

test('inherited validation overrides still enforce timing and accept the original argument', function () {
    $component = Livewire::test(LegacyHoneypotChild::class);
    $component->call('submit')->assertHasErrors('hp_website')->assertSet('submitted', false);
    $this->travel(2)->seconds();

    $component->call('submit', 1)
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSet('validationCount', 2);
});

test('custom model resets honor an inherited reset override', function () {
    $component = Livewire::test(LegacyHoneypotChild::class);
    $component->set('trap', 'spam')->set('hp_website', 'spam');

    $component->call('clearCustomHoneypot')
        ->assertSet('trap', '')
        ->assertSet('hp_website', '')
        ->assertSet('resetCount', 2);
});

class LegacyHoneypotBase extends Component
{
    use HasHoneypot;

    public string $trap = '';
    public bool $submitted = false;

    public function submit(?int $minimumSeconds = null): void
    {
        $this->validateHoneypot($minimumSeconds);
        $this->submitted = true;
        $this->resetHoneypot();
    }

    public function clearCustomHoneypot(): void
    {
        $this->resetHoneypotForModel('trap');
    }

    public function render(): string
    {
        return '<div><x-honeypot /></div>';
    }
}

class LegacyHoneypotChild extends LegacyHoneypotBase
{
    public int $validationCount = 0;
    public int $resetCount = 0;

    protected function validateHoneypot(?int $minimumSeconds = null): void
    {
        $this->validationCount++;
        parent::validateHoneypot($minimumSeconds);
    }

    protected function resetHoneypot(): void
    {
        $this->resetCount++;
        parent::resetHoneypot();
    }
}

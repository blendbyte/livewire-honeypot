<?php

// Local Testbench application used only by the Playwright regression suite.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Blendbyte\LivewireHoneypot\Tests\TestCase;
use Blendbyte\LivewireHoneypot\Responders\RedirectResponder;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Livewire;

$test = new class('browser') extends TestCase {
    public function bootBrowser(): void
    {
        $this->setUp();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        // Stable across HTTP requests so Livewire can verify its snapshots.
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('b', 32)));
        $app['config']->set('livewire.csp_safe', getenv('HONEYPOT_BROWSER_CSP') === '1');
        $app['config']->set('livewire-honeypot.minimum_fill_seconds', 0);
        $app['config']->set('livewire-honeypot.require_js_verification', false);
    }
};
$test->bootBrowser();

class JsVerificationBrowserComponent extends Component
{
    use HasHoneypot;

    #[Locked]
    public bool $custom = false;
    #[Locked]
    public bool $redirectSpam = false;
    public array $contact = ['trap' => ''];
    public string $email = '';
    public int $submissions = 0;

    protected function honeypotConfig(): array
    {
        return ['require_js_verification' => true];
    }

    public function submit(): void
    {
        if ($this->redirectSpam) {
            config(['livewire-honeypot.spam_responder' => RedirectResponder::class]);
        }
        $this->custom ? $this->validateHoneypotForModel('contact.trap') : $this->validateHoneypot();
        $this->validate(['email' => 'required|email']);
        $this->submissions++;
        $this->reset('email');
        $this->custom ? $this->resetHoneypotForModel('contact.trap') : $this->resetHoneypot();
    }

    public function submitExpired(): void
    {
        $this->hp_started_at = now()->getTimestamp() - 3601;
        $this->submit();
    }

    public function render(): string
    {
        return <<<'BLADE'
<form wire:submit="submit">
    @if($custom)
        <x-honeypot wire:model="contact.trap" />
    @else
        <x-honeypot />
    @endif
    <label>Email <input type="email" wire:model="email"></label>
    @error('email') <p class="email-error">{{ $message }}</p> @enderror
    <p class="submissions">{{ $submissions }}</p>
    <button type="submit">Submit</button>
    @if($redirectSpam)
        <button type="button" wire:click="submitExpired">Submit expired form</button>
    @endif
    <button type="button" wire:click="$refresh">Refresh</button>
</form>
BLADE;
    }
}

class ConfiguredFieldBrowserComponent extends JsVerificationBrowserComponent
{
    public string $trap = '';

    protected function honeypotConfig(): array
    {
        return [...parent::honeypotConfig(), 'field_name' => 'trap'];
    }
}

Livewire::component('browser-honeypot', JsVerificationBrowserComponent::class);
Livewire::component('configured-honeypot', ConfiguredFieldBrowserComponent::class);
Vite::useCspNonce('browser-test-nonce');
Route::middleware('web')->get('/{mode?}', function (string $mode = 'default') {
    $html = Blade::render(<<<'BLADE'
<!doctype html>
<html><head><meta name="csrf-token" content="{{ csrf_token() }}">@livewireStyles</head><body>
@if($configured)
    <livewire:configured-honeypot />
@else
    <livewire:browser-honeypot :custom="$custom" :redirect-spam="$redirectSpam" />
@endif
@if($multiple) <livewire:browser-honeypot :custom="true" /> @endif
@livewireScripts
</body></html>
BLADE, [
    'custom' => $mode === 'custom',
    'multiple' => $mode === 'multiple',
    'redirectSpam' => $mode === 'redirect',
    'configured' => $mode === 'configured',
]);

    $policy = "default-src 'self'; script-src 'self' 'nonce-browser-test-nonce'";
    if (! config('livewire.csp_safe')) {
        $policy .= " 'unsafe-eval'";
    }
    $policy .= "; style-src 'self' 'nonce-browser-test-nonce'";

    return response($html)->header('Content-Security-Policy', $policy);
});

$kernel = app(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

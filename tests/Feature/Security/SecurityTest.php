<?php

use App\Models\Attachment;
use App\Models\Customer;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = actingAsRole('company-admin');
    $this->customer = Customer::factory()->create();
});

it('envia cabeçalhos de segurança', function () {
    $this->get('/dashboard')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('armazena anexos no disco privado com nome aleatório e baixa somente com autorização', function () {
    $this->post('/anexos', [
        'attachable_type' => 'customer',
        'attachable_id' => $this->customer->id,
        'file' => UploadedFile::fake()->create('contrato assinado.pdf', 120, 'application/pdf'),
    ])->assertRedirect();

    $attachment = Attachment::query()->sole();

    expect($attachment->original_name)->toBe('contrato assinado.pdf')
        ->and($attachment->path)->toStartWith("attachments/{$this->customer->company_id}/customer/")
        ->and($attachment->path)->not->toContain('contrato');
    Storage::disk('local')->assertExists($attachment->path);

    $this->get("/anexos/{$attachment->id}")->assertOk()->assertDownload('contrato assinado.pdf');
});

it('bloqueia download de anexo de outra empresa', function () {
    $other = createCompany();
    $foreign = app(CurrentCompany::class)->run($other, function () {
        $customer = Customer::factory()->create();

        return $customer->attachments()->create([
            'disk' => 'local', 'path' => 'attachments/x/secreto.pdf', 'original_name' => 'secreto.pdf', 'size' => 10,
        ]);
    });

    $this->get("/anexos/{$foreign->id}")->assertNotFound();
});

it('recusa tipos de arquivo perigosos', function () {
    $this->post('/anexos', [
        'attachable_type' => 'customer',
        'attachable_id' => $this->customer->id,
        'file' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
    ])->assertSessionHasErrors('file');

    expect(Attachment::count())->toBe(0);
});

it('não aceita tipos de registro arbitrários no upload', function () {
    $this->post('/anexos', [
        'attachable_type' => 'App\\Models\\User',
        'attachable_id' => $this->user->id,
        'file' => UploadedFile::fake()->create('a.pdf', 1, 'application/pdf'),
    ])->assertSessionHasErrors('attachable_type');
});

it('escapa conteúdo digitado pelo usuário (proteção contra XSS)', function () {
    Customer::factory()->create(['name' => '<script>alert("x")</script>', 'trade_name' => null]);

    $this->get('/clientes')
        ->assertOk()
        ->assertDontSee('<script>alert("x")</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('protege formulários contra CSRF (o Laravel desativa a checagem apenas durante os testes)', function () {
    expect(app('router')->getMiddlewareGroups()['web'])
        ->toContain(PreventRequestForgery::class);
});

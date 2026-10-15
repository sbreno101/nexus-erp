<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->company = createCompany();
    $this->user = createUserWithRole($this->company, 'sales', ['email' => 'vendas@exemplo.com.br', 'password' => 'Senha@123']);
});

it('exibe a tela de login', function () {
    $this->get('/login')->assertOk()->assertSee('Acesse sua conta');
});

it('autentica com credenciais corretas e redireciona para o dashboard', function () {
    $this->post('/login', ['email' => 'vendas@exemplo.com.br', 'password' => 'Senha@123'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);
    expect($this->user->fresh()->last_login_at)->not->toBeNull();
});

it('recusa credenciais inválidas', function () {
    $this->post('/login', ['email' => 'vendas@exemplo.com.br', 'password' => 'errada'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('bloqueia login de conta desativada', function () {
    $this->user->forceFill(['is_active' => false])->save();

    $this->post('/login', ['email' => 'vendas@exemplo.com.br', 'password' => 'Senha@123'])
        ->assertSessionHasErrors(['email' => 'Sua conta está desativada. Procure o administrador do sistema.']);

    $this->assertGuest();
});

it('limita tentativas excessivas de login', function () {
    RateLimiter::clear('vendas@exemplo.com.br|127.0.0.1');

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => 'vendas@exemplo.com.br', 'password' => 'errada']);
    }

    $this->post('/login', ['email' => 'vendas@exemplo.com.br', 'password' => 'Senha@123'])->assertStatus(429);
    $this->assertGuest();
});

it('redireciona visitantes não autenticados para o login', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/dashboard', '/clientes', '/financeiro/receber', '/configuracoes/usuarios']);

it('exige e-mail verificado para acessar o sistema', function () {
    $user = createUserWithRole($this->company, 'sales', ['email_verified_at' => null]);

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/email/verify');
});

it('desconecta o usuário ao ser desativado durante a sessão', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk();

    $this->user->forceFill(['is_active' => false])->save();

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('encerra a sessão no logout', function () {
    $this->actingAs($this->user)->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

it('usuário sem vínculo com empresa ativa não consegue usar o sistema', function () {
    $orphan = User::factory()->create();

    $this->actingAs($orphan)->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

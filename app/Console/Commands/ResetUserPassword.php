<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:reset-password {id?} {password?}';

    /**
     * The console command description.
     */
    protected $description = 'Reset user password by ID or reset first 10 users to 123456';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('id');
        $newPassword = $this->argument('password');

        // Se não passar argumentos, resetar os 10 primeiros usuários
        if (!$userId && !$newPassword) {
            return $this->resetFirst10Users();
        }

        // Se passar apenas o ID, pedir confirmação
        if ($userId && !$newPassword) {
            $this->error("É necessário informar a nova senha!");
            $this->line("Uso: php artisan user:reset-password {id} {password}");
            return 1;
        }

        // Resetar senha de um usuário específico
        return $this->resetSingleUser($userId, $newPassword);
    }

    /**
     * Resetar senha dos 10 primeiros usuários
     */
    private function resetFirst10Users()
    {
        if (!$this->confirm('Deseja resetar a senha dos 10 primeiros usuários para "123456"?')) {
            $this->info('Operação cancelada.');
            return 0;
        }

        $users = User::orderBy('id')->limit(10)->get();

        if ($users->isEmpty()) {
            $this->error("Nenhum usuário encontrado!");
            return 1;
        }

        $this->info("Resetando senha de {$users->count()} usuários...\n");

        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            $user->update([
                'senha' => Hash::make('123456')
            ]);

            $this->line("\n✓ ID {$user->id} - {$user->nome} ({$user->login})");
            $bar->advance();
        }

        $bar->finish();

        $this->info("\n\n✓ Senhas resetadas com sucesso!");
        $this->line("Nova senha para todos: 123456");

        return 0;
    }

    /**
     * Resetar senha de um usuário específico
     */
    private function resetSingleUser($userId, $newPassword)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->error("Usuário com ID {$userId} não encontrado!");
            return 1;
        }

        $user->update([
            'senha' => Hash::make($newPassword)
        ]);

        $this->info("✓ Senha do usuário {$user->nome} (ID: {$userId}) foi resetada com sucesso!");
        $this->line("Login: {$user->login}");
        $this->line("Nova senha: {$newPassword}");

        return 0;
    }
}

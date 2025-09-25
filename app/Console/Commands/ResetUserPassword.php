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
    protected $signature = 'user:reset-password {id} {password}';

    /**
     * The console command description.
     */
    protected $description = 'Reset user password by ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('id');
        $newPassword = $this->argument('password');

        // Buscar o usuário
        $user = User::find($userId);

        if (!$user) {
            $this->error("Usuário com ID {$userId} não encontrado!");
            return 1;
        }

        // Resetar a senha
        $user->update([
            'senha' => Hash::make($newPassword)
        ]);

        $this->info("Senha do usuário {$user->nome} (ID: {$userId}) foi resetada com sucesso!");
        $this->line("Nova senha: {$newPassword}");

        return 0;
    }
}

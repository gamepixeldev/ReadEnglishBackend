<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ProvisionContentAdmin extends Command
{
    protected $signature = 'content-admin:provision';
    protected $description = 'Create the initial content administrator with a random one-time password';

    public function handle(): int
    {
        if (User::where('is_admin', true)->exists()) {
            $this->error('管理员账号已存在；为避免意外覆盖，本命令不会再创建账号。');
            return self::FAILURE;
        }

        $email = 'admin-'.bin2hex(random_bytes(5)).'@local.test';
        $password = bin2hex(random_bytes(18));
        $user = User::create([
            'name' => '内容管理员',
            'email' => $email,
            'password' => $password,
            'is_admin' => true,
        ]);

        $this->newLine();
        $this->info('管理员已创建。密码只显示这一次，请妥善保存。');
        $this->line('登录账号：'.$user->email);
        $this->line('登录密码：'.$password);
        $this->line('后台地址：'.rtrim((string) config('app.url'), '/').'/admin');
        $this->newLine();

        return self::SUCCESS;
    }
}

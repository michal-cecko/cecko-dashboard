<?php

namespace App\Console\Commands\Stride;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GeneratePushKeysCommand extends Command
{
    protected $signature = 'stride:push-keys {--force : Replace keys already present in the local environment file}';

    protected $description = 'Generate and save local Stride Web Push keys.';

    public function handle(): int
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            $this->error('The local .env file does not exist.');

            return self::FAILURE;
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            $this->error('The local .env file could not be read.');

            return self::FAILURE;
        }

        if (! $this->option('force') && preg_match('/^STRIDE_PUSH_(PUBLIC|PRIVATE)_KEY=.+$/m', $contents)) {
            $this->error('Push keys already exist. Use --force to replace them.');

            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();
        foreach (['PUBLIC_KEY' => $keys['publicKey'], 'PRIVATE_KEY' => $keys['privateKey']] as $name => $value) {
            $line = 'STRIDE_PUSH_'.$name.'='.$value;
            $pattern = '/^STRIDE_PUSH_'.$name.'=.*$/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents)."\n{$line}\n";
        }

        file_put_contents($path, $contents, LOCK_EX);
        chmod($path, 0600);
        $this->info('Local push keys saved. Public key: '.$keys['publicKey']);
        $this->comment('The private key was saved to .env and was not printed.');

        return self::SUCCESS;
    }
}

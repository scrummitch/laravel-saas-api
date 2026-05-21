<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class WriteVersionFile extends Command
{
    protected $signature = 'app:write-version-file {name} {env?}';

    protected $description = 'Command description';

    public function handle()
    {
        $input = [
            'version' => $this->argument('name'),
            'env' => $this->argument('env'),
        ];

        file_put_contents(storage_path('version.yaml'), Yaml::dump($input));
    }
}

# Console

Commands are [Symfony Console](https://symfony.com/doc/current/components/console.html) commands, registered
through the service container with the `console.command` tag. The runner is provided by `icanboogie/console`:

```bash
php vendor/bin/icanboogie <command>
```

## Commands

### `cache:clear`

Clears caches by emitting `Application\ClearCacheEvent` and printing what each hook reported:

```bash
$ php vendor/bin/icanboogie cache:clear
Cleared: app.storage_for_configs
```

### `configs:list` (alias `configs`)

Lists the config fragments registered through [Autoconfig](autoconfig.md), with the config class and
the builder that creates it:

```bash
$ php vendor/bin/icanboogie configs
```

| Fragment | Config               | Builder                     |
|----------|----------------------|-----------------------------|
| app      | ICanBoogie\AppConfig | ICanBoogie\AppConfigBuilder |

## Adding a command

Create a command, and declare it in `services.yml`:

```php
<?php

#[AsCommand(name: 'hello', description: 'Say hello')]
final class HelloCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello!');

        return Command::SUCCESS;
    }
}
```

```yaml
services:
  _defaults:
    autowire: true

  console.command.hello:
    class: Acme\HelloCommand
    tags:
    - { name: console.command, command: 'hello' }
```

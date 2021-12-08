<?php

namespace eLife\Journal;

use Closure;
use eLife\Journal\Guzzle\CiviCrmClient;
use GuzzleHttp\Client;
use LogicException;
use SplFileObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Kernel;

final class Console
{
    private $console;
    private $kernel;
    private $root;

    public static $quick_commands = [
        'etoc:subscribers' => ['description' => 'Get etoc subscribers'],
        'etoc:preferences' => ['description' => 'Get etoc preference links'],
    ];

    public function __construct(Application $console, Kernel $kernel)
    {
        $this->console = $console;
        $this->kernel = $kernel;
        $this->root = __DIR__.'/../..';
    }

    public function run(InputInterface $input = null, OutputInterface $output = null) : int
    {
        foreach (self::$quick_commands as $name => $cmd) {
            if (strpos($name, ':')) {
                $pieces = explode(':', $name);
                $first = array_shift($pieces);
                $pieces = array_map('ucfirst', $pieces);
                array_unshift($pieces, $first);
                $fn = implode('', $pieces);
            } else {
                $fn = $name;
            }
            if (!method_exists($this, $fn.'Command')) {
                throw new LogicException('Your command does not exist: '.$fn.'Command');
            }
            $command = $this->console
                ->register($name)
                ->setDescription($cmd['description'] ?? $name.' command')
                ->setCode(Closure::bind(function (InputInterface $input, OutputInterface $output) use ($fn) {
                    $this->{$fn.'Command'}($input, $output);
                }, $this));

            if (isset($cmd['args'])) {
                foreach ($cmd['args'] as $arg) {
                    $command->addArgument($arg['name'], $arg['mode'] ?? null, $arg['description'] ?? '', $arg['default'] ?? null);
                }
            }
        }
        return $this->console->run($input, $output);
    }

    public function etocSubscribersCommand(InputInterface $input, OutputInterface $output)
    {
        $client = $this->civiClient();

        $subscribers = $client->getAllSubscribers(1000, 100, 0);

        $output->writeln(implode(",", $subscribers));
    }

    public function etocPreferencesCommand(InputInterface $input, OutputInterface $output)
    {
        $client = $this->civiClient();

        $contacts = new SplFileObject(__DIR__.'/../contact-ids.txt');
        $contact_id = trim($contacts->current());
        $co = 0;

        while ($contact_id !== '') {
            $co++;
            $store = $client->storePreferencesUrl((int) $contacts->current(), 'https://elifesciences.org/content-alerts/'.uniqid())->wait();
            $output->writeln($co.': '.$store['contact_id']);

            $contacts->next();
            $contact_id = trim($contacts->current());
        }
    }

    private function civiClient() {
        return new CiviCrmClient(new Client([
            'base_uri' => 'https://crm.elifesciences.org/crm/sites/all/modules/civicrm/extern/rest.php',
            'connect_timeout' => 10,
            'timeout' => 20,
        ]), 'API_KEY', 'SITE_KEY');
    }
}

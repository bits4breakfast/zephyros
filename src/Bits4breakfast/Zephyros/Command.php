<?php
namespace Bits4breakfast\Zephyros;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends BaseCommand
{
    public static $app_base_path;

    protected $environment = 'dev';

    protected $config = null;
    protected $container = null;

    protected function initialize(InputInterface $input, OutputInterface $output) 
    {
        $this->environment = $input->getOption('env', 'dev');
        $this->app_base_path = self::$app_base_path;

        $this->config = new Config($this->app_base_path, 'console-commands', $this->environment);

        $services = new ServiceContainerDefinitions($this->app_base_path);
        $this->container = ServiceContainer::init($this->config, $services);
    }
}
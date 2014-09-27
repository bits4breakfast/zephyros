<?php
namespace Bits4breakfast\Zephyros;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends BaseCommand
{
	protected $environemnt = 'dev';
	protected $app_base_path;

	protected $config = null;
	protected $container = null;

	protected function configure() {
		$this->addOption(
            'env',
            InputArgument::OPTIONAL,
            'Indicate environment (default to "dev")'
        );
	}

	protected function initialize(InputInterface $input, OutputInterface $output) 
	{
		$this->environemnt = $input->getParameterOption('env', 'dev');
		$this->app_base_path = realpath(getcwd().'/..');

		$this->config = new Config($this->app_base_path, 'console-commands', $this->environemnt);

		$services = new ServiceContainerDefinitions($this->app_base_path);
		$this->container = ServiceContainer::init($this->config, $services);
	}
}
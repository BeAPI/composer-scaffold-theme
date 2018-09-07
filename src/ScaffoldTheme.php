<?php namespace BEA\Composer\ScaffoldTheme;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

class ScaffoldTheme implements PluginInterface, Capable, CommandProvider {

	/**
	 * @var Composer
	 */
	private $composer;

	/**
	 * @var IOInterface
	 */
	private $io;

	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
		$this->io       = $io;
	}

	public function getCapabilities() {
		return [
			'Composer\Plugin\Capability\CommandProvider' => __NAMESPACE__ . '\\ScaffoldTheme',
		];
	}

	public function getCommands() {
		return [
			new Command\ScaffoldThemeCommand,
		];
	}
}
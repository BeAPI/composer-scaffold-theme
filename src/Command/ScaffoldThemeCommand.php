<?php namespace BEA\Composer\ScaffoldTheme\Command;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ScaffoldThemeCommand extends BaseCommand {

	const WP_THEME_PACKAGE_TYPE = 'wordpress-theme';

	/**
	 * url to download zip file
	 * @var string
	 */
	protected static $zip_url = 'https://github.com/BeAPI/beapi-frontend-framework/archive/master.zip';

	/**
	 * @var string
	 */
	protected static $search = 'Be API Frontend Framework';

	protected function configure() {
		$this->setName( 'scaffold-theme' )
		     ->setDescription( 'Bootstrap a new WordPress theme using Be API\'s frontend framework.' )
		     ->addArgument( 'folder', InputArgument::REQUIRED, "Your theme's folder name" )
		     ->addOption( 'boilerplate-version', null, InputOption::VALUE_OPTIONAL, 'Which version to use ?', 'Latest' )
		     ->addOption( 'no-autoload', null, InputOption::VALUE_NONE, 'Autoload the class into composer.json' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		// Quick access
		$io        = new SymfonyStyle( $input, $output );
		$composer  = $this->getComposer();
		$themeName = $input->getArgument( 'folder' );
		$version    = $input->getOption( 'boilerplate-version' );
		$no_autoload = $input->getOption( 'no-autoload' );

		// what is the command's purpose
		$io->write( "\nHello, this command allows you to start a theme with the wonderful Be API theme Framework." );

		if ( ! empty( $args ) ) {
			// Get plugin name
			$themeName = array_shift( $args );
			$themeName = str_replace( [ ' ', '-' ], '-', trim( $themeName ) );
		}

		$io->write( "\nScaffolding theme $themeName" );
		// Get plugin name
		if ( empty( $themeName ) ) {
			$themeName = trim( $io->ask( "What is your theme's folder name ? " ) );
			if ( empty( $themeName ) ) {
				$io->write( "Your theme's folder name is invalid" );
				return Command::INVALID;
			}
		}

		$downloadPath = $composer->getConfig()->get( 'vendor-dir' ) . '/starter-theme';
		$themePath    = $this->getInstallPath( $themeName, $composer );

		if ( is_dir( $themePath ) ) {
			$io->write( "oops! Theme already exist" );
			return Command::FAILURE;
		}

		$themeCompleteName = $io->ask( "What is your theme's real name ? (for headers in style.css)" );
		if ( empty( $themeCompleteName ) ) {
			$io->write( "You did not provide any real name so I take the folder name by default." );
			$themeCompleteName = $themeName;
		}

		$this->generateTheme( $composer, $io, $themeName, $themePath, $themeCompleteName, $downloadPath, $output, $version, $no_autoload );
		$io->success( "\nYour theme is ready ! :)" );
		$io->success( 'Run composer dump-autoload to make the autoloading work :)' );
		return Command::SUCCESS;
	}

	/**
	 * @param $source
	 * @param $destination
	 *
	 * @author Julien Maury
	 * @source https://stackoverflow.com/a/7775949
	 */
	protected function recursive_copy( $source, $destination ) {
		foreach (
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST ) as $item
		) {
			if ( $item->isDir() ) {
				mkdir( $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName() );
			} else {
				copy( $item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName() );
			}
		}
	}

	/**
	 * Do a search/replace in folder
	 *
	 * @param string $path
	 * @param string $search
	 * @param string $replace
	 * @param string $extension
	 *
	 * @return bool
	 */
	protected function doStrReplace( $path, $search, $replace = '', $extension = 'php' ) {

		if ( empty( $path ) || empty( $search ) ) {
			return false;
		}

		$path     = realpath( $path );
		$fileList = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path ), \RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $fileList as $item ) {
			if ( $item->isFile() && ( false !== stripos( $item->getPathName(), $extension ) || 'style.css' === $item->getFileName() ) ) {
				$content = file_get_contents( $item->getPathName() );
				file_put_contents( $item->getPathName(), str_replace( $search, $replace, $content ) );
			}
		}

		return true;
	}

	/**
	 * Ask the user for a value and then ask for confirmation
	 *
	 * @param SymfonyStyle $io           Composer IO object
	 * @param string       $question     question to ask to the user
	 * @param string       $confirmation confirmation message
	 *
	 * @return string
	 */
	protected function askAndConfirm( SymfonyStyle $io, $question, $confirmation = '' ) {
		$value = '';
		while( empty( $value ) ) {
			$value = trim( $io->ask( $question ) );
		}
		if ( empty( $confirmation ) ) {
			$confirm_msg = sprintf( 'You have enter %s. Is that Ok ? ', $value );
		} else {
			$confirm_msg = sprintf( $confirmation, $value );
		}
		if ( $io->confirm( $confirm_msg ) ) {
			return $value;
		}
		return $this->askAndConfirm( $io, $question, $confirmation );
	}

	/**
	 * @param $path
	 * @param $search
	 * @param string $replace
	 *
	 * @return bool
	 * @author Julien Maury
	 */
	protected function replaceHeaderStyle( $path, $search, $replace ) {

		if ( empty( $path ) || empty( $search ) ) {
			return false;
		}

		$path    = $path . '/style.css';
		$content = file_get_contents( $path );
		file_put_contents( $path, str_replace( $search, $replace, $content ) );

	}

	/**
	 * @param $composer
	 * @param $io
	 * @param $themeName
	 * @param $themePath
	 * @param $themeCompleteName
	 * @param $downloadPath
	 *
	 * @param $output
	 * @param $version
	 * @param $no_autoload
	 *
	 * @throws \Exception
	 * @author Julien Maury
	 */
	protected function generateTheme( $composer, $io, $themeName, $themePath, $themeCompleteName, $downloadPath, $output, $version, $no_autoload ) {

		if ( ! file_exists( $downloadPath . '/index.php' ) ) {
			$this->downloadPackage( $composer, $this->getThemePackage( $version ), $downloadPath );
		}

		if ( ! file_exists( $downloadPath . '/index.php' ) ) {
			$io->write( "oops! Couldn't download starter theme files." );
			exit;
		}

		mkdir( $themePath, 0777, true );

		$this->recursive_copy( $downloadPath, $themePath );

		$themeNamespace = $this->askForThemeNamespace( $io, $output );

		$this->doStrReplace( $themePath, 'BEA\\Theme\\Framework', $themeNamespace );
		$this->replaceHeaderStyle( $themePath, static::$search, $themeCompleteName );
		// Replace text domain in translations and stylesheets
		$this->doStrReplace( $themePath, 'beapi-frontend-framework', $themeName );
		$this->replaceHeaderStyle( $themePath, 'beapi-frontend-framework', $themeName );

		/**
		 * Add the new namespace to the autoload entry of the composer.json file.
		 *
		 */
		if ( false === $no_autoload ) {
			$composerPath = $composer->getConfig()->getConfigSource()->getName();
			$composerFile = new JsonFile( $composerPath );

			try {
				$composerJson = $composerFile->read();
				$composerJson['autoload']['psr-4'][$themeNamespace."\\"] = $this->makeAutoloadPath( $themePath, $composer ) .'/inc/';

				$composerFile->write( $composerJson );
				$output->writeln( "The namespace have been added to the composer.json file !" );
			} catch ( \RuntimeException $e ) {
				$output->writeln( "<error>An error occurred</error>" );
				$output->writeln( sprintf( "<error>%s</error>", $e->getMessage() ) );
				exit;
			}
		}

	}

	/**
	 * Download a package.
	 *
	 * @param Composer $composer
	 * @param Package $package
	 * @param string $path
	 */
	protected function downloadPackage( Composer $composer, Package $package, $path ) {
		if ( version_compare( Composer::RUNTIME_API_VERSION, '2.0', '>=' ) ) {
			$promise = $composer->getDownloadManager()->download( $package, $path );
			$composer->getLoop()->wait([$promise]);
			$promise = $composer->getDownloadManager()->install($package, $path);
			$composer->getLoop()->wait([$promise]);
		} else {
			$composer
				->getDownloadManager()
				->download($package, $path);
		}
	}

	/**
	 * Setup a dummy package for Composer to download
	 *
	 * @param $version
	 *
	 * @return Package
	 */
	protected function getThemePackage( $version ) {
		$p = new Package( 'theme-framework', 'starter-php', $version );
		$p->setType( 'library' );
		$p->setInstallationSource('dist');
		$p->setDistType( 'zip' );

		$dist_url = self::$zip_url;

		if ( 'Latest' !== $version ) {
			$dist_url = sprintf( 'https://github.com/BeAPI/beapi-frontend-framework/archive/%s.zip', $version );
		}

		$p->setDistUrl( $dist_url );

		return $p;
	}

	/**
	 * Create dummy wordpress-theme package to get the installation path
	 *
	 * @param string $themeName
	 * @param Composer $composer
	 *
	 * @return string
	 */
	protected function getInstallPath( $themeName, $composer ) {
		$theme = new Package( $themeName, 'dev-master', 'Latest' );
		$theme->setType( self::WP_THEME_PACKAGE_TYPE );
		$path = $composer->getInstallationManager()->getInstallPath( $theme );

		return \rtrim( $path, '/' ) . '/';
	}

	/**
	 * Get theme namespace.
	 *
	 * Check that the namespace provided by the user is not the same
	 * as the default one.
	 *
	 * @param IOInterface $io
	 * @param OutputInterface $output
	 *
	 * @return string
	 */
	protected function askForThemeNamespace( $io, $output ) {

		$reserved_namespace = [ 'bea\\theme\\framework', 'beapi\\theme\\framework' ];
		$themeNamespace = '';
		while( empty( $themeNamespace ) ) {
			$themeNamespace    = $this->askAndConfirm( $io, "\nWhat is your theme's namespace ? (e.g: 'BEA\\Theme\\Framework')" );
			if ( in_array( mb_strtolower( trim( $themeNamespace ) ), $reserved_namespace, true ) ) {
				$themeNamespace = '';
				$output->writeln(
					[
						"<error>The namespace you chose is not allowed.</error>",
						"<error>Please choose a namespace matching your project like ClientName\Theme\MyThemeName.</error>"
					]
				);
			}
		}

		return $themeNamespace;
	}

	/**
	 * Take the package installation path and prepare it for autoload mapping.
	 *
	 * Will take care of converting absolute path to relative one.
	 *
	 * @param string $path the package installation path.
	 * @param Composer $composer the composer instance.
	 *
	 * @return string a relative path to the namespace directory which doesn't end with a slash.
	 */
	protected function makeAutoloadPath( $path, $composer ) {
		// Make path relative to package's root.
		if ( 0 === strpos( $path, '/' ) ) {
			$composerJsonFilePath = $composer->getConfig()->getConfigSource()->getName();
			$projectRootPath = dirname( $composerJsonFilePath );
			$path = str_replace( $projectRootPath, '', $path );
		}

		return trim( $path, '/' );
	}
}

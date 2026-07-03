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

	const BOILERPLATE_BFF = 'bff';

	const BOILERPLATE_BBT = 'bbt';

	/**
	 * Boilerplate configurations.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected static $boilerplates = [
		self::BOILERPLATE_BFF => [
			'label'              => 'Be API Frontend Framework (BFF)',
			'zip_repo'           => 'beapi-frontend-framework',
			'default_branch'     => 'master',
			'search'             => 'Be API Frontend Framework',
			'text_domain'        => 'beapi-frontend-framework',
			'namespace'          => 'BEA\\Theme\\Framework',
			'download_dir'       => 'starter-theme',
			'marker_file'        => 'index.php',
			'package_name'       => 'theme-framework',
			'replace_mode'       => 'legacy',
			'replace_extensions' => [ 'php' ],
			'rename_languages'   => false,
		],
		self::BOILERPLATE_BBT => [
			'label'              => 'BeAPI Blocks Theme (BBT)',
			'zip_repo'           => 'beapi-blocks-theme',
			'default_branch'     => 'main',
			'search'             => 'BeAPI Blocks Theme',
			'text_domain'        => 'beapi-blocks-theme',
			'namespace'          => 'BEA\\Theme\\Framework',
			'download_dir'       => 'starter-blocks-theme',
			'marker_file'        => 'functions.php',
			'package_name'       => 'blocks-theme',
			'replace_mode'       => 'extensions',
			'replace_extensions' => [ 'php', 'html', 'css', 'json', 'js', 'po', 'pot', 'scss' ],
			'rename_languages'   => true,
		],
	];

	protected function configure() {
		$this->setName( 'scaffold-theme' )
		     ->setDescription( 'Bootstrap a new WordPress theme using Be API\'s BFF or BBT boilerplate.' )
		     ->addArgument( 'folder', InputArgument::REQUIRED, "Your theme's folder name" )
		     ->addOption( 'boilerplate', 'b', InputOption::VALUE_OPTIONAL, 'Boilerplate to use: bff or bbt' )
		     ->addOption( 'boilerplate-version', null, InputOption::VALUE_OPTIONAL, 'Which version to use ?', 'Latest' )
		     ->addOption( 'no-autoload', null, InputOption::VALUE_NONE, 'Autoload the class into composer.json' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		// Quick access
		$io          = new SymfonyStyle( $input, $output );
		$composer    = $this->getComposer();
		$themeName   = $input->getArgument( 'folder' );
		$version     = $input->getOption( 'boilerplate-version' );
		$no_autoload = $input->getOption( 'no-autoload' );
		$boilerplate = $this->resolveBoilerplate( $input, $io );

		if ( null === $boilerplate ) {
			return Command::INVALID;
		}

		$config = static::$boilerplates[ $boilerplate ];

		$io->write( sprintf( "\nHello, this command allows you to start a theme with %s.", $config['label'] ) );

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

		$downloadPath = $composer->getConfig()->get( 'vendor-dir' ) . '/' . $config['download_dir'];
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

		$this->generateTheme( $composer, $io, $themeName, $themePath, $themeCompleteName, $downloadPath, $output, $version, $no_autoload, $config );
		$io->success( "\nYour theme is ready ! :)" );
		$io->success( 'Run composer dump-autoload to make the autoloading work :)' );
		return Command::SUCCESS;
	}

	/**
	 * Resolve the boilerplate from CLI option or interactive choice.
	 *
	 * @param InputInterface $input
	 * @param SymfonyStyle   $io
	 *
	 * @return string|null
	 *
	 * @author Jules Fell
	 */
	protected function resolveBoilerplate( InputInterface $input, SymfonyStyle $io ) {
		$boilerplate = $input->getOption( 'boilerplate' );

		if ( empty( $boilerplate ) ) {
			$boilerplate = $io->choice(
				'Which boilerplate do you want to use ?',
				[
					self::BOILERPLATE_BFF => static::$boilerplates[ self::BOILERPLATE_BFF ]['label'],
					self::BOILERPLATE_BBT => static::$boilerplates[ self::BOILERPLATE_BBT ]['label'],
				],
				self::BOILERPLATE_BFF
			);
		}

		$boilerplate = mb_strtolower( trim( $boilerplate ) );

		if ( ! isset( static::$boilerplates[ $boilerplate ] ) ) {
			$io->error( 'Invalid boilerplate. Use "bff" or "bbt".' );
			return null;
		}

		return $boilerplate;
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
	protected function doStrReplace( $path, $search, $replace = '', array $config = [] ) {

		if ( empty( $path ) || empty( $search ) ) {
			return false;
		}

		$path     = realpath( $path );
		$fileList = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path ), \RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $fileList as $item ) {
			if ( ! $item->isFile() || ! $this->shouldReplaceInFile( $item, $config ) ) {
				continue;
			}

			$content = file_get_contents( $item->getPathName() );
			file_put_contents( $item->getPathName(), str_replace( $search, $replace, $content ) );
		}

		return true;
	}

	/**
	 * Check whether a file should be included in search/replace.
	 *
	 * @param \SplFileInfo $item
	 * @param array        $config
	 *
	 * @return bool
	 *
	 * @author Jules Fell
	 */
	protected function shouldReplaceInFile( $item, array $config ) {
		if ( 'style.css' === $item->getFileName() ) {
			return true;
		}

		if ( 'legacy' === $config['replace_mode'] ) {
			return false !== stripos( $item->getPathName(), 'php' );
		}

		return in_array( $item->getExtension(), $config['replace_extensions'], true );
	}

	/**
	 * Rename language files after text domain replacement.
	 *
	 * @param string $themePath
	 * @param string $themeName
	 * @param array  $config
	 *
	 * @author Jules Fell
	 */
	protected function renameLanguageFiles( $themePath, $themeName, array $config ) {
		if ( empty( $config['rename_languages'] ) ) {
			return;
		}

		$languagesPath = $themePath . 'languages';

		if ( ! is_dir( $languagesPath ) ) {
			return;
		}

		$prefix = $config['text_domain'];

		foreach ( scandir( $languagesPath ) as $file ) {
			if ( 0 === strpos( $file, $prefix ) ) {
				rename(
					$languagesPath . '/' . $file,
					$languagesPath . '/' . str_replace( $prefix, $themeName, $file )
				);
			}
		}
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
	protected function generateTheme( $composer, $io, $themeName, $themePath, $themeCompleteName, $downloadPath, $output, $version, $no_autoload, array $config ) {

		$markerFile = $downloadPath . '/' . $config['marker_file'];

		if ( ! file_exists( $markerFile ) ) {
			$this->downloadPackage( $composer, $this->getThemePackage( $version, $config ), $downloadPath );
		}

		if ( ! file_exists( $markerFile ) ) {
			$io->write( "oops! Couldn't download starter theme files." );
			exit;
		}

		mkdir( $themePath, 0777, true );

		$this->recursive_copy( $downloadPath, $themePath );

		$themeNamespace = $this->askForThemeNamespace( $io, $output );

		$this->doStrReplace( $themePath, $config['namespace'], $themeNamespace, $config );
		$this->replaceHeaderStyle( $themePath, $config['search'], $themeCompleteName );
		$this->doStrReplace( $themePath, $config['text_domain'], $themeName, $config );
		$this->replaceHeaderStyle( $themePath, $config['text_domain'], $themeName );
		$this->renameLanguageFiles( $themePath, $themeName, $config );

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
	protected function getThemePackage( $version, array $config ) {
		$p = new Package( $config['package_name'], 'starter-php', $version );
		$p->setType( 'library' );
		$p->setInstallationSource('dist');
		$p->setDistType( 'zip' );

		$dist_url = sprintf(
			'https://github.com/BeAPI/%s/archive/%s.zip',
			$config['zip_repo'],
			$config['default_branch']
		);

		if ( 'Latest' !== $version ) {
			$dist_url = sprintf( 'https://github.com/BeAPI/%s/archive/%s.zip', $config['zip_repo'], $version );
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

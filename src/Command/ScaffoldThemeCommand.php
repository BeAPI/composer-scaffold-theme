<?php namespace BEA\Composer\ScaffoldTheme\Command;

use Composer\Command\BaseCommand;
use Composer\Package\Package;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ScaffoldThemeCommand extends BaseCommand {

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
		     ->addArgument( 'folder', InputArgument::REQUIRED, "Your theme's folder name" );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		// Quick access
		$io        = new SymfonyStyle( $input, $output );
		$composer  = $this->getComposer();
		$themeName = $input->getArgument( 'folder' );

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
				exit;
			}
		}

		$downloadPath = $composer->getConfig()->get( 'vendor-dir' ) . '/starter-theme';
		$themePath    = dirname( $composer->getConfig()->get( 'vendor-dir' ) ) . '/content/themes';

		if ( is_dir( $this->set_theme_relative_path( $themePath, $themeName ) ) ) {
			$io->write( "oops! Theme already exist" );
			exit;
		}

		$themeCompleteName = $io->ask( "What is your theme's real name ? (for headers in style.css)" );
		if ( empty( $themeCompleteName ) ) {
			$io->write( "You did not provide any real name so I take the folder name by default." );
			$themeCompleteName = $themeName;
		}
		$this->generateTheme( $composer, $io, $themeName, $themePath, $themeCompleteName, $downloadPath );
		$io->write( "\nYour theme is ready ! :)" );
	}

	/**
	 * @param $source
	 * @param $destination
	 *
	 * @author Julien Maury
	 * @source https://stackoverflow.com/a/7775949
	 */
	protected static function recursive_copy( $source, $destination ) {
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
	protected static function doStrReplace( $path, $search, $replace = '', $extension = 'php' ) {

		if ( empty( $path ) || empty( $search ) ) {
			return false;
		}

		$path     = realpath( $path );
		$fileList = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path ), \RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $fileList as $item ) {
			if ( $item->isFile() && false !== stripos( $item->getPathName(), $extension ) ) {
				$content = file_get_contents( $item->getPathName() );
				file_put_contents( $item->getPathName(), str_replace( $search, $replace, $content ) );
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
	 * @author Julien Maury
	 */
	protected function generateTheme( $composer, $io, $themeName, $themePath, $themeCompleteName, $downloadPath ) {

		if ( ! file_exists( $downloadPath . '/index.php' ) ) {
			$composer->getDownloadManager()->download( $this->getThemePackage(), $downloadPath );
		}

		if ( ! file_exists( $downloadPath . '/index.php' ) ) {
			$io->write( "oops! Couldn't download starter theme files." );
			exit;
		}

		if ( ! is_writable( $themePath ) ) {
			$io->write( "oops! Themes directory is not writable" );
			exit;
		}

		mkdir( $this->set_theme_relative_path( $themePath, $themeName ), 0777, true );

		$this->recursive_copy( $downloadPath, $this->set_theme_relative_path( $themePath, $themeName ) );

		$themeNamespace    = $this->askAndConfirm( $io, "\nWhat is your theme's namespace ? (e.g: 'BEA\\Theme\\Framework')" );
		$themeCompletePath = $this->set_theme_relative_path( $themePath, $themeName ) . '/';

		$this->doStrReplace( $themeCompletePath, 'BEA\\Theme\\Framework', $themeNamespace );
		$this->replaceHeaderStyle( $themeCompletePath, static::$search, $themeCompleteName );
	}


	/**
	 * @param $themePath
	 * @param $themeName
	 *
	 * @return string
	 * @author Julien Maury
	 */
	private function set_theme_relative_path( $themePath, $themeName ) {
		return $themePath . '/' . $themeName;
	}

	/**
	 * Setup a dummy package for Composer to download
	 *
	 * @return Package
	 */
	protected function getThemePackage() {
		$p = new Package( 'theme-framework', 'starter-php', 'Latest' );
		$p->setType( 'library' );
		$p->setDistType( 'zip' );
		$p->setDistUrl( self::$zip_url );

		return $p;
	}
}

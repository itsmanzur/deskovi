/**
 * Build a clean WordPress.org distributable zip using .distignore.
 * Output: deskovi-1.4.0.zip with root folder `deskovi/`.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const root = path.resolve( __dirname, '..' );
const version = '1.4.0';
const slug = 'deskovi';
const outZip = path.join( root, `${ slug }-${ version }.zip` );
const distIgnorePath = path.join( root, '.distignore' );

function loadIgnorePatterns() {
	if ( ! fs.existsSync( distIgnorePath ) ) {
		return [];
	}
	return fs
		.readFileSync( distIgnorePath, 'utf8' )
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line && ! line.startsWith( '#' ) );
}

function isIgnored( relPosix, patterns ) {
	const base = path.posix.basename( relPosix );
	for ( const pat of patterns ) {
		if ( pat.startsWith( '!' ) ) {
			continue;
		}
		// Directory-style: node_modules or node_modules/
		const clean = pat.replace( /\/$/, '' );
		if ( relPosix === clean || relPosix.startsWith( clean + '/' ) ) {
			return true;
		}
		if ( clean.startsWith( '*.' ) ) {
			const ext = clean.slice( 1 ); // .docx
			if ( base.endsWith( ext ) || relPosix.endsWith( ext ) ) {
				return true;
			}
		} else if ( clean.includes( '*' ) ) {
			const re = new RegExp(
				'^' +
					clean
						.replace( /[.+^${}()|[\]\\]/g, '\\$&' )
						.replace( /\*/g, '.*' ) +
					'$'
			);
			if ( re.test( base ) || re.test( relPosix ) ) {
				return true;
			}
		} else if ( base === clean || relPosix === clean ) {
			return true;
		}
	}
	return false;
}

function walk( dir, patterns, files = [] ) {
	for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {
		const abs = path.join( dir, entry.name );
		const rel = path.relative( root, abs ).split( path.sep ).join( '/' );
		if ( isIgnored( rel, patterns ) ) {
			continue;
		}
		if ( entry.isDirectory() ) {
			walk( abs, patterns, files );
		} else if ( entry.isFile() ) {
			files.push( rel );
		}
	}
	return files;
}

const patterns = loadIgnorePatterns();
const files = walk( root, patterns );

// Always require these.
const required = [ 'deskovi.php', 'readme.txt', 'license.txt', 'uninstall.php' ];
for ( const req of required ) {
	if ( ! files.includes( req ) ) {
		console.error( `Missing required file in package: ${ req }` );
		process.exit( 1 );
	}
}

if ( ! files.some( ( f ) => f.startsWith( 'assets/' ) ) ) {
	console.error( 'Missing built assets/ — run npm run build first.' );
	process.exit( 1 );
}

if ( fs.existsSync( outZip ) ) {
	fs.unlinkSync( outZip );
}

const staging = path.join( root, `.zip-staging-${ slug }` );
fs.rmSync( staging, { recursive: true, force: true } );
const stageRoot = path.join( staging, slug );
fs.mkdirSync( stageRoot, { recursive: true } );

for ( const rel of files ) {
	const dest = path.join( stageRoot, rel );
	fs.mkdirSync( path.dirname( dest ), { recursive: true } );
	fs.copyFileSync( path.join( root, rel ), dest );
}

// Compress-Archive in Windows PowerShell 5.1 stores backslashes in entry names, which
// Linux hosts unpack as flat files. Write the entries with forward slashes instead.
const ps = `
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$src = '${ staging.replace( /'/g, "''" ) }'
$dest = '${ outZip.replace( /'/g, "''" ) }'
if (Test-Path $dest) { Remove-Item $dest -Force }
$zip = [System.IO.Compression.ZipFile]::Open($dest, 'Create')
try {
	foreach ($f in Get-ChildItem -LiteralPath $src -Recurse -File) {
		$name = $f.FullName.Substring($src.Length).TrimStart('\\', '/').Replace('\\', '/')
		[void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $name, [System.IO.Compression.CompressionLevel]::Optimal)
	}
} finally {
	$zip.Dispose()
}
`
execFileSync(
	'powershell.exe',
	[ '-NoProfile', '-Command', ps ],
	{ stdio: 'inherit' }
);

fs.rmSync( staging, { recursive: true, force: true } );

console.log( `Created ${ outZip } (${ files.length } files)` );

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const adminConfig = {
	...defaultConfig,
	name: 'admin',
	entry: {
		index: path.resolve( __dirname, 'src/admin/index.tsx' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/admin' ),
	},
};

const widgetConfig = {
	...defaultConfig,
	name: 'widget',
	entry: {
		loader: path.resolve( __dirname, 'src/widget/loader.ts' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/widget' ),
		filename: '[name].js',
		chunkFilename: '[name].js',
		publicPath: 'auto',
	},
	// Standalone storefront bundle — do not expect WP script handles.
	externals: {},
};

module.exports = [ adminConfig, widgetConfig ];

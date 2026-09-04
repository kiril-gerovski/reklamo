// @ts-check
const { defineConfig } = require( '@playwright/test' );

// The stack binds to 127.0.0.1 on the VM; WP_URL must match WP_HOME (localhost:8080).
module.exports = defineConfig( {
	testDir: __dirname,
	timeout: 90_000,
	retries: 0,
	workers: 1, // tests share one store and one Mailpit; keep them sequential
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_URL || 'http://localhost:8080',
		locale: 'bg-BG',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [ { name: 'chromium', use: { browserName: 'chromium' } } ],
} );

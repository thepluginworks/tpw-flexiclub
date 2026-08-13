import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { defineConfig } from '@playwright/test';

const localEnvironmentFile = join(__dirname, '.env.local');

if (existsSync(localEnvironmentFile)) {
	for (const line of readFileSync(localEnvironmentFile, 'utf8').split(/\r?\n/)) {
		const trimmedLine = line.trim();
		if (!trimmedLine || trimmedLine.startsWith('#')) {
			continue;
		}

		const separator = trimmedLine.indexOf('=');
		if (separator === -1) {
			continue;
		}

		const name = trimmedLine.slice(0, separator).trim();
		const value = trimmedLine.slice(separator + 1).trim();
		if (name && process.env[name] === undefined) {
			process.env[name] = value;
		}
	}
}

const baseURL = process.env.ILUNGU_BASE_URL || 'https://flexiclub-smoke.local';

export default defineConfig({
	testDir: __dirname,
	testMatch: 'smoke/ilungu-branding-smoke.spec.ts',
	timeout: 45_000,
	retries: 1,
	use: {
		baseURL,
		ignoreHTTPSErrors: true,
		screenshot: 'only-on-failure',
		trace: 'on-first-retry',
	},
	outputDir: join(__dirname, 'test-results'),
	reporter: [['list']],
});
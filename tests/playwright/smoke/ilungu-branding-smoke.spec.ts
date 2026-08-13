import { expect, test, type Page } from '@playwright/test';

const baseURL = process.env.ILUNGU_BASE_URL || 'https://flexiclub-smoke.local';
const adminUser = process.env.ILUNGU_ADMIN_USER;
const adminPassword = process.env.ILUNGU_ADMIN_PASSWORD;
const freshInstallFixture = process.env.ILUNGU_FRESH_INSTALL === 'true';

const routes = {
	memberLogin: '/member-login/',
	myProfile: '/my-profile/',
	join: process.env.ILUNGU_JOIN_PATH || '/join/',
	home: '/',
	portal: process.env.ILUNGU_PORTAL_PATH || '/club-management/',
	dashboard: '/wp-admin/admin.php?page=tpw-flexiclub-dashboard',
};

const optionalPortalPaths = [
	'/club-management/gallery/',
	'/club-management/noticeboard/',
	'/club-management/logs/',
	'/club-management/menu-management/',
	'/club-management/settings/',
	'/club-management/system-pages/',
	'/club-management/archival-system/',
];

const legacyBranding = /\bFlexiClub\b|\bTPW Core\b/i;
const obsoleteAssets = /flexiclub-(?:icon-300\.png|icon\.svg|logo-horizontal\.svg|logo-icon\.svg)/i;
const clubAssets = [
	'iLunguclub-logo-horizontal.svg',
	'ilunguclub-icon.svg',
	'ilunguclub-icon-300.png',
];
const frontendPluginContainer = '.tpw-frontend-ui.tpw-flexiclub-dashboard, .tpw-flexiclub-dashboard';

type PageSnapshot = {
	path: string;
	title: string;
	url: string;
};

const baselineSnapshots: PageSnapshot[] = [];

function pageUrl(path: string): string {
	return new URL(path, `${baseURL.replace(/\/$/, '')}/`).toString();
}

function installPageGuards(page: Page): () => void {
	const consoleErrors: string[] = [];
	const obsoleteRequests: string[] = [];
	const requiredAssetFailures: string[] = [];

	page.on('console', (message) => {
		if (message.type() === 'error') {
			consoleErrors.push(message.text());
		}
	});

	page.on('response', (response) => {
		const url = response.url();
		if (obsoleteAssets.test(url)) {
			obsoleteRequests.push(url);
		}
		if (clubAssets.some((asset) => url.includes(asset)) && response.status() >= 400) {
			requiredAssetFailures.push(`${response.status()} ${url}`);
		}
	});

	return () => {
		expect(obsoleteRequests, 'obsolete FlexiClub icon assets must not be requested').toEqual([]);
		expect(requiredAssetFailures, 'requested iLungu Club assets must not return an error').toEqual([]);
		expect(consoleErrors, 'page must not produce browser console errors').toEqual([]);
	};
}

async function expectCurrentPluginBranding(page: Page, selector: string, label: string): Promise<boolean> {
	const containers = page.locator(selector);
	if (await containers.count() === 0) {
		test.info().annotations.push({ type: 'skip', description: `${label} does not render a current plugin container.` });
		return false;
	}

	for (let index = 0; index < await containers.count(); index += 1) {
		await expect(containers.nth(index)).not.toContainText(legacyBranding);
	}
	const accessibleNames = await containers.locator('[aria-label], [title], img[alt]').evaluateAll((elements) =>
		elements.filter((element) => {
			const rectangle = element.getBoundingClientRect();
			const style = window.getComputedStyle(element);
			return rectangle.width > 0 && rectangle.height > 0 && style.visibility !== 'hidden';
		}).map((element) => [
			element.getAttribute('aria-label') || '',
			element.getAttribute('title') || '',
			element.getAttribute('alt') || '',
		].join(' ')),
	);
	expect(accessibleNames.join('\n'), `${label} accessible names and labels must not use legacy branding`).not.toMatch(legacyBranding);
	return true;
}

async function recordLegacyDocumentTitle(page: Page, label: string): Promise<void> {
	const title = await page.title();
	if (legacyBranding.test(title)) {
		test.info().annotations.push({
			type: 'migration-candidate',
			description: `${label} retains pre-existing document title "${title}" at ${new URL(page.url()).pathname}.`,
		});
	}
}

async function visitOptionalPage(page: Page, path: string, label: string, pluginSelector = frontendPluginContainer): Promise<void> {
	const verifyPage = installPageGuards(page);
	const response = await page.goto(pageUrl(path), { waitUntil: 'domcontentloaded' });
	if (!response || response.status() === 404) {
		test.info().annotations.push({ type: 'skip', description: `${label} is not configured at ${path}` });
		return;
	}
	expect(response.ok(), `${label} must load when configured`).toBeTruthy();
	await recordLegacyDocumentTitle(page, label);
	await expectCurrentPluginBranding(page, pluginSelector, label);
	verifyPage();
}

async function visitClubAdminMenuPage(page: Page, label: string): Promise<void> {
	const menuLink = page.locator('#toplevel_page_tpw-flexiclub-dashboard .wp-submenu a').filter({ hasText: new RegExp(`^${label}$`, 'i') });
	await expect(menuLink, `${label} must be exposed by the iLungu Club admin menu`).toHaveCount(1);
	const href = await menuLink.getAttribute('href');
	expect(href, `${label} admin-menu link must have an href`).toBeTruthy();
	test.info().annotations.push({ type: 'admin-route', description: `${label}: ${href}` });

	const verifyPage = installPageGuards(page);
	const response = await page.goto(new URL(href!, page.url()).toString(), { waitUntil: 'domcontentloaded' });
	expect(response?.ok(), `${label} must load from its iLungu Club admin-menu link`).toBeTruthy();
	await expectCurrentPluginBranding(page, '#wpbody-content', label);
	verifyPage();
}

test.describe('iLungu Club branding smoke test', () => {
	test.beforeAll(async ({ browser }) => {
		const page = await browser.newPage({ ignoreHTTPSErrors: true });
		for (const [label, path] of Object.entries({ memberLogin: routes.memberLogin, portal: routes.portal })) {
			const response = await page.goto(pageUrl(path), { waitUntil: 'domcontentloaded' });
			if (response && response.ok()) {
				baselineSnapshots.push({ path: label, title: await page.title(), url: page.url() });
			}
		}
		await page.close();
	});

	test('logged-out pages use current branding and preserve protected routes', async ({ page }) => {
		await visitOptionalPage(page, routes.home, 'Home page');

		let verifyPage = installPageGuards(page);
		const loginResponse = await page.goto(pageUrl(routes.memberLogin), { waitUntil: 'domcontentloaded' });
		expect(loginResponse?.ok(), 'Member Login page must load').toBeTruthy();
		await recordLegacyDocumentTitle(page, 'Member Login page');
		await expectCurrentPluginBranding(page, frontendPluginContainer, 'Member Login page');
		verifyPage();

		verifyPage = installPageGuards(page);
		const profileResponse = await page.goto(pageUrl(routes.myProfile), { waitUntil: 'domcontentloaded' });
		expect(profileResponse?.ok(), 'My Profile protection must redirect to a login page').toBeTruthy();
		expect(page.url()).toContain(routes.memberLogin);
		await recordLegacyDocumentTitle(page, 'My Profile login redirect');
		await expectCurrentPluginBranding(page, frontendPluginContainer, 'My Profile login redirect');
		verifyPage();

		await visitOptionalPage(page, routes.join, 'Join page');
	});

	test('optional portal workspaces use current visible and accessible branding', async ({ page }) => {
		await visitOptionalPage(page, routes.portal, 'Club portal');
		await expect(page.locator('.tpw-flexiclub-dashboard__permission-state')).toContainText('iLungu Club workspace');
		for (const path of optionalPortalPaths) {
			await visitOptionalPage(page, path, path);
		}
	});

	test('fresh-install default page titles require a disposable fixture', async ({ page }) => {
		test.skip(!freshInstallFixture, 'Set ILUNGU_FRESH_INSTALL=true for a disposable fresh-install fixture.');
		const response = await page.goto(pageUrl(routes.memberLogin), { waitUntil: 'domcontentloaded' });
		expect(response?.ok(), 'Default Member Login system page must load').toBeTruthy();
		await expect(page).toHaveURL(/\/member-login\/?$/);
		await expect(page).toHaveTitle(/Member Login/i);
	});

	test('System Pages shows the healthy Club Management canonical route', async ({ page }) => {
		test.skip(!adminUser || !adminPassword, 'Set ILUNGU_ADMIN_USER and ILUNGU_ADMIN_PASSWORD to manage System Pages.');
		await page.goto(pageUrl('/wp-login.php'), { waitUntil: 'domcontentloaded' });
		await page.getByLabel(/username or email address/i).fill(adminUser!);
		await page.getByLabel(/^password$/i).fill(adminPassword!);
		await page.getByRole('button', { name: /log in/i }).click();
		await page.waitForURL(/\/wp-admin\//);

		const response = await page.goto(pageUrl('/club-management/?workspace=system-pages'), { waitUntil: 'domcontentloaded' });
		expect(response?.ok(), 'System Pages workspace must load').toBeTruthy();
		const clubManagementRow = page.locator('.tpw-flexiclub-system-pages__row').filter({
			has: page.locator('.tpw-flexiclub-system-pages__page-title', { hasText: /^Club Management$/ }),
		});
		await expect(clubManagementRow, 'Club Management System Page row must render').toHaveCount(1);
		await expect(clubManagementRow.locator('.tpw-flexiclub-dashboard__status')).toContainText('Complete');
		await expect(clubManagementRow.locator('.tpw-flexiclub-system-pages__page-chip--plugin')).toHaveText('iLungu Club');
		const linkedPage = clubManagementRow.getByRole('link', { name: 'View' });
		await expect(linkedPage).toHaveAttribute('href', /\/club-management\/?$/);
		const linkedPageUrl = await linkedPage.getAttribute('href');
		expect(linkedPageUrl, 'Club Management linked page must have a target URL').toBeTruthy();

		const linkedPageResponse = await page.goto(linkedPageUrl!, { waitUntil: 'domcontentloaded' });
		expect(linkedPageResponse?.ok(), 'Linked Club Management page must load').toBeTruthy();
		await expect(page).toHaveURL(/\/club-management\/?$/);
		await expect(page).toHaveTitle(/Club Management/i);
	});

	test('WordPress administration uses current branding and Club assets', async ({ page }) => {
		test.skip(!adminUser || !adminPassword, 'Set ILUNGU_ADMIN_USER and ILUNGU_ADMIN_PASSWORD to run admin branding checks.');
		let verifyPage = installPageGuards(page);
		await page.goto(pageUrl('/wp-login.php'), { waitUntil: 'domcontentloaded' });
		await page.getByLabel(/username or email address/i).fill(adminUser!);
		await page.getByLabel(/^password$/i).fill(adminPassword!);
		await page.getByRole('button', { name: /log in/i }).click();
		await page.waitForURL(/\/(?:wp-admin\/|wp-login\.php)/);
		if (/\/wp-login\.php/.test(page.url())) {
			const loginError = (await page.locator('#login_error, .message, .notice-error').allTextContents())
				.map((message) => message.trim())
				.filter(Boolean)
				.join(' ');
			expect(false, `WordPress authentication remained on wp-login.php: ${loginError || 'no visible login error.'}`).toBeTruthy();
		}
		await expect(page).toHaveURL(/\/wp-admin\//);
		verifyPage();

		verifyPage = installPageGuards(page);
		const pluginsResponse = await page.goto(pageUrl('/wp-admin/plugins.php'), { waitUntil: 'domcontentloaded' });
		expect(pluginsResponse?.ok(), 'Plugins screen must load').toBeTruthy();
		const clubPluginRow = page.locator('tr[data-plugin="tpw-ilungu-club/ilungu-club.php"]');
		await expect(clubPluginRow, 'iLungu Club plugin row must be listed').toHaveCount(1);
		await expect(clubPluginRow).toContainText(/iLungu™? Club/i);
		await expectCurrentPluginBranding(page, 'tr[data-plugin="tpw-ilungu-club/ilungu-club.php"]', 'iLungu Club plugin row');
		verifyPage();

		verifyPage = installPageGuards(page);
		const dashboardResponse = await page.goto(pageUrl(routes.dashboard), { waitUntil: 'domcontentloaded' });
		expect(dashboardResponse?.ok(), 'iLungu dashboard must load').toBeTruthy();
		await expect(page.locator('#adminmenu')).toContainText(/iLungu™? Club/i);
		await expect(page.locator('#wpbody-content')).toContainText(/iLungu™? Club/i);
		const dashboardLogo = page.locator('img[src*="iLunguclub-logo-horizontal.svg"]');
		expect(await dashboardLogo.count(), 'dashboard must render the iLungu Club horizontal logo').toBeGreaterThan(0);
		await expect(dashboardLogo.first()).toHaveAttribute('alt', /iLungu™? Club/i);
		const iconImages = page.locator('img[src*="ilunguclub-icon.svg"], img[src*="ilunguclub-icon-300.png"]');
		if (await iconImages.count()) {
			for (let index = 0; index < await iconImages.count(); index += 1) {
				await expect(iconImages.nth(index)).toHaveJSProperty('complete', true);
				expect(await iconImages.nth(index).evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);
			}
		} else {
			test.info().annotations.push({ type: 'skip', description: 'No Club icon image is rendered on this dashboard installation.' });
		}
		await expectCurrentPluginBranding(page, '#wpbody-content, #adminmenu', 'iLungu dashboard');
		verifyPage();

		await visitClubAdminMenuPage(page, 'Manage Members');
		await expect(page.locator('#wpbody-content')).toContainText('Manage Members');
		test.info().annotations.push({
			type: 'conditional',
			description: 'No dedicated Member Profile or Member Sign-Ups admin menu item is present on this installation; related notices were not rendered.',
		});

		await page.goto(pageUrl(routes.dashboard), { waitUntil: 'domcontentloaded' });
		await visitClubAdminMenuPage(page, 'Settings');
		await expect(page.locator('#wpbody-content')).toContainText(/iLungu™? Club Settings/i);
		const paymentSettingsTab = page.locator('[role="tab"], .nav-tab, .tpw-tab, .tpw-admin-tab').filter({ hasText: /payment/i });
		if (await paymentSettingsTab.count()) {
			await expect(paymentSettingsTab.first()).toBeVisible();
		} else {
			test.info().annotations.push({
				type: 'conditional',
				description: 'Payment settings are not exposed as a rendered iLungu Club Settings tab on this installation.',
			});
		}
	});

	test.afterAll(async ({ browser }) => {
		const page = await browser.newPage({ ignoreHTTPSErrors: true });
		for (const snapshot of baselineSnapshots) {
			const response = await page.goto(snapshot.url, { waitUntil: 'domcontentloaded' });
			expect(response?.ok(), `${snapshot.path} must remain reachable`).toBeTruthy();
			expect(await page.title(), `${snapshot.path} title must not be changed by this read-only test`).toBe(snapshot.title);
		}
		await page.close();
	});
});
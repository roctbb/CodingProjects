const { test, expect } = require('@playwright/test');

const baseURL = process.env.DESIGN_AUDIT_BASE_URL || 'http://localhost:8000';
const password = process.env.DESIGN_AUDIT_PASSWORD || 'DesignAudit2026!';
const outputDir = process.env.DESIGN_AUDIT_OUTPUT || 'storage/app/design-audit';
const desktopViewport = { width: 1280, height: 720 };
const mobileViewport = { width: 390, height: 844 };

const users = {
  student: 'design-audit-student@example.test',
  teacher: 'design-audit-teacher@example.test',
  admin: 'design-audit-admin@example.test',
};

const pages = [
  { role: 'public', name: 'auth-login', path: '/login' },
  { role: 'public', name: 'auth-register', path: '/register' },
  { role: 'public', name: 'auth-password-reset', path: '/password/reset' },
  { role: 'public', name: 'open-step', path: '/open/steps/3840' },
  { role: 'student', name: 'courses-index', path: '/insider/courses' },
  { role: 'student', name: 'course-details', path: '/insider/courses/158' },
  { role: 'student', name: 'step-details', path: '/insider/courses/158/steps/3840' },
  { role: 'student', name: 'market-index', path: '/insider/market' },
  { role: 'student', name: 'community', path: '/insider/community' },
  { role: 'student', name: 'profile-details', path: '/insider/profile/1103' },
  { role: 'student', name: 'profile-edit', path: '/insider/profile/1103/edit' },
  { role: 'teacher', name: 'teacher-courses-index', path: '/insider/courses' },
  { role: 'teacher', name: 'course-report', path: '/insider/courses/158/report' },
  { role: 'teacher', name: 'course-assessments', path: '/insider/courses/158/assessments' },
  { role: 'teacher', name: 'course-blocked', path: '/insider/courses/158/blocked' },
  { role: 'teacher', name: 'solution-review', path: '/insider/courses/158/tasks/2268/student/1103' },
  { role: 'admin', name: 'course-edit', path: '/insider/courses/158/edit' },
  { role: 'admin', name: 'course-create', path: '/insider/courses/create' },
  { role: 'admin', name: 'chapter-create', path: '/insider/courses/158/chapter' },
  { role: 'admin', name: 'chapter-edit', path: '/insider/courses/158/chapters/178/edit' },
  { role: 'admin', name: 'market-create', path: '/insider/market/create' },
  { role: 'admin', name: 'market-edit', path: '/insider/market/31/edit' },
  { role: 'admin', name: 'market-orders', path: '/insider/market/orders' },
  { role: 'admin', name: 'lesson-create', path: '/insider/courses/158/create' },
  { role: 'admin', name: 'lesson-edit', path: '/insider/courses/158/lessons/651/edit' },
  { role: 'admin', name: 'step-create', path: '/insider/courses/158/lessons/651/create' },
  { role: 'admin', name: 'step-edit', path: '/insider/courses/158/steps/3840/edit' },
  { role: 'admin', name: 'task-edit', path: '/insider/courses/158/tasks/2268/edit' },
  { role: 'admin', name: 'perform-step', path: '/insider/courses/158/perform/3840' },
];

async function login(page, role) {
  await page.goto(`${baseURL}/login`);
  await page.fill('input[name="email"]', users[role]);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/insider|email\/verify/, { timeout: 15000 });
  await expect(page.locator('body')).toBeVisible();
}

for (const pageSpec of pages) {
  test(`${pageSpec.role} ${pageSpec.name}`, async ({ page }) => {
    const errors = [];
    const badResponses = [];
    page.on('console', (message) => {
      if (['error', 'warning'].includes(message.type())) {
        errors.push(`${message.type()}: ${message.text()}`);
      }
    });
    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
    page.on('response', (response) => {
      const status = response.status();
      if (status >= 400) {
        badResponses.push(`${status}: ${response.url()}`);
      }
    });

    if (pageSpec.role !== 'public') {
      await login(page, pageSpec.role);
    }

    await page.setViewportSize(desktopViewport);
    const response = await page.goto(`${baseURL}${pageSpec.path}`, { waitUntil: 'networkidle' });
    expect(response && response.status(), `${pageSpec.path} should return 2xx/3xx`).toBeLessThan(400);
    await expect(page.locator('body')).toBeVisible();

    await page.screenshot({
      path: `${outputDir}/${pageSpec.role}-${pageSpec.name}.png`,
      fullPage: true,
    });

    await page.setViewportSize(mobileViewport);
    const mobileResponse = await page.goto(`${baseURL}${pageSpec.path}`, { waitUntil: 'networkidle' });
    expect(mobileResponse && mobileResponse.status(), `${pageSpec.path} mobile should return 2xx/3xx`).toBeLessThan(400);
    await expect(page.locator('body')).toBeVisible();

    await page.screenshot({
      path: `${outputDir}/mobile-${pageSpec.role}-${pageSpec.name}.png`,
      fullPage: true,
    });

    if (errors.length) {
      console.log(`DESIGN_AUDIT_ERRORS ${pageSpec.role}/${pageSpec.name}: ${errors.slice(0, 5).join(' | ')}`);
    }
    if (badResponses.length) {
      console.log(`DESIGN_AUDIT_BAD_RESPONSES ${pageSpec.role}/${pageSpec.name}: ${badResponses.slice(0, 8).join(' | ')}`);
    }
  });
}

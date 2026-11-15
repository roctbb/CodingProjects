import { spawn, execFile, execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, writeFileSync, copyFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import net from 'node:net';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';
import { randomBytes } from 'node:crypto';
import suite from './suite.mjs';
import interactions from './interactions.mjs';
import deadlineFlows from './deadline-flows.mjs';
import taskWorkspace from './task-workspace.mjs';
import lessonStatistics from './lesson-statistics.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const directory = mkdtempSync(path.join(tmpdir(), 'codingprojects-ui-'));
const mode = process.argv.includes('--baseline') ? 'baseline' : 'current';
if ((process.argv.includes('--flows-only') || process.argv.includes('--workspace-only') || process.argv.includes('--stats-only')) && (mode === 'baseline' || process.argv.includes('--compare'))) {
    throw new Error('Baseline and comparison require the complete screen suite.');
}
if (process.env.UI_BASELINE_CSS && mode !== 'baseline') throw new Error('UI_BASELINE_CSS is only allowed for a baseline.');
const output = path.join(root, 'output/playwright/ui-regression', process.env.UI_PALETTE || '', mode);
mkdirSync(output, { recursive: true });
execFileSync('php', [path.join(root, 'scripts/ui-checks/prepare.php'), directory], { cwd: root, stdio: 'inherit' });
const port = await new Promise(resolve => { const server = net.createServer(); server.listen(0, '127.0.0.1', () => { const port = server.address().port; server.close(() => resolve(port)); }); });
const url = `http://127.0.0.1:${port}`;
const php = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'scripts/ui-checks/router.php'], {
    cwd: root, env: { ...process.env, UI_QA_MODE: '1', UI_QA_DIRECTORY: directory, UI_QA_URL: url, UI_QA_KEY: 'base64:' + randomBytes(32).toString('base64') }, stdio: ['ignore', 'ignore', 'pipe'],
});
let serverLog = ''; php.stderr.on('data', chunk => { serverLog += chunk; });
const session = `gc-ui-${process.pid}`;
const cli = async args => (await promisify(execFile)('npx', ['--yes', '--package', '@playwright/cli', 'playwright-cli', `-s=${session}`, ...args], { cwd: root, encoding: 'utf8', maxBuffer: 16 * 1024 * 1024, timeout: 300000 })).stdout;
try {
    for (let attempt = 0; ; attempt++) {
        try { await fetch(`${url}/login`); break; } catch { if (attempt === 39) throw new Error('UI fixture server did not start'); await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    await cli(['open', 'about:blank', ...(process.env.UI_HEADED === '1' ? ['--headed'] : [])]);
    const config = { url, output, widths: [1440, 390], palette: process.env.UI_PALETTE || '', css: process.env.UI_BASELINE_CSS ? path.resolve(process.env.UI_BASELINE_CSS) : null };
    const report = { results: [], errors: [] };
    const flows = [['interactions', interactions], ['deadlines', deadlineFlows], ['task-workspace', taskWorkspace], ['lesson-statistics', lessonStatistics]];
    const checks = process.argv.includes('--workspace-only') ? [['task-workspace', taskWorkspace]] : process.argv.includes('--stats-only') ? [['lesson-statistics', lessonStatistics]] : process.argv.includes('--flows-only') ? flows : [['browser', suite], ...flows];
    for (const [name, check] of checks) {
        const raw = await cli(['run-code', `async(page)=>(${check.toString()})(page,${JSON.stringify(config)})`]);
        writeFileSync(path.join(output, `${name}.log`), raw);
        const result = JSON.parse(raw.split('### Result\n')[1].split('\n### ')[0]);
        report.results.push(...result.results); report.errors.push(...result.errors);
    }
    writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
    if (report.errors.length) throw new Error(JSON.stringify(report.errors, null, 2));
    if (mode === 'baseline') copyFileSync(config.css || path.join(root, 'public/build/css/app.css'), path.join(output, 'app.css'));
    if (process.argv.includes('--compare')) {
        const baseline = JSON.parse(readFileSync(path.join(output, '../baseline/report.json'), 'utf8'));
        const differences = report.results.filter(row => row.styles).filter(row => {
            const before = baseline.results.find(item => item.name === row.name && item.width === row.width && item.theme === row.theme);
            return !before || before.styles.length !== row.styles.length || row.styles.some((style, index) => {
                const previous = before.styles[index];
                // MathJax font metrics can vary by a fraction of a CSS pixel between runs.
                return style.box.some((value, axis) => Math.abs(value - previous.box[axis]) > 0.5)
                    || JSON.stringify({ ...style, box: null }) !== JSON.stringify({ ...previous, box: null });
            });
        }).map(({ name, width, theme }) => ({ name, width, theme }));
        writeFileSync(path.join(output, 'comparison.json'), JSON.stringify(differences, null, 2));
        if (differences.length) throw new Error(`UI differs from baseline: ${JSON.stringify(differences)}`);
    }
    console.log(`UI passed: ${report.results.length} screen and load-order checks; ${output}`);
} finally {
    try { await cli(['close']); } catch {}
    php.kill('SIGTERM');
    writeFileSync(path.join(output, 'server.log'), serverLog);
}

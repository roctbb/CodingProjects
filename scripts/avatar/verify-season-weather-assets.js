#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '../..');
const systemDir = path.join(root, 'public/images/avatar-layers/room-system');
const config = JSON.parse(fs.readFileSync(path.join(systemDir, 'config.json'), 'utf8'));
const errors = [];

const rel = (file) => path.relative(root, file).replace(/\\/g, '/');
const asset = (file) => path.join(systemDir, file || '');
const identify = (file) => {
  const result = spawnSync('magick', ['identify', '-format', '%w|%h', file], { encoding: 'utf8' });
  if (result.status !== 0) {
    errors.push(`Cannot identify weather asset: ${rel(file)}`);
    return null;
  }

  const [width, height] = result.stdout.trim().split('|').map(Number);
  return { width, height };
};

const weatherByKey = Object.fromEntries((config.weather || []).map((entry) => [entry.key, entry]));
const orderedFiles = [];

(config.seasons || []).forEach((season) => {
  (config.weatherStates || []).forEach((state) => {
    const key = `${season.key}_${state.key}`;
    const entry = weatherByKey[key];
    const file = asset(entry?.file);

    if (!entry) {
      errors.push(`Missing config.weather entry: ${key}`);
      return;
    }

    if (!fs.existsSync(file)) {
      errors.push(`Missing weather asset: ${rel(file)}`);
      return;
    }

    const info = identify(file);
    if (info && (info.width !== 1254 || info.height !== 1254)) {
      errors.push(`Weather asset must be 1254x1254: ${rel(file)} is ${info.width}x${info.height}`);
    }

    orderedFiles.push(file);
  });
});

if (errors.length) {
  console.error(errors.map((error) => `- ${error}`).join('\n'));
  process.exit(1);
}

console.log(`Season/weather assets OK: ${orderedFiles.length}`);

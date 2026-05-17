#!/usr/bin/env node

const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '../..');
const args = process.argv.slice(2);
const options = {
  skipTests: args.includes('--skip-tests'),
};

const run = (label, command, commandArgs) => {
  console.log(`\n== ${label}`);
  console.log(`${command} ${commandArgs.join(' ')}`);

  const result = spawnSync(command, commandArgs, {
    cwd: root,
    encoding: 'utf8',
    stdio: 'pipe',
  });

  if (result.stdout) process.stdout.write(result.stdout);
  if (result.stderr) process.stderr.write(result.stderr);

  if (result.status !== 0) {
    console.error(`\nFailed: ${label}`);
    process.exit(result.status || 1);
  }
};

run('Verify season/weather window assets', 'node', ['scripts/avatar/verify-season-weather-assets.js']);
run('Validate room-system assets', 'node', ['scripts/avatar/validate-room-system.js']);

if (!options.skipTests) {
  run('Run room avatar tests', 'php', ['artisan', 'test', '--filter=LearningAvatarTest|RoomDebugViewTest']);
}

console.log('\nRoom system asset check completed.');
